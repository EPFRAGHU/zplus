<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\TaxInvoiceBuilder;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TaxInvoiceTest extends TestCase
{
    use RefreshDatabase;

    /** October: the MPH sub-bill (₹89,311 before GST) plus a second project under the same work order.
     *  Uses the pre-November rule (service charge NOT including GST) so the figures match the October ICMR invoice. */
    private function twoSubBills(): array
    {
        $this->seed(DemoSeeder::class);
        $user = User::factory()->create(['role' => 'accounts']);
        $mph = Project::query()->where('code', 'MPH')->firstOrFail();
        $mph->contract->update(['service_charge_includes_gst' => false]);
        $snake = $mph->contract->projects()->create(['name' => 'Snakebite', 'code' => 'SNAKE']);
        $post = $snake->posts()->create(['designation' => 'PTS-III', 'sanctioned_qty' => 1, 'rate' => 33600]);
        Employee::query()->create(['emp_code' => 'EMP-9001', 'name' => 'Field Staff', 'post_id' => $post->id,
            'agency_id' => $mph->contract->agency_id, 'client_id' => $mph->contract->client_id]);

        $service = app(AttendanceService::class);
        $bills = [];
        foreach ([$mph, $snake] as $p) {
            $sheet = $service->open($p, CarbonImmutable::parse('2026-10-01'));
            $service->lock($sheet, $user, null);
            $bills[] = app(InvoiceGenerator::class)->generate($sheet->fresh(), $user);
        }

        return [$mph->contract->fresh(), $bills, $user];
    }

    public function test_tax_invoice_combines_the_months_sub_bills(): void
    {
        [$client, $bills, $user] = $this->twoSubBills();   // $client: the work order
        $builder = app(TaxInvoiceBuilder::class);

        $this->assertCount(2, $builder->pending($client, CarbonImmutable::parse('2026-10-01')));
        $tax = $builder->create($client, CarbonImmutable::parse('2026-10-01'), collect($bills)->pluck('id')->all(), $user);

        // MPH 89,311.00 + Snakebite 33,600 x 1.0385 = 34,893.60 -> 1,24,204.60 before GST
        $this->assertSame('ZP/001/26-27', $tax->number);   // the serial its sub-bills ZP/001/26-27(MPH) and (SNAKE) carry
        $this->assertSame('124204.60', $tax->taxable);
        $this->assertSame('11178.41', $tax->cgst);         // GST worked out once on the combined total, exactly (as on the agencies' tax invoice)
        $this->assertSame('146561.43', $tax->total);       // exact sum; the sub-bills' own totals add up to 146561.42
        $this->assertSame('146561.00', $tax->rounded_total);
        $this->assertSame(2, $tax->subBills()->count());
        $this->assertCount(0, $builder->pending($client, CarbonImmutable::parse('2026-10-01')));
    }

    public function test_single_project_tax_invoice_matches_the_october_icmr_invoice(): void
    {
        [$client, $bills, $user] = $this->twoSubBills();   // $client: the work order
        $tax = app(TaxInvoiceBuilder::class)->create($client, CarbonImmutable::parse('2026-10-01'), [$bills[0]->id], $user, 'ICMR/MPH/01/26', '2026-11-01');

        $this->assertSame('ICMR/MPH/01/26', $tax->number);
        $this->assertSame('89311.00', $tax->taxable);
        $this->assertSame('8037.99', $tax->cgst);
        $this->assertSame('8037.99', $tax->sgst);
        $this->assertSame('105387.00', $tax->rounded_total);
        $this->assertSame('Rupees One Lakh Five Thousand Three Hundred Eighty Seven Only', $tax->amountInWords());
    }

    public function test_sub_bill_can_only_be_in_one_tax_invoice_of_its_own_work_order_and_month(): void
    {
        [$client, $bills, $user] = $this->twoSubBills();   // $client: the work order
        $builder = app(TaxInvoiceBuilder::class);
        $builder->create($client, CarbonImmutable::parse('2026-10-01'), [$bills[0]->id], $user);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already in a tax invoice');
        $builder->create($client, CarbonImmutable::parse('2026-10-01'), [$bills[0]->id], $user);
    }

    public function test_wrong_month_and_wrong_work_order_are_refused(): void
    {
        [$client, $bills, $user] = $this->twoSubBills();   // $client: the work order
        $builder = app(TaxInvoiceBuilder::class);
        try {
            $builder->create($client, CarbonImmutable::parse('2026-11-01'), [$bills[0]->id], $user);
            $this->fail('Different month accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('different month', $e->getMessage());
        }
        $other = \App\Models\GemContract::query()->create(['agency_id' => $client->agency_id, 'client_id' => $client->client_id, 'bid_no' => 'B2', 'contract_no' => 'GEMC-OTHER']);
        $this->expectExceptionMessage('different work order');
        $builder->create($other, CarbonImmutable::parse('2026-10-01'), [$bills[0]->id], $user);
    }

    public function test_deleting_a_draft_tax_invoice_releases_its_sub_bills(): void
    {
        [$client, $bills, $user] = $this->twoSubBills();   // $client: the work order
        $builder = app(TaxInvoiceBuilder::class);
        $tax = $builder->create($client, CarbonImmutable::parse('2026-10-01'), collect($bills)->pluck('id')->all(), $user);

        $builder->delete($tax);
        $this->assertSame(0, Invoice::query()->whereNotNull('tax_invoice_id')->count());
        $this->assertCount(2, $builder->pending($client, CarbonImmutable::parse('2026-10-01')));
    }

    public function test_tax_invoice_screens_pdf_and_payment(): void
    {
        $this->withoutOwnerApproval();   // billing rules before owner approvals; see ApprovalTest
        [$client, $bills, $user] = $this->twoSubBills();   // $client: the work order

        $this->actingAs($user)->get('/invoices')->assertOk()->assertSee('Make tax invoice');
        $this->get('/tax-invoices/create?contract='.$client->id.'&month=2026-10')->assertOk()
            ->assertSee($bills[0]->invoice_no)->assertSee($bills[1]->invoice_no)->assertSee('ZP/001/26-27');
        $this->post('/tax-invoices', ['gem_contract_id' => $client->id, 'month' => '2026-10', 'sub_bills' => [$bills[0]->id, $bills[1]->id],
            'number' => '', 'invoice_date' => '2026-11-03'])->assertRedirect();

        $tax = TaxInvoice::query()->firstOrFail();
        $this->get('/tax-invoices')->assertOk()->assertSee('ZP/001/26-27')->assertSee('client-block', false);
        $this->get("/tax-invoices/{$tax->id}")->assertOk()->assertSee('₹1,46,561.00')->assertSee('Bank details are missing');
        $this->get("/tax-invoices/{$tax->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        $html = view('pdf.tax-invoice', \App\Http\Controllers\TaxInvoiceController::pdfData($tax))->render();
        $this->assertStringContainsString('TAX INVOICE', $html);
        $this->assertStringContainsString('Invoice No- ZP/001/26-27', $html);
        $this->assertStringContainsString('GEM CONTRACT NO-GEMC-511687731718030', $html);
        $this->assertStringContainsString('ACADEMIC PROGRAMME MANAGER', $html);   // posts of each project
        $this->assertStringContainsString('PTS-III', $html);
        $this->assertStringContainsString('998514', $html);
        $this->assertStringContainsString('Category of Service: Manpower Service', $html);
        $this->assertStringContainsString('One Lakh Forty Six Thousand Five Hundred Sixty One', $html);

        // A sub-bill inside a tax invoice cannot be deleted on its own.
        $this->delete("/invoices/{$bills[0]->id}")->assertSessionHasErrors('invoice');

        $this->post("/tax-invoices/{$tax->id}/status", ['status' => 'submitted'])->assertSessionHasNoErrors();
        $this->post("/tax-invoices/{$tax->id}/status", ['status' => 'paid', 'paid_on' => '2026-12-15'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $tax->fresh()->status);
        $this->assertSame(2, Invoice::query()->where('status', 'paid')->count());   // paying the tax invoice settles its sub-bills
        $this->delete("/tax-invoices/{$tax->id}")->assertSessionHasErrors('tax');                  // not a draft any more
    }

    public function test_supervisor_cannot_make_tax_invoices(): void
    {
        [$client, $bills] = $this->twoSubBills();
        $this->actingAs(User::factory()->create(['role' => 'supervisor']));
        $this->get('/tax-invoices')->assertOk();
        $this->get('/tax-invoices/create')->assertForbidden();
        $this->post('/tax-invoices', ['gem_contract_id' => $client->id, 'month' => '2026-10', 'sub_bills' => [$bills[0]->id], 'invoice_date' => '2026-11-03'])->assertForbidden();
    }

    public function test_same_post_in_two_projects_is_one_row_with_manpower_added(): void
    {
        $this->seed(DemoSeeder::class);
        $user = User::factory()->create(['role' => 'accounts']);
        $mph = Project::query()->where('code', 'MPH')->firstOrFail();
        $other = $mph->contract->projects()->create(['name' => 'Snakebite', 'code' => 'SNAKE']);
        $post = $other->posts()->create(['designation' => ' academic programme  manager', 'sanctioned_qty' => 1, 'rate' => 55000]);
        Employee::query()->create(['emp_code' => 'EMP-9001', 'name' => 'Field Staff', 'post_id' => $post->id,
            'agency_id' => $mph->contract->agency_id, 'client_id' => $mph->contract->client_id]);
        $service = app(AttendanceService::class);
        $bills = collect([$mph, $other])->map(function ($p) use ($service, $user) {
            $sheet = $service->open($p, CarbonImmutable::parse('2026-10-01'));
            $service->lock($sheet, $user, null);

            return app(InvoiceGenerator::class)->generate($sheet->fresh(), $user);
        });
        $tax = app(TaxInvoiceBuilder::class)->create($mph->contract->fresh(), CarbonImmutable::parse('2026-10-01'), $bills->pluck('id')->all(), $user);

        $p = \App\Http\Controllers\TaxInvoiceController::pdfData($tax)['p'];
        $manager = collect($p['rows'])->firstWhere('designation', 'Academic Programme Manager');
        $this->assertSame(2, $manager['manpower']);
        $this->assertSame('MPH, SNAKE', $manager['project']);
        $this->assertCount(2, $p['rows']);   // manager (2) + assistant (1)
        $this->assertSame(3, $p['manpower']);
    }

    public function test_contract_balance_carries_forward_month_to_month(): void
    {
        [$contract, $bills, $user] = $this->twoSubBills();
        $builder = app(TaxInvoiceBuilder::class);
        $oct = $builder->create($contract, CarbonImmutable::parse('2026-10-01'), [$bills[0]->id], $user);
        $this->assertNull(TaxInvoiceBuilder::contractBalance($oct));   // no tender value entered yet

        $contract->update(['contract_value' => '1000000.00']);
        $nov = TaxInvoice::query()->create(['agency_id' => $contract->agency_id, 'client_id' => $contract->client_id, 'gem_contract_id' => $contract->id,
            'number' => 'ZP/002/26-27', 'month' => '2026-11-01', 'invoice_date' => '2026-11-30', 'taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0,
            'total' => '50000.00', 'rounded_total' => '50000.00', 'status' => 'draft', 'created_by' => $user->id]);

        $first = TaxInvoiceBuilder::contractBalance($oct->fresh());
        $this->assertSame(['1000000.00', '0.00', '1000000.00', $oct->rounded_total], [$first['value'], $first['previous'], $first['opening'], $first['current']]);
        $second = TaxInvoiceBuilder::contractBalance($nov->fresh());
        $this->assertSame($first['closing'], $second['opening']);   // last month's net balance is this month's opening balance
        $this->assertSame(1, $second['count']);
        $this->assertSame(\App\Support\Exact::money(\App\Support\Exact::of($first['closing'])->minus(\App\Support\Exact::of('50000'))), $second['closing']);

        $html = view('pdf.tax-invoice', \App\Http\Controllers\TaxInvoiceController::pdfData($oct->fresh()))->render();
        $this->assertStringContainsString('Net balance of the contract (opening balance for next month)', $html);
        $this->actingAs($user)->get(route('tax-invoices.show', $oct))->assertSee('Contract value position');
    }
}
