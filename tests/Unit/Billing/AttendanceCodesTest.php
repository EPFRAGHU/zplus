<?php

namespace Tests\Unit\Billing;

use App\Services\Attendance\AttendanceCodes;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AttendanceCodesTest extends TestCase
{
    public function test_default_month_matches_pi_certificate_for_october_2026(): void
    {
        $codes = AttendanceCodes::defaultMonth(CarbonImmutable::parse('2026-10-01'), holidays: [2, 19, 20], saturdayOff: true);

        $this->assertCount(31, $codes);
        $this->assertSame('H', $codes[2]);
        $this->assertSame('SAT', $codes[3]);
        $this->assertSame('SUN', $codes[4]);
        $this->assertSame('P', $codes[5]);
        $this->assertSame('31.0', AttendanceCodes::paidDays($codes));
    }

    public function test_absent_and_leave_without_pay_reduce_paid_days(): void
    {
        $codes = AttendanceCodes::defaultMonth(CarbonImmutable::parse('2026-10-01'), holidays: [], saturdayOff: false);
        $codes[5] = 'A';
        $codes[6] = 'L';
        $codes[7] = 'CL';
        $codes[8] = 'EL';

        $this->assertSame('29.0', AttendanceCodes::paidDays($codes));
    }

    public function test_unknown_code_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AttendanceCodes::paidDays([1 => 'X']);
    }
}
