<?php

namespace App\Services\Billing;

use App\Support\Money;

/**
 * GeM manpower bill.
 *
 * Confirmed rule: service charge is levied on the total amount excluding GST,
 * and GST is then charged on (total + service charge).
 *   monthly posts: amount = rate x paid days / days in month, rounded to whole rupees per person
 *   daily posts:   amount = rate x paid days, plus employer EPF and ESIC
 */
final class GemBillCalculator
{
    public function __construct(
        private readonly string $serviceChargePercent,
        private readonly string $gstPercent,
        private readonly bool $interState = false,
        private readonly string $epfPercent = '0',
        private readonly string $esicPercent = '0',
    ) {}

    /** @param BillLine[] $lines */
    public function monthly(array $lines): BillTotals
    {
        return $this->build($lines, withStatutory: false);
    }

    /** @param BillLine[] $lines */
    public function daily(array $lines): BillTotals
    {
        return $this->build($lines, withStatutory: true);
    }

    /** @param BillLine[] $lines */
    private function build(array $lines, bool $withStatutory): BillTotals
    {
        $wages = 0;
        foreach ($lines as $line) {
            $rate = Money::toPaise($line->rate);
            $days10 = Money::scaled($line->paidDays, 1);
            $paise = $line->rateType === 'monthly'
                ? Money::mulDiv($rate, $days10, $line->daysInMonth * 10)
                : Money::mulDiv($rate, $days10, 10);
            $paise = Money::roundToRupee($paise);   // each person rounded to whole rupees, as on the sub-bill
            $line->amount = Money::format($paise);
            $wages += $paise;
        }

        $epf = $withStatutory ? Money::percent($wages, $this->epfPercent) : 0;
        $esic = $withStatutory ? Money::percent($wages, $this->esicPercent) : 0;
        $subtotal = $wages + $epf + $esic;
        $service = Money::percent($subtotal, $this->serviceChargePercent);
        $taxable = $subtotal + $service;

        if ($this->interState) {
            $igst = Money::percent($taxable, $this->gstPercent);
            $cgst = $sgst = 0;
        } else {
            $half = Money::scaled($this->gstPercent, 4);
            $cgst = $sgst = Money::mulDiv($taxable, $half, 2 * 100 * 10_000);
            $igst = 0;
        }
        $total = $taxable + $cgst + $sgst + $igst;

        return new BillTotals(
            lines: $lines,
            wages: Money::format($wages),
            epf: Money::format($epf),
            esic: Money::format($esic),
            subtotal: Money::format($subtotal),
            serviceCharge: Money::format($service),
            taxable: Money::format($taxable),
            cgst: Money::format($cgst),
            sgst: Money::format($sgst),
            igst: Money::format($igst),
            total: Money::format($total),
            roundedTotal: Money::format(Money::roundToRupee($total)),
        );
    }
}
