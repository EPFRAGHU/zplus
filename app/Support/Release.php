<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** The app's version history from config/releases.php (newest first). */
final class Release
{
    /** @return array<int, array{version: string, date: CarbonImmutable, title: string, type: string, notes: array<int, string>}> */
    public static function all(): array
    {
        return array_map(fn (array $r) => [...$r, 'date' => CarbonImmutable::parse($r['date'])], config('releases.releases', []));
    }

    public static function current(): array
    {
        return self::all()[0];
    }

    public static function version(): string
    {
        return self::current()['version'];
    }

    public static function started(): CarbonImmutable
    {
        return CarbonImmutable::parse(config('releases.started'));
    }
}
