<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SystemApiToken extends Model
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Hash token using HMAC-SHA256 with app key
     * SECURITY: Prevents rainbow table attacks by using app-specific secret
     */
    private static function hashToken(string $plainTextToken): string
    {
        return hash_hmac('sha256', $plainTextToken, config('app.key'));
    }

    public static function createToken(string $name, array $abilities = ['*'], ?int $createdBy = null, ?int $expiresInDays = null): array
    {
        $plainTextToken = Str::random(64);

        $expiresAt = null;
        if ($expiresInDays !== null && $expiresInDays > 0) {
            $expiresAt = now()->addDays($expiresInDays);
        }

        $token = static::create([
            'name' => $name,
            'token' => static::hashToken($plainTextToken),
            'abilities' => $abilities,
            'created_by' => $createdBy,
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'plainTextToken' => $plainTextToken,
        ];
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false; // Never expires
        }

        return $this->expires_at->isPast();
    }

    public static function findToken(string $token): ?self
    {
        return static::where('token', static::hashToken($token))->first();
    }

    public function updateLastUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }

    public function can(string $ability): bool
    {
        return in_array('*', $this->abilities ?? []) ||
               in_array($ability, $this->abilities ?? []);
    }
}
