<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\TaxInvoiceBuilder;
use App\Services\Payroll\SalaryPayoutSheet;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StatutorySwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function lockedSheet()
    {
        $service = app(AttendanceService::class);
        $sheet = $service->open(Project::query()->where('code', 'MPH')->firstOrFail(), CarbonImmutable::parse('2026-10-01'));
        $service->lock($sheet, $this->admin, null);

        return $sheet->fresh();
    }

    public function test_switches_on_the_employees_list_turn_epf_and_esic_on_and_off(): void
    {
        $e = Employee::query()->firstOrFail();
        $this->actingAs($this->admin)->get('/employees')->assertOk()->assertSee('EPF / ESIC')->assertSee('role="switch"', false);

        $this->patch("/employees/{$e->id}/statutory", ['pf_applicable' => '1'])->assertRedirect();
        $this->assertTrue($e->fresh()->pf_applicable);
        $this->assertFalse($e->fresh()->esic_applicable);

        $this->patch("/employees/{$e->id}/statutory", [])->assertRedirect();
        $this->assertFalse($e->fresh()->pf_applicable);

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->actingAs($supervisor)->patch("/employees/{$e->id}/statutory", ['pf_applicable' => '1'])->assertForbidden();
        $this->get('/employees')->assertSee('EPF off');
    }

    public function test_default_october_bill_is_unchanged_when_switched_off(): void
    {
        $invoice = app(InvoiceGenerator::class)->generate($this->lockedSheet(), $this->admin);
        $this->assertSame('0.00', $invoice->epf);
        $this->assertSame('105387.00', $invoice->rounded_total);
    }

    public function test_epf_switched_on_adds_employer_epf_to_sub_bill_and_tax_invoice_and_deducts_on_salary_sheet(): void
    {
        $apm = Employee::query()->whereHas('post', fn ($q) => $q->where('designation', 'Academic Programme Manager'))->firstOrFail();
        $apm->update(['pf_applicable' => true]);
        $sheet = $this->lockedSheet();

        $invoice = app(InvoiceGenerator::class)->generate($sheet, $this->admin)->load('lines', 'contract.agency', 'contract.client', 'project');
        $this->assertSame('1950.00', $invoice->epf);            // 13% of 15,000 for the APM only
        $this->assertSame('0.00', $invoice->esic);
        $this->assertSame('87950.00', $invoice->subtotal);      // 86,000 + 1,950
        $this->assertSame('1950.00', $invoice->lines->firstWhere('employee_id', $apm->id)->epf);

        $html = view('pdf.invoice', ['invoice' => $invoice, 'agency' => $invoice->contract->agency, 'client' => $invoice->contract->client,
            'project' => $invoice->project, 'contract' => $invoice->contract])->render();
        $this->assertStringContainsString('Employer EPF @ 13% on wages up to ₹15,000 (1 person)', $html);
        $this->assertStringNotContainsString('Employer ESIC', $html);
        $this->actingAs($this->admin)->get("/invoices/{$invoice->id}")->assertOk()->assertSee('Employer EPF');

        $tax = app(TaxInvoiceBuilder::class)->create($invoice->contract->client, CarbonImmutable::parse('2026-10-01'), [$invoice->id], $this->admin);
        $tax->load('agency', 'client', 'subBills.project', 'subBills.contract');
        $html = view('pdf.tax-invoice', ['tax' => $tax, 'agency' => $tax->agency, 'client' => $tax->client])->render();
        $this->assertStringContainsString('Add: Employer EPF', $html);
        $this->assertStringContainsString('1,950.00', $html);
        $this->get("/tax-invoices/{$tax->id}")->assertOk()->assertSee('Add: Employer EPF');

        $line = app(SalaryPayoutSheet::class)->lines($sheet)->first(fn ($l) => $l['employee']->id === $apm->id);
        $this->assertSame(180000, $line['epf']);               // employee 12% of 15,000
        $other = app(SalaryPayoutSheet::class)->lines($sheet)->first(fn ($l) => $l['employee']->id !== $apm->id);
        $this->assertSame(0, $other['epf']);
    }
}
