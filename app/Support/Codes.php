<?php

namespace App\Support;

/** Codes typed by users (bill code, letter code, project code, agency code) may contain letters, digits, "/" and "-". */
final class Codes
{
    public const PATTERN = 'regex:/^[A-Za-z0-9\/\-]+$/';

    public const MESSAGE = 'Use only letters, numbers, "/" and "-" (no spaces).';

    /** Safe text for a download file name: "/" in a code would break it. */
    public static function fileSafe(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9\-_.]+/', '-', $value), '-');
    }
}
