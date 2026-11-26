<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One database backup reported by a backup script (owner, 26 Nov 2026). Not agency data: the backup covers the
 * whole app, and only the developer sees it.
 */
#[Fillable(['location', 'server', 'status', 'file', 'size_bytes', 'note', 'finished_at'])]
class BackupRun extends Model
{
    public const LOCATIONS = ['primary' => 'Primary (on the server)', 'secondary' => 'Secondary (off-server copy)'];

    /** A backup older than this many hours is late (amber); none at all, or a failure, is red. */
    public const FRESH_HOURS = 26;

    protected function casts(): array
    {
        return ['finished_at' => 'datetime', 'size_bytes' => 'integer'];
    }

    /** Latest report for a location. */
    public static function latestFor(string $location): ?self
    {
        return self::query()->where('location', $location)->latest('finished_at')->latest('id')->first();
    }

    /** Latest successful backup for a location. */
    public static function lastGood(string $location): ?self
    {
        return self::query()->where('location', $location)->where('status', 'ok')->latest('finished_at')->latest('id')->first();
    }

    /** green | amber | red for a location. */
    public static function health(string $location): string
    {
        $latest = self::latestFor($location);
        if (! $latest || $latest->status !== 'ok') {
            return 'red';
        }

        return $latest->finished_at->greaterThan(CarbonImmutable::now()->subHours(self::FRESH_HOURS)) ? 'green' : 'amber';
    }

    public function sizeLabel(): string
    {
        if ($this->size_bytes === null) {
            return '—';
        }

        return $this->size_bytes >= 1048576 ? number_format($this->size_bytes / 1048576, 1).' MB' : number_format($this->size_bytes / 1024, 0).' KB';
    }
}
