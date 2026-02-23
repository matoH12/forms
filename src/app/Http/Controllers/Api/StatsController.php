<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $days = $request->get('days', 30);

        $submissionsByDay = FormSubmission::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $formStats = Form::withCount(['submissions' => function ($query) use ($days) {
            $query->where('created_at', '>=', now()->subDays($days));
        }])
            ->orderByDesc('submissions_count')
            ->take(10)
            ->get(['id', 'name']);

        $workflowStats = [
            'total' => Workflow::count(),
            'active' => Workflow::where('is_active', true)->count(),
            'executions' => WorkflowExecution::count(),
            'pending' => WorkflowExecution::where('status', 'waiting_approval')->count(),
            'completed' => WorkflowExecution::where('status', 'completed')->count(),
            'failed' => WorkflowExecution::where('status', 'failed')->count(),
        ];

        return response()->json([
            'submissions_by_day' => $submissionsByDay,
            'top_forms' => $formStats,
            'workflow_stats' => $workflowStats,
            'totals' => [
                'forms' => Form::count(),
                'submissions' => FormSubmission::count(),
                'users' => \App\Models\User::count(),
            ],
        ]);
    }
}
