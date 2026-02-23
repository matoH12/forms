<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create categories table
        Schema::create('form_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#A59466'); // Gold as default
            $table->string('icon')->nullable(); // SVG path or icon name
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Add category_id and tags to forms
        Schema::table('forms', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('is_public')->constrained('form_categories')->nullOnDelete();
            $table->json('tags')->nullable()->after('category_id'); // Array of tags
            $table->text('keywords')->nullable()->after('tags'); // Additional search keywords
        });

        // Create index for faster search
        Schema::table('forms', function (Blueprint $table) {
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'tags', 'keywords']);
        });

        Schema::dropIfExists('form_categories');
    }
};
