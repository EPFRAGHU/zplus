<?php

namespace App\Services\Attendance;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Codes used on the PI's Monthly Attendance and Working Certificate.
 */
final class AttendanceCodes
{
    public const LABELS = [
        'P' => 'Present',
        'CL' => 'Casual leave',
        'EL' => 'Earned leave',
        'RH' => 'Restricted holiday',
        'CH' => 'Compensatory leave / holiday',
        'T' => 'Tour / training',
        'H' => 'Holiday',
        'SAT' => 'Saturday',
        'SUN' => 'Sunday',
        'HD' => 'Half day',
        'A' => 'Absent (leave without pay)',
        'L' => 'Leave without pay / leave exhausted',
    ];

    /** Codes that count as a full paid day. HD counts half. A and L are unpaid. */
    private const PAID = ['P', 'CL', 'EL', 'RH', 'CH', 'T', 'H', 'SAT', 'SUN'];

    /**
     * @param  int[]  $holidays  day numbers that are holidays
     * @return array<int,string> day => code
     */
    public static function defaultMonth(CarbonImmutable $month, array $holidays = [], bool $saturdayOff = true): array
    {
        $codes = [];
        $start = $month->startOfMonth();
        for ($d = 1; $d <= $start->daysInMonth; $d++) {
            $date = $start->setDay($d);
            $codes[$d] = match (true) {
                in_array($d, $holidays, true) => 'H',
                $date->isSunday() => 'SUN',
                $saturdayOff && $date->isSaturday() => 'SAT',
                default => 'P',
            };
        }

        return $codes;
    }

    /** @param array<int,string> $codes  returns e.g. "29.5" */
    public static function paidDays(array $codes): string
    {
        $halves = 0;
        foreach ($codes as $code) {
            $code = strtoupper((string) $code);
            if (! array_key_exists($code, self::LABELS)) {
                throw new InvalidArgumentException("Unknown attendance code: {$code}");
            }
            $halves += in_array($code, self::PAID, true) ? 2 : ($code === 'HD' ? 1 : 0);
        }

        return intdiv($halves, 2).'.'.($halves % 2 ? '5' : '0');
    }
}
