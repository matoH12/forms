<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    /**
     * SECURITY: Whitelist of allowed model classes for dynamic instantiation
     * Only these classes can be loaded via getModelAttribute()
     */
    private const ALLOWED_MODEL_CLASSES = [
        \App\Models\User::class,
        \App\Models\Form::class,
        \App\Models\FormSubmission::class,
        \App\Models\FormVersion::class,
        \App\Models\FormCategory::class,
        \App\Models\Workflow::class,
        \App\Models\WorkflowExecution::class,
        \App\Models\WorkflowStep::class,
        \App\Models\ApprovalRequest::class,
        \App\Models\EmailTemplate::class,
        \App\Models\SubmissionComment::class,
        \App\Models\SystemApiToken::class,
    ];

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related model instance
     * SECURITY: Only allows whitelisted model classes to prevent arbitrary class instantiation
     */
    public function getModelAttribute()
    {
        if ($this->model_type && $this->model_id) {
            $modelClass = $this->model_type;

            // SECURITY: Validate against whitelist before instantiation
            if (!in_array($modelClass, self::ALLOWED_MODEL_CLASSES, true)) {
                return null;
            }

            if (class_exists($modelClass)) {
                return $modelClass::find($this->model_id);
            }
        }
        return null;
    }

    /**
     * Get human-readable action name
     */
    public function getActionLabelAttribute(): string
    {
        $labels = [
            // Form actions
            'form_created' => 'Vytvorenie formulára',
            'form_updated' => 'Úprava formulára',
            'form_deleted' => 'Zmazanie formulára',

            // Submission actions
            'form_submitted' => 'Odoslanie formulára',
            'submission_approved' => 'Schválenie žiadosti',
            'submission_rejected' => 'Zamietnutie žiadosti',
            'submission_deleted' => 'Zmazanie žiadosti',

            // Workflow actions
            'workflow_created' => 'Vytvorenie workflow',
            'workflow_updated' => 'Úprava workflow',
            'workflow_deleted' => 'Zmazanie workflow',
            'workflow_executed' => 'Spustenie workflow',

            // User actions
            'user_login' => 'Prihlásenie',
            'user_logout' => 'Odhlásenie',
            'user_updated' => 'Úprava používateľa',
            'user_admin_toggled' => 'Zmena admin práv',
            'user_deleted' => 'Zmazanie používateľa',

            // Settings
            'settings_updated' => 'Úprava nastavení',

            // Approval
            'approval_approved' => 'Schválenie (workflow)',
            'approval_rejected' => 'Zamietnutie (workflow)',
        ];

        return $labels[$this->action] ?? $this->action;
    }

    /**
     * Get icon for action
     */
    public function getActionIconAttribute(): string
    {
        $icons = [
            'form_created' => 'plus',
            'form_updated' => 'pencil',
            'form_deleted' => 'trash',
            'form_submitted' => 'paper-airplane',
            'submission_approved' => 'check',
            'submission_rejected' => 'x-mark',
            'submission_deleted' => 'trash',
            'workflow_created' => 'plus',
            'workflow_updated' => 'pencil',
            'workflow_deleted' => 'trash',
            'workflow_executed' => 'play',
            'user_login' => 'arrow-right-on-rectangle',
            'user_logout' => 'arrow-left-on-rectangle',
            'settings_updated' => 'cog',
        ];

        return $icons[$this->action] ?? 'document';
    }

    /**
     * Get color for action (for UI)
     */
    public function getActionColorAttribute(): string
    {
        $colors = [
            'form_created' => 'green',
            'form_updated' => 'blue',
            'form_deleted' => 'red',
            'form_submitted' => 'blue',
            'submission_approved' => 'green',
            'submission_rejected' => 'red',
            'submission_deleted' => 'red',
            'workflow_created' => 'green',
            'workflow_updated' => 'blue',
            'workflow_deleted' => 'red',
            'workflow_executed' => 'purple',
            'user_login' => 'gray',
            'user_logout' => 'gray',
            'settings_updated' => 'yellow',
        ];

        return $colors[$this->action] ?? 'gray';
    }
}
