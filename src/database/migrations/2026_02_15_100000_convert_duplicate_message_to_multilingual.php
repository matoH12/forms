<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert existing string duplicate_message to multilingual JSON format
        $forms = DB::table('forms')
            ->whereNotNull('duplicate_message')
            ->where('duplicate_message', '!=', '')
            ->get(['id', 'duplicate_message']);

        foreach ($forms as $form) {
            $message = $form->duplicate_message;

            // Check if it's already JSON
            $decoded = json_decode($message, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Already in JSON format, skip
                continue;
            }

            // Convert string to multilingual format
            $multilingualMessage = json_encode([
                'sk' => $message,
                'en' => '',
            ]);

            DB::table('forms')
                ->where('id', $form->id)
                ->update(['duplicate_message' => $multilingualMessage]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert multilingual JSON back to Slovak string
        $forms = DB::table('forms')
            ->whereNotNull('duplicate_message')
            ->where('duplicate_message', '!=', '')
            ->get(['id', 'duplicate_message']);

        foreach ($forms as $form) {
            $decoded = json_decode($form->duplicate_message, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $message = $decoded['sk'] ?? '';

                DB::table('forms')
                    ->where('id', $form->id)
                    ->update(['duplicate_message' => $message]);
            }
        }
    }
};
