<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sensitive setting keys that need to be encrypted
     */
    private array $sensitiveKeys = [
        'mail_password',
        'keycloak_client_secret',
        'backup_ftp_password',
        'backup_s3_secret',
    ];

    /**
     * Run the migrations - encrypt existing plain text values
     */
    public function up(): void
    {
        foreach ($this->sensitiveKeys as $key) {
            $setting = DB::table('settings')->where('key', $key)->first();

            if ($setting && !empty($setting->value)) {
                // Check if value is already encrypted (Laravel encrypted strings start with 'eyJ')
                if (!str_starts_with($setting->value, 'eyJ')) {
                    DB::table('settings')
                        ->where('key', $key)
                        ->update([
                            'value' => Crypt::encryptString($setting->value),
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations - decrypt values back to plain text
     * WARNING: This exposes secrets in plain text!
     */
    public function down(): void
    {
        foreach ($this->sensitiveKeys as $key) {
            $setting = DB::table('settings')->where('key', $key)->first();

            if ($setting && !empty($setting->value)) {
                // Check if value is encrypted
                if (str_starts_with($setting->value, 'eyJ')) {
                    try {
                        $decrypted = Crypt::decryptString($setting->value);
                        DB::table('settings')
                            ->where('key', $key)
                            ->update([
                                'value' => $decrypted,
                                'updated_at' => now(),
                            ]);
                    } catch (\Exception $e) {
                        // Cannot decrypt, leave as-is
                    }
                }
            }
        }
    }
};
