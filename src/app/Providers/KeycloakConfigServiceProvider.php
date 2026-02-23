<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class KeycloakConfigServiceProvider extends ServiceProvider
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

            // Check if Keycloak settings are stored in database
            if (!Setting::hasKeycloakSettings()) {
                return;
            }

            $settings = Setting::getKeycloakSettings();

            // Override Laravel Socialite Keycloak configuration
            config([
                'services.keycloak.base_url' => $settings['base_url'],
                'services.keycloak.realms' => $settings['realm'],
                'services.keycloak.client_id' => $settings['client_id'],
                'services.keycloak.client_secret' => $settings['client_secret'],
                'services.keycloak.redirect' => $settings['redirect_uri'],
            ]);
        } catch (\Exception $e) {
            // Silently fail if database is not available
        }
    }
}
