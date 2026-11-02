<?php

namespace Tests\Unit\Payroll;

use App\Services\Payroll\SalaryPayoutSheet;
use PHPUnit\Framework\TestCase;

class SalaryDeductionsTest extends TestCase
{
    private function d(int $earnedRupees, int $wageRupees, bool $pf = false): array
    {
        return array_map(fn ($p) => $p / 100, (new SalaryPayoutSheet)->deductions($earnedRupees * 100, $wageRupees * 100, $pf));
    }

    public function test_low_wage_gets_both_deductions(): void
    {
        // 12,000: EPF 1,440; ESIC 0.75% = 90
        $this->assertSame(['epf' => 1440, 'esic' => 90, 'net' => 10470], $this->d(12000, 12000));
    }

    public function test_epf_is_capped_at_the_15000_ceiling_and_esic_stops_above_21000(): void
    {
        // 31,000 with PF applicable: EPF on 15,000 = 1,800; no ESIC
        $this->assertSame(['epf' => 1800, 'esic' => 0, 'net' => 29200], $this->d(31000, 31000, pf: true));
        // above 15,000 and not marked PF applicable: no EPF
        $this->assertSame(['epf' => 0, 'esic' => 0, 'net' => 31000], $this->d(31000, 31000));
    }

    public function test_esic_applies_up_to_21000_and_rounds_up(): void
    {
        // 18,333 earned, PF applicable: EPF 1,800; ESIC 137.4975 -> 138
        $this->assertSame(['epf' => 1800, 'esic' => 138, 'net' => 16395], $this->d(18333, 21000, pf: true));
    }

    public function test_part_month_uses_earned_wages(): void
    {
        // rate 14,000, earned 7,000 for half a month: EPF 840; ESIC 52.5 -> 53
        $this->assertSame(['epf' => 840, 'esic' => 53, 'net' => 6107], $this->d(7000, 14000));
    }
}
