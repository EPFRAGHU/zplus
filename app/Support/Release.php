<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** The app's version history from config/releases.php (newest first). */
final class Release
{
    /** @return array<int, array{version: string, date: CarbonImmutable, title: string, type: string, notes: array<int, string>}> */
    public static function all(): array
    {
        return array_map(fn (array $r) => [...$r, 'date' => self::parse($r['date'])], config('releases.releases', []));
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
        return self::parse(config('releases.started'));
    }

    /** Release times are written in IST and shown in IST, whatever the server's timezone. */
    private static function parse(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, config('releases.timezone', 'Asia/Kolkata'));
    }
}
