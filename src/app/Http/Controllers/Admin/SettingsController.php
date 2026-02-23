<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormSubmission;
use App\Models\Setting;
use App\Models\SystemApiToken;
use App\Models\Workflow;
use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * SECURITY: Blocked IPv4 ranges for SSRF protection
     */
    private const BLOCKED_IPV4_RANGES = [
        '127.0.0.0/8',      // Loopback
        '10.0.0.0/8',       // Private
        '172.16.0.0/12',    // Private
        '192.168.0.0/16',   // Private
        '169.254.0.0/16',   // Link-local (AWS metadata)
        '100.64.0.0/10',    // Carrier-grade NAT
        '0.0.0.0/8',        // Reserved
    ];

    /**
     * SECURITY: Blocked IPv6 ranges for SSRF protection
     */
    private const BLOCKED_IPV6_RANGES = [
        '::1/128',           // Loopback
        '::/128',            // Unspecified
        '::ffff:0:0/96',     // IPv4-mapped (could bypass IPv4 blocks)
        '::ffff:127.0.0.0/104',  // IPv4-mapped loopback
        '::ffff:10.0.0.0/104',   // IPv4-mapped private
        '::ffff:172.16.0.0/108', // IPv4-mapped private
        '::ffff:192.168.0.0/112',// IPv4-mapped private
        '::ffff:169.254.0.0/112',// IPv4-mapped link-local
        'fc00::/7',          // Unique local (private)
        'fe80::/10',         // Link-local
        'ff00::/8',          // Multicast
        '64:ff9b::/96',      // NAT64
        '100::/64',          // Discard
        '2001:db8::/32',     // Documentation
    ];

    /**
     * SECURITY: Validate URL to prevent SSRF attacks (supports IPv4 and IPv6)
     */
    private function validateUrlForSsrf(string $url): ?string
    {
        $parsed = parse_url($url);

        if (!$parsed || empty($parsed['host'])) {
            return 'Neplatny format URL';
        }

        $host = strtolower($parsed['host']);
        $scheme = strtolower($parsed['scheme'] ?? 'http');

        // Only allow http and https
        if (!in_array($scheme, ['http', 'https'])) {
            return "Protokol '{$scheme}' nie je povoleny";
        }

        // Remove brackets from IPv6 addresses in URL (e.g., [::1] -> ::1)
        $cleanHost = trim($host, '[]');

        // Block localhost variations
        if (in_array($cleanHost, ['localhost', '127.0.0.1', '::1', '0.0.0.0'])) {
            return 'Pristup na localhost nie je povoleny';
        }

        // Check if host is already an IP address
        $ip = null;
        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            $ip = $cleanHost;
        } else {
            // Try to resolve hostname - first try IPv4
            $ip = gethostbyname($cleanHost);
            if ($ip === $cleanHost) {
                // IPv4 resolution failed, try IPv6
                $dns = dns_get_record($cleanHost, DNS_AAAA);
                if (!empty($dns) && isset($dns[0]['ipv6'])) {
                    $ip = $dns[0]['ipv6'];
                } else {
                    return 'Nepodarilo sa resolvovat hostname';
                }
            }
        }

        // Check if IP is blocked (handles both IPv4 and IPv6)
        return $this->isIpBlocked($ip);
    }

    /**
     * Check if IPv4 is in CIDR range
     */
    private function ipv4InRange(string $ip, string $range): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        [$subnet, $bits] = explode('/', $range);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int)$bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * Check if IPv6 is in CIDR range
     */
    private function ipv6InRange(string $ip, string $range): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }

        [$subnet, $bits] = explode('/', $range);
        $bits = (int)$bits;

        // Convert to binary representation
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // Compare the required number of bits
        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        // Compare full bytes
        for ($i = 0; $i < $fullBytes; $i++) {
            if ($ipBin[$i] !== $subnetBin[$i]) {
                return false;
            }
        }

        // Compare remaining bits if any
        if ($remainingBits > 0 && $fullBytes < 16) {
            $mask = 0xFF << (8 - $remainingBits);
            if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($subnetBin[$fullBytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if IP (v4 or v6) is blocked
     */
    private function isIpBlocked(string $ip): ?string
    {
        // Check IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach (self::BLOCKED_IPV4_RANGES as $range) {
                if ($this->ipv4InRange($ip, $range)) {
                    return "Pristup na internu siet ({$range}) nie je povoleny";
                }
            }
            return null;
        }

        // Check IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            foreach (self::BLOCKED_IPV6_RANGES as $range) {
                if ($this->ipv6InRange($ip, $range)) {
                    return "Pristup na internu siet ({$range}) nie je povoleny";
                }
            }
            return null;
        }

        // Unknown IP format - block by default
        return "Neplatny format IP adresy";
    }

    /**
     * SECURITY: Validate hostname/IP to prevent SSRF attacks (for FTP, S3, etc.)
     * Supports both IPv4 and IPv6
     */
    private function validateHostForSsrf(string $host): ?string
    {
        $host = strtolower(trim($host));

        if (empty($host)) {
            return 'Host je povinny';
        }

        // Remove brackets from IPv6 addresses (e.g., [::1] -> ::1)
        $cleanHost = trim($host, '[]');

        // Block localhost variations
        if (in_array($cleanHost, ['localhost', '127.0.0.1', '::1', '0.0.0.0'])) {
            return 'Pristup na localhost nie je povoleny';
        }

        // Check if host is already an IP address
        $ip = null;
        if (filter_var($cleanHost, FILTER_VALIDATE_IP)) {
            $ip = $cleanHost;
        } else {
            // Try to resolve hostname - first try IPv4
            $ip = gethostbyname($cleanHost);
            if ($ip === $cleanHost) {
                // IPv4 resolution failed, try IPv6
                $dns = @dns_get_record($cleanHost, DNS_AAAA);
                if (!empty($dns) && isset($dns[0]['ipv6'])) {
                    $ip = $dns[0]['ipv6'];
                } else {
                    return 'Nepodarilo sa resolvovat hostname';
                }
            }
        }

        // Check if IP is blocked (handles both IPv4 and IPv6)
        return $this->isIpBlocked($ip);
    }

    public function index()
    {
        $mailSettings = Setting::getMailSettings();
        $keycloakSettings = Setting::getKeycloakSettings();
        $brandingSettings = Setting::getBrandingSettings();
        $backupSettings = Setting::getBackupSettings();

        // Don't expose passwords to frontend
        $mailSettings['password'] = $mailSettings['password'] ? '********' : '';
        $keycloakSettings['client_secret'] = $keycloakSettings['client_secret'] ? '********' : '';
        $backupSettings['ftp_password'] = $backupSettings['ftp_password'] ? '********' : '';
        $backupSettings['s3_secret'] = $backupSettings['s3_secret'] ? '********' : '';

        // Get API tokens
        $apiTokens = SystemApiToken::with('creator:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'expires_at' => $token->expires_at?->toIso8601String(),
                    'is_expired' => $token->isExpired(),
                    'created_at' => $token->created_at->toIso8601String(),
                    'creator' => $token->creator ? [
                        'name' => $token->creator->name,
                        'email' => $token->creator->email,
                    ] : null,
                ];
            });

        // Get local backups
        $backupService = new BackupService();
        $localBackups = $backupService->getLocalBackups();

        return Inertia::render('Admin/Settings/Index', [
            'mailSettings' => $mailSettings,
            'keycloakSettings' => $keycloakSettings,
            'brandingSettings' => $brandingSettings,
            'backupSettings' => $backupSettings,
            'localBackups' => $localBackups,
            'apiTokens' => $apiTokens,
        ]);
    }

    public function updateMail(Request $request)
    {
        $validated = $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'encryption' => 'nullable|string|in:tls,ssl,null',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
        ]);

        // If password is masked, don't update it
        if ($validated['password'] === '********' || empty($validated['password'])) {
            unset($validated['password']);
        }

        Setting::saveMailSettings($validated);

        // Clear all mail-related caches
        Cache::flush();

        // Audit log (bez hesla)
        $auditChanges = $validated;
        if (isset($auditChanges['password'])) {
            $auditChanges['password'] = '***';
        }
        AuditService::settingsUpdated('mail', $auditChanges);

        return redirect()
            ->back()
            ->with('success', 'Nastavenia emailu boli ulozene. Pre aplikovanie zmien restartujte queue workery: docker-compose restart queue queue-high');
    }

    public function testMail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            // Configure mailer with current settings
            $this->configureMailer();

            Mail::raw('Toto je testovaci email z aplikacie Formulare.', function ($message) use ($request) {
                $settings = Setting::getMailSettings();
                $message->to($request->email)
                    ->subject('Test emailu - Formulare')
                    ->from($settings['from_address'], $settings['from_name']);
            });

            // Audit log for successful test
            AuditService::log('mail_test', null, null, [
                'test_email' => $request->email,
                'status' => 'success',
            ]);

            return redirect()
                ->back()
                ->with('success', 'Testovaci email bol odoslany na ' . $request->email);
        } catch (\Exception $e) {
            // SECURITY: Log full error for debugging, but don't expose to user
            Log::error('Mail test failed', [
                'test_email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Audit log for failed test (sanitized)
            AuditService::log('mail_test', null, null, [
                'test_email' => $request->email,
                'status' => 'failed',
                'error_type' => get_class($e),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Chyba pri odosielani testovacieho emailu. Skontrolujte nastavenia SMTP servera.');
        }
    }

    private function configureMailer(): void
    {
        $settings = Setting::getMailSettings();

        config([
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => $settings['port'],
            'mail.mailers.smtp.username' => $settings['username'],
            'mail.mailers.smtp.password' => $settings['password'],
            'mail.mailers.smtp.encryption' => $settings['encryption'] === 'null' ? null : $settings['encryption'],
            'mail.from.address' => $settings['from_address'],
            'mail.from.name' => $settings['from_name'],
        ]);
    }

    public function updateKeycloak(Request $request)
    {
        $validated = $request->validate([
            'base_url' => 'required|url|max:500',
            'realm' => 'required|string|max:255',
            'client_id' => 'required|string|max:255',
            'client_secret' => 'nullable|string|max:500',
            'redirect_uri' => 'required|url|max:500',
        ]);

        // SECURITY: SSRF protection - validate base_url before saving
        $ssrfError = $this->validateUrlForSsrf($validated['base_url']);
        if ($ssrfError) {
            return redirect()
                ->back()
                ->withErrors(['base_url' => 'SSRF ochrana: ' . $ssrfError])
                ->withInput();
        }

        // If client_secret is masked, don't update it
        if ($validated['client_secret'] === '********' || empty($validated['client_secret'])) {
            unset($validated['client_secret']);
        }

        Setting::saveKeycloakSettings($validated);

        // Audit log (bez client_secret)
        $auditChanges = $validated;
        if (isset($auditChanges['client_secret'])) {
            $auditChanges['client_secret'] = '***';
        }
        AuditService::settingsUpdated('keycloak', $auditChanges);

        return redirect()
            ->back()
            ->with('success', 'Nastavenia Keycloak boli ulozene');
    }

    public function testKeycloak()
    {
        try {
            $settings = Setting::getKeycloakSettings();

            if (empty($settings['base_url'])) {
                return redirect()
                    ->back()
                    ->with('error', 'Keycloak nie je nakonfigurovany');
            }

            // Test connection to Keycloak well-known endpoint
            $wellKnownUrl = rtrim($settings['base_url'], '/') . '/realms/' . $settings['realm'] . '/.well-known/openid-configuration';

            // SECURITY: SSRF protection - validate URL before making request
            $ssrfError = $this->validateUrlForSsrf($wellKnownUrl);
            if ($ssrfError) {
                // Audit log for SSRF blocked attempt
                AuditService::log('keycloak_test', null, null, [
                    'url' => $wellKnownUrl,
                    'status' => 'blocked',
                    'reason' => 'ssrf_protection',
                ]);

                return redirect()
                    ->back()
                    ->with('error', 'SSRF ochrana: ' . $ssrfError);
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $wellKnownUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            // SECURITY: Prevent redirects to internal URLs
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                // Audit log for connection error
                AuditService::log('keycloak_test', null, null, [
                    'base_url' => $settings['base_url'],
                    'realm' => $settings['realm'],
                    'status' => 'failed',
                    'error' => $error,
                ]);

                return redirect()
                    ->back()
                    ->with('error', 'Chyba pripojenia: ' . $error);
            }

            if ($httpCode !== 200) {
                // Audit log for HTTP error
                AuditService::log('keycloak_test', null, null, [
                    'base_url' => $settings['base_url'],
                    'realm' => $settings['realm'],
                    'status' => 'failed',
                    'http_code' => $httpCode,
                ]);

                return redirect()
                    ->back()
                    ->with('error', 'Keycloak server vratil HTTP ' . $httpCode);
            }

            $data = json_decode($response, true, 32);
            if (!isset($data['issuer'])) {
                return redirect()
                    ->back()
                    ->with('error', 'Neplatna odpoved zo servera Keycloak');
            }

            // Audit log for successful test
            AuditService::log('keycloak_test', null, null, [
                'base_url' => $settings['base_url'],
                'realm' => $settings['realm'],
                'status' => 'success',
                'issuer' => $data['issuer'],
            ]);

            return redirect()
                ->back()
                ->with('success', 'Pripojenie k Keycloak funguje. Issuer: ' . $data['issuer']);
        } catch (\Exception $e) {
            // SECURITY: Log full error for debugging, but don't expose to user
            Log::error('Keycloak test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Audit log for exception (sanitized)
            AuditService::log('keycloak_test', null, null, [
                'status' => 'failed',
                'error_type' => get_class($e),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Chyba pripojenia k Keycloak serveru. Skontrolujte nastavenia.');
        }
    }

    public function updateBranding(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'required|string|max:100',
            'site_subtitle' => 'nullable|string|max:50',
            'organization_name' => 'required|string|max:255',
            'footer_text' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'support_email' => 'nullable|email|max:255',
        ]);

        Setting::saveBrandingSettings($validated);

        // Audit log
        AuditService::settingsUpdated('branding', $validated);

        return redirect()
            ->back()
            ->with('success', 'Nastavenia vzhľadu boli uložené');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        try {
            // Delete old logo if exists
            $currentLogo = Setting::get('branding_logo');
            if ($currentLogo && Storage::disk('public')->exists($currentLogo)) {
                Storage::disk('public')->delete($currentLogo);
            }

            // Store new logo
            $path = $request->file('logo')->store('branding', 'public');

            // Save to settings
            Setting::set('branding_logo', $path);

            // Audit log
            AuditService::settingsUpdated('branding_logo', ['logo' => $path]);

            return redirect()
                ->back()
                ->with('success', 'Logo bolo úspešne nahrané');
        } catch (\Exception $e) {
            // SECURITY: Log full error for debugging, but don't expose to user
            Log::error('Logo upload failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Chyba pri nahrávaní loga. Skúste to znova.');
        }
    }

    public function deleteLogo()
    {
        try {
            $currentLogo = Setting::get('branding_logo');

            if ($currentLogo && Storage::disk('public')->exists($currentLogo)) {
                Storage::disk('public')->delete($currentLogo);
            }

            Setting::set('branding_logo', '');

            // Audit log
            AuditService::settingsUpdated('branding_logo', ['logo' => 'deleted']);

            return redirect()
                ->back()
                ->with('success', 'Logo bolo odstránené');
        } catch (\Exception $e) {
            // SECURITY: Log full error for debugging, but don't expose to user
            Log::error('Logo delete failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Chyba pri odstraňovaní loga. Skúste to znova.');
        }
    }

    /**
     * Available API token abilities
     */
    public const API_TOKEN_ABILITIES = [
        'submissions:read' => 'Read submissions',
        'submissions:import' => 'Import submissions',
        'forms:read' => 'Read forms',
        'api:access' => 'General API access',
    ];

    public function createApiToken(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'expires_in_days' => 'nullable|integer|min:0|max:3650',
            'abilities' => 'nullable|array',
            'abilities.*' => 'string|in:' . implode(',', array_keys(self::API_TOKEN_ABILITIES)),
        ]);

        // 0 means never expires
        $expiresInDays = $validated['expires_in_days'] ?? null;
        if ($expiresInDays === 0) {
            $expiresInDays = null;
        }

        // SECURITY: Default to read-only abilities, never use ['*']
        $abilities = $validated['abilities'] ?? ['submissions:read', 'forms:read'];
        if (empty($abilities)) {
            $abilities = ['submissions:read', 'forms:read'];
        }

        $result = SystemApiToken::createToken(
            $validated['name'],
            $abilities,
            auth()->id(),
            $expiresInDays
        );

        // Audit log
        AuditService::log('api_token_created', null, null, [
            'name' => $validated['name'],
            'abilities' => $abilities,
            'expires_in_days' => $expiresInDays ?? 'never',
        ]);

        return redirect()
            ->back()
            ->with('success', 'API token bol vytvorený')
            ->with('newToken', $result['plainTextToken']);
    }

    public function deleteApiToken(SystemApiToken $token)
    {
        $tokenName = $token->name;
        $token->delete();

        // Audit log
        AuditService::log('api_token_deleted', null, null, ['name' => $tokenName]);

        return redirect()
            ->back()
            ->with('success', 'API token bol zmazaný');
    }

    // ==========================================
    // BACKUP & RESTORE
    // ==========================================

    public function createBackup(Request $request)
    {
        $request->validate([
            'include_submissions' => 'boolean',
        ]);

        $includeSubmissions = $request->boolean('include_submissions', false);

        try {
            $backup = [
                'backup_type' => 'full',
                'backup_date' => now()->toIso8601String(),
                'version' => '1.0',
                'app_version' => config('app.version', '1.0.0'),
            ];

            // Categories
            $backup['categories'] = FormCategory::orderBy('order')->get()->map(function ($category) {
                return [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'color' => $category->color,
                    'icon' => $category->icon,
                    'order' => $category->order,
                ];
            })->toArray();

            // Email Templates
            $backup['email_templates'] = EmailTemplate::all()->map(function ($template) {
                return [
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'subject' => $template->subject,
                    'body_html' => $template->body_html,
                    'body_text' => $template->body_text,
                    'variables' => $template->variables,
                    'is_active' => $template->is_active,
                    'is_default' => $template->is_default,
                ];
            })->toArray();

            // Workflows (standalone, not linked to forms)
            $backup['workflows'] = Workflow::whereNull('form_id')->get()->map(function ($workflow) {
                return [
                    'name' => $workflow->name,
                    'description' => $workflow->description,
                    'trigger_on' => $workflow->trigger_on,
                    'is_active' => $workflow->is_active,
                    'nodes' => $workflow->nodes,
                    'edges' => $workflow->edges,
                ];
            })->toArray();

            // Forms with their workflows
            $backup['forms'] = Form::with(['category', 'workflow'])->get()->map(function ($form) {
                $data = [
                    'name' => $form->name,
                    'slug' => $form->slug,
                    'description' => $form->description,
                    'schema' => $form->schema,
                    'settings' => $form->settings,
                    'is_public' => $form->is_public,
                    'is_active' => $form->is_active,
                    'prevent_duplicates' => $form->prevent_duplicates,
                    'duplicate_message' => $form->duplicate_message,
                    'tags' => $form->tags,
                    'keywords' => $form->keywords,
                    'send_confirmation_email' => $form->send_confirmation_email,
                    'category_slug' => $form->category?->slug,
                ];

                if ($form->workflow) {
                    $data['workflow'] = [
                        'name' => $form->workflow->name,
                        'description' => $form->workflow->description,
                        'trigger_on' => $form->workflow->trigger_on,
                        'is_active' => $form->workflow->is_active,
                        'nodes' => $form->workflow->nodes,
                        'edges' => $form->workflow->edges,
                    ];
                }

                return $data;
            })->toArray();

            // Settings (without sensitive data)
            $backup['settings'] = [
                'branding' => Setting::getBrandingSettings(),
                // Note: Mail and Keycloak settings are excluded for security
            ];

            // Submissions (optional - can be large)
            if ($includeSubmissions) {
                $backup['submissions'] = FormSubmission::with(['form:id,slug'])->get()->map(function ($submission) {
                    return [
                        'form_slug' => $submission->form?->slug,
                        'data' => $submission->data,
                        'status' => $submission->status,
                        'response' => $submission->response,
                        'user_email' => $submission->user?->email,
                        'submitted_at' => $submission->created_at->toIso8601String(),
                    ];
                })->toArray();
            }

            // Statistics
            $backup['stats'] = [
                'categories_count' => count($backup['categories']),
                'forms_count' => count($backup['forms']),
                'email_templates_count' => count($backup['email_templates']),
                'workflows_count' => count($backup['workflows']),
                'submissions_count' => $includeSubmissions ? count($backup['submissions']) : 'not_included',
            ];

            // Audit log
            AuditService::log('backup_created', null, null, [
                'include_submissions' => $includeSubmissions,
                'stats' => $backup['stats'],
            ]);

            $filename = 'backup_' . now()->format('Y-m-d_His') . '.json';

            return response()->json($backup)
                ->header('Content-Type', 'application/json')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            // SECURITY: Log full error for debugging, but don't expose to user
            Log::error('Backup creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Chyba pri vytvarani zalohy'], 500);
        }
    }

    public function restoreBackup(Request $request)
    {
        $request->validate([
            'backup' => 'required|array',
            'restore_categories' => 'boolean',
            'restore_forms' => 'boolean',
            'restore_email_templates' => 'boolean',
            'restore_workflows' => 'boolean',
            'restore_settings' => 'boolean',
        ]);

        $backup = $request->input('backup');

        // Validate backup structure
        if (!isset($backup['backup_type']) || $backup['backup_type'] !== 'full') {
            return response()->json(['error' => 'Neplatny format zalohy'], 400);
        }

        $results = [
            'categories' => ['imported' => 0, 'updated' => 0, 'errors' => []],
            'forms' => ['imported' => 0, 'errors' => []],
            'email_templates' => ['imported' => 0, 'updated' => 0, 'errors' => []],
            'workflows' => ['imported' => 0, 'errors' => []],
            'settings' => ['restored' => false, 'errors' => []],
        ];

        DB::beginTransaction();
        try {
            // Restore Categories
            if ($request->boolean('restore_categories', true) && isset($backup['categories'])) {
                foreach ($backup['categories'] as $categoryData) {
                    try {
                        $name = $categoryData['name'];
                        if (is_string($name)) {
                            $name = ['sk' => $name, 'en' => ''];
                        }

                        $description = $categoryData['description'] ?? null;
                        if (is_string($description)) {
                            $description = ['sk' => $description, 'en' => ''];
                        }

                        $existing = FormCategory::where('slug', $categoryData['slug'])->first();

                        if ($existing) {
                            $existing->update([
                                'name' => $name,
                                'description' => $description,
                                'color' => $categoryData['color'] ?? '#A59466',
                                'icon' => $categoryData['icon'] ?? null,
                                'order' => $categoryData['order'] ?? 0,
                            ]);
                            $results['categories']['updated']++;
                        } else {
                            FormCategory::create([
                                'name' => $name,
                                'slug' => $categoryData['slug'],
                                'description' => $description,
                                'color' => $categoryData['color'] ?? '#A59466',
                                'icon' => $categoryData['icon'] ?? null,
                                'order' => $categoryData['order'] ?? 0,
                            ]);
                            $results['categories']['imported']++;
                        }
                    } catch (\Exception $e) {
                        // SECURITY: Don't expose internal error details
                        Log::warning('Category restore failed', ['slug' => $categoryData['slug'] ?? 'unknown', 'error' => $e->getMessage()]);
                        $results['categories']['errors'][] = 'Chyba pri obnove kategorie: ' . ($categoryData['slug'] ?? 'unknown');
                    }
                }
            }

            // Restore Email Templates
            if ($request->boolean('restore_email_templates', true) && isset($backup['email_templates'])) {
                foreach ($backup['email_templates'] as $templateData) {
                    try {
                        $slug = $templateData['slug'] ?? Str::slug($templateData['name']);
                        $existing = EmailTemplate::where('slug', $slug)->first();

                        if ($existing) {
                            $existing->update([
                                'name' => $templateData['name'],
                                'subject' => $templateData['subject'],
                                'body_html' => $templateData['body_html'] ?? null,
                                'body_text' => $templateData['body_text'] ?? null,
                                'variables' => $templateData['variables'] ?? [],
                                'is_active' => $templateData['is_active'] ?? true,
                                'is_default' => $templateData['is_default'] ?? false,
                            ]);
                            $results['email_templates']['updated']++;
                        } else {
                            EmailTemplate::create([
                                'name' => $templateData['name'],
                                'slug' => $slug,
                                'subject' => $templateData['subject'],
                                'body_html' => $templateData['body_html'] ?? null,
                                'body_text' => $templateData['body_text'] ?? null,
                                'variables' => $templateData['variables'] ?? [],
                                'is_active' => $templateData['is_active'] ?? true,
                                'is_default' => $templateData['is_default'] ?? false,
                            ]);
                            $results['email_templates']['imported']++;
                        }
                    } catch (\Exception $e) {
                        // SECURITY: Don't expose internal error details
                        Log::warning('Email template restore failed', ['slug' => $slug ?? 'unknown', 'error' => $e->getMessage()]);
                        $results['email_templates']['errors'][] = 'Chyba pri obnove sablony: ' . ($templateData['name'] ?? 'unknown');
                    }
                }
            }

            // Restore standalone Workflows
            if ($request->boolean('restore_workflows', true) && isset($backup['workflows'])) {
                foreach ($backup['workflows'] as $workflowData) {
                    try {
                        Workflow::create([
                            'name' => $workflowData['name'] . ' (obnovene)',
                            'description' => $workflowData['description'] ?? null,
                            'trigger_on' => $workflowData['trigger_on'] ?? 'submission',
                            'is_active' => false,
                            'nodes' => $workflowData['nodes'] ?? [],
                            'edges' => $workflowData['edges'] ?? [],
                            'current_version' => 1,
                        ]);
                        $results['workflows']['imported']++;
                    } catch (\Exception $e) {
                        // SECURITY: Don't expose internal error details
                        Log::warning('Workflow restore failed', ['name' => $workflowData['name'] ?? 'unknown', 'error' => $e->getMessage()]);
                        $results['workflows']['errors'][] = 'Chyba pri obnove workflow: ' . ($workflowData['name'] ?? 'unknown');
                    }
                }
            }

            // Restore Forms
            if ($request->boolean('restore_forms', true) && isset($backup['forms'])) {
                foreach ($backup['forms'] as $formData) {
                    try {
                        // Check if form with same slug exists
                        $existingForm = Form::where('slug', $formData['slug'])->first();
                        if ($existingForm) {
                            // Skip or create with different slug
                            $formData['slug'] = $formData['slug'] . '-restored-' . time();
                        }

                        $categoryId = null;
                        if (!empty($formData['category_slug'])) {
                            $category = FormCategory::where('slug', $formData['category_slug'])->first();
                            $categoryId = $category?->id;
                        }

                        $name = $formData['name'];
                        if (is_array($name)) {
                            $name['sk'] = ($name['sk'] ?? '') . ' (obnovene)';
                        } else {
                            $name = $name . ' (obnovene)';
                        }

                        $form = Form::create([
                            'name' => $name,
                            'slug' => $formData['slug'],
                            'description' => $formData['description'] ?? null,
                            'schema' => $formData['schema'],
                            'settings' => $formData['settings'] ?? null,
                            'is_public' => $formData['is_public'] ?? false,
                            'is_active' => false,
                            'prevent_duplicates' => $formData['prevent_duplicates'] ?? false,
                            'duplicate_message' => $formData['duplicate_message'] ?? null,
                            'tags' => $formData['tags'] ?? null,
                            'keywords' => $formData['keywords'] ?? null,
                            'send_confirmation_email' => $formData['send_confirmation_email'] ?? false,
                            'category_id' => $categoryId,
                            'created_by' => auth()->id(),
                            'current_version' => 1,
                        ]);

                        // Create workflow if present
                        if (!empty($formData['workflow'])) {
                            $workflow = Workflow::create([
                                'name' => $formData['workflow']['name'] . ' (obnovene)',
                                'description' => $formData['workflow']['description'] ?? null,
                                'form_id' => $form->id,
                                'trigger_on' => $formData['workflow']['trigger_on'] ?? 'submission',
                                'is_active' => false,
                                'nodes' => $formData['workflow']['nodes'] ?? [],
                                'edges' => $formData['workflow']['edges'] ?? [],
                                'current_version' => 1,
                            ]);
                            $form->update(['workflow_id' => $workflow->id]);
                        }

                        $results['forms']['imported']++;
                    } catch (\Exception $e) {
                        // SECURITY: Don't expose internal error details
                        Log::warning('Form restore failed', ['slug' => $formData['slug'] ?? 'unknown', 'error' => $e->getMessage()]);
                        $results['forms']['errors'][] = 'Chyba pri obnove formulara: ' . ($formData['slug'] ?? 'unknown');
                    }
                }
            }

            // Restore Settings
            if ($request->boolean('restore_settings', true) && isset($backup['settings'])) {
                try {
                    if (isset($backup['settings']['branding'])) {
                        Setting::saveBrandingSettings($backup['settings']['branding']);
                    }
                    $results['settings']['restored'] = true;
                } catch (\Exception $e) {
                    // SECURITY: Don't expose internal error details
                    Log::warning('Settings restore failed', ['error' => $e->getMessage()]);
                    $results['settings']['errors'][] = 'Chyba pri obnove nastaveni';
                }
            }

            DB::commit();

            // Audit log
            AuditService::log('backup_restored', null, null, $results);

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // SECURITY: Log full error for debugging, but don't expose to user
            Log::error('Backup restore failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Chyba pri obnove zalohy'], 500);
        }
    }

    /**
     * Update backup settings
     */
    public function updateBackupSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'boolean',
            'frequency' => 'nullable|string|in:daily,weekly,monthly',
            'time' => 'nullable|date_format:H:i', // HH:MM format
            'include_submissions' => 'boolean',
            'retention_local' => 'nullable|integer|min:1|max:100',

            // FTP
            'ftp_enabled' => 'boolean',
            'ftp_host' => 'nullable|string|max:255',
            'ftp_port' => 'nullable|integer|min:1|max:65535',
            'ftp_username' => 'nullable|string|max:255',
            'ftp_password' => 'nullable|string|max:255',
            'ftp_path' => 'nullable|string|max:500',
            'ftp_passive' => 'boolean',
            'ftp_ssl' => 'boolean',
            'ftp_retention' => 'nullable|integer|min:1|max:100',

            // S3
            's3_enabled' => 'boolean',
            's3_key' => 'nullable|string|max:255',
            's3_secret' => 'nullable|string|max:255',
            's3_region' => 'nullable|string|max:50',
            's3_bucket' => 'nullable|string|max:255',
            's3_endpoint' => 'nullable|string|max:500',
            's3_path' => 'nullable|string|max:500',
            's3_use_path_style' => 'boolean',
            's3_retention' => 'nullable|integer|min:1|max:100',
        ]);

        // Don't update masked passwords
        if ($validated['ftp_password'] === '********') {
            unset($validated['ftp_password']);
        }
        if ($validated['s3_secret'] === '********') {
            unset($validated['s3_secret']);
        }

        Setting::saveBackupSettings($validated);

        // Audit log (bez citlivych udajov)
        $auditData = $validated;
        unset($auditData['ftp_password'], $auditData['s3_secret']);
        AuditService::settingsUpdated('backup', $auditData);

        return redirect()
            ->back()
            ->with('success', 'Nastavenia zalohovania boli ulozene');
    }

    /**
     * Test FTP connection
     */
    public function testFtpConnection(Request $request)
    {
        $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'path' => 'nullable|string|max:500',
            'passive' => 'boolean',
            'ssl' => 'boolean',
        ]);

        // SECURITY: SSRF protection - validate host before connecting
        $ssrfError = $this->validateHostForSsrf($request->host);
        if ($ssrfError) {
            // Audit log for SSRF blocked attempt
            AuditService::log('ftp_test', null, null, [
                'host' => $request->host,
                'port' => $request->port,
                'status' => 'blocked',
                'reason' => 'ssrf_protection',
            ]);

            return response()->json(['success' => false, 'message' => 'SSRF ochrana: ' . $ssrfError], 400);
        }

        try {
            // Get real password if masked
            $password = $request->password;
            if ($password === '********') {
                $settings = Setting::getBackupSettings();
                $password = $settings['ftp_password'];
            }

            config(['filesystems.disks.test_ftp' => [
                'driver' => 'ftp',
                'host' => $request->host,
                'username' => $request->username,
                'password' => $password,
                'port' => $request->port,
                'root' => $request->path ?? '/',
                'passive' => $request->boolean('passive', true),
                'ssl' => $request->boolean('ssl', false),
                'timeout' => 10,
            ]]);

            // Try to list files
            Storage::disk('test_ftp')->files();

            // Audit log for successful test
            AuditService::log('ftp_test', null, null, [
                'host' => $request->host,
                'port' => $request->port,
                'username' => $request->username,
                'path' => $request->path,
                'status' => 'success',
            ]);

            return response()->json(['success' => true, 'message' => 'Pripojenie k FTP uspesne']);

        } catch (\Exception $e) {
            // Audit log for failed test
            AuditService::log('ftp_test', null, null, [
                'host' => $request->host,
                'port' => $request->port,
                'username' => $request->username,
                'status' => 'failed',
            ]);

            return response()->json(['success' => false, 'message' => 'Chyba pripojenia'], 400);
        }
    }

    /**
     * Test S3 connection
     */
    public function testS3Connection(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:255',
            'secret' => 'required|string|max:255',
            'region' => 'required|string|max:50',
            'bucket' => 'required|string|max:255|regex:/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/',
            'endpoint' => 'nullable|url|max:500',
            'path' => 'nullable|string|max:500',
            'use_path_style' => 'boolean',
        ]);

        // SECURITY: SSRF protection - validate custom endpoint if provided
        if ($request->filled('endpoint')) {
            $ssrfError = $this->validateUrlForSsrf($request->endpoint);
            if ($ssrfError) {
                // Audit log for SSRF blocked attempt
                AuditService::log('s3_test', null, null, [
                    'endpoint' => $request->endpoint,
                    'bucket' => $request->bucket,
                    'region' => $request->region,
                    'status' => 'blocked',
                    'reason' => 'ssrf_protection',
                ]);

                return response()->json(['success' => false, 'message' => 'SSRF ochrana: ' . $ssrfError], 400);
            }
        }

        try {
            // Get real secret if masked
            $secret = $request->secret;
            if ($secret === '********') {
                $settings = Setting::getBackupSettings();
                $secret = $settings['s3_secret'];
            }

            config(['filesystems.disks.test_s3' => [
                'driver' => 's3',
                'key' => $request->key,
                'secret' => $secret,
                'region' => $request->region,
                'bucket' => $request->bucket,
                'endpoint' => $request->endpoint ?: null,
                'use_path_style_endpoint' => $request->boolean('use_path_style', false),
            ]]);

            // Try to list files
            $path = trim($request->path ?? '', '/');
            Storage::disk('test_s3')->files($path);

            // Audit log for successful test
            AuditService::log('s3_test', null, null, [
                'endpoint' => $request->endpoint,
                'bucket' => $request->bucket,
                'region' => $request->region,
                'path' => $request->path,
                'status' => 'success',
            ]);

            return response()->json(['success' => true, 'message' => 'Pripojenie k S3 uspesne']);

        } catch (\Exception $e) {
            // Audit log for failed test
            AuditService::log('s3_test', null, null, [
                'endpoint' => $request->endpoint,
                'bucket' => $request->bucket,
                'region' => $request->region,
                'status' => 'failed',
            ]);

            return response()->json(['success' => false, 'message' => 'Chyba pripojenia'], 400);
        }
    }

    /**
     * Run backup now
     */
    public function runBackupNow(Request $request)
    {
        $request->validate([
            'destinations' => 'required|array',
            'destinations.*' => 'in:local,ftp,s3',
            'include_submissions' => 'boolean',
        ]);

        try {
            $backupService = new BackupService();
            $includeSubmissions = $request->boolean('include_submissions', false);
            $backupData = $backupService->createBackupData($includeSubmissions);

            $results = [];
            $errors = [];
            $settings = Setting::getBackupSettings();

            foreach ($request->destinations as $dest) {
                try {
                    if ($dest === 'local') {
                        $path = $backupService->saveToLocal($backupData);
                        $results['local'] = $path;
                    } elseif ($dest === 'ftp' && $settings['ftp_enabled']) {
                        $ftpConfig = [
                            'host' => $settings['ftp_host'],
                            'username' => $settings['ftp_username'],
                            'password' => $settings['ftp_password'],
                            'port' => $settings['ftp_port'],
                            'path' => $settings['ftp_path'],
                            'passive' => $settings['ftp_passive'],
                            'ssl' => $settings['ftp_ssl'],
                            'retention' => $settings['ftp_retention'],
                        ];
                        $backupService->uploadToFtp($backupData, $ftpConfig);
                        $results['ftp'] = true;
                    } elseif ($dest === 's3' && $settings['s3_enabled']) {
                        $s3Config = [
                            'key' => $settings['s3_key'],
                            'secret' => $settings['s3_secret'],
                            'region' => $settings['s3_region'],
                            'bucket' => $settings['s3_bucket'],
                            'endpoint' => $settings['s3_endpoint'],
                            'path' => $settings['s3_path'],
                            'use_path_style_endpoint' => $settings['s3_use_path_style'],
                            'retention' => $settings['s3_retention'],
                        ];
                        $backupService->uploadToS3($backupData, $s3Config);
                        $results['s3'] = true;
                    }
                } catch (\Exception $e) {
                    // SECURITY: Don't expose internal error details to user
                    // The BackupService already logs the full error
                    $errors[$dest] = 'Backup na ' . $dest . ' zlyhal';
                }
            }

            AuditService::log('manual_backup', null, null, [
                'destinations' => $request->destinations,
                'include_submissions' => $includeSubmissions,
                'results' => $results,
                'errors' => array_keys($errors), // Only log which destinations failed
            ]);

            if (empty($errors)) {
                return response()->json(['success' => true, 'results' => $results]);
            }

            return response()->json([
                'success' => false,
                'results' => $results,
                'errors' => $errors,
            ], 207);

        } catch (\Exception $e) {
            // SECURITY: Log full error for debugging, but don't expose to user
            Log::error('Manual backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Chyba pri zalohovani'], 500);
        }
    }

    /**
     * Get local backups list
     */
    public function getLocalBackups()
    {
        $backupService = new BackupService();
        return response()->json($backupService->getLocalBackups());
    }

    /**
     * Validate backup filename to prevent path traversal (defense in depth)
     */
    private function validateBackupFilename(string $filename): string
    {
        // Remove any path components
        $sanitized = basename($filename);

        // Validate format: only safe characters and must be .json
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.json$/', $sanitized)) {
            abort(400, 'Neplatny nazov suboru');
        }

        // Block path traversal attempts
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(400, 'Neplatny nazov suboru');
        }

        return $sanitized;
    }

    /**
     * Download a local backup
     */
    public function downloadLocalBackup(string $filename)
    {
        // Validate filename first (path traversal protection)
        $safeFilename = $this->validateBackupFilename($filename);

        $backupService = new BackupService();
        $content = $backupService->getLocalBackupContent($safeFilename);

        if (!$content) {
            abort(404, 'Backup nenajdeny');
        }

        // Audit log for backup download (important for data exfiltration monitoring)
        AuditService::log('backup_downloaded', null, null, [
            'filename' => $safeFilename,
            'size_bytes' => strlen($content),
        ]);

        // Use sanitized filename in response header
        return response($content)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="' . $safeFilename . '"');
    }

    /**
     * Delete a local backup
     */
    public function deleteLocalBackup(string $filename)
    {
        // Validate filename first (path traversal protection)
        $safeFilename = $this->validateBackupFilename($filename);

        $backupService = new BackupService();

        if ($backupService->deleteLocalBackup($safeFilename)) {
            AuditService::log('backup_deleted', null, null, ['filename' => $safeFilename]);
            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Nepodarilo sa zmazat zalohu'], 500);
    }
}
