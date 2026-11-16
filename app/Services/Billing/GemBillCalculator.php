<?php

namespace App\Services\Billing;

use App\Support\Exact;
use App\Support\Statutory;

/**
 * GeM manpower bill.
 *
 * Service charge is levied on the total amount excluding GST, and GST is then charged on (total + service charge).
 * When the contract's service charge % INCLUDES GST (owner's rule from November 2026, the default), the service charge is
 *   (total x service charge %) / (1 + GST %), e.g. 3.85% / 1.18 = 3.26% of the total;
 * otherwise it is total x service charge % (bills made before that change).
 *   monthly posts: amount = rate x paid days / days in month;  daily posts: amount = rate x paid days.
 * Owner, 16 Nov 2026: no figure on a bill is rounded; everything is carried exactly (as in the agencies' Excel) and only
 * shown to 2 decimals, the grand total being rounded to rupees at the end. The salary payout sheet rounds each person
 * to whole rupees.
 * Employer EPF and ESIC are added per person, only for staff with EPF / ESIC switched on (set on the employee):
 *   EPF  = EPF % of wages up to the Rs 15,000 ceiling;  ESIC = ESIC % of wages.
 */
final class GemBillCalculator
{
    public function __construct(
        private readonly string $serviceChargePercent,
        private readonly string $gstPercent,
        private readonly bool $interState = false,
        private readonly string $epfPercent = '0',
        private readonly string $esicPercent = '0',
        private readonly bool $serviceChargeIncludesGst = false,
        private readonly bool $roundEachPerson = false,
    ) {}

    /** @param BillLine[] $lines */
    public function monthly(array $lines): BillTotals
    {
        return $this->build($lines);
    }

    /** @param BillLine[] $lines */
    public function daily(array $lines): BillTotals
    {
        return $this->build($lines);
    }

    /** @param BillLine[] $lines */
    private function build(array $lines): BillTotals
    {
        // Exact throughout, like the agencies' Excel: each figure is only shown to 2 decimals; the grand total is rounded at the end.
        $wages = $epf = $esic = Exact::zero();
        $ceiling = Exact::of((string) Statutory::EPF_WAGE_CEILING);
        foreach ($lines as $line) {
            $amount = $line->rateType === 'monthly'
                ? Exact::mulDiv(Exact::of($line->rate), $line->paidDays, $line->daysInMonth)
                : Exact::of($line->rate)->multipliedBy($line->paidDays);
            if ($this->roundEachPerson) {
                $amount = Exact::of(Exact::rupees($amount));   // salary payout sheet: whole rupees per person
            }
            $line->exact = $amount;
            $line->amount = Exact::money($amount);
            $wages = $wages->plus($amount);

            $epfBase = $amount->compareTo($ceiling) > 0 ? $ceiling : $amount;
            $personEpf = $line->epf ? Exact::mulDiv($epfBase, $this->epfPercent, 100) : Exact::zero();
            $personEsic = $line->esic ? Exact::mulDiv($amount, $this->esicPercent, 100) : Exact::zero();
            $line->epfAmount = Exact::money($personEpf);
            $line->esicAmount = Exact::money($personEsic);
            $epf = $epf->plus($personEpf);
            $esic = $esic->plus($personEsic);
        }

        $subtotal = $wages->plus($epf)->plus($esic);
        $service = $this->serviceChargeIncludesGst
            ? Exact::mulDiv($subtotal, $this->serviceChargePercent, Exact::of(100)->plus(Exact::of($this->gstPercent)))
            : Exact::mulDiv($subtotal, $this->serviceChargePercent, 100);
        $taxable = $subtotal->plus($service);
        $igst = $this->interState ? Exact::mulDiv($taxable, $this->gstPercent, 100) : Exact::zero();
        $cgst = $sgst = $this->interState ? Exact::zero() : Exact::mulDiv($taxable, $this->gstPercent, 200);
        $total = $taxable->plus($cgst)->plus($sgst)->plus($igst);

        return new BillTotals(
            lines: $lines,
            wages: Exact::money($wages),
            epf: Exact::money($epf),
            esic: Exact::money($esic),
            subtotal: Exact::money($subtotal),
            serviceCharge: Exact::money($service),
            taxable: Exact::money($taxable),
            cgst: Exact::money($cgst),
            sgst: Exact::money($sgst),
            igst: Exact::money($igst),
            total: Exact::money($total),
            roundedTotal: Exact::rupees($total),
        );
    }
}
