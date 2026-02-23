<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Escape LIKE wildcards to prevent DoS attacks
     */
    private function escapeLikeWildcards(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }

    public function index(Request $request)
    {
        // SECURITY: Only super_admin can access user management
        if (!auth()->user()->hasRole(User::ROLE_SUPER_ADMIN)) {
            abort(403, 'Nemáte oprávnenie pristupovať k správe používateľov');
        }

        $query = User::query()->latest();

        if ($request->filled('search')) {
            $search = $this->escapeLikeWildcards($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            if (in_array($request->role, User::ROLES)) {
                $query->where('role', $request->role);
            }
        }

        $users = $query->paginate(20);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $this->sanitizeFilters($request->only(['search', 'role'])),
            'roles' => User::ROLES,
        ]);
    }

    public function edit(User $user)
    {
        // SECURITY: Only super_admin can edit users
        if (!auth()->user()->hasRole(User::ROLE_SUPER_ADMIN)) {
            abort(403, 'Nemáte oprávnenie upravovať používateľov');
        }

        $forms = Form::orderBy('name')->get()->map(function ($form) {
            return [
                'id' => $form->id,
                'name' => $form->localized_name,
            ];
        });
        $user->load('allowedForms:id');

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'forms' => $forms,
            'allowedFormIds' => $user->allowedForms->pluck('id'),
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();

        // SECURITY: Only super_admin can change roles
        if (!$currentUser->hasRole(User::ROLE_SUPER_ADMIN)) {
            abort(403, 'Nemáte oprávnenie meniť role používateľov');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => ['required', Rule::in(User::ROLES)],
            'can_see_all_forms' => 'boolean',
            'allowed_form_ids' => 'array',
            'allowed_form_ids.*' => 'exists:forms,id',
        ]);

        // SECURITY: Prevent self-demotion for super_admin
        if ($user->id === $currentUser->id && $user->role === User::ROLE_SUPER_ADMIN) {
            if ($validated['role'] !== User::ROLE_SUPER_ADMIN) {
                return redirect()
                    ->back()
                    ->with('error', 'Nemôžete si znížiť vlastnú rolu');
            }
        }

        // SECURITY: Prevent privilege escalation - cannot set role higher than your own
        $currentUserLevel = User::ROLE_HIERARCHY[$currentUser->role] ?? 0;
        $newRoleLevel = User::ROLE_HIERARCHY[$validated['role']] ?? 0;
        if ($newRoleLevel > $currentUserLevel) {
            return redirect()
                ->back()
                ->with('error', 'Nemôžete nastaviť rolu vyššiu ako máte vy');
        }

        // Track changes for audit log
        $changes = [];

        // Update safe fields via mass assignment
        if ($user->name !== $validated['name']) {
            $changes['name'] = $validated['name'];
            $user->name = $validated['name'];
        }

        // Update role explicitly (not via mass assignment)
        if ($user->role !== $validated['role']) {
            $changes['role'] = ['from' => $user->role, 'to' => $validated['role']];
            $user->role = $validated['role'];
        }

        // Update can_see_all_forms only for roles that can access admin panel
        $newCanSeeAllForms = $validated['can_see_all_forms'] ?? $user->can_see_all_forms;
        if ($user->can_see_all_forms !== $newCanSeeAllForms) {
            $changes['can_see_all_forms'] = $newCanSeeAllForms;
            $user->can_see_all_forms = $newCanSeeAllForms;
        }

        $user->save();

        // Update form permissions
        $allowedFormIds = $validated['allowed_form_ids'] ?? [];
        $user->allowedForms()->sync($allowedFormIds);

        // Audit log
        if (!empty($changes)) {
            AuditService::userUpdated($user, $changes);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Používateľ bol aktualizovaný');
    }

    public function destroy(User $user)
    {
        $currentUser = auth()->user();

        // SECURITY: Only super_admin can delete users
        if (!$currentUser->hasRole(User::ROLE_SUPER_ADMIN)) {
            abort(403, 'Nemáte oprávnenie mazať používateľov');
        }

        // Nemôžeš zmazať seba
        if ($user->id === $currentUser->id) {
            return redirect()
                ->back()
                ->with('error', 'Nemôžete zmazať svoj vlastný účet');
        }

        // SECURITY: Cannot delete another super_admin (prevent lockout)
        if ($user->role === User::ROLE_SUPER_ADMIN) {
            return redirect()
                ->back()
                ->with('error', 'Nemôžete zmazať super admina');
        }

        // Audit log before deletion
        AuditService::userDeleted($user);

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Používateľ bol zmazaný');
    }
}
