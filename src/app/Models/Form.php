<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Form extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'schema',
        'settings',
        'workflow_id',
        'is_public',
        'is_active',
        'is_featured',
        'featured_order',
        'allowed_email_domains',
        'domain_restriction_mode',
        'prevent_duplicates',
        'duplicate_message',
        'created_by',
        'category_id',
        'tags',
        'keywords',
        'email_template_id',
        'approval_email_template_id',
        'rejection_email_template_id',
        'send_confirmation_email',
        'current_version',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'schema' => 'array',
            'settings' => 'array',
            'tags' => 'array',
            'keywords' => 'array',
            'duplicate_message' => 'array',
            'allowed_email_domains' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'prevent_duplicates' => 'boolean',
            'send_confirmation_email' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($form) {
            if (empty($form->slug)) {
                // Handle multilingual name for slug generation
                $name = $form->name;
                if (is_array($name)) {
                    $name = $name['sk'] ?? $name['en'] ?? '';
                }
                $form->slug = Str::slug($name) . '-' . Str::random(6);
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category()
    {
        return $this->belongsTo(FormCategory::class, 'category_id');
    }

    /**
     * Get all searchable text for this form
     */
    public function getSearchableTextAttribute(): string
    {
        $parts = [
            $this->name,
            $this->description,
            $this->keywords,
        ];

        if ($this->tags) {
            $parts = array_merge($parts, $this->tags);
        }

        if ($this->category) {
            $parts[] = $this->category->name;
        }

        return mb_strtolower(implode(' ', array_filter($parts)));
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function emailTemplate()
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function approvalEmailTemplate()
    {
        return $this->belongsTo(EmailTemplate::class, 'approval_email_template_id');
    }

    public function rejectionEmailTemplate()
    {
        return $this->belongsTo(EmailTemplate::class, 'rejection_email_template_id');
    }

    public function getFieldsAttribute()
    {
        return $this->schema['fields'] ?? [];
    }

    /**
     * Get localized form name (handles both string and multilingual array)
     */
    public function getLocalizedNameAttribute(): string
    {
        $name = $this->name;
        if (is_array($name)) {
            return $name['sk'] ?? $name['en'] ?? '';
        }
        return $name ?? '';
    }

    /**
     * Check if a user's email matches the domain restriction rules for this form.
     *
     * Modes:
     * - 'none' (default): visible to everyone
     * - 'allow': only visible to users with email from listed domains
     * - 'block': visible to everyone EXCEPT users with email from listed domains
     */
    public function isVisibleForEmail(?string $email): bool
    {
        $mode = $this->domain_restriction_mode ?? 'none';
        $domains = $this->allowed_email_domains;

        // No restriction - visible to everyone
        if ($mode === 'none' || empty($domains)) {
            return true;
        }

        // Restriction set but no email provided
        if (!$email) {
            // For 'allow' mode: no email = not visible
            // For 'block' mode: no email = visible (can't match a blocked domain)
            return $mode === 'block';
        }

        $emailDomain = mb_strtolower(substr($email, strrpos($email, '@') + 1));

        $matches = false;
        foreach ($domains as $domain) {
            if ($emailDomain === mb_strtolower(trim($domain))) {
                $matches = true;
                break;
            }
        }

        // 'allow' mode: visible only if email matches a listed domain
        // 'block' mode: visible only if email does NOT match a listed domain
        return $mode === 'allow' ? $matches : !$matches;
    }

    /**
     * Users who have permission to view this form
     */
    public function allowedUsers()
    {
        return $this->belongsToMany(User::class, 'form_user')->withTimestamps();
    }

    /**
     * Users who are subscribed to receive notifications about new submissions
     */
    public function notificationSubscribers()
    {
        return $this->belongsToMany(User::class, 'form_notification_subscribers')
            ->withPivot('notify_enabled')
            ->withTimestamps();
    }

    /**
     * All versions of this form
     */
    public function versions()
    {
        return $this->hasMany(FormVersion::class)->orderBy('version_number', 'desc');
    }

    /**
     * Create a new version snapshot of the current form state
     * Maintains maximum of 20 versions, deleting oldest when limit exceeded
     */
    public function createVersion(?string $changeNote = null, ?int $userId = null): FormVersion
    {
        $nextVersion = ($this->current_version ?? 0) + 1;

        $version = FormVersion::create([
            'form_id' => $this->id,
            'version_number' => $nextVersion,
            'schema' => $this->schema,
            'settings' => $this->settings,
            'change_note' => $changeNote,
            'created_by' => $userId,
        ]);

        $this->update(['current_version' => $nextVersion]);

        // Delete old versions if more than 20 exist (keep newest 20)
        $versionCount = $this->versions()->count();
        if ($versionCount > 20) {
            $oldVersionIds = $this->versions()
                ->orderBy('version_number', 'asc')
                ->take($versionCount - 20)
                ->pluck('id');

            if ($oldVersionIds->isNotEmpty()) {
                FormVersion::whereIn('id', $oldVersionIds)->delete();
            }
        }

        return $version;
    }

    /**
     * Restore form to a specific version
     */
    public function restoreToVersion(FormVersion $version, ?string $changeNote = null, ?int $userId = null): FormVersion
    {
        $this->update([
            'schema' => $version->schema,
            'settings' => $version->settings,
        ]);

        return $this->createVersion($changeNote ?? "Obnovené z verzie {$version->version_number}", $userId);
    }
}
