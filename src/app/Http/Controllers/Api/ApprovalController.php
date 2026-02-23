<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\AuditService;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ApprovalController extends Controller
{
    public function __construct(
        private WorkflowEngine $workflowEngine
    ) {}

    #[OA\Post(
        path: "/api/v1/approvals/approve",
        summary: "Approve a request",
        description: "Approve a pending approval request using the token from email",
        operationId: "approveRequest",
        tags: ["Approvals"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["token"],
                properties: [
                    new OA\Property(property: "token", type: "string", description: "Approval token from email"),
                    new OA\Property(property: "comment", type: "string", description: "Optional comment")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Request approved"),
            new OA\Response(response: 400, description: "Token expired"),
            new OA\Response(response: 404, description: "Token not found or already processed")
        ]
    )]
    public function approve(Request $request)
    {
        $validated = $request->validate([
            // SECURITY: Token is 64-char alphanumeric (not UUID anymore)
            'token' => 'required|string|size:64|alpha_num',
            'comment' => 'nullable|string|max:1000',
        ]);

        $approval = ApprovalRequest::where('token', $validated['token'])
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->firstOrFail();

        // SECURITY: Check token expiration
        if ($approval->isExpired()) {
            AuditService::log('approval_token_expired', $approval->execution?->submission, null, [
                'approval_id' => $approval->id,
                'token' => substr($validated['token'], 0, 8) . '...',
                'expired_at' => $approval->expires_at?->toIso8601String(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Token expiroval. Kontaktujte administratora.',
                'error' => 'token_expired',
            ], 400);
        }

        $approval->update([
            'status' => ApprovalRequest::STATUS_APPROVED,
            'approved_by' => auth()->id(),
            'comment' => $validated['comment'] ?? null,
            'responded_at' => now(),
        ]);

        // SECURITY: Audit log approval action
        AuditService::log('approval_approved', $approval->execution?->submission, null, [
            'approval_id' => $approval->id,
            'approver_email' => $approval->approver_email,
            'ip' => $request->ip(),
        ]);

        $this->workflowEngine->continueExecution($approval->execution);

        return response()->json(['message' => 'Schválené']);
    }

    #[OA\Post(
        path: "/api/v1/approvals/reject",
        summary: "Reject a request",
        description: "Reject a pending approval request using the token from email",
        operationId: "rejectRequest",
        tags: ["Approvals"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["token"],
                properties: [
                    new OA\Property(property: "token", type: "string", description: "Approval token from email"),
                    new OA\Property(property: "comment", type: "string", description: "Reason for rejection")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Request rejected"),
            new OA\Response(response: 400, description: "Token expired"),
            new OA\Response(response: 404, description: "Token not found or already processed")
        ]
    )]
    public function reject(Request $request)
    {
        $validated = $request->validate([
            // SECURITY: Token is 64-char alphanumeric (not UUID anymore)
            'token' => 'required|string|size:64|alpha_num',
            'comment' => 'nullable|string|max:1000',
        ]);

        $approval = ApprovalRequest::where('token', $validated['token'])
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->firstOrFail();

        // SECURITY: Check token expiration
        if ($approval->isExpired()) {
            AuditService::log('approval_token_expired', $approval->execution?->submission, null, [
                'approval_id' => $approval->id,
                'token' => substr($validated['token'], 0, 8) . '...',
                'expired_at' => $approval->expires_at?->toIso8601String(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Token expiroval. Kontaktujte administratora.',
                'error' => 'token_expired',
            ], 400);
        }

        $approval->update([
            'status' => ApprovalRequest::STATUS_REJECTED,
            'approved_by' => auth()->id(),
            'comment' => $validated['comment'] ?? null,
            'responded_at' => now(),
        ]);

        $approval->execution->update([
            'status' => 'rejected',
            'completed_at' => now(),
        ]);

        $approval->execution->addLog('Žiadosť zamietnutá', [
            'comment' => $validated['comment'] ?? null,
        ]);

        // SECURITY: Audit log rejection action
        AuditService::log('approval_rejected', $approval->execution?->submission, null, [
            'approval_id' => $approval->id,
            'approver_email' => $approval->approver_email,
            'comment' => $validated['comment'] ?? null,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Zamietnuté']);
    }
}
