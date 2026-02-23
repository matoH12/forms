<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormSubmission;
use App\Models\Setting;
use App\Models\Workflow;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BackupService
{
    /**
     * Create a backup array with all data
     */
    public function createBackupData(bool $includeSubmissions = false): array
    {
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

        // Workflows (standalone)
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

        // Forms with workflows
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

        // Settings
        $backup['settings'] = [
            'branding' => Setting::getBrandingSettings(),
        ];

        // Submissions (optional)
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

        // Stats
        $backup['stats'] = [
            'categories_count' => count($backup['categories']),
            'forms_count' => count($backup['forms']),
            'email_templates_count' => count($backup['email_templates']),
            'workflows_count' => count($backup['workflows']),
            'submissions_count' => $includeSubmissions ? count($backup['submissions']) : 'not_included',
        ];

        return $backup;
    }

    /**
     * Save backup to local storage
     */
    public function saveToLocal(array $backupData, string $filename = null): string
    {
        $filename = $filename ?? 'backup_' . now()->format('Y-m-d_His') . '.json';
        $path = 'backups/' . $filename;

        Storage::disk('local')->put($path, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Cleanup old backups (keep last 10)
        $this->cleanupOldBackups('local', 10);

        return $path;
    }

    /**
     * Upload backup to FTP
     */
    public function uploadToFtp(array $backupData, array $ftpConfig): bool
    {
        $filename = 'backup_' . now()->format('Y-m-d_His') . '.json';

        try {
            $content = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Configure FTP disk dynamically
            config(['filesystems.disks.backup_ftp' => [
                'driver' => 'ftp',
                'host' => $ftpConfig['host'],
                'username' => $ftpConfig['username'],
                'password' => $ftpConfig['password'],
                'port' => $ftpConfig['port'] ?? 21,
                'root' => $ftpConfig['path'] ?? '/',
                'passive' => $ftpConfig['passive'] ?? true,
                'ssl' => $ftpConfig['ssl'] ?? false,
                'timeout' => 30,
            ]]);

            Storage::disk('backup_ftp')->put($filename, $content);

            // Cleanup old backups on FTP (keep last 10)
            $this->cleanupOldBackups('backup_ftp', $ftpConfig['retention'] ?? 10);

            Log::info('Backup uploaded to FTP successfully', ['filename' => $filename]);
            return true;

        } catch (\Exception $e) {
            // SECURITY: Log only safe information, never credentials
            // Error messages might contain connection strings with passwords
            Log::error('FTP backup upload failed', [
                'filename' => $filename,
                'host' => $ftpConfig['host'] ?? 'unknown',
                'port' => $ftpConfig['port'] ?? 21,
                // Don't log: username, password, full error message (may contain credentials)
                'error_type' => get_class($e),
            ]);

            // Clear the config to prevent credential leakage in subsequent errors
            config(['filesystems.disks.backup_ftp' => null]);

            // Throw generic exception without potentially sensitive details
            throw new \RuntimeException('FTP backup upload failed');
        } finally {
            // Always clear credentials from runtime config
            config(['filesystems.disks.backup_ftp' => null]);
        }
    }

    /**
     * Upload backup to S3
     */
    public function uploadToS3(array $backupData, array $s3Config): bool
    {
        $filename = 'backup_' . now()->format('Y-m-d_His') . '.json';
        $path = trim($s3Config['path'] ?? '', '/');
        $fullPath = $path ? $path . '/' . $filename : $filename;

        try {
            $content = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Configure S3 disk dynamically
            config(['filesystems.disks.backup_s3' => [
                'driver' => 's3',
                'key' => $s3Config['key'],
                'secret' => $s3Config['secret'],
                'region' => $s3Config['region'] ?? 'eu-central-1',
                'bucket' => $s3Config['bucket'],
                'endpoint' => $s3Config['endpoint'] ?? null,
                'use_path_style_endpoint' => $s3Config['use_path_style_endpoint'] ?? false,
            ]]);

            Storage::disk('backup_s3')->put($fullPath, $content);

            // Cleanup old backups on S3 (keep last N)
            $this->cleanupOldBackupsS3('backup_s3', $path, $s3Config['retention'] ?? 10);

            Log::info('Backup uploaded to S3 successfully', ['filename' => $fullPath]);
            return true;

        } catch (\Exception $e) {
            // SECURITY: Log only safe information, never credentials
            // Error messages might contain access keys or secrets
            Log::error('S3 backup upload failed', [
                'filename' => $fullPath,
                'bucket' => $s3Config['bucket'] ?? 'unknown',
                'region' => $s3Config['region'] ?? 'unknown',
                // Don't log: key, secret, full error message (may contain credentials)
                'error_type' => get_class($e),
            ]);

            // Clear the config to prevent credential leakage in subsequent errors
            config(['filesystems.disks.backup_s3' => null]);

            // Throw generic exception without potentially sensitive details
            throw new \RuntimeException('S3 backup upload failed');
        } finally {
            // Always clear credentials from runtime config
            config(['filesystems.disks.backup_s3' => null]);
        }
    }

    /**
     * Clean up old backups, keeping only the last N
     */
    protected function cleanupOldBackups(string $disk, int $keep): void
    {
        try {
            $files = Storage::disk($disk)->files('backups');

            // Filter only backup JSON files
            $backupFiles = array_filter($files, function ($file) {
                return preg_match('/^backups\/backup_.*\.json$/', $file);
            });

            // Sort by name (which includes date)
            rsort($backupFiles);

            // Delete files beyond retention limit
            $filesToDelete = array_slice($backupFiles, $keep);
            foreach ($filesToDelete as $file) {
                Storage::disk($disk)->delete($file);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to cleanup old backups', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Clean up old S3 backups
     */
    protected function cleanupOldBackupsS3(string $disk, string $path, int $keep): void
    {
        try {
            $files = Storage::disk($disk)->files($path);

            $backupFiles = array_filter($files, function ($file) {
                return preg_match('/backup_.*\.json$/', $file);
            });

            rsort($backupFiles);

            $filesToDelete = array_slice($backupFiles, $keep);
            foreach ($filesToDelete as $file) {
                Storage::disk($disk)->delete($file);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to cleanup old S3 backups', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get list of local backups
     */
    public function getLocalBackups(): array
    {
        try {
            $files = Storage::disk('local')->files('backups');

            $backups = [];
            foreach ($files as $file) {
                if (preg_match('/^backups\/backup_.*\.json$/', $file)) {
                    $backups[] = [
                        'filename' => basename($file),
                        'path' => $file,
                        'size' => Storage::disk('local')->size($file),
                        'created_at' => date('Y-m-d H:i:s', Storage::disk('local')->lastModified($file)),
                    ];
                }
            }

            // Sort by date descending
            usort($backups, function ($a, $b) {
                return strcmp($b['created_at'], $a['created_at']);
            });

            return $backups;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Sanitize and validate backup filename to prevent path traversal attacks
     *
     * @throws \InvalidArgumentException if filename is invalid
     */
    private function sanitizeBackupFilename(string $filename): string
    {
        // Remove any directory components (path traversal protection)
        $sanitized = basename($filename);

        // Check for empty filename after sanitization
        if (empty($sanitized)) {
            throw new \InvalidArgumentException('Invalid backup filename');
        }

        // Only allow alphanumeric, dash, underscore, dot
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $sanitized)) {
            throw new \InvalidArgumentException('Invalid characters in backup filename');
        }

        // Must end with .json (our backup format)
        if (!str_ends_with($sanitized, '.json')) {
            throw new \InvalidArgumentException('Invalid backup file extension');
        }

        // Double-check: ensure the sanitized path stays within backups directory
        $fullPath = Storage::disk('local')->path('backups/' . $sanitized);
        $backupsDir = Storage::disk('local')->path('backups');

        if (!str_starts_with(realpath(dirname($fullPath)) ?: $fullPath, $backupsDir)) {
            throw new \InvalidArgumentException('Invalid backup path');
        }

        return $sanitized;
    }

    /**
     * Delete a local backup
     */
    public function deleteLocalBackup(string $filename): bool
    {
        try {
            $sanitized = $this->sanitizeBackupFilename($filename);
            $path = 'backups/' . $sanitized;
            return Storage::disk('local')->delete($path);
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Download a local backup
     */
    public function getLocalBackupContent(string $filename): ?string
    {
        try {
            $sanitized = $this->sanitizeBackupFilename($filename);
            $path = 'backups/' . $sanitized;

            if (Storage::disk('local')->exists($path)) {
                return Storage::disk('local')->get($path);
            }
        } catch (\InvalidArgumentException $e) {
            // Invalid filename, return null
        }

        return null;
    }
}
