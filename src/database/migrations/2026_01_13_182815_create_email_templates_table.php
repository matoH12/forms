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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subject');
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->boolean('include_submission_data')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Add email_template_id to forms table
        Schema::table('forms', function (Blueprint $table) {
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->boolean('send_confirmation_email')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First remove columns from forms table
        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['email_template_id']);
            $table->dropColumn(['email_template_id', 'send_confirmation_email']);
        });

        Schema::dropIfExists('email_templates');
    }
};
