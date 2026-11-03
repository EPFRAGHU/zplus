<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Payroll\SalaryPayoutSheet;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SalaryPayoutTest extends TestCase
{
    use RefreshDatabase;

    private function sheet(bool $lock = true)
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $service = app(AttendanceService::class);
        $sheet = $service->open(Project::query()->where('code', 'MPH')->firstOrFail(), CarbonImmutable::parse('2026-10-01'));
        if ($lock) {
            $service->lock($sheet, User::factory()->create(['role' => 'admin']), null);
        }

        return $sheet->fresh();
    }

    public function test_payout_lines_deduct_epf_and_esic_from_earned_salary(): void
    {
        $sheet = $this->sheet();
        Employee::query()->update(['pf_applicable' => true]);
        $lines = app(SalaryPayoutSheet::class)->lines($sheet->fresh())->keyBy(fn ($l) => $l['employee']->post->designation);

        $apm = $lines['Academic Programme Manager'];   // 55,000 for the full month
        $this->assertSame([5500000, 180000, 0, 5320000], [$apm['earned'], $apm['epf'], $apm['esic'], $apm['net']]);
        $apa = $lines['Academic Programme Assistant'];  // 31,000
        $this->assertSame([3100000, 180000, 0, 2920000], [$apa['earned'], $apa['epf'], $apa['esic'], $apa['net']]);
    }

    public function test_sheet_prints_on_the_agency_letterhead_for_accounts_only(): void
    {
        $sheet = $this->sheet();
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $sheet->project->contract->update(['agency_id' => $swh->id, 'client_id' => \App\Models\Client::query()->where('agency_id', $swh->id)->value('id')]);

        $accounts = User::factory()->create(['role' => 'accounts']);
        $this->actingAs($accounts)->get("/attendance/{$sheet->id}")->assertOk()->assertSee('Salary payout sheet');
        $this->get("/attendance/{$sheet->id}/salary-payouts")->assertOk()->assertHeader('content-type', 'application/pdf');

        $html = view('pdf.salary-payouts', ['sheet' => $sheet->fresh()->load('project.contract.agency', 'project.contract.client'),
            'lines' => app(SalaryPayoutSheet::class)->lines($sheet->fresh()), 'agency' => $swh->fresh()])->render();
        $this->assertStringContainsString('&ldquo;SWACHHA&rdquo;', $html);
        $this->assertStringContainsString('SALARY PAYOUT SHEET – OCTOBER 2026', $html);
        $this->assertStringContainsString('Total net pay to transfer', $html);

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->actingAs($supervisor)->get("/attendance/{$sheet->id}/salary-payouts")->assertForbidden();
        $this->get("/attendance/{$sheet->id}")->assertDontSee('Salary payout sheet');
    }

    public function test_month_must_be_locked(): void
    {
        $sheet = $this->sheet(lock: false);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get("/attendance/{$sheet->id}/salary-payouts")->assertStatus(422);
    }
}
