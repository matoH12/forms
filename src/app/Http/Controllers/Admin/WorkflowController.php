<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkflowController extends Controller
{
    public function index()
    {
        $workflows = Workflow::with('form:id,name')
            ->withCount('executions')
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Workflows/Index', [
            'workflows' => $workflows,
        ]);
    }

    public function create()
    {
        $forms = Form::where('is_active', true)->select('id', 'name', 'schema')->get();
        $emailTemplates = EmailTemplate::where('is_active', true)->select('id', 'name', 'subject')->get();

        return Inertia::render('Admin/Workflows/Create', [
            'forms' => $forms,
            'emailTemplates' => $emailTemplates,
        ]);
    }

    public function store(Request $request)
    {
        // Convert string "null" to actual null
        if ($request->form_id === 'null' || $request->form_id === '') {
            $request->merge(['form_id' => null]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_id' => 'nullable|integer|exists:forms,id',
            'trigger_on' => 'required|string|in:submission,approval,manual',
            'is_active' => 'boolean',
            'nodes' => 'required|array',
            'edges' => 'nullable|array',
        ]);

        $validated['current_version'] = 0;
        $validated['edges'] = $validated['edges'] ?? [];
        $workflow = Workflow::create($validated);

        // Create initial version (sets current_version to 1)
        $workflow->createVersion('Vytvorenie workflow', auth()->id());

        // Save steps from nodes
        $this->syncSteps($workflow, $validated['nodes']);

        // Audit log
        AuditService::workflowCreated($workflow);

        return redirect()
            ->route('admin.workflows.edit', $workflow)
            ->with('success', 'Workflow bol vytvorený');
    }

    public function edit(Workflow $workflow)
    {
        $workflow->load('steps');
        $forms = Form::where('is_active', true)->select('id', 'name', 'schema')->get();
        $emailTemplates = EmailTemplate::where('is_active', true)->select('id', 'name', 'subject')->get();

        return Inertia::render('Admin/Workflows/Edit', [
            'workflow' => $workflow,
            'forms' => $forms,
            'emailTemplates' => $emailTemplates,
        ]);
    }

    public function update(Request $request, Workflow $workflow)
    {
        // Convert string "null" to actual null
        if ($request->form_id === 'null' || $request->form_id === '') {
            $request->merge(['form_id' => null]);
        }

        // Ensure edges is an array (can be empty)
        if (!$request->has('edges') || $request->edges === null) {
            $request->merge(['edges' => []]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_id' => 'nullable|integer|exists:forms,id',
            'trigger_on' => 'required|string|in:submission,approval,manual',
            'is_active' => 'boolean',
            'nodes' => 'required|array',
            'edges' => 'nullable|array',
        ]);

        $validated['edges'] = $validated['edges'] ?? [];

        // Store old values for audit
        $oldValues = $workflow->only(['name', 'is_active', 'trigger_on']);

        // Normalize and compare nodes/edges to detect real changes
        $normalizeNodes = function ($nodes) {
            return collect($nodes)->map(function ($node) {
                return [
                    'id' => $node['id'] ?? '',
                    'type' => $node['type'] ?? '',
                    'position' => [
                        'x' => round($node['position']['x'] ?? 0),
                        'y' => round($node['position']['y'] ?? 0),
                    ],
                    'data' => $node['data'] ?? [],
                ];
            })->sortBy('id')->values()->toJson();
        };

        $normalizeEdges = function ($edges) {
            return collect($edges)->map(function ($edge) {
                return [
                    'id' => $edge['id'] ?? '',
                    'source' => $edge['source'] ?? '',
                    'target' => $edge['target'] ?? '',
                ];
            })->sortBy('id')->values()->toJson();
        };

        $oldNodesNorm = $normalizeNodes($workflow->nodes ?? []);
        $newNodesNorm = $normalizeNodes($validated['nodes'] ?? []);
        $oldEdgesNorm = $normalizeEdges($workflow->edges ?? []);
        $newEdgesNorm = $normalizeEdges($validated['edges'] ?? []);

        $nodesChanged = $oldNodesNorm !== $newNodesNorm;
        $edgesChanged = $oldEdgesNorm !== $newEdgesNorm;

        $workflow->update($validated);

        // Create new version if nodes or edges changed
        if ($nodesChanged || $edgesChanged) {
            $changeNote = $request->input('version_note', 'Aktualizácia workflow');
            $workflow->createVersion($changeNote, auth()->id());
        }

        // Sync steps
        $this->syncSteps($workflow, $validated['nodes']);

        // Audit log
        AuditService::workflowUpdated($workflow, $oldValues);

        return redirect()
            ->back()
            ->with('success', 'Workflow bol aktualizovaný');
    }

    public function destroy(Workflow $workflow)
    {
        // Audit log before deletion
        AuditService::workflowDeleted($workflow);

        $workflow->delete();

        return redirect()
            ->route('admin.workflows.index')
            ->with('success', 'Workflow bol zmazaný');
    }

    public function executions(Workflow $workflow)
    {
        $executions = $workflow->executions()
            ->with('submission:id,form_id,created_at')
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Workflows/Executions', [
            'workflow' => $workflow,
            'executions' => $executions,
        ]);
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

    public function versions(Workflow $workflow)
    {
        $versions = $workflow->versions()
            ->with('creator:id,name,email')
            ->get();

        return response()->json([
            'versions' => $versions,
            'current_version' => $workflow->current_version,
        ]);
    }

    public function showVersion(Workflow $workflow, WorkflowVersion $version)
    {
        if ($version->workflow_id !== $workflow->id) {
            abort(404);
        }

        return response()->json([
            'version' => $version->load('creator:id,name,email'),
        ]);
    }

    public function restoreVersion(Request $request, Workflow $workflow, WorkflowVersion $version)
    {
        if ($version->workflow_id !== $workflow->id) {
            abort(404);
        }

        $changeNote = $request->input('change_note', "Obnovené z verzie {$version->version_number}");

        $workflow->restoreToVersion($version, $changeNote, auth()->id());

        // Sync steps with restored nodes
        $this->syncSteps($workflow->fresh(), $workflow->fresh()->nodes);

        return response()->json([
            'success' => true,
            'message' => 'Workflow bol obnovený na verziu ' . $version->version_number,
            'current_version' => $workflow->fresh()->current_version,
        ]);
    }
}
