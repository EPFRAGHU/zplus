<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\TaxInvoice;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sub-bills grouped by GeM contract in serial order; tax invoices in serial order with their sub-bills; one-page PDF (owner, 22 Nov 2026). */
class BillListsOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $first = GemContract::query()->firstOrFail();
        $person = Employee::query()->whereNotNull('post_id')->firstOrFail();
        // A second work order of the same agency and client, with two projects (so it has two sub-bills).
        $second = GemContract::query()->create(['agency_id' => $first->agency_id, 'client_id' => $first->client_id, 'bid_no' => 'GEM/2', 'contract_no' => 'GEMC-000000000000002']);
        foreach (['AAA', 'BBB'] as $i => $code) {
            $post = $second->projects()->create(['name' => 'Project '.$code, 'code' => $code])
                ->posts()->create(['designation' => 'Field Worker', 'sanctioned_qty' => 1, 'rate' => 20000]);
            Employee::query()->create(['emp_code' => 'ZP-80'.$i, 'name' => 'Worker '.$code, 'agency_id' => $person->agency_id, 'client_id' => $person->client_id,
                'post_id' => $post->id, 'status' => 'active']);
        }
        $this->dev = $this->makeDeveloper(User::factory()->create(['role' => 'admin']));
        $this->artisan('demo:full-month', ['month' => '2026-10', '--force' => true])->assertSuccessful();
    }

    public function test_sub_bills_are_grouped_by_contract_in_serial_order(): void
    {
        $html = $this->actingAs($this->dev)->get('/invoices')->assertOk()->getContent();
        $numbers = Invoice::query()->orderBy('invoice_no')->pluck('invoice_no')->all();
        $this->assertCount(3, $numbers);
        $positions = array_map(fn ($n) => strpos($html, '>'.$n.'</a>'), $numbers);
        $this->assertSame($positions, collect($positions)->sort()->values()->all(), 'bills listed in serial order');
        $this->assertSame(2, substr_count($html, 'contract-group'), 'one group row per work order');
        $this->assertStringContainsString('2 sub-bills', $html);
        // Components in columns, a total row per work order and the total of everything listed.
        $this->assertStringContainsString('<th class="num">Service charge</th>', $html);
        $this->assertSame(2, substr_count($html, 'bills-group-total'));
        $this->assertStringContainsString('Total of all 3 sub-bills listed', $html);
        // Staff column: employees per bill (click for names), added up in the totals.
        $this->assertStringContainsString('<th>Month</th><th class="text-center">Staff</th>', $html);
        $this->assertStringContainsString('Staff on ZP/001/26-27(AAA)', $html);
        $this->assertStringContainsString('<td class="text-center">'.\App\Models\InvoiceLine::query()->count().'</td>', $html);
        $this->assertStringContainsString(inr((float) Invoice::query()->sum('rounded_total'), true), $html);
        $this->assertStringContainsString(inr((float) Invoice::query()->sum('service_charge'), true), $html);
    }

    public function test_tax_invoices_in_serial_order_with_clickable_sub_bill_count(): void
    {
        $html = $this->actingAs($this->dev)->get('/tax-invoices')->assertOk()->getContent();
        $numbers = TaxInvoice::query()->pluck('number')->sort(fn ($a, $b) => strnatcmp($a, $b))->values()->all();
        $this->assertCount(2, $numbers);
        $this->assertLessThan(strpos($html, '>'.$numbers[1].'</a>'), strpos($html, '>'.$numbers[0].'</a>'));
        $this->assertStringContainsString('<th>Tax invoice no.</th><th class="text-center">Sub-bills</th>', $html);
        $this->assertStringContainsString('data-pop', $html);
        $two = TaxInvoice::query()->withCount('subBills')->get()->firstWhere('sub_bills_count', 2);
        $this->assertStringContainsString('Sub-bills in '.$two->number, $html);
        $this->assertStringContainsString('<th class="num">CGST</th><th class="num">SGST</th>', $html);
        $this->assertStringContainsString('Total of all 2 tax invoices listed', $html);
        $this->assertStringContainsString(inr((float) TaxInvoice::query()->sum('rounded_total'), true), $html);
    }

    public function test_tax_invoice_pdf_with_many_posts_stays_on_one_page(): void
    {
        $contract = GemContract::query()->where('contract_no', 'GEMC-000000000000002')->firstOrFail();
        $contract->update(['contract_value' => '9000000', 'start_date' => '2026-10-01', 'end_date' => '2027-09-30']);   // the balance box prints too
        $tax = TaxInvoice::query()->where('gem_contract_id', $contract->id)->firstOrFail();
        // 18 more posts on the same tax invoice's sub-bill lines would be slow to build; instead render with many particulars rows.
        $bill = $tax->subBills()->firstOrFail();
        foreach (range(1, 18) as $i) {
            $line = $bill->lines()->firstOrFail()->replicate();
            $line->designation = 'Extra Post '.$i;
            $line->save();
        }
        app(\App\Services\Billing\TaxInvoiceBuilder::class)->refresh($tax->fresh());
        $this->assertGreaterThanOrEqual(19, count(\App\Http\Controllers\TaxInvoiceController::pdfData($tax->fresh())['p']['rows']), 'many particulars rows');
        $pdf = $this->actingAs($this->dev)->get(route('tax-invoices.pdf', $tax))->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'one page');
    }

    public function test_month_zip_has_a_folder_per_work_order_and_each_work_order_has_its_own_zip(): void
    {
        $this->actingAs($this->dev);
        $agency = \App\Models\Agency::query()->where('code', 'ZPS')->firstOrFail();
        $second = GemContract::query()->where('contract_no', 'GEMC-000000000000002')->firstOrFail();
        $entries = function (string $path): array {
            $zip = new \ZipArchive;
            $zip->open($path);
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = $zip->getNameIndex($i);
            }
            $zip->close();
            @unlink($path);
            sort($names);

            return $names;
        };
        $docs = app(\App\Services\Billing\MonthDocuments::class);

        [$path, $name] = $docs->zip($agency, \Carbon\CarbonImmutable::parse('2026-10-01'), false);
        $all = $entries($path);
        $this->assertContains('GEMC-000000000000002/sub-bills/ZP-001-26-27-AAA.pdf', $all);
        $this->assertContains('GEMC-000000000000002/tax-invoice/ZP-001-26-27.pdf', $all);
        $this->assertContains('GEMC-511687731718030/sub-bills/ZP-002-26-27-MPH.pdf', $all);

        [$path, $name] = $docs->zip($agency, \Carbon\CarbonImmutable::parse('2026-10-01'), true, $second);
        $one = $entries($path);
        $this->assertSame('ZP-GEMC-000000000000002-documents-2026-10.zip', $name);
        $this->assertSame(['attendance-sheets/attendance-ZP-001-26-27-AAA.pdf', 'attendance-sheets/attendance-ZP-001-26-27-BBB.pdf',
            'covering-letters/covering-letter-ZP-001-26-27-AAA.pdf', 'covering-letters/covering-letter-ZP-001-26-27-BBB.pdf',
            'sub-bills/ZP-001-26-27-AAA.pdf', 'sub-bills/ZP-001-26-27-BBB.pdf', 'tax-invoice/ZP-001-26-27.pdf'], $one, 'only this work order with its attendance sheets, no bank letter');

        // The PI's signed certificate goes in too when it was uploaded on the attendance sheet.
        \Illuminate\Support\Facades\Storage::disk('local')->put('certificates/aaa.pdf', '%PDF-1.4 signed');
        \App\Models\AttendanceSheet::query()->whereHas('project', fn ($q) => $q->where('code', 'AAA'))->update(['certificate_path' => 'certificates/aaa.pdf']);
        [$path] = $docs->zip($agency, \Carbon\CarbonImmutable::parse('2026-10-01'), false, $second);
        $this->assertContains('attendance-sheets/attendance-ZP-001-26-27-AAA-signed-by-PI.pdf', $entries($path));

        $this->get('/invoices?month=2026-10&agency='.$agency->id)->assertSee(route('month-documents.zip', ['agency' => $agency->id, 'month' => '2026-10', 'contract' => $second->id]));
        $this->get(route('month-documents.zip', ['agency' => $agency->id, 'month' => '2026-10', 'contract' => $second->id]))->assertOk()->assertHeader('content-type', 'application/zip');
    }
}
