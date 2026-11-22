<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\AttendanceSheet;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Laptop demo (owner, 22 Nov 2026): full October attendance, a sub-bill per project, one tax invoice per work order. */
class DemoFullMonthTest extends TestCase
{
    use RefreshDatabase;

    private function setUpData(): array
    {
        $this->seed(DemoSeeder::class);
        $dev = $this->makeDeveloper(User::factory()->create(['role' => 'admin']));
        $contract = GemContract::query()->firstOrFail();
        // A second project under the same work order, with the same post as MPH, so the tax invoice clubs two sub-bills.
        $second = $contract->projects()->create(['name' => 'Second Project', 'code' => 'SEC']);
        $post = $second->posts()->create(['designation' => 'Academic Programme Manager', 'sanctioned_qty' => 1, 'rate' => 30000]);
        $mph = Project::query()->where('code', 'MPH')->firstOrFail();
        $first = Employee::query()->whereNotNull('post_id')->firstOrFail();
        Employee::query()->create(['emp_code' => 'ZP-7001', 'name' => 'Demo Person', 'agency_id' => $first->agency_id, 'client_id' => $first->client_id,
            'post_id' => $post->id, 'status' => 'active']);

        return [$dev, $contract, $mph, $second];
    }

    public function test_full_month_sub_bill_per_project_and_one_tax_invoice_per_work_order(): void
    {
        [$dev, $contract, $mph, $second] = $this->setUpData();

        $this->artisan('demo:full-month', ['month' => '2026-10', '--force' => true])->assertSuccessful();

        foreach ([$mph, $second] as $project) {
            $sheet = AttendanceSheet::query()->where('project_id', $project->id)->where('month', '2026-10-01')->firstOrFail();
            $this->assertTrue($sheet->isLocked());
            foreach ($sheet->entries as $entry) {
                $this->assertSame('31.0', (string) $entry->paid_days, 'every day of October paid');
            }
            $bill = Invoice::query()->where('attendance_sheet_id', $sheet->id)->firstOrFail();
            $this->assertStringEndsWith('('.$project->code.')', $bill->invoice_no);   // project short code in the sub-bill number
        }
        $tax = TaxInvoice::query()->where('gem_contract_id', $contract->id)->firstOrFail();
        $this->assertSame(2, Invoice::query()->where('tax_invoice_id', $tax->id)->count());
        $this->assertSame(0, Approval::query()->where('status', 'pending')->count());
        $this->assertTrue(Approval::query()->where('type', 'tax_invoice')->where('status', 'approved')->where('label', 'like', '%(demo)')->exists());

        $this->actingAs($dev)->get(route('tax-invoices.show', $tax))->assertOk()->assertSee('(MPH)')->assertSee('(SEC)');
        $this->get(route('tax-invoices.pdf', $tax))->assertOk()->assertHeader('content-type', 'application/pdf');   // approved in the demo, so the PDF opens
    }

    public function test_refuses_on_the_live_site(): void
    {
        [, $contract] = $this->setUpData();
        config(['app.url' => 'https://zplus.epf-dashboard.online']);

        $this->artisan('demo:full-month', ['month' => '2026-10', '--force' => true])->assertFailed();
        $this->assertSame(0, AttendanceSheet::query()->count());
        $this->assertSame(0, TaxInvoice::query()->where('gem_contract_id', $contract->id)->count());
    }

    public function test_months_already_locked_are_kept_and_nothing_happens_without_typing_demo(): void
    {
        [$dev, , $mph, $second] = $this->setUpData();
        $service = app(AttendanceService::class);
        $locked = $service->open($mph, CarbonImmutable::parse('2026-10-01'));
        $service->lock($locked, $dev, null);

        $this->artisan('demo:full-month', ['month' => '2026-10'])->expectsQuestion('Type DEMO to go ahead', 'no')->assertSuccessful();
        $this->assertSame(0, Invoice::query()->count());

        $this->artisan('demo:full-month', ['month' => '2026-10', '--force' => true])->assertSuccessful();
        $this->assertFalse(Invoice::query()->where('attendance_sheet_id', $locked->id)->exists(), 'the locked month was left as it was');
        $this->assertTrue(Invoice::query()->where('project_id', $second->id)->exists());
    }
}
