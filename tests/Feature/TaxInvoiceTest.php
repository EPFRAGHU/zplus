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

    /** October: the MPH sub-bill (₹89,311 before GST) plus a second project under the same client. */
    private function twoSubBills(): array
    {
        $this->seed(DemoSeeder::class);
        $user = User::factory()->create(['role' => 'accounts']);
        $mph = Project::query()->where('code', 'MPH')->firstOrFail();
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

        return [$mph->contract->client, $bills, $user];
    }

    public function test_tax_invoice_combines_the_months_sub_bills(): void
    {
        [$client, $bills, $user] = $this->twoSubBills();
        $builder = app(TaxInvoiceBuilder::class);

        $this->assertCount(2, $builder->pending($client, CarbonImmutable::parse('2026-10-01')));
        $tax = $builder->create($client, CarbonImmutable::parse('2026-10-01'), collect($bills)->pluck('id')->all(), $user);

        // MPH 89,311.00 + Snakebite 33,600 x 1.0385 = 34,893.60 -> 1,24,204.60 before GST
        $this->assertSame('ICMR/TI/01/2026', $tax->number);
        $this->assertSame('124204.60', $tax->taxable);
        $this->assertSame('11178.41', $tax->cgst);         // 8,037.99 + 3,140.42: GST worked out per sub-bill line, as on the sheet
        $this->assertSame('146561.42', $tax->total);
        $this->assertSame('146561.00', $tax->rounded_total);
        $this->assertSame(2, $tax->subBills()->count());
        $this->assertCount(0, $builder->pending($client, CarbonImmutable::parse('2026-10-01')));
    }

    public function test_single_project_tax_invoice_matches_the_october_icmr_invoice(): void
    {
        [$client, $bills, $user] = $this->twoSubBills();
        $tax = app(TaxInvoiceBuilder::class)->create($client, CarbonImmutable::parse('2026-10-01'), [$bills[0]->id], $user, 'ICMR/MPH/01/26', '2026-11-01');

        $this->assertSame('ICMR/MPH/01/26', $tax->number);
        $this->assertSame('89311.00', $tax->taxable);
        $this->assertSame('8037.99', $tax->cgst);
        $this->assertSame('8037.99', $tax->sgst);
        $this->assertSame('105387.00', $tax->rounded_total);
        $this->assertSame('Rupees One Lakh Five Thousand Three Hundred Eighty Seven Only', $tax->amountInWords());
    }

    public function test_sub_bill_can_only_be_in_one_tax_invoice_of_its_own_client_and_month(): void
    {
        [$client, $bills, $user] = $this->twoSubBills();
        $builder = app(TaxInvoiceBuilder::class);
        $builder->create($client, CarbonImmutable::parse('2026-10-01'), [$bills[0]->id], $user);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already in a tax invoice');
        $builder->create($client, CarbonImmutable::parse('2026-10-01'), [$bills[0]->id], $user);
    }

    public function test_wrong_month_and_wrong_client_are_refused(): void
    {
        [$client, $bills, $user] = $this->twoSubBills();
        $builder = app(TaxInvoiceBuilder::class);
        try {
            $builder->create($client, CarbonImmutable::parse('2026-11-01'), [$bills[0]->id], $user);
            $this->fail('Different month accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('different month', $e->getMessage());
        }
        $other = Client::query()->where('id', '!=', $client->id)->firstOrFail();
        $this->expectExceptionMessage('different client');
        $builder->create($other, CarbonImmutable::parse('2026-10-01'), [$bills[0]->id], $user);
    }

    public function test_deleting_a_draft_tax_invoice_releases_its_sub_bills(): void
    {
        [$client, $bills, $user] = $this->twoSubBills();
        $builder = app(TaxInvoiceBuilder::class);
        $tax = $builder->create($client, CarbonImmutable::parse('2026-10-01'), collect($bills)->pluck('id')->all(), $user);

        $builder->delete($tax);
        $this->assertSame(0, Invoice::query()->whereNotNull('tax_invoice_id')->count());
        $this->assertCount(2, $builder->pending($client, CarbonImmutable::parse('2026-10-01')));
    }

    public function test_tax_invoice_screens_pdf_and_payment(): void
    {
        [$client, $bills, $user] = $this->twoSubBills();

        $this->actingAs($user)->get('/invoices')->assertOk()->assertSee('Make tax invoice');
        $this->get('/tax-invoices/create?client='.$client->id.'&month=2026-10')->assertOk()
            ->assertSee($bills[0]->invoice_no)->assertSee($bills[1]->invoice_no)->assertSee('ICMR/TI/01/2026');
        $this->post('/tax-invoices', ['client_id' => $client->id, 'month' => '2026-10', 'sub_bills' => [$bills[0]->id, $bills[1]->id],
            'number' => 'ICMR/TI/01/2026', 'invoice_date' => '2026-11-03'])->assertRedirect();

        $tax = TaxInvoice::query()->firstOrFail();
        $this->get('/tax-invoices')->assertOk()->assertSee('ICMR/TI/01/2026')->assertSee('client-block', false);
        $this->get("/tax-invoices/{$tax->id}")->assertOk()->assertSee('₹1,46,561.00')->assertSee('Bank details are missing');
        $this->get("/tax-invoices/{$tax->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');

        $html = view('pdf.tax-invoice', ['tax' => $tax->load('subBills.project', 'subBills.contract'), 'agency' => $tax->agency, 'client' => $tax->client])->render();
        $this->assertStringContainsString('TAX INVOICE', $html);
        $this->assertStringContainsString('Providing Manpower – MPH', $html);
        $this->assertStringContainsString('Providing Manpower – Snakebite', $html);
        $this->assertStringContainsString('998514', $html);
        $this->assertStringContainsString('Reverse Charge (Y/N): N', $html);
        $this->assertStringContainsString('Place of Supply: Odisha', $html);
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
        $this->post('/tax-invoices', ['client_id' => $client->id, 'month' => '2026-10', 'sub_bills' => [$bills[0]->id], 'invoice_date' => '2026-11-03'])->assertForbidden();
    }
}
