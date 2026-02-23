<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Console\Command;

class CreateBackup extends Command
{
    protected $signature = 'backup:create
                            {--include-submissions : Include form submissions in backup}
                            {--local : Save backup locally}
                            {--ftp : Upload backup to FTP}
                            {--s3 : Upload backup to S3}
                            {--all : Upload to all configured destinations}';

    protected $description = 'Create a backup of forms, categories, workflows, and settings';

    public function handle(BackupService $backupService): int
    {
        $this->info('Creating backup...');

        $includeSubmissions = $this->option('include-submissions');
        $backupData = $backupService->createBackupData($includeSubmissions);

        $this->info('Backup data created:');
        $this->line('  - Categories: ' . $backupData['stats']['categories_count']);
        $this->line('  - Forms: ' . $backupData['stats']['forms_count']);
        $this->line('  - Email templates: ' . $backupData['stats']['email_templates_count']);
        $this->line('  - Workflows: ' . $backupData['stats']['workflows_count']);
        if ($includeSubmissions) {
            $this->line('  - Submissions: ' . $backupData['stats']['submissions_count']);
        }

        $uploadAll = $this->option('all');
        $destinations = [];
        $errors = [];

        // Get backup settings
        $backupSettings = Setting::getBackupSettings();

        // Local backup
        if ($this->option('local') || $uploadAll || (!$this->option('ftp') && !$this->option('s3'))) {
            try {
                $path = $backupService->saveToLocal($backupData);
                $this->info("Backup saved locally: {$path}");
                $destinations[] = 'local';
            } catch (\Exception $e) {
                $this->error("Local backup failed: {$e->getMessage()}");
                $errors[] = 'local: ' . $e->getMessage();
            }
        }

        // FTP backup
        if ($this->option('ftp') || $uploadAll) {
            if (!empty($backupSettings['ftp_enabled']) && !empty($backupSettings['ftp_host'])) {
                try {
                    $ftpConfig = [
                        'host' => $backupSettings['ftp_host'],
                        'username' => $backupSettings['ftp_username'],
                        'password' => $backupSettings['ftp_password'],
                        'port' => $backupSettings['ftp_port'] ?? 21,
                        'path' => $backupSettings['ftp_path'] ?? '/',
                        'passive' => $backupSettings['ftp_passive'] ?? true,
                        'ssl' => $backupSettings['ftp_ssl'] ?? false,
                        'retention' => $backupSettings['ftp_retention'] ?? 10,
                    ];

                    $backupService->uploadToFtp($backupData, $ftpConfig);
                    $this->info('Backup uploaded to FTP successfully');
                    $destinations[] = 'ftp';
                } catch (\Exception $e) {
                    $this->error("FTP backup failed: {$e->getMessage()}");
                    $errors[] = 'ftp: ' . $e->getMessage();
                }
            } else {
                $this->warn('FTP backup skipped - not configured');
            }
        }

        // S3 backup
        if ($this->option('s3') || $uploadAll) {
            if (!empty($backupSettings['s3_enabled']) && !empty($backupSettings['s3_bucket'])) {
                try {
                    $s3Config = [
                        'key' => $backupSettings['s3_key'],
                        'secret' => $backupSettings['s3_secret'],
                        'region' => $backupSettings['s3_region'] ?? 'eu-central-1',
                        'bucket' => $backupSettings['s3_bucket'],
                        'endpoint' => $backupSettings['s3_endpoint'] ?? null,
                        'path' => $backupSettings['s3_path'] ?? '',
                        'use_path_style_endpoint' => $backupSettings['s3_use_path_style'] ?? false,
                        'retention' => $backupSettings['s3_retention'] ?? 10,
                    ];

                    $backupService->uploadToS3($backupData, $s3Config);
                    $this->info('Backup uploaded to S3 successfully');
                    $destinations[] = 's3';
                } catch (\Exception $e) {
                    $this->error("S3 backup failed: {$e->getMessage()}");
                    $errors[] = 's3: ' . $e->getMessage();
                }
            } else {
                $this->warn('S3 backup skipped - not configured');
            }
        }

        // Audit log
        AuditService::log('scheduled_backup', null, null, [
            'include_submissions' => $includeSubmissions,
            'destinations' => $destinations,
            'errors' => $errors,
            'stats' => $backupData['stats'],
        ]);

        if (empty($errors)) {
            $this->info('Backup completed successfully!');
            return Command::SUCCESS;
        }

        $this->warn('Backup completed with some errors');
        return Command::FAILURE;
    }
}
