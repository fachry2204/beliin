<?php

namespace App\Console\Commands;

use App\Models\CompanySetting;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class RunAutomaticBackup extends Command
{
    protected $signature = 'backup:run {--type=} {--automatic}';

    protected $description = 'Membuat backup aplikasi atau database';

    public function handle(BackupService $backups): int
    {
        $setting = CompanySetting::first();
        if ($this->option('automatic') && (! $setting?->backup_auto_enabled || ! $this->isDue($setting))) {
            return self::SUCCESS;
        }

        $type = $this->option('type') ?: ($setting?->backup_auto_type ?? 'database');
        try {
            $automatic = (bool) $this->option('automatic');
            $backup = $backups->create($type, $automatic);
            if ($automatic) {
                $backups->prune((int) ($setting?->backup_retention_count ?? 7));
                $setting?->forceFill(['backup_last_run_at' => now(), 'backup_last_error' => null])->save();
            }
            $this->info("Backup berhasil: {$backup['filename']}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($this->option('automatic')) {
                $setting?->forceFill(['backup_last_error' => $exception->getMessage()])->save();
            }
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function isDue(CompanySetting $setting): bool
    {
        $now = now();
        if ($now->format('H:i') < $setting->backup_auto_time) {
            return false;
        }
        $last = $setting->backup_last_run_at;

        return match ($setting->backup_auto_frequency) {
            'weekly' => ! $last || $last->lte($now->copy()->subWeek()),
            'monthly' => ! $last || $last->lte($now->copy()->subMonth()),
            default => ! $last || ! $last->isSameDay($now),
        };
    }
}
