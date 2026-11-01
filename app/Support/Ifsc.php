<?php

namespace App\Support;

final class Ifsc
{
    /** 4 letters (bank), a zero, then 6 letters/digits (branch). */
    public static function isValid(?string $ifsc): bool
    {
        return $ifsc !== null && (bool) preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper(trim($ifsc)));
    }

    public static function normalize(?string $ifsc): ?string
    {
        $v = $ifsc === null ? '' : strtoupper(preg_replace('/\s+/', '', $ifsc));

        return $v === '' ? null : $v;
    }
}
