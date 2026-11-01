<?php

namespace App\Services\Billing;

final class BillLine
{
    public string $amount = '0.00';

    private function __construct(
        public readonly string $label,
        public readonly string $rate,
        public readonly string $rateType,   // monthly | daily
        public readonly ?int $daysInMonth,
        public readonly string $paidDays,
        public readonly mixed $ref = null,  // e.g. the Employee model, carried through untouched
    ) {}

    public static function monthly(string $label, string $rate, int $daysInMonth, string $paidDays, mixed $ref = null): self
    {
        return new self($label, $rate, 'monthly', $daysInMonth, $paidDays, $ref);
    }

    public static function daily(string $label, string $rate, string $paidDays, mixed $ref = null): self
    {
        return new self($label, $rate, 'daily', null, $paidDays, $ref);
    }
}
