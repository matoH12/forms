<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an action
     */
    public static function log(
        string $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log form creation
     */
    public static function formCreated(Model $form): AuditLog
    {
        return self::log('form_created', $form, null, [
            'name' => $form->localized_name,
            'slug' => $form->slug,
        ]);
    }

    /**
     * Log form update
     */
    public static function formUpdated(Model $form, array $oldValues): AuditLog
    {
        return self::log('form_updated', $form, $oldValues, [
            'name' => $form->localized_name,
        ]);
    }

    /**
     * Log form deletion
     */
    public static function formDeleted(Model $form): AuditLog
    {
        return self::log('form_deleted', null, [
            'id' => $form->id,
            'name' => $form->localized_name,
            'slug' => $form->slug,
        ]);
    }

    /**
     * Log form submission
     */
    public static function formSubmitted(Model $submission): AuditLog
    {
        return self::log('form_submitted', $submission, null, null, [
            'form_id' => $submission->form_id,
            'form_name' => $submission->form?->localized_name,
        ]);
    }

    /**
     * Log submission approval
     */
    public static function submissionApproved(Model $submission, ?string $response = null): AuditLog
    {
        return self::log('submission_approved', $submission, [
            'status' => 'pending',
        ], [
            'status' => 'approved',
            'admin_response' => $response,
        ], [
            'form_name' => $submission->form?->localized_name,
        ]);
    }

    /**
     * Log submission rejection
     */
    public static function submissionRejected(Model $submission, ?string $response = null): AuditLog
    {
        return self::log('submission_rejected', $submission, [
            'status' => 'pending',
        ], [
            'status' => 'rejected',
            'admin_response' => $response,
        ], [
            'form_name' => $submission->form?->localized_name,
        ]);
    }

    /**
     * Log submission deletion
     */
    public static function submissionDeleted(Model $submission): AuditLog
    {
        return self::log('submission_deleted', null, [
            'id' => $submission->id,
            'form_id' => $submission->form_id,
        ], null, [
            'form_name' => $submission->form?->localized_name,
        ]);
    }

    /**
     * Log workflow creation
     */
    public static function workflowCreated(Model $workflow): AuditLog
    {
        return self::log('workflow_created', $workflow, null, [
            'name' => $workflow->name,
        ]);
    }

    /**
     * Log workflow update
     */
    public static function workflowUpdated(Model $workflow, array $oldValues): AuditLog
    {
        return self::log('workflow_updated', $workflow, $oldValues, [
            'name' => $workflow->name,
        ]);
    }

    /**
     * Log workflow deletion
     */
    public static function workflowDeleted(Model $workflow): AuditLog
    {
        return self::log('workflow_deleted', null, [
            'id' => $workflow->id,
            'name' => $workflow->name,
        ]);
    }

    /**
     * Log workflow execution
     */
    public static function workflowExecuted(Model $execution): AuditLog
    {
        return self::log('workflow_executed', $execution, null, [
            'status' => $execution->status,
        ], [
            'workflow_name' => $execution->workflow?->name,
            'submission_id' => $execution->submission_id,
        ]);
    }

    /**
     * Log user login
     */
    public static function userLogin(Model $user): AuditLog
    {
        return self::log('user_login', $user, null, null, [
            'email' => $user->email,
        ]);
    }

    /**
     * Log user logout
     */
    public static function userLogout(Model $user): AuditLog
    {
        return self::log('user_logout', $user, null, null, [
            'email' => $user->email,
        ]);
    }

    /**
     * Log user update
     */
    public static function userUpdated(Model $user, array $changes): AuditLog
    {
        return self::log('user_updated', $user, null, $changes, [
            'email' => $user->email,
        ]);
    }

    /**
     * Log admin toggle
     */
    public static function userAdminToggled(Model $user, bool $isAdmin): AuditLog
    {
        return self::log('user_admin_toggled', $user, [
            'is_admin' => !$isAdmin,
        ], [
            'is_admin' => $isAdmin,
        ], [
            'email' => $user->email,
        ]);
    }

    /**
     * Log user deletion
     */
    public static function userDeleted(Model $user): AuditLog
    {
        return self::log('user_deleted', null, [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }

    /**
     * Log settings update
     */
    public static function settingsUpdated(string $section, array $changes): AuditLog
    {
        return self::log('settings_updated', null, null, $changes, [
            'section' => $section,
        ]);
    }

    /**
     * Log approval action (from workflow)
     */
    public static function approvalAction(Model $approval, string $action): AuditLog
    {
        return self::log(
            $action === 'approved' ? 'approval_approved' : 'approval_rejected',
            $approval,
            ['status' => 'pending'],
            ['status' => $action]
        );
    }
}
