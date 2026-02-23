<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApprovalController extends Controller
{
    public function __construct(
        private WorkflowEngine $workflowEngine
    ) {}

    /**
     * Show approval page (token in URL for email links)
     * Adds Referrer-Policy header to prevent token leakage
     */
    public function show(string $token)
    {
        $approval = ApprovalRequest::where('token', $token)
            ->with(['execution.submission.form', 'execution.workflow'])
            ->firstOrFail();

        // Check if token is expired
        if ($approval->isExpired()) {
            return Inertia::render('Public/ApprovalExpired', [
                'message' => 'Platnosť tohto odkazu vypršala.',
            ])->withHeaders([
                'Referrer-Policy' => 'no-referrer',
            ]);
        }

        return Inertia::render('Public/Approval', [
            'approval' => $approval,
            'submission' => $approval->execution->submission,
            'form' => $approval->execution->submission->form,
        ])->withHeaders([
            // Prevent token leakage via Referrer header
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    /**
     * Approve request (token in request body for security)
     */
    public function approve(Request $request)
    {
        $request->validate([
            // SECURITY: Token must be exactly 64-char alphanumeric (consistent with API endpoint)
            'token' => 'required|string|size:64|alpha_num',
            'comment' => 'nullable|string|max:5000',
        ]);

        $approval = ApprovalRequest::where('token', $request->input('token'))
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->firstOrFail();

        // Check if token is expired
        if ($approval->isExpired()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Platnosť odkazu vypršala'], 410);
            }
            return redirect()->back()->with('error', 'Platnosť odkazu vypršala');
        }

        $approval->update([
            'status' => ApprovalRequest::STATUS_APPROVED,
            'approved_by' => $request->user()?->id,
            'comment' => strip_tags($request->input('comment') ?? ''),
            'responded_at' => now(),
        ]);

        $this->workflowEngine->continueExecution($approval->execution);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Schválené']);
        }

        return redirect()->back()->with('success', 'Žiadosť bola schválená');
    }

    /**
     * Reject request (token in request body for security)
     */
    public function reject(Request $request)
    {
        $request->validate([
            // SECURITY: Token must be exactly 64-char alphanumeric (consistent with API endpoint)
            'token' => 'required|string|size:64|alpha_num',
            'comment' => 'nullable|string|max:5000',
        ]);

        $approval = ApprovalRequest::where('token', $request->input('token'))
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->firstOrFail();

        // Check if token is expired
        if ($approval->isExpired()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Platnosť odkazu vypršala'], 410);
            }
            return redirect()->back()->with('error', 'Platnosť odkazu vypršala');
        }

        $approval->update([
            'status' => ApprovalRequest::STATUS_REJECTED,
            'approved_by' => $request->user()?->id,
            'comment' => strip_tags($request->input('comment') ?? ''),
            'responded_at' => now(),
        ]);

        $approval->execution->update([
            'status' => 'rejected',
            'completed_at' => now(),
        ]);

        $approval->execution->addLog('Žiadosť zamietnutá', [
            'comment' => $request->input('comment'),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Zamietnuté']);
        }

        return redirect()->back()->with('success', 'Žiadosť bola zamietnutá');
    }
}
