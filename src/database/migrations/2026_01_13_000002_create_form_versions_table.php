<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->onDelete('cascade');
            $table->unsignedInteger('version_number');
            $table->json('schema')->nullable();
            $table->json('settings')->nullable();
            $table->text('change_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['form_id', 'version_number']);
        });

        // Add current_version to forms table
        Schema::table('forms', function (Blueprint $table) {
            $table->unsignedInteger('current_version')->default(1)->after('settings');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_versions');

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('current_version');
        });
    }
};
