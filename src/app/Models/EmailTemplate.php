<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EmailTemplate extends Model
{
    /**
     * System template types
     */
    public const TYPE_NEW_SUBMISSION_ADMIN = 'new_submission_admin';

    protected $fillable = [
        'name',
        'slug',
        'system_type',
        'subject',
        'body_html',
        'body_text',
        'include_submission_data',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'include_submission_data' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (empty($template->slug)) {
                $template->slug = static::generateUniqueSlug($template->name);
            }
        });
    }

    /**
     * Generate a unique slug from the given name
     */
    protected static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    /**
     * Extract localized text from a value that may be a string or multilingual array
     */
    private function getLocalizedText(mixed $value, string $locale = 'sk'): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            return $value[$locale] ?? $value['sk'] ?? $value['en'] ?? '';
        }
        return (string) $value;
    }

    /**
     * Get the default template
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Get a template by system type
     */
    public static function getBySystemType(string $type): ?self
    {
        return static::where('system_type', $type)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Render the email subject with submission data
     */
    public function renderSubject(FormSubmission $submission): string
    {
        return $this->replacePlaceholders($this->subject, $submission);
    }

    /**
     * Render the email body with submission data
     */
    public function renderHtml(FormSubmission $submission): string
    {
        $body = $this->body_html;

        // Replace placeholders
        $body = $this->replacePlaceholders($body, $submission);

        // Add submission data table if enabled
        if ($this->include_submission_data) {
            $body .= $this->generateSubmissionTable($submission);
        }

        return $body;
    }

    /**
     * Render the plain text body with submission data
     */
    public function renderText(FormSubmission $submission): string
    {
        $body = $this->body_text ?? strip_tags($this->body_html);

        // Replace placeholders
        $body = $this->replacePlaceholders($body, $submission);

        // Add submission data if enabled
        if ($this->include_submission_data) {
            $body .= $this->generateSubmissionText($submission);
        }

        return $body;
    }

    /**
     * Replace placeholders in body
     */
    private function replacePlaceholders(string $body, FormSubmission $submission): string
    {
        $form = $submission->form;
        $user = $submission->user;

        $replacements = [
            '{{form_name}}' => $form->localized_name ?? '',
            '{{submission_id}}' => $submission->id,
            '{{submission_date}}' => $submission->created_at->format('d.m.Y H:i'),
            '{{user_name}}' => $user->name ?? 'Anonymný používateľ',
            '{{user_email}}' => $user->email ?? '',
            '{{admin_response}}' => $submission->admin_response ?? '',
            '{{status}}' => $submission->status === 'approved' ? 'Schválené' : ($submission->status === 'rejected' ? 'Zamietnuté' : $submission->status ?? ''),
            '{{reviewed_at}}' => $submission->reviewed_at ? $submission->reviewed_at->format('d.m.Y H:i') : '',
            '{{submission_url}}' => url("/admin/submissions/{$submission->id}"),
        ];

        // Add form field values as placeholders
        $data = $submission->data ?? [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $replacements["{{submission.$key}}"] = $value;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $body);
    }

    /**
     * Generate HTML table with submission data
     */
    private function generateSubmissionTable(FormSubmission $submission): string
    {
        $form = $submission->form;
        $data = $submission->data ?? [];
        $schema = $form->schema ?? [];
        $fields = $schema['fields'] ?? [];

        // Create field labels map
        $fieldLabels = [];
        foreach ($fields as $field) {
            $fieldLabels[$field['name']] = $this->getLocalizedText($field['label'] ?? $field['name']);
        }

        $html = '<div style="margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 24px;">';
        $html .= '<h3 style="margin: 0 0 16px 0; color: #374151; font-size: 16px;">Prehľad vašich odpovedí</h3>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';

        foreach ($data as $key => $value) {
            $label = $fieldLabels[$key] ?? $key;
            $displayValue = is_array($value) ? implode(', ', $value) : (string) $value;

            $html .= '<tr style="border-bottom: 1px solid #f3f4f6;">';
            $html .= '<td style="padding: 12px 8px; color: #6b7280; font-weight: 500; width: 40%;">' . htmlspecialchars($label) . '</td>';
            $html .= '<td style="padding: 12px 8px; color: #111827;">' . htmlspecialchars($displayValue) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate plain text version of submission data
     */
    private function generateSubmissionText(FormSubmission $submission): string
    {
        $form = $submission->form;
        $data = $submission->data ?? [];
        $schema = $form->schema ?? [];
        $fields = $schema['fields'] ?? [];

        // Create field labels map
        $fieldLabels = [];
        foreach ($fields as $field) {
            $fieldLabels[$field['name']] = $this->getLocalizedText($field['label'] ?? $field['name']);
        }

        $text = "\n\n--- Prehľad vašich odpovedí ---\n\n";

        foreach ($data as $key => $value) {
            $label = $fieldLabels[$key] ?? $key;
            $displayValue = is_array($value) ? implode(', ', $value) : (string) $value;
            $text .= "$label: $displayValue\n";
        }

        return $text;
    }
}
