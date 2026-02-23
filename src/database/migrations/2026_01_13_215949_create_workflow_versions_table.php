<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->onDelete('cascade');
            $table->unsignedInteger('version_number');
            $table->json('nodes')->nullable();
            $table->json('edges')->nullable();
            $table->text('change_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['workflow_id', 'version_number']);
        });

        // Add current_version to workflows table
        Schema::table('workflows', function (Blueprint $table) {
            $table->unsignedInteger('current_version')->default(1)->after('edges');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('current_version');
        });
    }
};
