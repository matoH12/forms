<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailTemplateController extends Controller
{
    public function index()
    {
        $templates = EmailTemplate::with('creator:id,name')
            ->withCount('forms')
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/EmailTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/EmailTemplates/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'include_submission_data' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // If setting as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            EmailTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $template = EmailTemplate::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        AuditService::log('email_template_created', $template);

        return redirect()
            ->route('admin.email-templates.index')
            ->with('success', 'Emailová šablóna bola vytvorená');
    }

    public function edit(EmailTemplate $emailTemplate)
    {
        return Inertia::render('Admin/EmailTemplates/Edit', [
            'template' => $emailTemplate,
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'body_text' => 'nullable|string',
            'include_submission_data' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // If setting as default, unset other defaults
        if (($validated['is_default'] ?? false) && !$emailTemplate->is_default) {
            EmailTemplate::where('is_default', true)->update(['is_default' => false]);
        }

        $emailTemplate->update($validated);

        AuditService::log('email_template_updated', $emailTemplate);

        return redirect()
            ->back()
            ->with('success', 'Emailová šablóna bola aktualizovaná');
    }

    public function destroy(EmailTemplate $emailTemplate)
    {
        // Check if template is used by any forms
        if ($emailTemplate->forms()->count() > 0) {
            return redirect()
                ->back()
                ->with('error', 'Nemožno zmazať šablónu, ktorá je používaná formulármi');
        }

        AuditService::log('email_template_deleted', $emailTemplate);

        $emailTemplate->delete();

        return redirect()
            ->route('admin.email-templates.index')
            ->with('success', 'Emailová šablóna bola zmazaná');
    }

    public function preview(Request $request, EmailTemplate $emailTemplate)
    {
        // Return rendered preview with sample data
        $sampleData = [
            'form_name' => 'Ukážkový formulár',
            'submission_id' => 1,
            'submission_date' => now()->format('d.m.Y H:i'),
            'user_name' => 'Ján Ukážka',
            'user_email' => 'jan.ukazka@example.com',
        ];

        $body = $emailTemplate->body_html;

        // Replace placeholders with sample data
        foreach ($sampleData as $key => $value) {
            $body = str_replace("{{{$key}}}", $value, $body);
        }

        // Add sample submission table if enabled
        if ($emailTemplate->include_submission_data) {
            $body .= $this->generateSampleTable();
        }

        return response()->json([
            'subject' => str_replace('{{form_name}}', 'Ukážkový formulár', $emailTemplate->subject),
            'body' => $body,
        ]);
    }

    private function generateSampleTable(): string
    {
        $html = '<div style="margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 24px;">';
        $html .= '<h3 style="margin: 0 0 16px 0; color: #374151; font-size: 16px;">Prehľad vašich odpovedí</h3>';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';

        $sampleFields = [
            'Meno a priezvisko' => 'Ján Ukážka',
            'Email' => 'jan.ukazka@example.com',
            'Telefón' => '+421 900 123 456',
            'Správa' => 'Toto je ukážková správa pre náhľad emailu.',
        ];

        foreach ($sampleFields as $label => $value) {
            $html .= '<tr style="border-bottom: 1px solid #f3f4f6;">';
            $html .= '<td style="padding: 12px 8px; color: #6b7280; font-weight: 500; width: 40%;">' . htmlspecialchars($label) . '</td>';
            $html .= '<td style="padding: 12px 8px; color: #111827;">' . htmlspecialchars($value) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }
}
