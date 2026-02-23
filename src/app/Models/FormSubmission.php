<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'user_id',
        'user_login',
        'data',
        'status',
        'admin_response',
        'reviewed_by',
        'reviewed_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function workflowExecutions()
    {
        return $this->hasMany(WorkflowExecution::class, 'submission_id');
    }

    public function comments()
    {
        return $this->hasMany(SubmissionComment::class, 'submission_id')->orderBy('created_at', 'desc');
    }
}
