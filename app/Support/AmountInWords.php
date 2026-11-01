<?php

namespace App\Support;

final class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /** "105387.00" => "Rupees One Lakh Five Thousand Three Hundred Eighty Seven Only" (Indian numbering). */
    public static function rupees(string|int $amount): string
    {
        $paise = Money::toPaise((string) $amount);
        $rupees = intdiv($paise, 100);
        $p = $paise % 100;

        $words = $rupees === 0 ? 'Zero' : self::number($rupees);
        if ($p > 0) {
            $words .= ' and '.self::twoDigits($p).' Paise';
        }

        return 'Rupees '.$words.' Only';
    }

    private static function number(int $n): string
    {
        $parts = [];
        foreach ([[10_000_000, 'Crore'], [100_000, 'Lakh'], [1_000, 'Thousand']] as [$unit, $name]) {
            if ($n >= $unit) {
                $chunk = intdiv($n, $unit);
                $parts[] = ($chunk >= 100 ? self::number($chunk) : self::twoDigits($chunk)).' '.$name;
                $n %= $unit;
            }
        }
        if ($n >= 100) {
            $parts[] = self::ONES[intdiv($n, 100)].' Hundred';
            $n %= 100;
        }
        if ($n > 0) {
            $parts[] = self::twoDigits($n);
        }

        return implode(' ', $parts);
    }

    private static function twoDigits(int $n): string
    {
        return $n < 20 ? self::ONES[$n] : trim(self::TENS[intdiv($n, 10)].' '.self::ONES[$n % 10]);
    }
}
