<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add login to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('login')->nullable()->unique()->after('email');
        });

        // Add user_login to form_submissions table
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('user_login')->nullable()->after('user_id');
            $table->index('user_login');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('login');
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex(['user_login']);
            $table->dropColumn('user_login');
        });
    }
};
