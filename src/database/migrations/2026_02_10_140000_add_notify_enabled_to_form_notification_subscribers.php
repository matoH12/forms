<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_notification_subscribers', function (Blueprint $table) {
            $table->boolean('notify_enabled')->default(true)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('form_notification_subscribers', function (Blueprint $table) {
            $table->dropColumn('notify_enabled');
        });
    }
};
