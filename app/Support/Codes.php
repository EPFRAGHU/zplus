<?php

namespace App\Support;

/**
 * Codes typed by users (short project name, client and letter codes, agency code). Owner, 22 Nov 2026: up to 20 characters,
 * with spaces and common punctuation; quotes, < > and \ stay out because codes go into bill numbers, file names and PDFs.
 */
final class Codes
{
    public const PATTERN = 'regex:/^[A-Za-z0-9 \/\-._()&+#,]+$/';

    /** Short project name: as above but without "/", because it goes inside sub-bill numbers SW/001/26-27(CODE) (owner, 22 Nov 2026). */
    public const PROJECT_PATTERN = 'regex:/^[A-Za-z0-9 \-._()&+#,]+$/';

    public const PROJECT_MESSAGE = 'Use letters, numbers, spaces and - . _ ( ) & + # , only (up to 20); no "/", because the short name goes inside the sub-bill number.';

    public const MAX = 20;

    public const MESSAGE = 'Use letters, numbers, spaces and / - . _ ( ) & + # , only (up to 20).';

    /** Safe text for a download file name: "/" in a code would break it. */
    public static function fileSafe(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9\-_.]+/', '-', $value), '-');
    }
}
