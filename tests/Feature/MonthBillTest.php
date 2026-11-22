<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\TaxInvoice;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Month bill: an agency's month in one sheet, one row per work order (owner, 22 Nov 2026). */
class MonthBillTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private Agency $zps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $first = GemContract::query()->firstOrFail();
        $this->zps = $first->agency;
        $person = Employee::query()->whereNotNull('post_id')->firstOrFail();
        $second = GemContract::query()->create(['agency_id' => $first->agency_id, 'client_id' => $first->client_id, 'bid_no' => 'GEM/2', 'contract_no' => 'GEMC-000000000000002']);
        foreach (['AAA', 'BBB'] as $i => $code) {
            $post = $second->projects()->create(['name' => 'Project '.$code, 'code' => $code])->posts()->create(['designation' => 'Field Worker', 'sanctioned_qty' => 1, 'rate' => 20000]);
            Employee::query()->create(['emp_code' => 'ZP-80'.$i, 'name' => 'Worker '.$code, 'agency_id' => $person->agency_id, 'client_id' => $person->client_id, 'post_id' => $post->id, 'status' => 'active']);
        }
        $this->dev = $this->makeDeveloper(User::factory()->create(['role' => 'admin']));
        $this->artisan('demo:full-month', ['month' => '2026-10', '--force' => true])->assertSuccessful();
    }

    public function test_month_bill_lists_each_work_order_and_the_month_total(): void
    {
        $html = $this->actingAs($this->dev)->get(route('month-bill.show', ['agency' => $this->zps->id, 'month' => '2026-10']))->assertOk()
            ->assertSee('Month bill – October 2026')->assertSee('GEMC-000000000000002')->assertSee('GEMC-511687731718030')
            ->assertSee('Month total · 2 work orders')->getContent();
        foreach (TaxInvoice::query()->pluck('number') as $number) {
            $this->assertStringContainsString($number, $html);
        }
        $this->assertStringContainsString(inr((float) TaxInvoice::query()->sum('rounded_total'), true), $html);   // total of the tax invoices
        $this->assertStringContainsString(inr((float) Invoice::query()->sum('service_charge'), true), $html);
        // The work order with bill serial 001 comes first.
        $this->assertLessThan(strpos($html, 'GEMC-511687731718030</a>'), strpos($html, 'GEMC-000000000000002</a>'));
    }

    public function test_pdf_downloads_on_one_landscape_page(): void
    {
        $pdf = $this->actingAs($this->dev)->get(route('month-bill.pdf', ['agency' => $this->zps->id, 'month' => '2026-10']))->assertOk()
            ->assertHeader('content-type', 'application/pdf')->getContent();
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf));
    }

    public function test_sub_bills_list_has_the_month_bill_button(): void
    {
        $this->actingAs($this->dev)->get('/invoices?month=2026-10&agency='.$this->zps->id)->assertOk()
            ->assertSee('Show October 2026 month bill')->assertSee(route('month-bill.show', ['agency' => $this->zps->id, 'month' => '2026-10']));
    }

    public function test_agency_login_cannot_open_the_other_agencys_month_bill(): void
    {
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $swhAccounts = User::factory()->create(['role' => 'accounts', 'agency_id' => $swh->id]);
        $this->actingAs($swhAccounts)->get(route('month-bill.show', ['agency' => $this->zps->id, 'month' => '2026-10']))->assertNotFound();
        $this->get(route('month-bill.pdf', ['agency' => $this->zps->id, 'month' => '2026-10']))->assertNotFound();
        $this->get(route('month-bill.show', ['month' => '2026-10']))->assertOk()->assertSee('No sub-bills for SWACHHA');   // own agency, nothing billed
    }

    public function test_logins_without_billing_rights_are_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'supervisor']))->get(route('month-bill.show', ['agency' => $this->zps->id]))->assertForbidden();
    }

    public function test_consolidated_tax_invoice_statement_adds_up_the_months_tax_invoices(): void
    {
        $c = app(\App\Services\Billing\MonthBill::class)->consolidated($this->zps, \Carbon\CarbonImmutable::parse('2026-10-01'));
        $this->assertSame(2, $c['taxes']->count());
        $this->assertSame(number_format((float) TaxInvoice::query()->sum('rounded_total'), 2, '.', ''), $c['t']['roundedTotal']);
        $this->assertSame(number_format((float) TaxInvoice::query()->sum('taxable'), 2, '.', ''), $c['t']['taxable']);
        $this->assertSame(Invoice::query()->withCount('lines')->get()->sum('lines_count'), $c['manpower']);
        $field = collect($c['rows'])->firstWhere('designation', 'Field Worker');
        $this->assertSame(2, $field['manpower'], 'the same post at the same rate is one row');

        $this->actingAs($this->dev)->get(route('month-bill.show', ['agency' => $this->zps->id, 'month' => '2026-10']))->assertOk()
            ->assertSee('Consolidated tax invoice statement – October 2026')->assertSee('not a separate tax invoice');
        $pdf = $this->get(route('month-bill.consolidated', ['agency' => $this->zps->id, 'month' => '2026-10']))->assertOk()
            ->assertHeader('content-type', 'application/pdf')->getContent();
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf));
        $this->get(route('month-bill.consolidated', ['agency' => $this->zps->id, 'month' => '2026-12']))->assertNotFound();   // no tax invoices that month
    }
}
