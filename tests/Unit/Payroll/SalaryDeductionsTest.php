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
        $this->assertSame(['epf' => 1440, 'esic' => 90, 'net' => 10470], $this->d(12000, true, true));
    }

    public function test_nothing_is_deducted_when_switched_off(): void
    {
        $this->assertSame(['epf' => 0, 'esic' => 0, 'net' => 12000], $this->d(12000, false, false));
        $this->assertSame(['epf' => 1440, 'esic' => 0, 'net' => 10560], $this->d(12000, true, false));
        $this->assertSame(['epf' => 0, 'esic' => 90, 'net' => 11910], $this->d(12000, false, true));
    }

    public function test_epf_is_worked_out_on_wages_up_to_15000(): void
    {
        $this->assertSame(['epf' => 1800, 'esic' => 0, 'net' => 29200], $this->d(31000, true, false));
    }

    public function test_esic_rounds_up_to_the_next_rupee(): void
    {
        // 7,000 x 0.75% = 52.50 -> 53; EPF 840
        $this->assertSame(['epf' => 840, 'esic' => 53, 'net' => 6107], $this->d(7000, true, true));
    }
}
