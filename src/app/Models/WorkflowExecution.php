<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowExecution extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_WAITING_APPROVAL = 'waiting_approval';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_STOPPED = 'stopped';

    protected $fillable = [
        'workflow_id',
        'submission_id',
        'current_node_id',
        'status',
        'context',
        'logs',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'logs' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function approvalRequests()
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    public function addLog(string $message, array $data = [])
    {
        $logs = $this->logs ?? [];
        $logs[] = [
            'timestamp' => now()->toISOString(),
            'message' => $message,
            'data' => $data,
        ];
        $this->logs = $logs;
        $this->save();
    }
}
