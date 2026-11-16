<?php

namespace Tests\Unit\Payroll;

use App\Services\Payroll\SalaryPayoutSheet;
use PHPUnit\Framework\TestCase;

class SalaryDeductionsTest extends TestCase
{
    private function d(int $earnedRupees, bool $epf, bool $esic): array
    {
        return array_map(fn ($p) => $p / 100, (new SalaryPayoutSheet)->deductions($earnedRupees * 100, $epf, $esic));
    }

    public function test_both_switched_on(): void
    {
        $this->assertSame(['epf' => 1440, 'esic' => 90, 'tds' => 240, 'net' => 10230], $this->d(12000, true, true));
    }

    public function test_nothing_is_deducted_when_switched_off(): void
    {
        $this->assertSame(['epf' => 0, 'esic' => 0, 'tds' => 240, 'net' => 11760], $this->d(12000, false, false));
        $this->assertSame(['epf' => 1440, 'esic' => 0, 'tds' => 240, 'net' => 10320], $this->d(12000, true, false));
        $this->assertSame(['epf' => 0, 'esic' => 90, 'tds' => 240, 'net' => 11670], $this->d(12000, false, true));
    }

    public function test_epf_is_worked_out_on_wages_up_to_15000(): void
    {
        $this->assertSame(['epf' => 1800, 'esic' => 0, 'tds' => 620, 'net' => 28580], $this->d(31000, true, false));
    }

    public function test_esic_rounds_up_to_the_next_rupee(): void
    {
        // 7,000 x 0.75% = 52.50 -> 53; EPF 840; TDS 2% = 140
        $this->assertSame(['epf' => 840, 'esic' => 53, 'tds' => 140, 'net' => 5967], $this->d(7000, true, true));
    }

    public function test_tds_two_percent_is_deducted_from_everyone_and_rounded_to_the_rupee(): void
    {
        // 2% of 29,966.67 = 599.33, rounded to the rupee: 599
        $this->assertSame(59900, (new SalaryPayoutSheet)->deductions(2996667, false, false)['tds']);
        $this->assertSame(['epf' => 0, 'esic' => 0, 'tds' => 1100, 'net' => 53900], $this->d(55000, false, false));
    }
}
