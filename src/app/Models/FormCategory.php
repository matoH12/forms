<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FormCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                // Handle multilingual name for slug generation
                $name = $category->name;
                if (is_array($name)) {
                    $name = $name['sk'] ?? $name['en'] ?? '';
                }
                $category->slug = Str::slug($name);
            }
        });
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class, 'category_id');
    }

    public function getActiveFormsCountAttribute(): int
    {
        return $this->forms()->where('is_active', true)->count();
    }
}
