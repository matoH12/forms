<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // SECURITY: Only show submissions from forms user has access to
        $allowedFormIds = $user->getVisibleFormsQuery(false)->pluck('id');

        $query = FormSubmission::with(['form:id,name,slug', 'user:id,name,email'])
            ->whereIn('form_id', $allowedFormIds)
            ->latest();

        if ($request->filled('form_id')) {
            // Verify the user can see this form
            if (!$allowedFormIds->contains($request->form_id)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $query->where('form_id', $request->form_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $submissions = $query->paginate($request->get('per_page', 20));

        return response()->json($submissions);
    }

    public function show(FormSubmission $submission)
    {
        $submission->load('form');

        // SECURITY: Check if user has access to this form's submissions
        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $submission->load([
            'user:id,name,email',
            'workflowExecutions.workflow:id,name',
            'workflowExecutions.approvalRequests',
        ]);

        return response()->json($submission);
    }

    public function destroy(FormSubmission $submission)
    {
        // SECURITY: Only admin+ can delete submissions
        if (!auth()->user()->hasMinRole(User::ROLE_ADMIN)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $submission->load('form');

        // SECURITY: Check if user has access to this form's submissions
        if (!auth()->user()->canSeeFormSubmissions($submission->form)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $submission->delete();

        return response()->json(null, 204);
    }

    public function byForm(Form $form, Request $request)
    {
        // SECURITY: Check if user has access to this form's submissions
        if (!auth()->user()->canSeeFormSubmissions($form)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $submissions = $form->submissions()
            ->with('user:id,name,email')
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json($submissions);
    }

    public function mySubmissions(Request $request)
    {
        $submissions = FormSubmission::where('user_id', auth()->id())
            ->with('form:id,name,slug')
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json($submissions);
    }
}
