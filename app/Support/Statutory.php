<?php

namespace App\Support;

/** EPF and ESIC rules shared by bills (employer share) and the salary payout sheet (employee share). */
final class Statutory
{
    public const EMPLOYEE_EPF_PERCENT = '12';
    public const EMPLOYEE_ESIC_PERCENT = '0.75';
    public const EPF_WAGE_CEILING = 15000;   // EPF is worked out on wages up to this amount a month
    public const ESIC_WAGE_LIMIT = 21000;    // ESIC covers staff earning up to this amount a month

    /** EPF base in paise: earned wages up to the ceiling. */
    public static function epfBase(int $earnedPaise): int
    {
        return min($earnedPaise, self::EPF_WAGE_CEILING * 100);
    }
}
