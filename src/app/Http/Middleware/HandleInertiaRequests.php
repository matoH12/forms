<?php

namespace App\Http\Middleware;

use App\Models\FormSubmission;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'first_name' => $request->user()->first_name,
                    'last_name' => $request->user()->last_name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'is_admin' => $request->user()->is_admin, // backward compatibility (computed from role)
                    'avatar' => $request->user()->avatar,
                    'settings' => $request->user()->settings ?? [],
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'newToken' => fn () => $request->session()->get('newToken'),
            ],
            'branding' => fn () => Setting::getBrandingSettings(),
            'pendingSubmissionsCount' => fn () => $this->getPendingSubmissionsCount($request),
        ];
    }

    /**
     * Get count of pending submissions for current admin user
     */
    private function getPendingSubmissionsCount(Request $request): int
    {
        $user = $request->user();

        // Only for users with at least viewer role
        if (!$user || !$user->hasMinRole(User::ROLE_VIEWER)) {
            return 0;
        }

        // Get allowed form IDs (explicit permissions only)
        $allowedFormIds = $user->getVisibleFormsQuery(false)->pluck('id');

        if ($allowedFormIds->isEmpty()) {
            return 0;
        }

        return DB::table('form_submissions')
            ->whereIn('form_id', $allowedFormIds)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', 'pending')
                    ->orWhere('status', 'submitted');
            })
            ->count();
    }
}
