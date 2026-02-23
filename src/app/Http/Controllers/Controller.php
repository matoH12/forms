<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Strip HTML tags from filter values before passing to Inertia.
     * Prevents reflected XSS via query parameters in JSON responses.
     */
    protected function sanitizeFilters(array $filters): array
    {
        return array_map(function ($value) {
            return is_string($value) ? strip_tags($value) : $value;
        }, $filters);
    }
}
