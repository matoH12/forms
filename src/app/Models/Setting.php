<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * List of setting keys that contain sensitive data and must be encrypted
     */
    private static array $encryptedKeys = [
        'mail_password',
        'keycloak_client_secret',
        'backup_ftp_password',
        'backup_s3_secret',
    ];

    /**
     * Check if a key should be encrypted
     */
    private static function shouldEncrypt(string $key): bool
    {
        return in_array($key, self::$encryptedKeys);
    }

    /**
     * Get a setting value by key (auto-decrypts sensitive values)
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // SECURITY: Sensitive settings use shorter cache TTL (5 min vs 1 hour)
        // to reduce exposure window if app key is compromised
        $cacheTtl = self::shouldEncrypt($key) ? 300 : 3600;

        return Cache::remember("setting.{$key}", $cacheTtl, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            $value = $setting->value;

            // Auto-decrypt sensitive values
            if (self::shouldEncrypt($key) && !empty($value)) {
                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException $e) {
                    // Value might not be encrypted yet (legacy data)
                    // Return as-is for backward compatibility
                    return $value;
                }
            }

            return $value;
        });
    }

    /**
     * Set a setting value (auto-encrypts sensitive values)
     */
    public static function set(string $key, mixed $value): void
    {
        // Auto-encrypt sensitive values
        if (self::shouldEncrypt($key) && !empty($value)) {
            $value = Crypt::encryptString($value);
        }

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget("setting.{$key}");
    }

    /**
     * Get all mail settings
     * Falls back to .env values if database values are empty
     */
    public static function getMailSettings(): array
    {
        return [
            'host' => static::get('mail_host') ?: env('MAIL_HOST', ''),
            'port' => (int) (static::get('mail_port') ?: env('MAIL_PORT', 587)),
            'username' => static::get('mail_username') ?: env('MAIL_USERNAME', ''),
            'password' => static::get('mail_password') ?: env('MAIL_PASSWORD', ''),
            'encryption' => static::get('mail_encryption') ?: env('MAIL_ENCRYPTION', 'tls'),
            'from_address' => static::get('mail_from_address') ?: env('MAIL_FROM_ADDRESS', ''),
            'from_name' => static::get('mail_from_name') ?: env('MAIL_FROM_NAME', 'Forms'),
        ];
    }

    /**
     * Save mail settings
     */
    public static function saveMailSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            static::set("mail_{$key}", $value);
        }

        // Clear all mail setting caches
        Cache::forget('mail_config');
    }

    /**
     * Get all Keycloak settings
     * Falls back to .env values if database values are empty
     */
    public static function getKeycloakSettings(): array
    {
        return [
            'base_url' => static::get('keycloak_base_url') ?: env('KEYCLOAK_BASE_URL', ''),
            'realm' => static::get('keycloak_realm') ?: env('KEYCLOAK_REALM', ''),
            'client_id' => static::get('keycloak_client_id') ?: env('KEYCLOAK_CLIENT_ID', ''),
            'client_secret' => static::get('keycloak_client_secret') ?: env('KEYCLOAK_CLIENT_SECRET', ''),
            'redirect_uri' => static::get('keycloak_redirect_uri') ?: env('KEYCLOAK_REDIRECT_URI', ''),
        ];
    }

    /**
     * Save Keycloak settings
     */
    public static function saveKeycloakSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            static::set("keycloak_{$key}", $value);
        }

        // Clear keycloak config cache
        Cache::forget('keycloak_config');
    }

    /**
     * Check if Keycloak is configured in database
     */
    public static function hasKeycloakSettings(): bool
    {
        return !empty(static::get('keycloak_base_url'));
    }

    /**
     * Get all branding settings
     */
    public static function getBrandingSettings(): array
    {
        return [
            'site_name' => static::get('branding_site_name', 'Forms'),
            'site_subtitle' => static::get('branding_site_subtitle', ''),
            'organization_name' => static::get('branding_organization_name', 'Your Organization'),
            'footer_text' => static::get('branding_footer_text', ''),
            'primary_color' => static::get('branding_primary_color', '#1e3a5f'),
            'accent_color' => static::get('branding_accent_color', '#c9a227'),
            'logo' => static::get('branding_logo', ''),
            'support_email' => static::get('branding_support_email', ''),
        ];
    }

    /**
     * Save branding settings
     */
    public static function saveBrandingSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            static::set("branding_{$key}", $value);
        }

        // Clear branding config cache
        Cache::forget('branding_config');
    }

    /**
     * Get all backup settings
     */
    public static function getBackupSettings(): array
    {
        return [
            // Schedule
            'enabled' => (bool) static::get('backup_enabled', false),
            'frequency' => static::get('backup_frequency', 'daily'), // daily, weekly, monthly
            'time' => static::get('backup_time', '02:00'), // HH:MM format
            'include_submissions' => (bool) static::get('backup_include_submissions', false),
            'retention_local' => (int) static::get('backup_retention_local', 10),

            // FTP
            'ftp_enabled' => (bool) static::get('backup_ftp_enabled', false),
            'ftp_host' => static::get('backup_ftp_host', ''),
            'ftp_port' => (int) static::get('backup_ftp_port', 21),
            'ftp_username' => static::get('backup_ftp_username', ''),
            'ftp_password' => static::get('backup_ftp_password', ''),
            'ftp_path' => static::get('backup_ftp_path', '/'),
            'ftp_passive' => (bool) static::get('backup_ftp_passive', true),
            'ftp_ssl' => (bool) static::get('backup_ftp_ssl', false),
            'ftp_retention' => (int) static::get('backup_ftp_retention', 10),

            // S3
            's3_enabled' => (bool) static::get('backup_s3_enabled', false),
            's3_key' => static::get('backup_s3_key', ''),
            's3_secret' => static::get('backup_s3_secret', ''),
            's3_region' => static::get('backup_s3_region', 'eu-central-1'),
            's3_bucket' => static::get('backup_s3_bucket', ''),
            's3_endpoint' => static::get('backup_s3_endpoint', ''),
            's3_path' => static::get('backup_s3_path', ''),
            's3_use_path_style' => (bool) static::get('backup_s3_use_path_style', false),
            's3_retention' => (int) static::get('backup_s3_retention', 10),
        ];
    }

    /**
     * Save backup settings
     */
    public static function saveBackupSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            static::set("backup_{$key}", $value);
        }

        // Clear backup config cache
        Cache::forget('backup_config');
    }
}
