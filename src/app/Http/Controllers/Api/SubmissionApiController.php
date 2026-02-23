<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Submissions", description: "API endpoints for form submissions")]
#[OA\Tag(name: "Forms", description: "API endpoints for forms")]
class SubmissionApiController extends Controller
{
    #[OA\Get(
        path: "/api/v1/submissions/approved",
        summary: "Get approved submissions",
        description: "Returns a paginated list of approved form submissions with optional filtering",
        operationId: "getApprovedSubmissions",
        tags: ["Submissions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "form_id", in: "query", description: "Filter by form ID", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "form_slug", in: "query", description: "Filter by form slug", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "user_login", in: "query", description: "Filter by user login (email or keycloak_id)", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "user_email", in: "query", description: "Filter by user email", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "date_from", in: "query", description: "Filter submissions from date (Y-m-d format)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "date_to", in: "query", description: "Filter submissions to date (Y-m-d format)", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "per_page", in: "query", description: "Number of items per page (default 15, max 100)", required: false, schema: new OA\Schema(type: "integer", default: 15)),
            new OA\Parameter(name: "page", in: "query", description: "Page number", required: false, schema: new OA\Schema(type: "integer", default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Successful operation"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function approved(Request $request): JsonResponse
    {
        $query = FormSubmission::with(['form:id,name,slug', 'user:id,name,first_name,last_name,email,login'])
            ->where('status', 'approved');

        // Filter by form_id
        if ($request->filled('form_id')) {
            $query->where('form_id', $request->form_id);
        }

        // Filter by form_slug
        if ($request->filled('form_slug')) {
            $query->whereHas('form', function ($q) use ($request) {
                $q->where('slug', $request->form_slug);
            });
        }

        // Filter by user login (from submission table or user table)
        if ($request->filled('user_login')) {
            $login = $request->user_login;
            $query->where(function ($q) use ($login) {
                $q->where('user_login', $login)
                    ->orWhereHas('user', function ($q2) use ($login) {
                        $q2->where('login', $login);
                    });
            });
        }

        // Filter by user email
        if ($request->filled('user_email')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('email', $request->user_email);
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $submissions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $submissions->map(function ($submission) {
                return $this->formatSubmission($submission);
            }),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/v1/submissions/{id}",
        summary: "Get submission by ID",
        description: "Returns a single submission by its ID",
        operationId: "getSubmissionById",
        tags: ["Submissions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", description: "Submission ID", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Successful operation"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Submission not found")
        ]
    )]
    public function show(Request $request, FormSubmission $submission): JsonResponse
    {
        $submission->load(['form:id,name,slug', 'user:id,name,first_name,last_name,email,login']);

        // SECURITY: Audit log API access to specific submission
        $token = $request->attributes->get('system_api_token');
        AuditService::log('api_submission_accessed', $submission, null, [
            'submission_id' => $submission->id,
            'form_id' => $submission->form_id,
            'token_name' => $token?->name ?? 'unknown',
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'data' => $this->formatSubmission($submission),
        ]);
    }

    #[OA\Get(
        path: "/api/v1/submissions",
        summary: "Get all submissions",
        description: "Returns a paginated list of all form submissions with optional filtering by status",
        operationId: "getAllSubmissions",
        tags: ["Submissions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", description: "Filter by status (pending, approved, rejected)", required: false, schema: new OA\Schema(type: "string", enum: ["pending", "approved", "rejected"])),
            new OA\Parameter(name: "form_id", in: "query", description: "Filter by form ID", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "user_login", in: "query", description: "Filter by user login (email or keycloak_id)", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", description: "Number of items per page", required: false, schema: new OA\Schema(type: "integer", default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: "Successful operation"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = FormSubmission::with(['form:id,name,slug', 'user:id,name,first_name,last_name,email,login']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by form_id
        if ($request->filled('form_id')) {
            $query->where('form_id', $request->form_id);
        }

        // Filter by user login (from submission table or user table)
        if ($request->filled('user_login')) {
            $login = $request->user_login;
            $query->where(function ($q) use ($login) {
                $q->where('user_login', $login)
                    ->orWhereHas('user', function ($q2) use ($login) {
                        $q2->where('login', $login);
                    });
            });
        }

        // Date filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = min($request->input('per_page', 15), 100);
        $submissions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $submissions->map(function ($submission) {
                return $this->formatSubmission($submission);
            }),
            'meta' => [
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'per_page' => $submissions->perPage(),
                'total' => $submissions->total(),
            ],
        ]);
    }

    #[OA\Get(
        path: "/api/v1/forms",
        summary: "Get all forms",
        description: "Returns a list of all active forms",
        operationId: "getAllForms",
        tags: ["Forms"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Successful operation"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function forms(): JsonResponse
    {
        $forms = Form::where('is_active', true)
            ->select('id', 'name', 'slug', 'description', 'created_at')
            ->get()
            ->map(function ($form) {
                return [
                    'id' => $form->id,
                    'name' => $form->localized_name,
                    'slug' => $form->slug,
                    'description' => is_array($form->description)
                        ? ($form->description['sk'] ?? $form->description['en'] ?? '')
                        : $form->description,
                    'created_at' => $form->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $forms,
        ]);
    }

    #[OA\Post(
        path: "/api/v1/submissions/import",
        summary: "Import a submission",
        description: "Import a single submission from external system. Does NOT trigger workflows. Submission is marked as resolved.",
        operationId: "importSubmission",
        tags: ["Submissions"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["form_id", "data"],
                properties: [
                    new OA\Property(property: "form_id", type: "integer", description: "Form ID"),
                    new OA\Property(property: "form_slug", type: "string", description: "Form slug (alternative to form_id)"),
                    new OA\Property(property: "data", type: "object", description: "Submission data (form fields)"),
                    new OA\Property(property: "user_login", type: "string", description: "User login/identifier"),
                    new OA\Property(property: "status", type: "string", enum: ["approved", "rejected"], description: "Status (default: approved)"),
                    new OA\Property(property: "admin_response", type: "string", description: "Admin response/note"),
                    new OA\Property(property: "created_at", type: "string", format: "date-time", description: "Original submission date"),
                    new OA\Property(property: "reviewed_at", type: "string", format: "date-time", description: "Review date"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Submission imported successfully"),
            new OA\Response(response: 400, description: "Validation error"),
            new OA\Response(response: 401, description: "Unauthorized"),
            new OA\Response(response: 404, description: "Form not found")
        ]
    )]
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_id' => 'required_without:form_slug|integer|exists:forms,id',
            'form_slug' => 'required_without:form_id|string',
            'data' => 'required|array',
            'user_login' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:approved,rejected',
            'admin_response' => 'nullable|string',
            'created_at' => 'nullable|date',
            'reviewed_at' => 'nullable|date',
        ]);

        // Find form by ID or slug
        $formId = $validated['form_id'] ?? null;
        $form = null;
        if (!$formId && !empty($validated['form_slug'])) {
            $form = Form::where('slug', $validated['form_slug'])->first();
            if (!$form) {
                return response()->json(['error' => 'Form not found'], 404);
            }
            $formId = $form->id;
        } else {
            $form = Form::find($formId);
        }

        // SECURITY: Validate required fields from form schema
        $validationErrors = $this->validateFormData($form, $validated['data']);
        if (!empty($validationErrors)) {
            return response()->json([
                'error' => 'Validation failed',
                'validation_errors' => $validationErrors,
            ], 422);
        }

        // Create submission without triggering workflow
        $submission = new FormSubmission();
        $submission->form_id = $formId;
        $submission->data = $validated['data'];
        $submission->user_login = $validated['user_login'] ?? null;
        $submission->status = $validated['status'] ?? 'approved';
        $submission->admin_response = $validated['admin_response'] ?? null;
        $submission->ip_address = 'imported';
        $submission->user_agent = 'API Import';

        // Set timestamps if provided
        if (!empty($validated['created_at'])) {
            $submission->created_at = $validated['created_at'];
        }
        if (!empty($validated['reviewed_at'])) {
            $submission->reviewed_at = $validated['reviewed_at'];
        }

        $submission->save();

        // SECURITY: Audit log import
        $token = $request->attributes->get('system_api_token');
        AuditService::log('submission_imported', $submission, null, [
            'form_id' => $formId,
            'token_name' => $token?->name ?? 'unknown',
            'ip' => $request->ip(),
        ]);

        $submission->load(['form:id,name,slug']);

        return response()->json([
            'message' => 'Submission imported successfully',
            'data' => $this->formatSubmission($submission),
        ], 201);
    }

    /**
     * Validate imported data against form schema
     * SECURITY: Ensures required fields are present
     */
    private function validateFormData(Form $form, array $data): array
    {
        $errors = [];
        $schema = $form->schema ?? [];
        $fields = $schema['fields'] ?? [];

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? null;
            $isRequired = $field['required'] ?? false;

            // Skip required validation if field has conditions that are not met
            if ($isRequired && !empty($field['conditions'])) {
                if (!$this->isFieldVisible($field, $data)) {
                    $isRequired = false;
                }
            }

            if ($isRequired && $fieldName) {
                if (!isset($data[$fieldName]) || $data[$fieldName] === '' || $data[$fieldName] === null) {
                    $label = is_array($field['label'] ?? null)
                        ? ($field['label']['sk'] ?? $field['label']['en'] ?? $fieldName)
                        : ($field['label'] ?? $fieldName);
                    $errors[$fieldName] = "Field '{$label}' is required";
                }
            }
        }

        return $errors;
    }

    /**
     * Check if a field should be visible based on its conditions and submitted data.
     */
    private function isFieldVisible(array $field, array $data): bool
    {
        if (empty($field['conditions'])) {
            return true;
        }

        foreach ($field['conditions'] as $condition) {
            $fieldValue = $data[$condition['field']] ?? null;
            $conditionValue = $condition['value'] ?? null;

            $met = match ($condition['operator'] ?? 'equals') {
                'equals' => $fieldValue == $conditionValue,
                'not_equals' => $fieldValue != $conditionValue,
                'contains' => str_contains(
                    strtolower((string) ($fieldValue ?? '')),
                    strtolower((string) $conditionValue)
                ),
                'is_empty' => !$fieldValue || $fieldValue === '' || $fieldValue === false,
                'not_empty' => $fieldValue && $fieldValue !== '' && $fieldValue !== false,
                default => true,
            };

            if (!$met) {
                return false;
            }
        }

        return true;
    }

    #[OA\Post(
        path: "/api/v1/submissions/import/batch",
        summary: "Import multiple submissions",
        description: "Import multiple submissions from external system in batch. Does NOT trigger workflows. All submissions are marked as resolved.",
        operationId: "importSubmissionsBatch",
        tags: ["Submissions"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["submissions"],
                properties: [
                    new OA\Property(
                        property: "submissions",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "form_id", type: "integer"),
                                new OA\Property(property: "form_slug", type: "string"),
                                new OA\Property(property: "data", type: "object"),
                                new OA\Property(property: "user_login", type: "string"),
                                new OA\Property(property: "status", type: "string"),
                                new OA\Property(property: "admin_response", type: "string"),
                                new OA\Property(property: "created_at", type: "string"),
                                new OA\Property(property: "reviewed_at", type: "string"),
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Submissions imported successfully"),
            new OA\Response(response: 400, description: "Validation error"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function importBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'submissions' => 'required|array|min:1|max:1000',
            'submissions.*.form_id' => 'required_without:submissions.*.form_slug|integer|exists:forms,id',
            'submissions.*.form_slug' => 'required_without:submissions.*.form_id|string',
            'submissions.*.data' => 'required|array',
            'submissions.*.user_login' => 'nullable|string|max:255',
            'submissions.*.status' => 'nullable|string|in:approved,rejected',
            'submissions.*.admin_response' => 'nullable|string',
            'submissions.*.created_at' => 'nullable|date',
            'submissions.*.reviewed_at' => 'nullable|date',
        ]);

        // Cache form slugs to IDs and forms
        $formCache = [];

        $imported = [];
        $errors = [];

        foreach ($validated['submissions'] as $index => $item) {
            try {
                // Find form by ID or slug
                $formId = $item['form_id'] ?? null;
                $form = null;

                if (!$formId && !empty($item['form_slug'])) {
                    $slug = $item['form_slug'];
                    if (!isset($formCache[$slug])) {
                        $formCache[$slug] = Form::where('slug', $slug)->first();
                    }
                    $form = $formCache[$slug];
                    $formId = $form?->id;

                    if (!$formId) {
                        $errors[] = ['index' => $index, 'error' => "Form not found: {$slug}"];
                        continue;
                    }
                } else {
                    if (!isset($formCache[$formId])) {
                        $formCache[$formId] = Form::find($formId);
                    }
                    $form = $formCache[$formId];
                }

                // SECURITY: Validate required fields
                $validationErrors = $this->validateFormData($form, $item['data']);
                if (!empty($validationErrors)) {
                    $errors[] = ['index' => $index, 'error' => 'Validation failed', 'fields' => $validationErrors];
                    continue;
                }

                $submission = new FormSubmission();
                $submission->form_id = $formId;
                $submission->data = $item['data'];
                $submission->user_login = $item['user_login'] ?? null;
                $submission->status = $item['status'] ?? 'approved';
                $submission->admin_response = $item['admin_response'] ?? null;
                $submission->ip_address = 'imported';
                $submission->user_agent = 'API Import Batch';

                if (!empty($item['created_at'])) {
                    $submission->created_at = $item['created_at'];
                }
                if (!empty($item['reviewed_at'])) {
                    $submission->reviewed_at = $item['reviewed_at'];
                }

                $submission->save();
                $imported[] = $submission->id;

            } catch (\Exception $e) {
                $errors[] = ['index' => $index, 'error' => $e->getMessage()];
            }
        }

        // SECURITY: Audit log batch import
        $token = $request->attributes->get('system_api_token');
        AuditService::log('submissions_batch_imported', null, null, [
            'count' => count($imported),
            'errors' => count($errors),
            'token_name' => $token?->name ?? 'unknown',
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Batch import completed',
            'imported_count' => count($imported),
            'imported_ids' => $imported,
            'errors_count' => count($errors),
            'errors' => $errors,
        ], 201);
    }

    private function formatSubmission(FormSubmission $submission): array
    {
        $formName = '';
        if ($submission->form) {
            $formName = $submission->form->localized_name ?? '';
        }

        // Get user login - prefer from submission, fallback to user
        $userLogin = $submission->user_login ?? $submission->user?->login;

        // Get full name
        $userName = '';
        if ($submission->user) {
            if ($submission->user->first_name && $submission->user->last_name) {
                $userName = $submission->user->first_name . ' ' . $submission->user->last_name;
            } else {
                $userName = $submission->user->name;
            }
        }

        return [
            'id' => $submission->id,
            'form_id' => $submission->form_id,
            'form_name' => $formName,
            'form_slug' => $submission->form?->slug,
            'user_login' => $userLogin,
            'user' => $submission->user ? [
                'id' => $submission->user->id,
                'name' => $userName,
                'email' => $submission->user->email,
                'login' => $submission->user->login,
            ] : null,
            'data' => $submission->data,
            'status' => $submission->status ?? 'pending',
            'ip_address' => $submission->ip_address,
            'created_at' => $submission->created_at->toIso8601String(),
            'updated_at' => $submission->updated_at->toIso8601String(),
        ];
    }
}
