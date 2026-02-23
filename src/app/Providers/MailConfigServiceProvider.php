<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        try {
            // Only configure if the settings table exists
            if (!Schema::hasTable('settings')) {
                return;
            }

            $settings = Setting::getMailSettings();

            // Only override if settings are configured
            if (!empty($settings['host'])) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $settings['host'],
                    'mail.mailers.smtp.port' => $settings['port'],
                    'mail.mailers.smtp.username' => $settings['username'],
                    'mail.mailers.smtp.password' => $settings['password'],
                    'mail.mailers.smtp.encryption' => $settings['encryption'] === 'null' ? null : $settings['encryption'],
                    'mail.from.address' => $settings['from_address'],
                    'mail.from.name' => $settings['from_name'],
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if database is not available
        }
    }
}
