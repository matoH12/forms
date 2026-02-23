<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Available roles (ordered from lowest to highest privilege)
     */
    public const ROLE_USER = 'user';
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_APPROVER = 'approver';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    /**
     * Role hierarchy (index = privilege level)
     */
    public const ROLE_HIERARCHY = [
        self::ROLE_USER => 0,
        self::ROLE_VIEWER => 1,
        self::ROLE_APPROVER => 2,
        self::ROLE_ADMIN => 3,
        self::ROLE_SUPER_ADMIN => 4,
    ];

    /**
     * All available roles for validation
     */
    public const ROLES = [
        self::ROLE_USER,
        self::ROLE_VIEWER,
        self::ROLE_APPROVER,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    /**
     * Mass assignable attributes.
     * Note: is_admin, role and can_see_all_forms are intentionally excluded
     * to prevent privilege escalation via mass assignment attacks.
     * Use setRole() and setCanSeeAllForms() methods instead.
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'login',
        'keycloak_id',
        'avatar',
        'settings',
    ];

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has at least the given role level (hierarchical)
     */
    public function hasMinRole(string $minRole): bool
    {
        $userLevel = self::ROLE_HIERARCHY[$this->role] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$minRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Get localized role label
     */
    public function getRoleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_ADMIN => 'Správca',
            self::ROLE_APPROVER => 'Schvaľovateľ',
            self::ROLE_VIEWER => 'Čitateľ',
            default => 'Používateľ',
        };
    }

    /**
     * Safely set role (prevents mass assignment attacks)
     */
    public function setRole(string $role): void
    {
        if (in_array($role, self::ROLES)) {
            $this->role = $role;
            $this->save();
        }
    }

    /**
     * Safely set admin status (prevents mass assignment attacks)
     * @deprecated Use setRole() instead
     */
    public function setAdmin(bool $isAdmin): void
    {
        $this->is_admin = $isAdmin;
        $this->save();
    }

    /**
     * Safely set can_see_all_forms permission (prevents mass assignment attacks)
     */
    public function setCanSeeAllForms(bool $canSeeAllForms): void
    {
        $this->can_see_all_forms = $canSeeAllForms;
        $this->save();
    }

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'can_see_all_forms' => 'boolean',
            'email_verified_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /**
     * Check if user is admin (has at least viewer role = can access admin panel)
     * Used for backward compatibility
     */
    public function getIsAdminAttribute(): bool
    {
        return $this->hasMinRole(self::ROLE_VIEWER);
    }

    /**
     * Get a specific setting value
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a specific setting value
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Get preferred theme (dark/light/system)
     */
    public function getPreferredTheme(): string
    {
        return $this->getSetting('theme', 'system');
    }

    /**
     * Get display name (first + last name or name)
     */
    public function getDisplayName(): string
    {
        if ($this->first_name && $this->last_name) {
            return $this->first_name . ' ' . $this->last_name;
        }
        return $this->name ?? '';
    }

    public function forms()
    {
        return $this->hasMany(Form::class, 'created_by');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * Forms that user has explicit permission to view
     */
    public function allowedForms()
    {
        return $this->belongsToMany(Form::class, 'form_user')->withTimestamps();
    }

    /**
     * Forms that user is subscribed to for new submission notifications
     */
    public function subscribedForms()
    {
        return $this->belongsToMany(Form::class, 'form_notification_subscribers')
            ->withPivot('notify_enabled')
            ->withTimestamps();
    }

    /**
     * Check if user wants to receive notifications about new submissions (global setting)
     */
    public function wantsNewSubmissionNotifications(): bool
    {
        return $this->hasMinRole(self::ROLE_APPROVER) &&
               $this->getSetting('notify_new_submissions', false);
    }

    /**
     * Check if user can see a specific form
     */
    public function canSeeForm(Form $form): bool
    {
        // Regular users see all public and active forms
        if (!$this->is_admin) {
            return $form->is_public && $form->is_active;
        }

        // Admin with "can see all forms" permission
        if ($this->can_see_all_forms) {
            return true;
        }

        // Creator can always see their own forms
        if ($form->created_by === $this->id) {
            return true;
        }

        // Admin - check if form is in allowed forms
        return $this->allowedForms()->where('forms.id', $form->id)->exists();
    }

    /**
     * Get all forms that user can see (for queries)
     * @param bool $includeCreated - include forms created by user (for form listing)
     */
    public function getVisibleFormsQuery(bool $includeCreated = true)
    {
        // Regular users see all public and active forms
        if (!$this->is_admin) {
            return Form::where('is_public', true)->where('is_active', true);
        }

        // Admin with "can see all forms" permission
        if ($this->can_see_all_forms) {
            return Form::query();
        }

        // Admin - get explicitly allowed forms
        $allowedFormIds = $this->allowedForms()->pluck('forms.id')->toArray();

        if ($includeCreated) {
            // For form listing: include forms they created OR have explicit permission for
            return Form::where('created_by', $this->id)
                ->orWhereIn('id', $allowedFormIds);
        }

        // For submissions/dashboard: only explicitly allowed forms
        return Form::whereIn('id', $allowedFormIds);
    }

    /**
     * Get form IDs that user has explicit permission to see submissions for
     */
    public function getAllowedFormIdsForSubmissions(): array
    {
        // Regular users - not applicable (they use public interface)
        if (!$this->is_admin) {
            return [];
        }

        // Admin with "can see all forms" permission
        if ($this->can_see_all_forms) {
            return Form::pluck('id')->toArray();
        }

        // Admin - only explicitly allowed forms
        return $this->allowedForms()->pluck('forms.id')->toArray();
    }

    /**
     * Check if user can see submissions for a specific form
     * (stricter than canSeeForm - only explicit permissions, not created_by)
     */
    public function canSeeFormSubmissions(Form $form): bool
    {
        // Regular users don't use admin submission views
        if (!$this->is_admin) {
            return false;
        }

        // Admin with "can see all forms" permission
        if ($this->can_see_all_forms) {
            return true;
        }

        // Admin - check if form is in explicitly allowed forms
        return $this->allowedForms()->where('forms.id', $form->id)->exists();
    }
}
