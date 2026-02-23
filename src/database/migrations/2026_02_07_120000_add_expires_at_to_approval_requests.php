<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('responded_at');
        });

        // Set expiry for existing pending requests (7 days from now)
        DB::table('approval_requests')
            ->where('status', 'pending')
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addDays(7)]);
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
