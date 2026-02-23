<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    use HasFactory;

    const TYPE_API_CALL = 'api_call';
    const TYPE_APPROVAL = 'approval';
    const TYPE_CONDITION = 'condition';
    const TYPE_TRANSFORM = 'transform';
    const TYPE_EMAIL = 'email';

    protected $fillable = [
        'workflow_id',
        'node_id',
        'type',
        'name',
        'config',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
}
