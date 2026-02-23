<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Get all forms
        $forms = DB::table('forms')->get();

        foreach ($forms as $form) {
            $name = $form->name;
            $description = $form->description;

            // Convert name to multilingual format if it's a string
            if (is_string($name) && !$this->isJson($name)) {
                $name = json_encode(['sk' => $name, 'en' => '']);
            } elseif (is_string($name) && $this->isJson($name)) {
                // Already JSON, check if it has the right format
                $decoded = json_decode($name, true);
                if (!isset($decoded['sk'])) {
                    $name = json_encode(['sk' => $name, 'en' => '']);
                }
            }

            // Convert description to multilingual format if it's a string
            if (is_string($description) && !empty($description) && !$this->isJson($description)) {
                $description = json_encode(['sk' => $description, 'en' => '']);
            } elseif (is_string($description) && $this->isJson($description)) {
                $decoded = json_decode($description, true);
                if (!isset($decoded['sk'])) {
                    $description = json_encode(['sk' => $description, 'en' => '']);
                }
            } elseif (empty($description)) {
                $description = json_encode(['sk' => '', 'en' => '']);
            }

            DB::table('forms')
                ->where('id', $form->id)
                ->update([
                    'name' => $name,
                    'description' => $description,
                ]);
        }
    }

    public function down(): void
    {
        // Get all forms and convert back to string (SK only)
        $forms = DB::table('forms')->get();

        foreach ($forms as $form) {
            $name = $form->name;
            $description = $form->description;

            if ($this->isJson($name)) {
                $decoded = json_decode($name, true);
                $name = $decoded['sk'] ?? $name;
            }

            if ($this->isJson($description)) {
                $decoded = json_decode($description, true);
                $description = $decoded['sk'] ?? $description;
            }

            DB::table('forms')
                ->where('id', $form->id)
                ->update([
                    'name' => $name,
                    'description' => $description,
                ]);
        }
    }

    private function isJson($string): bool
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
};
