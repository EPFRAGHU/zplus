<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Support\Activity\Activity;
use Illuminate\Console\Command;

/**
 * Called by the backup scripts after each run (owner, 26 Nov 2026), e.g. on the server:
 *   php artisan backups:record --location=primary --server=srv1962767 --file=db-2026-11-27.sql.gz --size=812345
 * and after copying it to the laptop: --location=secondary --server=RAGHU-DELL. A failed run: --status=failed --note="…".
 */
class RecordBackup extends Command
{
    protected $signature = 'backups:record {--location=primary : primary or secondary} {--server= : name of the machine holding the backup}
        {--status=ok : ok or failed} {--file= : backup file name} {--size= : size in bytes} {--note= : short note, e.g. why it failed}';

    protected $description = 'Record a database backup so the developer sees it on the dashboard';

    public function handle(): int
    {
        $location = (string) $this->option('location');
        $status = (string) $this->option('status');
        $server = trim((string) $this->option('server'));
        if (! array_key_exists($location, BackupRun::LOCATIONS) || ! in_array($status, ['ok', 'failed'], true) || $server === '') {
            $this->error('Use --location=primary|secondary, --status=ok|failed and --server=<name>.');

            return self::FAILURE;
        }
        $size = $this->option('size');
        $run = BackupRun::query()->create([
            'location' => $location, 'server' => mb_substr($server, 0, 80), 'status' => $status,
            'file' => $this->option('file') ? mb_substr(basename((string) $this->option('file')), 0, 190) : null,
            'size_bytes' => is_numeric($size) ? (int) $size : null,
            'note' => $this->option('note') ? mb_substr((string) $this->option('note'), 0, 255) : null,
            'finished_at' => now(),
        ]);
        Activity::log($status === 'ok' ? 'created' : 'status', null, [], "Backup {$status}: {$location} on {$run->server}".($run->file ? " ({$run->file}, {$run->sizeLabel()})" : '').($run->note ? " – {$run->note}" : ''), section: 'Backups');
        $this->info("Recorded {$location} backup on {$run->server}: {$status}.");

        return self::SUCCESS;
    }
}
