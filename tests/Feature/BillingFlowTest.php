<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function mph(): array
    {
        $agency = Agency::query()->create(['name' => 'M/S Z Plus Surakhya Seva', 'code' => 'ZPS']);
        $client = Client::query()->create(['name' => 'ICMR-NIHR Bhubaneswar', 'short_code' => 'ICMR']);
        $contract = GemContract::query()->create([
            'agency_id' => $agency->id, 'client_id' => $client->id,
            'bid_no' => 'GEM/2026/B/7972720', 'contract_no' => 'GEMC-511687731718030',
        ]);
        $project = $contract->projects()->create(['name' => 'MPH', 'code' => 'MPH', 'title' => 'MPH Academic Programme', 'pi_name' => 'Dr. S. Kanungo']);
        $apm = $project->posts()->create(['designation' => 'APM', 'sanctioned_qty' => 1, 'rate' => 55000]);
        $apa = $project->posts()->create(['designation' => 'APA', 'sanctioned_qty' => 1, 'rate' => 31000]);
        Employee::query()->create(['emp_code' => 'EMP-1001', 'name' => 'Test Manager', 'post_id' => $apm->id, 'joining_date' => '2026-10-01']);
        Employee::query()->create(['emp_code' => 'EMP-1002', 'name' => 'Test Assistant', 'post_id' => $apa->id, 'joining_date' => '2026-10-01']);

        return [$project, User::factory()->create(['role' => 'accounts'])];
    }

    public function test_october_mph_attendance_to_bill(): void
    {
        [$project, $user] = $this->mph();
        $attendance = app(AttendanceService::class);

        $sheet = $attendance->open($project, CarbonImmutable::parse('2026-10-01'), holidays: [2, 19, 20]);
        $this->assertCount(2, $sheet->entries);
        $this->assertSame('31.0', (string) $sheet->entries[0]->paid_days);

        $attendance->lock($sheet, $user, null);
        $invoice = app(InvoiceGenerator::class)->generate($sheet->fresh(), $user);

        $this->assertSame('ICMR/MPH/01/2026', $invoice->invoice_no);
        $this->assertSame('86000.00', $invoice->wages);
        $this->assertSame('3311.00', $invoice->service_charge);
        $this->assertSame('105387.00', $invoice->rounded_total);
        $this->assertSame('Rupees One Lakh Five Thousand Three Hundred Eighty Seven Only', $invoice->amountInWords());
        $this->assertCount(2, $invoice->lines);
    }

    public function test_absence_reduces_the_bill_and_numbers_continue(): void
    {
        [$project, $user] = $this->mph();
        $attendance = app(AttendanceService::class);

        $sheet = $attendance->open($project, CarbonImmutable::parse('2026-11-01'));
        $entry = $sheet->entries->firstWhere('employee.name', 'Test Assistant');
        $codes = $entry->codes;
        $codes[3] = 'A';                                   // one absent day in a 30-day month
        $attendance->save($sheet, [$entry->id => $codes]);
        $attendance->lock($sheet->fresh(), $user, null);

        $first = app(InvoiceGenerator::class)->generate($sheet->fresh(), $user);
        // APA: 31000 / 30 * 29 = 29966.67; APM: 55000
        $this->assertSame('84966.67', $first->wages);
        $this->assertSame('ICMR/MPH/01/2026', $first->invoice_no);
    }

    public function test_bill_needs_locked_attendance_and_only_once(): void
    {
        [$project, $user] = $this->mph();
        $attendance = app(AttendanceService::class);
        $sheet = $attendance->open($project, CarbonImmutable::parse('2026-10-01'));

        try {
            app(InvoiceGenerator::class)->generate($sheet, $user);
            $this->fail('Unlocked sheet was billed');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Lock the attendance', $e->getMessage());
        }

        $attendance->lock($sheet, $user, null);
        app(InvoiceGenerator::class)->generate($sheet->fresh(), $user);
        $this->expectExceptionMessage('already exists');
        app(InvoiceGenerator::class)->generate($sheet->fresh(), $user);
    }

    public function test_locked_sheet_cannot_be_edited(): void
    {
        [$project, $user] = $this->mph();
        $attendance = app(AttendanceService::class);
        $sheet = $attendance->open($project, CarbonImmutable::parse('2026-10-01'));
        $attendance->lock($sheet, $user, null);

        $this->expectExceptionMessage('locked');
        $attendance->save($sheet->fresh(), []);
    }
}
