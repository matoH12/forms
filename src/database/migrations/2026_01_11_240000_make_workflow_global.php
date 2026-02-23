<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Workflow form_id je teraz volitelne (workflow je globalny)
        Schema::table('workflows', function (Blueprint $table) {
            $table->foreignId('form_id')->nullable()->change();
        });

        // Formular si vyberie ktory workflow pouzit
        Schema::table('forms', function (Blueprint $table) {
            $table->foreignId('workflow_id')->nullable()->after('settings')->constrained('workflows')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['workflow_id']);
            $table->dropColumn('workflow_id');
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->foreignId('form_id')->nullable(false)->change();
        });
    }
};
