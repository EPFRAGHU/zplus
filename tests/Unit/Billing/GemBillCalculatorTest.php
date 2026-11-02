<?php

namespace Tests\Unit\Billing;

use App\Services\Billing\BillLine;
use App\Services\Billing\GemBillCalculator;
use PHPUnit\Framework\TestCase;

class GemBillCalculatorTest extends TestCase
{
    private function calc(): GemBillCalculator
    {
        return new GemBillCalculator(serviceChargePercent: '3.85', gstPercent: '18');
    }

    public function test_october_mph_bill_matches_the_bill_actually_submitted(): void
    {
        // ICMR/MPH/01/2026: APM 55,000 + APA 31,000, 31 of 31 days each.
        $bill = $this->calc()->monthly([
            BillLine::monthly('APM', rate: '55000', daysInMonth: 31, paidDays: '31'),
            BillLine::monthly('APA', rate: '31000', daysInMonth: 31, paidDays: '31'),
        ]);

        $this->assertSame('86000.00', $bill->wages);
        $this->assertSame('3311.00', $bill->serviceCharge);
        $this->assertSame('89311.00', $bill->taxable);
        $this->assertSame('8037.99', $bill->cgst);
        $this->assertSame('8037.99', $bill->sgst);
        $this->assertSame('0.00', $bill->igst);
        $this->assertSame('105386.98', $bill->total);
        $this->assertSame('105387.00', $bill->roundedTotal);
    }

    public function test_partial_month_is_prorated_by_days_in_month(): void
    {
        $bill = $this->calc()->monthly([
            BillLine::monthly('APA', rate: '31000', daysInMonth: 31, paidDays: '29'),
        ]);

        // 31000 / 31 * 29 = 29000.00
        $this->assertSame('29000.00', $bill->wages);
        $this->assertSame('29000.00', $bill->lines[0]->amount);
    }

    public function test_epf_and_esic_are_added_before_service_charge_for_staff_with_them_switched_on(): void
    {
        $calc = new GemBillCalculator(serviceChargePercent: '3.85', gstPercent: '18', epfPercent: '13', esicPercent: '3.25');
        $bill = $calc->daily([BillLine::daily('MTS', rate: '556', paidDays: '26', epf: true, esic: true)]);

        $this->assertSame('14456.00', $bill->wages);          // 556 x 26
        $this->assertSame('1879.28', $bill->epf);             // 13%
        $this->assertSame('469.82', $bill->esic);             // 3.25%
        $this->assertSame('16805.10', $bill->subtotal);       // wages + EPF + ESIC
        $this->assertSame('647.00', $bill->serviceCharge);    // 3.85% of 16805.10 = 646.996..
        $this->assertSame('17452.10', $bill->taxable);
    }

    public function test_interstate_bill_uses_igst(): void
    {
        $calc = new GemBillCalculator(serviceChargePercent: '3.85', gstPercent: '18', interState: true);
        $bill = $calc->monthly([BillLine::monthly('APM', rate: '55000', daysInMonth: 30, paidDays: '30')]);

        $this->assertSame('0.00', $bill->cgst);
        $this->assertSame('10281.15', $bill->igst); // 18% of 57117.50
    }

    public function test_each_person_is_rounded_to_whole_rupees_like_the_sub_bill(): void
    {
        // Sub-bill: rate/day = 31000 / 30; amount = ROUND(29 x rate/day, 0) = 29967 (29966.67 rounded).
        $bill = $this->calc()->monthly([
            BillLine::monthly('APA', rate: '31000', daysInMonth: 30, paidDays: '29'),
            BillLine::monthly('APM', rate: '55000', daysInMonth: 30, paidDays: '30'),
        ]);

        $this->assertSame('29967.00', $bill->lines[0]->amount);
        $this->assertSame('84967.00', $bill->wages);
    }

    public function test_staff_with_epf_and_esic_switched_off_add_nothing(): void
    {
        $calc = new GemBillCalculator(serviceChargePercent: '3.85', gstPercent: '18', epfPercent: '13', esicPercent: '3.25');
        $bill = $calc->daily([BillLine::daily('MTS', rate: '556', paidDays: '26')]);
        $this->assertSame('0.00', $bill->epf);
        $this->assertSame('0.00', $bill->esic);
        $this->assertSame('14456.00', $bill->subtotal);
    }

    public function test_monthly_contract_adds_epf_on_wages_up_to_15000_and_esic_only_for_those_switched_on(): void
    {
        $calc = new GemBillCalculator(serviceChargePercent: '3.85', gstPercent: '18', epfPercent: '13', esicPercent: '3.25');
        $bill = $calc->monthly([
            BillLine::monthly('APM', rate: '55000', daysInMonth: 31, paidDays: '31', epf: true),             // EPF on 15,000 = 1,950
            BillLine::monthly('DEO', rate: '18000', daysInMonth: 31, paidDays: '31', epf: true, esic: true), // EPF 1,950; ESIC 585
            BillLine::monthly('APA', rate: '31000', daysInMonth: 31, paidDays: '31'),                        // neither
        ]);
        $this->assertSame('3900.00', $bill->epf);
        $this->assertSame('585.00', $bill->esic);
        $this->assertSame(['1950.00', '1950.00', '0.00'], array_map(fn ($l) => $l->epfAmount, $bill->lines));
        $this->assertSame('108485.00', $bill->subtotal);   // 104,000 + 3,900 + 585
    }
}
