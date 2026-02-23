<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct(
        private WorkflowEngine $workflowEngine
    ) {}

    public function index()
    {
        // SECURITY: Only admin+ can access workflows
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $workflows = Workflow::with('form:id,name')
            ->withCount('executions')
            ->latest()
            ->paginate(20);

        return response()->json($workflows);
    }

    public function store(Request $request)
    {
        // SECURITY: Only admin+ can create workflows
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_id' => 'required|exists:forms,id',
            'trigger_on' => 'required|string|in:submission,update,manual',
            'is_active' => 'boolean',
            'nodes' => 'required|array',
            'edges' => 'required|array',
        ]);

        $workflow = Workflow::create($validated);

        $this->syncSteps($workflow, $validated['nodes']);

        return response()->json($workflow, 201);
    }

    public function show(Workflow $workflow)
    {
        // SECURITY: Only admin+ can view workflows
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $workflow->load(['steps', 'form:id,name']);

        return response()->json($workflow);
    }

    public function update(Request $request, Workflow $workflow)
    {
        // SECURITY: Only admin+ can update workflows
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_id' => 'required|exists:forms,id',
            'trigger_on' => 'required|string|in:submission,update,manual',
            'is_active' => 'boolean',
            'nodes' => 'required|array',
            'edges' => 'required|array',
        ]);

        $workflow->update($validated);

        $this->syncSteps($workflow, $validated['nodes']);

        return response()->json($workflow);
    }

    public function destroy(Workflow $workflow)
    {
        // SECURITY: Only admin+ can delete workflows
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $workflow->delete();

        return response()->json(null, 204);
    }

    public function toggle(Workflow $workflow)
    {
        // SECURITY: Only admin+ can toggle workflows
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $workflow->update(['is_active' => !$workflow->is_active]);

        return response()->json($workflow);
    }

    public function executions(Workflow $workflow)
    {
        // SECURITY: Only admin+ can view workflow executions
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $executions = $workflow->executions()
            ->with('submission:id,form_id,created_at')
            ->latest()
            ->paginate(20);

        return response()->json($executions);
    }

    public function test(Request $request, Workflow $workflow)
    {
        // SECURITY: Only admin+ can test workflows
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $testData = $request->input('data', []);

        $result = $this->workflowEngine->testWorkflow($workflow, $testData);

        return response()->json($result);
    }

    private function syncSteps(Workflow $workflow, array $nodes): void
    {
        $workflow->steps()->delete();

        $order = 0;
        foreach ($nodes as $node) {
            if (in_array($node['type'], ['start', 'end'])) {
                continue;
            }

            WorkflowStep::create([
                'workflow_id' => $workflow->id,
                'node_id' => $node['id'],
                'type' => $node['type'],
                'name' => $node['data']['label'] ?? $node['type'],
                'config' => $node['data'] ?? [],
                'order' => $order++,
            ]);
        }
    }
}
