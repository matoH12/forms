<?php

namespace App\Jobs;

use App\Models\WorkflowExecution;
use App\Services\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteWorkflowStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public WorkflowExecution $execution
    ) {}

    public function handle(WorkflowEngine $engine): void
    {
        $engine->executeStep($this->execution);
    }

    public function failed(\Throwable $exception): void
    {
        $this->execution->update([
            'status' => WorkflowExecution::STATUS_FAILED,
            'completed_at' => now(),
        ]);

        $this->execution->addLog('Job zlyhal: ' . $exception->getMessage());
    }
}
