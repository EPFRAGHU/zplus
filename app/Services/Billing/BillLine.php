<?php

namespace App\Services\Billing;

final class BillLine
{
    public string $amount = '0.00';
    public string $epfAmount = '0.00';    // employer EPF for this person
    public string $esicAmount = '0.00';   // employer ESIC for this person

    private function __construct(
        public readonly string $label,
        public readonly string $rate,
        public readonly string $rateType,   // monthly | daily
        public readonly ?int $daysInMonth,
        public readonly string $paidDays,
        public readonly mixed $ref = null,  // e.g. the Employee model, carried through untouched
        public readonly bool $epf = false,  // EPF switched on for this employee
        public readonly bool $esic = false, // ESIC switched on for this employee
    ) {}

    public static function monthly(string $label, string $rate, int $daysInMonth, string $paidDays, mixed $ref = null, bool $epf = false, bool $esic = false): self
    {
        return new self($label, $rate, 'monthly', $daysInMonth, $paidDays, $ref, $epf, $esic);
    }

    public static function daily(string $label, string $rate, string $paidDays, mixed $ref = null, bool $epf = false, bool $esic = false): self
    {
        return new self($label, $rate, 'daily', null, $paidDays, $ref, $epf, $esic);
    }
}
