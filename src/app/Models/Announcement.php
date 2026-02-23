<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'message',
        'type',
        'icon',
        'link_url',
        'link_text',
        'is_active',
        'is_dismissible',
        'starts_at',
        'ends_at',
        'order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_dismissible' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for active announcements
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * Get type color classes
     */
    public function getTypeColorsAttribute(): array
    {
        return match ($this->type) {
            'warning' => [
                'bg' => 'bg-amber-50 dark:bg-amber-900/20',
                'border' => 'border-amber-200 dark:border-amber-800',
                'text' => 'text-amber-800 dark:text-amber-200',
                'icon' => 'text-amber-500',
            ],
            'error' => [
                'bg' => 'bg-red-50 dark:bg-red-900/20',
                'border' => 'border-red-200 dark:border-red-800',
                'text' => 'text-red-800 dark:text-red-200',
                'icon' => 'text-red-500',
            ],
            'success' => [
                'bg' => 'bg-green-50 dark:bg-green-900/20',
                'border' => 'border-green-200 dark:border-green-800',
                'text' => 'text-green-800 dark:text-green-200',
                'icon' => 'text-green-500',
            ],
            default => [
                'bg' => 'bg-blue-50 dark:bg-blue-900/20',
                'border' => 'border-blue-200 dark:border-blue-800',
                'text' => 'text-blue-800 dark:text-blue-200',
                'icon' => 'text-blue-500',
            ],
        };
    }
}
