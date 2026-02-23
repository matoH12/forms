<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'form_id',
        'trigger_on',
        'is_active',
        'nodes',
        'edges',
        'current_version',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'nodes' => 'array',
            'edges' => 'array',
        ];
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('order');
    }

    public function executions()
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    public function getStartNodeAttribute()
    {
        return collect($this->nodes)->firstWhere('type', 'start');
    }

    /**
     * All versions of this workflow
     */
    public function versions()
    {
        return $this->hasMany(WorkflowVersion::class)->orderBy('version_number', 'desc');
    }

    /**
     * Create a new version snapshot of the current workflow state
     * Maintains maximum of 20 versions, deleting oldest when limit exceeded
     */
    public function createVersion(?string $changeNote = null, ?int $userId = null): WorkflowVersion
    {
        $nextVersion = ($this->current_version ?? 0) + 1;

        $version = WorkflowVersion::create([
            'workflow_id' => $this->id,
            'version_number' => $nextVersion,
            'nodes' => $this->nodes,
            'edges' => $this->edges,
            'change_note' => $changeNote,
            'created_by' => $userId,
        ]);

        $this->update(['current_version' => $nextVersion]);

        // Delete old versions if more than 20 exist (keep newest 20)
        $versionCount = $this->versions()->count();
        if ($versionCount > 20) {
            $oldVersionIds = $this->versions()
                ->orderBy('version_number', 'asc')
                ->take($versionCount - 20)
                ->pluck('id');

            if ($oldVersionIds->isNotEmpty()) {
                WorkflowVersion::whereIn('id', $oldVersionIds)->delete();
            }
        }

        return $version;
    }

    /**
     * Restore workflow to a specific version
     */
    public function restoreToVersion(WorkflowVersion $version, ?string $changeNote = null, ?int $userId = null): WorkflowVersion
    {
        $this->update([
            'nodes' => $version->nodes,
            'edges' => $version->edges,
        ]);

        return $this->createVersion($changeNote ?? "Obnovené z verzie {$version->version_number}", $userId);
    }
}
