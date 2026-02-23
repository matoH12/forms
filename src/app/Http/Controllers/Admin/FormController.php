<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SubmissionStatusChanged;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Models\User;
use App\Models\Workflow;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class FormController extends Controller
{
    /**
     * Check if current user can access the form
     */
    private function authorizeForm(Form $form): void
    {
        if (!auth()->user()->canSeeForm($form)) {
            abort(403, 'Nemáte oprávnenie na prístup k tomuto formuláru.');
        }
    }

    /**
     * Check if current user can access form submissions (stricter - explicit permissions only)
     */
    private function authorizeFormSubmissions(Form $form): void
    {
        if (!auth()->user()->canSeeFormSubmissions($form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        // Start with user's visible forms
        $query = $user->getVisibleFormsQuery()
            ->withCount('submissions')
            ->with(['creator:id,name', 'category:id,name,color']);

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        $forms = $query->latest()->paginate(20);
        $categories = FormCategory::orderBy('order')->get();

        return Inertia::render('Admin/Forms/Index', [
            'forms' => $forms,
            'categories' => $categories,
            'filters' => $this->sanitizeFilters($request->only(['category'])),
        ]);
    }

    public function create()
    {
        // SECURITY: Only admin+ can create forms
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403, 'Nemáte oprávnenie vytvárať formuláre.');
        }

        $categories = FormCategory::orderBy('order')->get();

        return Inertia::render('Admin/Forms/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        // SECURITY: Only admin+ can create forms
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403, 'Nemáte oprávnenie vytvárať formuláre.');
        }

        $validated = $request->validate([
            'name' => 'required|array',
            'name.sk' => 'required|string|max:255',
            'name.en' => 'nullable|string|max:255',
            'description' => 'nullable|array',
            'description.sk' => 'nullable|string',
            'description.en' => 'nullable|string',
            'schema' => 'required|array',
            'schema.fields' => 'required|array|min:1',
            'settings' => 'nullable|array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'prevent_duplicates' => 'boolean',
            'duplicate_message' => 'nullable|array',
            'duplicate_message.sk' => 'nullable|string|max:1000',
            'duplicate_message.en' => 'nullable|string|max:1000',
            'category_id' => 'nullable|exists:form_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'keywords' => 'nullable|string|max:1000',
            'domain_restriction_mode' => 'nullable|string|in:none,allow,block',
            'allowed_email_domains' => 'nullable|array',
            'allowed_email_domains.*' => 'string|max:255',
        ]);

        // Clean up tags
        if (isset($validated['tags'])) {
            $validated['tags'] = array_values(array_filter(array_map('trim', $validated['tags'])));
        }

        // Clean up email domains
        if (($validated['domain_restriction_mode'] ?? 'none') === 'none') {
            $validated['allowed_email_domains'] = null;
        } elseif (isset($validated['allowed_email_domains'])) {
            $validated['allowed_email_domains'] = array_values(array_filter(array_map('trim', $validated['allowed_email_domains'])));
            if (empty($validated['allowed_email_domains'])) {
                $validated['allowed_email_domains'] = null;
                $validated['domain_restriction_mode'] = 'none';
            }
        }

        $form = Form::create([
            ...$validated,
            'created_by' => auth()->id(),
            'current_version' => 1,
        ]);

        // Create initial version
        $form->createVersion('Vytvorenie formulára', auth()->id());

        // Clear caches
        Cache::forget('public_forms');
        Cache::forget('forms_list');
        Cache::forget('dashboard_stats');

        // Audit log
        AuditService::formCreated($form);

        return redirect()
            ->route('admin.forms.edit', $form)
            ->with('success', 'Formulár bol vytvorený');
    }

    public function show(Form $form)
    {
        $this->authorizeForm($form);
        return redirect()->route('admin.forms.edit', $form);
    }

    public function edit(Form $form)
    {
        $this->authorizeForm($form);
        $form->load('category:id,name,color');

        $workflows = Workflow::where('is_active', true)
            ->select('id', 'name', 'trigger_on', 'is_active')
            ->get();

        $categories = FormCategory::orderBy('order')->get();

        $emailTemplates = EmailTemplate::where('is_active', true)
            ->select('id', 'name', 'is_default')
            ->get();

        return Inertia::render('Admin/Forms/Edit', [
            'form' => $form,
            'workflows' => $workflows,
            'categories' => $categories,
            'emailTemplates' => $emailTemplates,
        ]);
    }

    public function update(Request $request, Form $form)
    {
        // SECURITY: Only admin+ can update forms
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403, 'Nemáte oprávnenie upravovať formuláre.');
        }

        $this->authorizeForm($form);

        $validated = $request->validate([
            'name' => 'required|array',
            'name.sk' => 'required|string|max:255',
            'name.en' => 'nullable|string|max:255',
            'description' => 'nullable|array',
            'description.sk' => 'nullable|string',
            'description.en' => 'nullable|string',
            'schema' => 'required|array',
            'schema.fields' => 'required|array|min:1',
            'settings' => 'nullable|array',
            'workflow_id' => 'nullable|exists:workflows,id',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'featured_order' => 'nullable|integer|min:0|max:100',
            'prevent_duplicates' => 'boolean',
            'duplicate_message' => 'nullable|array',
            'duplicate_message.sk' => 'nullable|string|max:1000',
            'duplicate_message.en' => 'nullable|string|max:1000',
            'category_id' => 'nullable|exists:form_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'keywords' => 'nullable|string|max:1000',
            'domain_restriction_mode' => 'nullable|string|in:none,allow,block',
            'allowed_email_domains' => 'nullable|array',
            'allowed_email_domains.*' => 'string|max:255',
            'email_template_id' => 'nullable|exists:email_templates,id',
            'approval_email_template_id' => 'nullable|exists:email_templates,id',
            'rejection_email_template_id' => 'nullable|exists:email_templates,id',
            'send_confirmation_email' => 'boolean',
        ]);

        // Clean up tags
        if (isset($validated['tags'])) {
            $validated['tags'] = array_values(array_filter(array_map('trim', $validated['tags'])));
        }

        // Clean up email domains
        if (($validated['domain_restriction_mode'] ?? 'none') === 'none') {
            $validated['allowed_email_domains'] = null;
        } elseif (isset($validated['allowed_email_domains'])) {
            $validated['allowed_email_domains'] = array_values(array_filter(array_map('trim', $validated['allowed_email_domains'])));
            if (empty($validated['allowed_email_domains'])) {
                $validated['allowed_email_domains'] = null;
                $validated['domain_restriction_mode'] = 'none';
            }
        }

        // Store old values for audit
        $oldValues = $form->only(['name', 'is_active', 'is_public', 'workflow_id']);

        // Check if schema or settings changed (need new version)
        $schemaChanged = json_encode($form->schema) !== json_encode($validated['schema']);
        $settingsChanged = json_encode($form->settings ?? []) !== json_encode($validated['settings'] ?? []);

        $form->update($validated);

        // Create new version if schema or settings changed
        if ($schemaChanged || $settingsChanged) {
            $changeNote = $request->input('version_note', 'Aktualizácia formulára');
            $form->createVersion($changeNote, auth()->id());
        }

        // Clear caches
        Cache::forget('public_forms');
        Cache::forget('forms_list');

        // Audit log
        AuditService::formUpdated($form, $oldValues);

        return redirect()
            ->back()
            ->with('success', 'Formulár bol aktualizovaný');
    }

    public function destroy(Form $form)
    {
        // SECURITY: Only admin+ can delete forms
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403, 'Nemáte oprávnenie mazať formuláre.');
        }

        $this->authorizeForm($form);

        // Audit log before deletion
        AuditService::formDeleted($form);

        $form->delete();

        // Clear caches
        Cache::forget('public_forms');
        Cache::forget('forms_list');
        Cache::forget('dashboard_stats');

        return redirect()
            ->route('admin.forms.index')
            ->with('success', 'Formulár bol zmazaný');
    }

    public function submissions(Form $form, Request $request)
    {
        $this->authorizeFormSubmissions($form);

        $query = $form->submissions()->with('user:id,name,email');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $submissions = $query->latest()->paginate(20);

        return Inertia::render('Admin/Forms/Submissions', [
            'form' => $form,
            'submissions' => $submissions,
            'filters' => $request->only(['status']),
        ]);
    }

    public function approveSubmission(Request $request, Form $form, FormSubmission $submission)
    {
        // SECURITY: Only approver+ can approve submissions
        if (!auth()->user()->hasMinRole(User::ROLE_APPROVER)) {
            abort(403, 'Nemáte oprávnenie schvaľovať odpovede.');
        }

        $this->authorizeFormSubmissions($form);

        if ($submission->form_id !== $form->id) {
            abort(404);
        }

        $submission->update([
            'status' => 'approved',
            'admin_response' => $request->input('admin_response'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Audit log
        AuditService::submissionApproved($submission, $request->input('admin_response'));

        // Send email notification
        $this->sendStatusNotification($submission);

        // Trigger approval workflows (unless explicitly disabled)
        $runWorkflow = $request->input('run_workflow', true);
        if ($runWorkflow) {
            $this->triggerApprovalWorkflows($submission);
        }

        return redirect()
            ->back()
            ->with('success', 'Odpoved bola schvalena');
    }

    /**
     * Trigger workflows that are set to run on approval
     */
    private function triggerApprovalWorkflows(FormSubmission $submission): void
    {
        $workflows = Workflow::where('form_id', $submission->form_id)
            ->where('is_active', true)
            ->where('trigger_on', 'approval')
            ->get();

        if ($workflows->isEmpty()) {
            return;
        }

        $workflowEngine = app(WorkflowEngine::class);

        foreach ($workflows as $workflow) {
            try {
                $workflowEngine->startExecution($workflow, $submission);
            } catch (\Exception $e) {
                \Log::error('Failed to start approval workflow', [
                    'workflow_id' => $workflow->id,
                    'submission_id' => $submission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function rejectSubmission(Request $request, Form $form, FormSubmission $submission)
    {
        // SECURITY: Only approver+ can reject submissions
        if (!auth()->user()->hasMinRole(User::ROLE_APPROVER)) {
            abort(403, 'Nemáte oprávnenie zamietať odpovede.');
        }

        $this->authorizeFormSubmissions($form);

        if ($submission->form_id !== $form->id) {
            abort(404);
        }

        $submission->update([
            'status' => 'rejected',
            'admin_response' => $request->input('admin_response'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Audit log
        AuditService::submissionRejected($submission, $request->input('admin_response'));

        // Send email notification
        $this->sendStatusNotification($submission);

        return redirect()
            ->back()
            ->with('success', 'Odpoved bola zamietnuta');
    }

    private function sendStatusNotification(FormSubmission $submission): void
    {
        // Only send if user has email
        if ($submission->user && $submission->user->email) {
            $submission->load('form');
            Mail::to($submission->user->email)
                ->send(new SubmissionStatusChanged($submission));
        }
    }

    public function deleteSubmission(Form $form, FormSubmission $submission)
    {
        // SECURITY: Only admin+ can delete submissions
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403, 'Nemáte oprávnenie mazať odpovede.');
        }

        $this->authorizeFormSubmissions($form);

        if ($submission->form_id !== $form->id) {
            abort(404);
        }

        // Audit log before deletion
        AuditService::submissionDeleted($submission);

        $submission->delete();

        return redirect()
            ->back()
            ->with('success', 'Odpoveď bola zmazaná');
    }

    public function exportSubmissions(Request $request, Form $form, ExportService $exportService)
    {
        $this->authorizeFormSubmissions($form);

        $query = $form->submissions()->with(['user:id,name,email', 'reviewer:id,name']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $submissions = $query->latest()->get();

        $format = $request->input('format', 'csv');

        if ($format === 'xlsx') {
            return $exportService->exportToExcel($submissions, $form);
        }

        return $exportService->exportToCsv($submissions, $form);
    }

    public function versions(Form $form)
    {
        $this->authorizeForm($form);

        $versions = $form->versions()
            ->with('creator:id,name,email')
            ->get();

        return response()->json([
            'versions' => $versions,
            'current_version' => $form->current_version,
        ]);
    }

    public function showVersion(Form $form, FormVersion $version)
    {
        $this->authorizeForm($form);

        if ($version->form_id !== $form->id) {
            abort(404);
        }

        return response()->json([
            'version' => $version->load('creator:id,name,email'),
        ]);
    }

    public function restoreVersion(Request $request, Form $form, FormVersion $version)
    {
        // SECURITY: Only admin+ can restore versions
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403, 'Nemáte oprávnenie obnoviť verzie formulárov.');
        }

        $this->authorizeForm($form);

        if ($version->form_id !== $form->id) {
            abort(404);
        }

        $changeNote = $request->input('change_note', "Obnovené z verzie {$version->version_number}");

        $form->restoreToVersion($version, $changeNote, auth()->id());

        // Clear caches
        Cache::forget('public_forms');
        Cache::forget('forms_list');

        return response()->json([
            'success' => true,
            'message' => 'Formulár bol obnovený na verziu ' . $version->version_number,
            'current_version' => $form->fresh()->current_version,
        ]);
    }
}
