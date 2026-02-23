<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Insert default mail settings
        DB::table('settings')->insert([
            ['key' => 'mail_host', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'mail_port', 'value' => '587', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'mail_username', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'mail_password', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'mail_encryption', 'value' => 'tls', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'mail_from_address', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'mail_from_name', 'value' => 'Formulare', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
