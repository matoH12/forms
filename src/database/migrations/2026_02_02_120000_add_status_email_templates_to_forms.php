<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->foreignId('approval_email_template_id')
                ->nullable()
                ->after('email_template_id')
                ->constrained('email_templates')
                ->nullOnDelete();

            $table->foreignId('rejection_email_template_id')
                ->nullable()
                ->after('approval_email_template_id')
                ->constrained('email_templates')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['approval_email_template_id']);
            $table->dropForeign(['rejection_email_template_id']);
            $table->dropColumn(['approval_email_template_id', 'rejection_email_template_id']);
        });
    }
};
