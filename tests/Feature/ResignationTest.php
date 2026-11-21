<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Owner, 21 Nov 2026: the PI enters the last working day; pay stops then; it is shown in red; Inactive next month. */
class ResignationTest extends TestCase
{
    use RefreshDatabase;

    private Project $mph;

    private User $pi;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutOwnerApproval();
        $this->seed(DemoSeeder::class);
        $this->mph = Project::query()->where('code', 'MPH')->firstOrFail();
        $this->pi = User::factory()->create(['role' => 'pi', 'agency_id' => $this->mph->contract->agency_id]);
        $this->pi->projects()->attach($this->mph->id);
    }

    private function fullMonth(\App\Models\AttendanceSheet $sheet): array
    {
        return $sheet->entries->mapWithKeys(fn ($e) => [$e->id => array_fill(1, $sheet->month->daysInMonth, 'P')])->all();
    }

    public function test_pi_enters_last_working_day_and_pay_stops_on_that_date(): void
    {
        $service = app(AttendanceService::class);
        $sheet = $service->open($this->mph, CarbonImmutable::parse('2026-11-01'));
        $leaver = $sheet->entries->sortBy('employee.name')->first();

        $this->actingAs($this->pi)->get(route('pi.sheet', $sheet))->assertOk()->assertSee('Last working day');
        $this->put(route('pi.save', $sheet), ['codes' => $this->fullMonth($sheet), 'exit' => [$leaver->id => '2026-12-02']])
            ->assertSessionHasErrors('attendance');   // must be in the sheet's month
        $this->assertNull($leaver->employee->fresh()->exit_date);

        $this->put(route('pi.save', $sheet), ['codes' => $this->fullMonth($sheet), 'exit' => [$leaver->id => '2026-11-10']])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-10', $leaver->employee->fresh()->exit_date->toDateString());
        $entry = $leaver->fresh();
        $this->assertSame('10', rtrim(rtrim((string) $entry->paid_days, '0'), '.'));   // 1–10 Nov only, even though P was sent for every day
        $this->assertSame('A', $entry->codes[11] ?? $entry->codes['11']);
        $this->get(route('pi.sheet', $sheet))->assertSee('Resigned · last day 10.11.2026');

        // Lock and bill: highlighted on the sub-bill and the tax invoice, then Inactive and off next month's sheet.
        $admin = $this->makeDeveloper(User::factory()->create());
        $service->lock($sheet->fresh(), $admin, null);
        $this->assertSame('inactive', $leaver->employee->fresh()->status);
        $invoice = app(InvoiceGenerator::class)->generate($sheet->fresh(), $admin);
        $this->actingAs($admin)->get(route('invoices.show', $invoice))->assertSee('Resigned · last day 10.11.2026');
        $this->assertStringContainsString('Resigned, last working day 10.11.2026',
            view('pdf.invoice', \App\Http\Controllers\InvoiceController::pdfData($invoice))->render());

        $next = $service->open($this->mph, CarbonImmutable::parse('2026-12-01'));
        $this->assertNotContains($leaver->employee_id, $next->entries->pluck('employee_id')->all());
    }

    public function test_clearing_the_date_and_agency_can_enter_it_too(): void
    {
        $service = app(AttendanceService::class);
        $sheet = $service->open($this->mph, CarbonImmutable::parse('2026-11-01'));
        $e = $sheet->entries->first();
        $admin = $this->makeDeveloper(User::factory()->create());

        $this->actingAs($admin)->put(route('attendance.update', $sheet), ['codes' => $this->fullMonth($sheet), 'exit' => [$e->id => '2026-11-20']])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-20', $e->employee->fresh()->exit_date->toDateString());
        $this->put(route('attendance.update', $sheet), ['codes' => $this->fullMonth($sheet), 'exit' => [$e->id => '']])->assertSessionHasNoErrors();
        $this->assertNull($e->employee->fresh()->exit_date);
        $this->assertSame(30, (int) $e->fresh()->paid_days);
    }
}
