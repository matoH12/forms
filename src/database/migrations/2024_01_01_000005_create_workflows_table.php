<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_on')->default('submission');
            $table->boolean('is_active')->default(true);
            $table->json('nodes');
            $table->json('edges');
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('node_id');
            $table->string('type');
            $table->string('name');
            $table->json('config');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['workflow_id', 'node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
    }
};
