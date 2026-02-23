<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FormController extends Controller
{
    public function __construct(
        private WorkflowEngine $workflowEngine
    ) {}

    public function index()
    {
        $user = auth()->user();

        // SECURITY: Only show forms user has access to
        $query = $user->getVisibleFormsQuery()
            ->withCount('submissions')
            ->with('creator:id,name');

        $forms = $query->latest()->paginate(20);

        return response()->json($forms);
    }

    public function store(Request $request)
    {
        // SECURITY: Only admin+ can create forms
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'schema' => 'required|array',
            'schema.fields' => 'required|array|min:1',
            'settings' => 'nullable|array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $form = Form::create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        return response()->json($form, 201);
    }

    #[OA\Get(
        path: "/api/v1/forms/{slug}",
        summary: "Get form details",
        description: "Get form schema and settings by slug. Public forms are accessible without authentication.",
        operationId: "getFormBySlug",
        tags: ["Public Forms"],
        parameters: [
            new OA\Parameter(name: "slug", in: "path", required: true, description: "Form slug", schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Form details"),
            new OA\Response(response: 401, description: "Unauthorized - form is not public"),
            new OA\Response(response: 404, description: "Form not found")
        ]
    )]
    public function show(string $idOrSlug)
    {
        $form = Form::where('slug', $idOrSlug)
            ->orWhere('id', $idOrSlug)
            ->where('is_active', true)
            ->firstOrFail();

        if (!$form->is_public && !auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check email domain restriction
        if (!$form->isVisibleForEmail(auth()->user()?->email)) {
            return response()->json(['message' => 'Forbidden - form not available for your account type'], 403);
        }

        return response()->json($form);
    }

    public function update(Request $request, Form $form)
    {
        // SECURITY: Only admin+ can update forms
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // SECURITY: Check if user has access to this form
        if (!auth()->user()->canSeeForm($form)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'schema' => 'required|array',
            'schema.fields' => 'required|array|min:1',
            'settings' => 'nullable|array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $form->update($validated);

        return response()->json($form);
    }

    public function destroy(Form $form)
    {
        // SECURITY: Only admin+ can delete forms
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // SECURITY: Check if user has access to this form
        if (!auth()->user()->canSeeForm($form)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $form->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: "/api/v1/forms/{slug}/submit",
        summary: "Submit a form",
        description: "Submit form data. Public forms can be submitted without authentication. Triggers associated workflows.",
        operationId: "submitForm",
        tags: ["Public Forms"],
        parameters: [
            new OA\Parameter(name: "slug", in: "path", required: true, description: "Form slug", schema: new OA\Schema(type: "string"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Form field values as key-value pairs",
            content: new OA\JsonContent(
                type: "object",
                additionalProperties: true,
                example: ["name" => "John Doe", "email" => "john@example.com"]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Form submitted successfully"),
            new OA\Response(response: 401, description: "Unauthorized - form is not public"),
            new OA\Response(response: 404, description: "Form not found"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function submit(Request $request, string $slug)
    {
        $form = Form::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        if (!$form->is_public && !auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check email domain restriction
        if (!$form->isVisibleForEmail(auth()->user()?->email)) {
            return response()->json(['message' => 'Forbidden - form not available for your account type'], 403);
        }

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'user_id' => auth()->id(),
            'user_login' => auth()->user()?->login,
            'data' => $request->all(),
            'status' => 'submitted',
            'ip_address' => $this->getClientIp($request),
            'user_agent' => $request->userAgent(),
        ]);

        $this->workflowEngine->triggerForSubmission($submission);

        return response()->json([
            'message' => 'Formulár bol úspešne odoslaný',
            'submission_id' => $submission->id,
        ], 201);
    }

    /**
     * Get the real client IP from proxy headers.
     */
    private function getClientIp(Request $request): string
    {
        $xff = $request->header('X-Forwarded-For');
        if ($xff) {
            $firstIp = trim(explode(',', $xff)[0]);
            if (filter_var($firstIp, FILTER_VALIDATE_IP)) {
                return $firstIp;
            }
        }

        $realIp = $request->header('X-Real-IP');
        if ($realIp && filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }

        return $request->ip();
    }

    public function myForms()
    {
        $forms = Form::where('is_active', true)
            ->select(['id', 'name', 'slug', 'description', 'is_public'])
            ->get();

        return response()->json($forms);
    }

    public function duplicate(Form $form)
    {
        // SECURITY: Only admin+ can duplicate forms
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // SECURITY: Check if user has access to this form
        if (!auth()->user()->canSeeForm($form)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $newForm = $form->replicate();

        // Handle multilingual name for copy
        $name = $form->name;
        if (is_array($name)) {
            $name['sk'] = ($name['sk'] ?? '') . ' (kópia)';
            if (!empty($name['en'])) {
                $name['en'] = $name['en'] . ' (copy)';
            }
            $newForm->name = $name;
        } else {
            $newForm->name = $name . ' (kópia)';
        }

        $newForm->slug = null;
        $newForm->created_by = auth()->id();
        $newForm->save();

        return response()->json($newForm, 201);
    }
}
