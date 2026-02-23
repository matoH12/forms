<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\User;
use App\Models\Workflow;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Export/Import", description: "Export and import forms, workflows, and categories")]
class ExportImportController extends Controller
{
    // ==========================================
    // CATEGORY EXPORT/IMPORT
    // ==========================================

    #[OA\Get(
        path: "/api/v1/admin/export/categories",
        summary: "Export all categories",
        description: "Export all form categories as JSON",
        operationId: "exportCategories",
        tags: ["Export/Import"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Categories exported successfully"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function exportCategories()
    {
        // SECURITY: Only admin+ can export categories
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $categories = FormCategory::orderBy('order')->get()->map(function ($category) {
            return [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'color' => $category->color,
                'icon' => $category->icon,
                'order' => $category->order,
            ];
        });

        return response()->json([
            'export_type' => 'categories',
            'export_date' => now()->toIso8601String(),
            'version' => '1.0',
            'count' => $categories->count(),
            'data' => $categories,
        ]);
    }

    #[OA\Post(
        path: "/api/v1/admin/import/categories",
        summary: "Import categories",
        description: "Import form categories from JSON. Existing categories with same slug will be updated.",
        operationId: "importCategories",
        tags: ["Export/Import"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                    new OA\Property(property: "mode", type: "string", enum: ["merge", "replace"], description: "merge=update existing, replace=delete all and import")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Categories imported successfully"),
            new OA\Response(response: 400, description: "Invalid data"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function importCategories(Request $request)
    {
        // SECURITY: Only super_admin can import categories
        if (!auth()->user()->hasMinRole(User::ROLE_SUPER_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.name' => 'required', // Can be string or array (multilingual)
            'data.*.slug' => 'nullable|string|max:255',
            'data.*.description' => 'nullable', // Can be string or array (multilingual)
            'data.*.color' => 'nullable|string|max:7',
            'data.*.icon' => 'nullable|string|max:500',
            'data.*.order' => 'nullable|integer',
            'mode' => 'nullable|string|in:merge,replace',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $mode = $request->input('mode', 'merge');
        $imported = 0;
        $updated = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            if ($mode === 'replace') {
                // Check if any categories have forms
                $categoriesWithForms = FormCategory::whereHas('forms')->count();
                if ($categoriesWithForms > 0) {
                    return response()->json([
                        'error' => 'Nie je možné nahradiť kategórie - niektoré obsahujú formuláre',
                        'categories_with_forms' => $categoriesWithForms,
                    ], 400);
                }
                FormCategory::truncate();
            }

            foreach ($request->input('data') as $index => $categoryData) {
                try {
                    // Handle multilingual name - convert to array if string
                    $name = $categoryData['name'];
                    if (is_string($name)) {
                        $name = ['sk' => $name, 'en' => ''];
                    }

                    // Handle multilingual description - convert to array if string
                    $description = $categoryData['description'] ?? null;
                    if (is_string($description)) {
                        $description = ['sk' => $description, 'en' => ''];
                    }

                    // Generate slug from name
                    $slugSource = is_array($name) ? ($name['sk'] ?? $name['en'] ?? '') : $name;
                    $slug = $categoryData['slug'] ?? Str::slug($slugSource);

                    $existing = FormCategory::where('slug', $slug)->first();

                    if ($existing) {
                        $existing->update([
                            'name' => $name,
                            'description' => $description,
                            'color' => $categoryData['color'] ?? '#A59466',
                            'icon' => $categoryData['icon'] ?? null,
                            'order' => $categoryData['order'] ?? $index,
                        ]);
                        $updated++;
                    } else {
                        FormCategory::create([
                            'name' => $name,
                            'slug' => $slug,
                            'description' => $description,
                            'color' => $categoryData['color'] ?? '#A59466',
                            'icon' => $categoryData['icon'] ?? null,
                            'order' => $categoryData['order'] ?? $index,
                        ]);
                        $imported++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Riadok {$index}: " . $e->getMessage();
                }
            }

            DB::commit();
            Cache::forget('form_categories');

            return response()->json([
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // FORM EXPORT/IMPORT
    // ==========================================

    #[OA\Get(
        path: "/api/v1/admin/export/forms",
        summary: "Export forms",
        description: "Export forms as JSON. Can filter by IDs or export all.",
        operationId: "exportForms",
        tags: ["Export/Import"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "ids", in: "query", description: "Comma-separated form IDs to export", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "include_workflow", in: "query", description: "Include assigned workflow", schema: new OA\Schema(type: "boolean"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Forms exported successfully"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function exportForms(Request $request)
    {
        // SECURITY: Only admin+ can export forms
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = auth()->user();

        // SECURITY: Filter forms by user's permissions
        if ($user->can_see_all_forms || $user->hasMinRole(User::ROLE_SUPER_ADMIN)) {
            $query = Form::with('category:id,name,slug');
        } else {
            // Only export forms the user has explicit access to
            $allowedFormIds = $user->allowedForms()->pluck('id');
            $query = Form::with('category:id,name,slug')->whereIn('id', $allowedFormIds);
        }

        if ($request->filled('ids')) {
            $ids = array_map('intval', explode(',', $request->input('ids')));

            // SECURITY: Validate user can see all requested forms
            if (!$user->can_see_all_forms && !$user->hasMinRole(User::ROLE_SUPER_ADMIN)) {
                $allowedFormIds = $user->allowedForms()->pluck('id')->toArray();
                $unauthorizedIds = array_diff($ids, $allowedFormIds);
                if (!empty($unauthorizedIds)) {
                    return response()->json([
                        'message' => 'Forbidden - no access to some requested forms',
                        'unauthorized_ids' => array_values($unauthorizedIds),
                    ], 403);
                }
            }

            $query->whereIn('id', $ids);
        }

        $includeWorkflow = $request->boolean('include_workflow', false);
        if ($includeWorkflow) {
            $query->with('workflow');
        }

        $forms = $query->get()->map(function ($form) use ($includeWorkflow) {
            $data = [
                'name' => $form->name,
                'description' => $form->description,
                'schema' => $form->schema,
                'settings' => $form->settings,
                'is_public' => $form->is_public,
                'is_active' => $form->is_active,
                'prevent_duplicates' => $form->prevent_duplicates,
                'duplicate_message' => $form->duplicate_message,
                'tags' => $form->tags,
                'keywords' => $form->keywords,
                'send_confirmation_email' => $form->send_confirmation_email,
                'category_slug' => $form->category?->slug,
            ];

            if ($includeWorkflow && $form->workflow) {
                $data['workflow'] = [
                    'name' => $form->workflow->name,
                    'description' => $form->workflow->description,
                    'trigger_on' => $form->workflow->trigger_on,
                    'is_active' => $form->workflow->is_active,
                    'nodes' => $form->workflow->nodes,
                    'edges' => $form->workflow->edges,
                ];
            }

            return $data;
        });

        return response()->json([
            'export_type' => 'forms',
            'export_date' => now()->toIso8601String(),
            'version' => '1.0',
            'count' => $forms->count(),
            'data' => $forms,
        ]);
    }

    #[OA\Get(
        path: "/api/v1/admin/export/forms/{form}",
        summary: "Export single form",
        description: "Export a single form as JSON with optional workflow",
        operationId: "exportForm",
        tags: ["Export/Import"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "form", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "include_workflow", in: "query", description: "Include assigned workflow", schema: new OA\Schema(type: "boolean"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Form exported successfully"),
            new OA\Response(response: 404, description: "Form not found"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function exportForm(Request $request, Form $form)
    {
        // SECURITY: Only admin+ can export forms
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // SECURITY: Check if user has access to this form
        if (!auth()->user()->canSeeForm($form)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $includeWorkflow = $request->boolean('include_workflow', true);
        $form->load('category:id,name,slug');

        if ($includeWorkflow) {
            $form->load('workflow');
        }

        $data = [
            'name' => $form->name,
            'description' => $form->description,
            'schema' => $form->schema,
            'settings' => $form->settings,
            'is_public' => $form->is_public,
            'is_active' => $form->is_active,
            'prevent_duplicates' => $form->prevent_duplicates,
            'duplicate_message' => $form->duplicate_message,
            'tags' => $form->tags,
            'keywords' => $form->keywords,
            'send_confirmation_email' => $form->send_confirmation_email,
            'category_slug' => $form->category?->slug,
        ];

        if ($includeWorkflow && $form->workflow) {
            $data['workflow'] = [
                'name' => $form->workflow->name,
                'description' => $form->workflow->description,
                'trigger_on' => $form->workflow->trigger_on,
                'is_active' => $form->workflow->is_active,
                'nodes' => $form->workflow->nodes,
                'edges' => $form->workflow->edges,
            ];
        }

        return response()->json([
            'export_type' => 'form',
            'export_date' => now()->toIso8601String(),
            'version' => '1.0',
            'data' => $data,
        ]);
    }

    #[OA\Post(
        path: "/api/v1/admin/import/forms",
        summary: "Import forms",
        description: "Import forms from JSON. Creates new forms with new slugs.",
        operationId: "importForms",
        tags: ["Export/Import"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                    new OA\Property(property: "import_workflows", type: "boolean", description: "Also import embedded workflows")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Forms imported successfully"),
            new OA\Response(response: 400, description: "Invalid data"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function importForms(Request $request)
    {
        // SECURITY: Only super_admin can import forms
        if (!auth()->user()->hasMinRole(User::ROLE_SUPER_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->input('data');

        // Handle single form import (wrapped in export_type structure)
        if (isset($request->all()['export_type']) && $request->input('export_type') === 'form') {
            $data = [$request->input('data')];
        }

        $validator = Validator::make(['data' => $data], [
            'data' => 'required|array',
            'data.*.name' => 'required',
            'data.*.schema' => 'required|array',
            'data.*.schema.fields' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $importWorkflows = $request->boolean('import_workflows', true);
        $imported = 0;
        $workflowsImported = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $formData) {
                try {
                    // Find category by slug if provided
                    $categoryId = null;
                    if (!empty($formData['category_slug'])) {
                        $category = FormCategory::where('slug', $formData['category_slug'])->first();
                        $categoryId = $category?->id;
                    }

                    // Create form with "(import)" suffix in name
                    $name = $formData['name'];
                    if (is_array($name)) {
                        $name['sk'] = ($name['sk'] ?? '') . ' (import)';
                        if (!empty($name['en'])) {
                            $name['en'] = $name['en'] . ' (import)';
                        }
                    } else {
                        $name = $name . ' (import)';
                    }

                    $form = Form::create([
                        'name' => $name,
                        'description' => $formData['description'] ?? null,
                        'schema' => $formData['schema'],
                        'settings' => $formData['settings'] ?? null,
                        'is_public' => $formData['is_public'] ?? false,
                        'is_active' => false, // Always inactive on import
                        'prevent_duplicates' => $formData['prevent_duplicates'] ?? false,
                        'duplicate_message' => $formData['duplicate_message'] ?? null,
                        'tags' => $formData['tags'] ?? null,
                        'keywords' => $formData['keywords'] ?? null,
                        'send_confirmation_email' => $formData['send_confirmation_email'] ?? false,
                        'category_id' => $categoryId,
                        'created_by' => auth()->id(),
                        'current_version' => 1,
                    ]);

                    // Create initial version
                    $form->createVersion('Import formulára', auth()->id());

                    // Import workflow if present and enabled
                    if ($importWorkflows && !empty($formData['workflow'])) {
                        $workflowData = $formData['workflow'];
                        $workflow = Workflow::create([
                            'name' => $workflowData['name'] . ' (import)',
                            'description' => $workflowData['description'] ?? null,
                            'form_id' => $form->id,
                            'trigger_on' => $workflowData['trigger_on'] ?? 'submission',
                            'is_active' => false, // Always inactive on import
                            'nodes' => $workflowData['nodes'] ?? [],
                            'edges' => $workflowData['edges'] ?? [],
                            'current_version' => 1,
                        ]);

                        $workflow->createVersion('Import workflow', auth()->id());
                        $form->update(['workflow_id' => $workflow->id]);
                        $workflowsImported++;
                    }

                    AuditService::formCreated($form);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Formulár {$index}: " . $e->getMessage();
                }
            }

            DB::commit();
            Cache::forget('public_forms');
            Cache::forget('forms_list');

            return response()->json([
                'success' => true,
                'imported' => $imported,
                'workflows_imported' => $workflowsImported,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // WORKFLOW EXPORT/IMPORT
    // ==========================================

    #[OA\Get(
        path: "/api/v1/admin/export/workflows",
        summary: "Export workflows",
        description: "Export workflows as JSON. Can filter by IDs or export all.",
        operationId: "exportWorkflows",
        tags: ["Export/Import"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "ids", in: "query", description: "Comma-separated workflow IDs to export", schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Workflows exported successfully"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function exportWorkflows(Request $request)
    {
        // SECURITY: Only admin+ can export workflows
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = Workflow::query();

        if ($request->filled('ids')) {
            $ids = explode(',', $request->input('ids'));
            $query->whereIn('id', $ids);
        }

        $workflows = $query->get()->map(function ($workflow) {
            return [
                'name' => $workflow->name,
                'description' => $workflow->description,
                'trigger_on' => $workflow->trigger_on,
                'is_active' => $workflow->is_active,
                'nodes' => $workflow->nodes,
                'edges' => $workflow->edges,
            ];
        });

        return response()->json([
            'export_type' => 'workflows',
            'export_date' => now()->toIso8601String(),
            'version' => '1.0',
            'count' => $workflows->count(),
            'data' => $workflows,
        ]);
    }

    #[OA\Get(
        path: "/api/v1/admin/export/workflows/{workflow}",
        summary: "Export single workflow",
        description: "Export a single workflow as JSON",
        operationId: "exportWorkflow",
        tags: ["Export/Import"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "workflow", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Workflow exported successfully"),
            new OA\Response(response: 404, description: "Workflow not found"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function exportWorkflow(Workflow $workflow)
    {
        // SECURITY: Only admin+ can export workflows
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'export_type' => 'workflow',
            'export_date' => now()->toIso8601String(),
            'version' => '1.0',
            'data' => [
                'name' => $workflow->name,
                'description' => $workflow->description,
                'trigger_on' => $workflow->trigger_on,
                'is_active' => $workflow->is_active,
                'nodes' => $workflow->nodes,
                'edges' => $workflow->edges,
            ],
        ]);
    }

    #[OA\Post(
        path: "/api/v1/admin/import/workflows",
        summary: "Import workflows",
        description: "Import workflows from JSON. Creates new workflows.",
        operationId: "importWorkflows",
        tags: ["Export/Import"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                    new OA\Property(property: "form_id", type: "integer", description: "Assign imported workflows to this form")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Workflows imported successfully"),
            new OA\Response(response: 400, description: "Invalid data"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function importWorkflows(Request $request)
    {
        // SECURITY: Only super_admin can import workflows
        if (!auth()->user()->hasMinRole(User::ROLE_SUPER_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->input('data');

        // Handle single workflow import (wrapped in export_type structure)
        if (isset($request->all()['export_type']) && $request->input('export_type') === 'workflow') {
            $data = [$request->input('data')];
        }

        $validator = Validator::make(['data' => $data], [
            'data' => 'required|array',
            'data.*.name' => 'required|string|max:255',
            'data.*.nodes' => 'required|array',
            'data.*.edges' => 'required|array',
            'form_id' => 'nullable|exists:forms,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $formId = $request->input('form_id');
        $imported = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $workflowData) {
                try {
                    $workflow = Workflow::create([
                        'name' => $workflowData['name'] . ' (import)',
                        'description' => $workflowData['description'] ?? null,
                        'form_id' => $formId,
                        'trigger_on' => $workflowData['trigger_on'] ?? 'submission',
                        'is_active' => false, // Always inactive on import
                        'nodes' => $workflowData['nodes'],
                        'edges' => $workflowData['edges'],
                        'current_version' => 1,
                    ]);

                    $workflow->createVersion('Import workflow', auth()->id());
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Workflow {$index}: " . $e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'imported' => $imported,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
