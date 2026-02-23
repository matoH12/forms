<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserSettingsController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        // For approver+ users, get ALL active forms with their notification status
        $availableForms = [];
        if ($user->hasMinRole(User::ROLE_APPROVER)) {
            // Get user's current subscriptions
            $subscriptions = $user->subscribedForms()
                ->pluck('notify_enabled', 'forms.id')
                ->toArray();

            // Get all active forms
            $availableForms = Form::where('is_active', true)
                ->select('id', 'name', 'slug')
                ->orderBy('name')
                ->get()
                ->map(function ($form) use ($subscriptions) {
                    // Get localized name
                    $name = $form->name;
                    if (is_array($name)) {
                        $name = $name['sk'] ?? $name['en'] ?? '';
                    }
                    return [
                        'id' => $form->id,
                        'name' => $name,
                        'slug' => $form->slug,
                        'notify_enabled' => isset($subscriptions[$form->id]) ? (bool) $subscriptions[$form->id] : false,
                    ];
                });
        }

        $settings = $user->settings ?? [];

        return Inertia::render('Profile/Settings', [
            'profile' => [
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'keycloak_id' => $user->keycloak_id,
                'role' => $user->role,
                'is_admin' => $user->is_admin, // backward compatibility
                'created_at' => $user->created_at,
            ],
            'settings' => array_merge([
                'theme' => 'system',
                'email_notifications' => true,
                'language' => 'system',
                'notify_new_submissions' => false,
            ], $settings),
            'availableForms' => $availableForms,
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'theme' => 'required|in:light,dark,system',
            'email_notifications' => 'boolean',
            'language' => 'in:system,sk,en',
            'notify_new_submissions' => 'boolean',
        ]);

        $oldSettings = $user->settings ?? [];

        $user->update([
            'settings' => array_merge($oldSettings, $validated),
        ]);

        // Audit log
        AuditService::log('settings_updated', $user, $oldSettings, $validated);

        return redirect()
            ->back()
            ->with('success', __('messages.settings_saved'));
    }

    public function updateTheme(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'theme' => 'required|in:light,dark,system',
        ]);

        $settings = $user->settings ?? [];
        $settings['theme'] = $validated['theme'];

        $user->update(['settings' => $settings]);

        return response()->json(['success' => true, 'theme' => $validated['theme']]);
    }

    public function updateLanguage(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'language' => 'required|in:system,sk,en',
        ]);

        $settings = $user->settings ?? [];
        $settings['language'] = $validated['language'];

        $user->update(['settings' => $settings]);

        return response()->json(['success' => true, 'language' => $validated['language']]);
    }

    /**
     * Toggle notification setting for a specific form
     */
    public function toggleFormNotification(Request $request, int $formId)
    {
        $user = auth()->user();

        // Only approver+ can toggle form notifications
        if (!$user->hasMinRole(User::ROLE_APPROVER)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'notify_enabled' => 'required|boolean',
        ]);

        // Check if form exists and is active
        $form = Form::where('id', $formId)->where('is_active', true)->first();
        if (!$form) {
            return response()->json(['error' => 'Form not found'], 404);
        }

        // Use syncWithoutDetaching to create or update the subscription
        if ($validated['notify_enabled']) {
            // Enable notifications - create or update subscription
            $user->subscribedForms()->syncWithoutDetaching([
                $formId => ['notify_enabled' => true],
            ]);
        } else {
            // Disable notifications
            if ($user->subscribedForms()->where('forms.id', $formId)->exists()) {
                $user->subscribedForms()->updateExistingPivot($formId, [
                    'notify_enabled' => false,
                ]);
            } else {
                // Create with notify_enabled = false (user explicitly disabled)
                $user->subscribedForms()->syncWithoutDetaching([
                    $formId => ['notify_enabled' => false],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'form_id' => $formId,
            'notify_enabled' => $validated['notify_enabled'],
        ]);
    }
}
