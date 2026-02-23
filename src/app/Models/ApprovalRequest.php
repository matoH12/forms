<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApprovalRequest extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // Token validity period in days
    const TOKEN_VALIDITY_DAYS = 7;

    // SECURITY: Token length for cryptographically secure random generation
    const TOKEN_LENGTH = 64;

    protected $fillable = [
        'workflow_execution_id',
        'node_id',
        'token',
        'approver_email',
        'status',
        'approved_by',
        'comment',
        'responded_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($request) {
            if (empty($request->token)) {
                // SECURITY: Use cryptographically secure random string instead of UUID
                // Str::random() uses random_bytes() internally (CSPRNG)
                // 64 chars = 384 bits of entropy (vs UUID's 122 bits)
                $request->token = Str::random(self::TOKEN_LENGTH);
            }
            if (empty($request->expires_at)) {
                $request->expires_at = now()->addDays(self::TOKEN_VALIDITY_DAYS);
            }
        });
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Scope: expired tokens that are still pending (cleanup candidates)
     */
    public function scopeExpiredPending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Clean up expired pending approval tokens from the database.
     * Returns the number of deleted records.
     */
    public static function cleanupExpired(): int
    {
        return static::expiredPending()->delete();
    }

    public function execution()
    {
        return $this->belongsTo(WorkflowExecution::class, 'workflow_execution_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
