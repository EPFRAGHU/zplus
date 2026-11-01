<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact money arithmetic in integer paise. Never use floats for bill amounts.
 */
final class Money
{
    public static function toPaise(string|int|float $amount): int
    {
        $s = trim((string) $amount);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $s, $m)) {
            throw new InvalidArgumentException("Not a money amount: {$s}");
        }
        $paise = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$paise : $paise;
    }

    public static function format(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $paise = abs($paise);

        return $sign.intdiv($paise, 100).'.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }

    /** round(a * b / c), half away from zero, using integers only. */
    public static function mulDiv(int $a, int $b, int $c): int
    {
        $num = $a * $b;
        $q = intdiv($num, $c);
        $r = $num % $c;
        if (abs($r) * 2 >= abs($c)) {
            $q += ($num < 0) === ($c < 0) ? 1 : -1;
        }

        return $q;
    }

    /** Percentage of an amount, percent given as a string with up to 4 decimals (e.g. "3.85"). */
    public static function percent(int $paise, string $percent): int
    {
        return self::mulDiv($paise, self::scaled($percent, 4), 100 * 10_000);
    }

    /** Decimal string to integer scaled by 10^$places ("3.85", 4 => 38500). */
    public static function scaled(string $value, int $places): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', trim($value), $m) || strlen($m[2] ?? '') > $places) {
            throw new InvalidArgumentException("Bad decimal: {$value}");
        }

        return ((int) $m[1]) * (10 ** $places) + (int) str_pad($m[2] ?? '0', $places, '0');
    }

    public static function roundToRupee(int $paise): int
    {
        return self::mulDiv($paise, 1, 100) * 100;
    }
}
