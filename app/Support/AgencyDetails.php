<?php

namespace App\Support;

use App\Models\Agency;

/** Registration numbers printed on bills, read from the agency's "Registrations" text (Agencies → Edit). */
final class AgencyDetails
{
    public static function epf(Agency $agency): ?string
    {
        return self::find($agency, 'EPF');
    }

    public static function esic(Agency $agency): ?string
    {
        return self::find($agency, 'ESIC');
    }

    /** Contract Labour (Regulation & Abolition) licence, e.g. "K-438/2013" from a line "CL&RA No: K-438/2013". */
    public static function clra(Agency $agency): ?string
    {
        return self::find($agency, 'CL\s*(?:&|and)?\s*R\s*&?\s*A|CL\s+License');
    }

    /** One-line address: "Plot No- 1468/5398/7693, Bhaktamadhu Nagar, Gandamunda, Bhubaneswar-751030". */
    public static function address(Agency $agency): string
    {
        return trim((string) preg_replace('/,?\s*\n\s*/', ', ', (string) $agency->address));
    }

    private static function find(Agency $agency, string $label): ?string
    {
        foreach (preg_split('/\R|•|,\s+(?=[A-Z])/u', (string) $agency->registrations) as $line) {
            if (preg_match('/^\s*(?:'.$label.')\b[^:\-]*?(?:No\.?|Registration|Regd\.?)?\s*[:\-]\s*(.+?)\s*$/i', $line, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
