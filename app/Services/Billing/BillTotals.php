<?php

namespace App\Services\Billing;

final class BillTotals
{
    /** @param BillLine[] $lines */
    public function __construct(
        public readonly array $lines,
        public readonly string $wages,
        public readonly string $epf,
        public readonly string $esic,
        public readonly string $subtotal,       // total before service charge and GST
        public readonly string $serviceCharge,
        public readonly string $taxable,        // total before GST
        public readonly string $cgst,
        public readonly string $sgst,
        public readonly string $igst,
        public readonly string $total,
        public readonly string $roundedTotal,
    ) {}
}
