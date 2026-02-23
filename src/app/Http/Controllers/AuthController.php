<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login()
    {
        return Socialite::driver('keycloak')->redirect();
    }

    public function callback()
    {
        try {
            $keycloakUser = Socialite::driver('keycloak')->user();

            // Skontroluj či je používateľ v zozname adminov z .env
            $isEnvAdmin = $this->isEnvAdmin($keycloakUser);

            // Skontroluj či má admin rolu v Keycloak
            $hasKeycloakAdminRole = $this->checkAdminRole($keycloakUser);

            // Nájdi existujúceho používateľa
            $existingUser = User::where('keycloak_id', $keycloakUser->getId())->first();

            // Determine the appropriate role
            $role = $this->determineUserRole($existingUser, $isEnvAdmin, $hasKeycloakAdminRole);

            // Get login/username from Keycloak (preferred_username)
            $login = $keycloakUser->getNickname() ?? $keycloakUser->user['preferred_username'] ?? null;

            // Create or update user (without sensitive fields to prevent mass assignment)
            $user = User::updateOrCreate(
                ['keycloak_id' => $keycloakUser->getId()],
                [
                    'name' => $keycloakUser->getName(),
                    'email' => $keycloakUser->getEmail(),
                    'login' => $login,
                    'avatar' => $keycloakUser->getAvatar(),
                ]
            );

            // Set role explicitly (secure - not via mass assignment)
            // Only upgrade role, never downgrade existing users
            if ($this->shouldUpdateRole($user, $role)) {
                $oldRole = $user->role;
                $user->role = $role;
                $user->save();

                // SECURITY: Audit log role changes
                $source = $hasKeycloakAdminRole ? 'keycloak' : ($isEnvAdmin ? 'env_config' : 'auto');
                $this->logRoleChange($user, $oldRole, $role, $source);
            }

            // SECURITY: Regenerate session to prevent session fixation attacks
            $request = request();
            $request->session()->regenerate();

            Auth::login($user, true);

            // Audit log
            AuditService::userLogin($user);

            // Vždy presmerovať na hlavnú stránku (user rozhranie)
            // Admin tlačidlo sa zobrazí v navigácii
            return redirect()->intended('/');
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Prihlásenie zlyhalo: ' . $e->getMessage());
        }
    }

    /**
     * Determine the role for the user based on Keycloak and env settings
     * SECURITY: Keycloak roles alone CANNOT escalate existing users to super_admin.
     * Only env-based admin list can grant super_admin, and only on first login.
     * This prevents privilege escalation if Keycloak is compromised.
     */
    private function determineUserRole(?User $existingUser, bool $isEnvAdmin, bool $hasKeycloakAdminRole): string
    {
        if ($existingUser) {
            // SECURITY: Existing users keep their current role.
            // Keycloak admin role alone is NOT sufficient to auto-escalate.
            // Role changes for existing users must be done manually via admin panel.
            return $existingUser->role;
        }

        // NEW USER: Only env-based admin list grants super_admin on first login.
        // Keycloak admin role alone grants admin (not super_admin) for new users.
        if ($isEnvAdmin) {
            return User::ROLE_SUPER_ADMIN;
        }

        if ($hasKeycloakAdminRole) {
            return User::ROLE_ADMIN;
        }

        // Default: regular user
        return User::ROLE_USER;
    }

    /**
     * Check if user's role should be updated (only upgrade, never downgrade automatically)
     */
    private function shouldUpdateRole(User $user, string $newRole): bool
    {
        $currentLevel = User::ROLE_HIERARCHY[$user->role] ?? 0;
        $newLevel = User::ROLE_HIERARCHY[$newRole] ?? 0;

        // Only update if new role is higher or user is new (role is default 'user')
        return $newLevel > $currentLevel || ($user->wasRecentlyCreated && $newRole !== User::ROLE_USER);
    }

    /**
     * Log role change in audit log
     */
    private function logRoleChange(User $user, string $oldRole, string $newRole, string $source): void
    {
        AuditService::log('user_role_changed', $user, null, [
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'source' => $source,
            'ip' => request()->ip(),
        ]);
    }

    public function logout(Request $request)
    {
        // Audit log before logout
        if (auth()->check()) {
            AuditService::userLogout(auth()->user());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Keycloak 18+ logout URL s redirect späť na aplikáciu
        $logoutUrl = config('services.keycloak.base_url') . '/realms/' .
            config('services.keycloak.realms') . '/protocol/openid-connect/logout?' .
            http_build_query([
                'client_id' => config('services.keycloak.client_id'),
                'post_logout_redirect_uri' => config('app.url'),
            ]);

        // Použiť Inertia::location() pre externý redirect
        return Inertia::location($logoutUrl);
    }

    private function checkAdminRole($keycloakUser): bool
    {
        $roles = $keycloakUser->user['realm_access']['roles'] ?? [];
        return in_array('admin', $roles) || in_array('formulare-admin', $roles);
    }

    private function isEnvAdmin($keycloakUser): bool
    {
        $email = strtolower($keycloakUser->getEmail());
        $username = strtolower($keycloakUser->getNickname() ?? '');

        // Získaj zoznam admin emailov z .env
        $adminEmails = array_filter(array_map(
            'trim',
            explode(',', strtolower(env('ADMIN_EMAILS', '')))
        ));

        // Získaj zoznam admin usernames z .env
        $adminUsernames = array_filter(array_map(
            'trim',
            explode(',', strtolower(env('ADMIN_USERNAMES', '')))
        ));

        return in_array($email, $adminEmails) || in_array($username, $adminUsernames);
    }
}
