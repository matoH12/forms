<?php

use App\Models\ApprovalRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();

// SECURITY: Clean up expired pending approval tokens daily at 3:00 AM
// Prevents token enumeration and reduces database bloat
Schedule::call(function () {
    $deleted = ApprovalRequest::cleanupExpired();
    if ($deleted > 0) {
        Log::info("Cleaned up {$deleted} expired approval tokens");
    }
})->name('cleanup-expired-approvals')->dailyAt('03:00')->withoutOverlapping();

// Scheduled backups - runs every minute to check if backup should be executed
Schedule::call(function () {
    $settings = Setting::getBackupSettings();

    if (!$settings['enabled']) {
        return;
    }

    // Parse scheduled time (HH:MM format)
    $scheduledTime = $settings['time'] ?? '02:00';
    [$scheduledHour, $scheduledMinute] = array_map('intval', explode(':', $scheduledTime));

    $now = now();
    $frequency = $settings['frequency'] ?? 'daily';

    // Check if we should run based on frequency and time
    $shouldRun = match ($frequency) {
        'daily' => $now->hour === $scheduledHour && $now->minute === $scheduledMinute,
        'weekly' => $now->dayOfWeek === 0 && $now->hour === $scheduledHour && $now->minute === $scheduledMinute, // Sunday
        'monthly' => $now->day === 1 && $now->hour === $scheduledHour && $now->minute === $scheduledMinute, // 1st day
        default => false,
    };

    if (!$shouldRun) {
        return;
    }

    Log::info('Scheduled backup starting', [
        'frequency' => $frequency,
        'time' => $scheduledTime,
        'include_submissions' => $settings['include_submissions'],
    ]);

    $options = ['--all'];
    if ($settings['include_submissions']) {
        $options[] = '--include-submissions';
    }

    \Artisan::call('backup:create', $options);

    Log::info('Scheduled backup completed');
})->name('scheduled-backup')->everyMinute()->withoutOverlapping();
