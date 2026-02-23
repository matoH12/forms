<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SubmissionStatusChanged;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\SubmissionComment;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SubmissionController extends Controller
{
    /**
     * Escape LIKE wildcards to prevent DoS attacks
     * (e.g., "%%%%%" causing slow queries)
     */
    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        // Get IDs of forms the user has explicit permission for (not including created forms)
        $allowedFormIds = $user->getVisibleFormsQuery(false)->pluck('id');

        $query = FormSubmission::with(['form:id,name,slug', 'user:id,name,first_name,last_name,email'])
            ->whereIn('form_id', $allowedFormIds)
            ->latest();

        if ($request->filled('form_id')) {
            // Verify the user can see this form
            if (!$allowedFormIds->contains($request->form_id)) {
                abort(403, 'Nemáte oprávnenie na prístup k tomuto formuláru.');
            }
            $query->where('form_id', $request->form_id);
        }

        // Default to pending if no status filter
        $status = $request->input('status', 'pending');
        if ($status === 'all') {
            // Show all
        } else {
            $query->where(function ($q) use ($status) {
                if ($status === 'pending') {
                    $q->whereNull('status')
                      ->orWhere('status', 'pending')
                      ->orWhere('status', 'submitted');
                } else {
                    $q->where('status', $status);
                }
            });
        }

        if ($request->filled('search')) {
            $escapedSearch = $this->escapeLikeWildcards($request->search);
            $query->where('data', 'like', '%' . $escapedSearch . '%');
        }

        $submissions = $query->paginate(20);

        // Get only forms the user has explicit permission for
        $forms = $user->getVisibleFormsQuery(false)->select('id', 'name')->get();

        // Get counts only for forms user can see
        $counts = DB::table('form_submissions')
            ->whereIn('form_id', $allowedFormIds)
            ->selectRaw("
                SUM(CASE WHEN status IS NULL OR status = 'pending' OR status = 'submitted' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            ")
            ->first();

        $counts = [
            'pending' => (int) ($counts->pending ?? 0),
            'approved' => (int) ($counts->approved ?? 0),
            'rejected' => (int) ($counts->rejected ?? 0),
        ];

        return Inertia::render('Admin/Submissions/Index', [
            'submissions' => $submissions,
            'forms' => $forms,
            'filters' => $this->sanitizeFilters([
                'form_id' => $request->input('form_id'),
                'status' => $status,
                'search' => $request->input('search'),
            ]),
            'counts' => $counts,
        ]);
    }

    public function show(FormSubmission $submission)
    {
        $submission->load('form');

        // Check if user has explicit permission for this form's submissions
        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }

        $submission->load([
            'user:id,name,first_name,last_name,email',
            'reviewer:id,name,first_name,last_name,email',
            'workflowExecutions.workflow:id,name',
            'workflowExecutions.approvalRequests',
            'comments.user:id,name,first_name,last_name,email',
        ]);

        return Inertia::render('Admin/Submissions/Show', [
            'submission' => $submission,
        ]);
    }

    public function addComment(Request $request, FormSubmission $submission)
    {
        // SECURITY: Only approver+ can add comments
        if (!auth()->user()->hasMinRole(User::ROLE_APPROVER)) {
            abort(403, 'Nemáte oprávnenie pridávať komentáre.');
        }

        $submission->load('form');

        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }

        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        // Sanitize HTML to prevent XSS
        $sanitizedContent = strip_tags($request->input('content'));

        $comment = SubmissionComment::create([
            'submission_id' => $submission->id,
            'user_id' => auth()->id(),
            'content' => $sanitizedContent,
            'is_internal' => true,
        ]);

        $comment->load('user:id,name,first_name,last_name,email');

        return response()->json([
            'success' => true,
            'comment' => $comment,
        ]);
    }

    public function updateComment(Request $request, FormSubmission $submission, SubmissionComment $comment)
    {
        // SECURITY: Only approver+ can update comments
        if (!auth()->user()->hasMinRole(User::ROLE_APPROVER)) {
            abort(403, 'Nemáte oprávnenie upravovať komentáre.');
        }

        $submission->load('form');

        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }

        // Only allow editing own comments
        if ($comment->user_id !== auth()->id()) {
            abort(403, 'Môžete upravovať len vlastné komentáre.');
        }

        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        // Sanitize HTML to prevent XSS
        $sanitizedContent = strip_tags($request->input('content'));

        $comment->update([
            'content' => $sanitizedContent,
        ]);

        $comment->load('user:id,name,first_name,last_name,email');

        return response()->json([
            'success' => true,
            'comment' => $comment,
        ]);
    }

    public function deleteComment(FormSubmission $submission, SubmissionComment $comment)
    {
        // SECURITY: Only approver+ can delete comments
        if (!auth()->user()->hasMinRole(User::ROLE_APPROVER)) {
            abort(403, 'Nemáte oprávnenie mazať komentáre.');
        }

        $submission->load('form');

        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }

        // Only allow deleting own comments
        if ($comment->user_id !== auth()->id()) {
            abort(403, 'Môžete mazať len vlastné komentáre.');
        }

        $comment->delete();

        return response()->json(['success' => true]);
    }

    public function destroy(FormSubmission $submission)
    {
        // SECURITY: Only admin+ can delete submissions
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403, 'Nemáte oprávnenie mazať odpovede.');
        }

        $submission->load('form');

        // Check if user has explicit permission for this form's submissions
        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }

        // Audit log before deletion
        AuditService::submissionDeleted($submission);

        $submission->delete();

        // Clear submission counts cache
        Cache::forget('submission_counts');

        return redirect()
            ->back()
            ->with('success', 'Odpoveď bola zmazaná');
    }

    public function deleteFile(Request $request, FormSubmission $submission)
    {
        // SECURITY: Only admin+ can delete files
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403, 'Nemáte oprávnenie mazať súbory.');
        }

        $submission->load('form');

        // Check if user has explicit permission for this form's submissions
        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }

        $fieldName = $request->input('field');
        $fileIndex = $request->input('index'); // null for single file, number for array

        // Validate that field exists in form schema and is a file type
        $schema = $submission->form->schema ?? [];
        $fields = $schema['fields'] ?? [];
        $fieldDefinition = collect($fields)->firstWhere('name', $fieldName);

        if (!$fieldDefinition) {
            return response()->json(['error' => 'Pole neexistuje v schéme formulára'], 404);
        }

        if ($fieldDefinition['type'] !== 'file') {
            return response()->json(['error' => 'Pole nie je typu súbor'], 400);
        }

        $data = $submission->data;

        if (!isset($data[$fieldName])) {
            return response()->json(['error' => 'Pole neexistuje'], 404);
        }

        $fileData = $data[$fieldName];

        // Handle array of files
        if ($fileIndex !== null && is_array($fileData) && isset($fileData[$fileIndex])) {
            $file = $fileData[$fileIndex];

            // Delete from storage
            if (isset($file['path'])) {
                Storage::disk('public')->delete($file['path']);
            }

            // Remove from array
            array_splice($data[$fieldName], $fileIndex, 1);

            // If array is empty, set to null
            if (empty($data[$fieldName])) {
                $data[$fieldName] = null;
            }
        }
        // Handle single file
        elseif ($fileIndex === null && is_array($fileData) && isset($fileData['path'])) {
            // Delete from storage
            Storage::disk('public')->delete($fileData['path']);

            // Set to null
            $data[$fieldName] = null;
        } else {
            return response()->json(['error' => 'Súbor neexistuje'], 404);
        }

        // Update submission
        $submission->update(['data' => $data]);

        // Audit log
        AuditService::log('file_deleted', $submission, [
            'field' => $fieldName,
            'index' => $fileIndex,
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function approve(Request $request, FormSubmission $submission)
    {
        // SECURITY: Only approver+ can approve submissions
        if (!auth()->user()->hasMinRole(User::ROLE_APPROVER)) {
            abort(403, 'Nemáte oprávnenie schvaľovať odpovede.');
        }

        $submission->load('form');

        // Check if user has explicit permission for this form's submissions
        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }

        $submission->update([
            'status' => 'approved',
            'admin_response' => $request->input('admin_response'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Clear submission counts cache
        Cache::forget('submission_counts');

        // Audit log
        AuditService::submissionApproved($submission, $request->input('admin_response'));

        // Send email notification (queued)
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

    public function reject(Request $request, FormSubmission $submission)
    {
        // SECURITY: Only approver+ can reject submissions
        if (!auth()->user()->hasMinRole(User::ROLE_APPROVER)) {
            abort(403, 'Nemáte oprávnenie zamietať odpovede.');
        }

        $submission->load('form');

        // Check if user has explicit permission for this form's submissions
        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }

        $submission->update([
            'status' => 'rejected',
            'admin_response' => $request->input('admin_response'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Clear submission counts cache
        Cache::forget('submission_counts');

        // Audit log
        AuditService::submissionRejected($submission, $request->input('admin_response'));

        // Send email notification (queued)
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

    private function triggerApprovalWorkflows(FormSubmission $submission): void
    {
        // Get workflows that trigger on approval for this form
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

    public function bulkApprove(Request $request)
    {
        // SECURITY: Only approver+ can approve submissions
        if (!auth()->user()->hasMinRole(User::ROLE_APPROVER)) {
            abort(403, 'Nemáte oprávnenie schvaľovať odpovede.');
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:form_submissions,id',
            'admin_response' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();
        $allowedFormIds = $user->getVisibleFormsQuery(false)->pluck('id');

        $submissions = FormSubmission::with('form')
            ->whereIn('id', $request->ids)
            ->whereIn('form_id', $allowedFormIds)
            ->get();

        if ($submissions->count() !== count($request->ids)) {
            abort(403, 'Nemáte oprávnenie na niektoré z vybraných odpovedí.');
        }

        $approved = 0;
        foreach ($submissions as $submission) {
            if ($submission->status !== 'approved') {
                $submission->update([
                    'status' => 'approved',
                    'admin_response' => $request->input('admin_response'),
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                AuditService::submissionApproved($submission, $request->input('admin_response'));
                $this->sendStatusNotification($submission);
                $this->triggerApprovalWorkflows($submission);
                $approved++;
            }
        }

        Cache::forget('submission_counts');

        return redirect()
            ->back()
            ->with('success', "Schválených $approved odpovedí");
    }

    public function bulkReject(Request $request)
    {
        // SECURITY: Only approver+ can reject submissions
        if (!auth()->user()->hasMinRole(User::ROLE_APPROVER)) {
            abort(403, 'Nemáte oprávnenie zamietať odpovede.');
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:form_submissions,id',
            'admin_response' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();
        $allowedFormIds = $user->getVisibleFormsQuery(false)->pluck('id');

        $submissions = FormSubmission::with('form')
            ->whereIn('id', $request->ids)
            ->whereIn('form_id', $allowedFormIds)
            ->get();

        if ($submissions->count() !== count($request->ids)) {
            abort(403, 'Nemáte oprávnenie na niektoré z vybraných odpovedí.');
        }

        $rejected = 0;
        foreach ($submissions as $submission) {
            if ($submission->status !== 'rejected') {
                $submission->update([
                    'status' => 'rejected',
                    'admin_response' => $request->input('admin_response'),
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                AuditService::submissionRejected($submission, $request->input('admin_response'));
                $this->sendStatusNotification($submission);
                $rejected++;
            }
        }

        Cache::forget('submission_counts');

        return redirect()
            ->back()
            ->with('success', "Zamietnutých $rejected odpovedí");
    }

    public function bulkDelete(Request $request)
    {
        // SECURITY: Only admin+ can delete submissions
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403, 'Nemáte oprávnenie mazať odpovede.');
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:form_submissions,id',
        ]);

        $user = auth()->user();
        $allowedFormIds = $user->getVisibleFormsQuery(false)->pluck('id');

        $submissions = FormSubmission::with('form')
            ->whereIn('id', $request->ids)
            ->whereIn('form_id', $allowedFormIds)
            ->get();

        if ($submissions->count() !== count($request->ids)) {
            abort(403, 'Nemáte oprávnenie na niektoré z vybraných odpovedí.');
        }

        $deleted = 0;
        foreach ($submissions as $submission) {
            AuditService::submissionDeleted($submission);
            $submission->delete();
            $deleted++;
        }

        Cache::forget('submission_counts');

        return redirect()
            ->back()
            ->with('success', "Zmazaných $deleted odpovedí");
    }

    public function export(Request $request, ExportService $exportService)
    {
        $user = auth()->user();

        // Get IDs of forms the user has explicit permission for
        $allowedFormIds = $user->getVisibleFormsQuery(false)->pluck('id');

        $query = FormSubmission::with(['form:id,name,slug,schema', 'user:id,name,first_name,last_name,email', 'reviewer:id,name'])
            ->whereIn('form_id', $allowedFormIds)
            ->latest();

        // Apply filters
        if ($request->filled('form_id')) {
            // Verify the user can see this form
            if (!$allowedFormIds->contains($request->form_id)) {
                abort(403, 'Nemáte oprávnenie na prístup k tomuto formuláru.');
            }
            $query->where('form_id', $request->form_id);
        }

        $status = $request->input('status', 'all');
        if ($status !== 'all') {
            $query->where(function ($q) use ($status) {
                if ($status === 'pending') {
                    $q->whereNull('status')
                      ->orWhere('status', 'pending')
                      ->orWhere('status', 'submitted');
                } else {
                    $q->where('status', $status);
                }
            });
        }

        $submissions = $query->get();

        $format = $request->input('format', 'csv');

        // If exporting for a specific form, use form-specific export
        if ($request->filled('form_id')) {
            $form = Form::findOrFail($request->form_id);
            if ($format === 'xlsx') {
                return $exportService->exportToExcel($submissions, $form);
            }
            return $exportService->exportToCsv($submissions, $form);
        }

        // Otherwise use generic export
        return $exportService->exportAllToCsv($submissions);
    }

    /**
     * Get current workflow execution status for a submission (used for polling/auto-refresh)
     */
    public function getWorkflowStatus(FormSubmission $submission)
    {
        $submission->load('form');

        // Check if user has explicit permission for this form's submissions
        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }

        $submission->load([
            'workflowExecutions.workflow:id,name',
            'workflowExecutions.approvalRequests',
        ]);

        return response()->json([
            'workflow_executions' => $submission->workflowExecutions,
        ]);
    }

    /**
     * Stop a running workflow execution
     */
    public function stopWorkflowExecution(FormSubmission $submission, WorkflowExecution $execution)
    {
        // SECURITY: Only admin+ can stop workflow executions
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403, 'Nemáte oprávnenie zastaviť workflow.');
        }

        $submission->load('form');

        // Check if user has permission for this form's submissions
        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }

        // Verify the execution belongs to this submission
        if ($execution->submission_id !== $submission->id) {
            abort(404, 'Workflow vykonanie nepatrí k tejto odpovedi.');
        }

        // Only stop if running or waiting
        if (!in_array($execution->status, [WorkflowExecution::STATUS_RUNNING, WorkflowExecution::STATUS_WAITING_APPROVAL, WorkflowExecution::STATUS_PENDING])) {
            return response()->json([
                'success' => false,
                'message' => 'Workflow nie je možné zastaviť, pretože už nie je aktívny.',
            ], 400);
        }

        $execution->addLog('Workflow zastavený používateľom: ' . auth()->user()->name);
        $execution->update([
            'status' => WorkflowExecution::STATUS_STOPPED,
            'completed_at' => now(),
        ]);

        // Audit log
        AuditService::log('workflow_stopped', $submission, [
            'workflow_execution_id' => $execution->id,
            'workflow_name' => $execution->workflow->name ?? 'Unknown',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Workflow bol zastavený.',
            'execution' => $execution->fresh()->load('workflow:id,name', 'approvalRequests'),
        ]);
    }

    /**
     * Restart workflow for a submission
     */
    public function restartWorkflow(Request $request, FormSubmission $submission)
    {
        // SECURITY: Only admin+ can restart workflows
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            abort(403, 'Nemáte oprávnenie spustiť workflow.');
        }

        $submission->load('form');

        // Check if user has permission for this form's submissions
        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            abort(403, 'Nemáte oprávnenie na prístup k odpovediam tohto formulára.');
        }

        // Get the workflow to restart (either from request or from form's default workflow)
        $workflowId = $request->input('workflow_id');

        if ($workflowId) {
            $workflow = Workflow::find($workflowId);
        } else {
            // Get the form's workflow
            $workflow = $submission->form->workflow;
        }

        if (!$workflow) {
            return response()->json([
                'success' => false,
                'message' => 'Žiadny workflow nie je priradený k tomuto formuláru.',
            ], 400);
        }

        if (!$workflow->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Workflow nie je aktívny.',
            ], 400);
        }

        // Start new workflow execution
        $workflowEngine = app(WorkflowEngine::class);
        $execution = $workflowEngine->startExecution($workflow, $submission);

        // Audit log
        AuditService::log('workflow_restarted', $submission, [
            'workflow_execution_id' => $execution->id,
            'workflow_name' => $workflow->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Workflow bol spustený.',
            'execution' => $execution->load('workflow:id,name', 'approvalRequests'),
        ]);
    }
}
