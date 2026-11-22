<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Services\Payments\Receipts;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Payments received from the client: deductions per sub-bill, maker–checker, sub-bills turning Paid, registers (owner, 22 Nov 2026). */
class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $accounts;

    private User $owner;

    private User $secondOwner;

    private GemContract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $first = GemContract::query()->firstOrFail();
        $person = Employee::query()->whereNotNull('post_id')->firstOrFail();
        $this->contract = GemContract::query()->create(['agency_id' => $first->agency_id, 'client_id' => $first->client_id, 'bid_no' => 'GEM/2', 'contract_no' => 'GEMC-000000000000002']);
        foreach (['AAA', 'BBB'] as $i => $code) {
            $post = $this->contract->projects()->create(['name' => 'Project '.$code, 'code' => $code])->posts()->create(['designation' => 'Field Worker', 'sanctioned_qty' => 1, 'rate' => 20000]);
            Employee::query()->create(['emp_code' => 'ZP-80'.$i, 'name' => 'Worker '.$code, 'agency_id' => $person->agency_id, 'client_id' => $person->client_id, 'post_id' => $post->id, 'status' => 'active']);
        }
        $this->owner = $this->makeDeveloper(User::factory()->create(['role' => 'admin', 'name' => 'Owner']));
        $this->secondOwner = User::factory()->create(['role' => 'admin', 'name' => 'Second owner']);
        $this->secondOwner->forceFill(['is_super_admin' => true])->save();
        $this->accounts = User::factory()->create(['role' => 'accounts', 'agency_id' => $first->agency_id, 'name' => 'Accounts ZPS']);
        $this->artisan('demo:full-month', ['month' => '2026-10', '--force' => true])->assertSuccessful();
        Invoice::query()->update(['status' => 'submitted']);   // sent to the client
        TaxInvoice::query()->update(['status' => 'submitted']);
    }

    private function bills()
    {
        return Invoice::query()->where('gem_contract_id', $this->contract->id)->orderBy('invoice_no')->get();
    }

    /** Form lines with the expected deductions, as the entry page fills them in. */
    private function lines(iterable $bills, array $override = []): array
    {
        $out = [];
        foreach ($bills as $b) {
            $out[$b->id] = ['pick' => 1] + array_merge(app(Receipts::class)->expected($b), $override);
        }

        return $out;
    }

    private function enter(array $lines, array $fields = []): \Illuminate\Testing\TestResponse
    {
        $amount = collect($lines)->sum(fn ($l) => (float) $l['received']);

        return $this->post(route('payments.store'), array_merge(['gem_contract_id' => $this->contract->id, 'mode' => 'neft', 'utr_no' => 'SBIN226001234567',
            'transfer_date' => '2026-11-20', 'credit_date' => '2026-11-21', 'bank_account' => 'SBI A/c ****1234', 'amount' => number_format($amount, 2, '.', ''), 'lines' => $lines], $fields));
    }

    public function test_expected_deductions_are_two_percent_tds_and_two_percent_gst_tds_on_the_value_before_gst(): void
    {
        $bill = $this->bills()->first();
        $e = app(Receipts::class)->expected($bill);
        $taxable = (float) $bill->taxable;
        $this->assertSame(number_format(round($taxable * 0.02), 2, '.', ''), $e['tds_it']);
        $this->assertSame(number_format(round($taxable * 0.01), 2, '.', ''), $e['tds_cgst']);
        $this->assertSame($e['tds_cgst'], $e['tds_sgst']);
        $this->assertSame('0.00', $e['tds_igst']);
        $this->assertEqualsWithDelta((float) $bill->rounded_total - (float) $e['tds_it'] - 2 * (float) $e['tds_cgst'], (float) $e['received'], 0.001);
    }

    public function test_maker_enters_owner_approves_and_the_sub_bills_and_tax_invoice_turn_paid(): void
    {
        $bills = $this->bills();
        $this->actingAs($this->accounts)->get(route('payments.create', ['contract' => $this->contract->id]))->assertOk()
            ->assertSee($bills[0]->invoice_no)->assertSee($bills[1]->invoice_no)->assertSee('TDS 2%');
        $this->enter($this->lines($bills), ['advice' => UploadedFile::fake()->create('advice.pdf', 30, 'application/pdf')])->assertSessionHasNoErrors()->assertRedirect();
        $receipt = PaymentReceipt::query()->firstOrFail();
        $this->assertSame('pending', $receipt->status);
        $this->assertCount(2, $receipt->lines);
        $this->assertSame('submitted', $bills[0]->fresh()->status, 'nothing counts before approval');

        // Accounts cannot approve; the Super Admin can.
        $this->post(route('payments.approve', $receipt))->assertSessionHasErrors('receipt');
        $this->actingAs($this->owner)->get('/approvals')->assertSee('Payments received waiting for approval')->assertSee('SBIN226001234567');
        $this->post(route('payments.approve', $receipt))->assertSessionHasNoErrors();
        $this->assertSame('approved', $receipt->fresh()->status);
        foreach ($bills as $b) {
            $this->assertSame('paid', $b->fresh()->status);
            $this->assertSame('2026-11-21', $b->fresh()->paid_on->toDateString());
        }
        $tax = TaxInvoice::query()->where('gem_contract_id', $this->contract->id)->firstOrFail();
        $this->assertSame('paid', $tax->status);

        $this->get(route('payments.show', $receipt))->assertOk()->assertSee('Save TDS ticks')->assertSee('advice.pdf');
        $this->get(route('payments.advice', $receipt))->assertOk();
        $this->get(route('invoices.show', $bills[0]))->assertOk()->assertSee('Payments received')->assertSee('SBIN226001234567')->assertSee('Nothing due');
        $this->get(route('tax-invoices.show', $tax))->assertOk()->assertSee('SBIN226001234567');
        $this->get(route('payments.index', ['tab' => 'outstanding']))->assertOk()->assertDontSee($bills[0]->invoice_no);
        $this->get(route('payments.edit', $receipt))->assertRedirect(route('payments.show', $receipt));
    }

    public function test_part_payment_leaves_the_rest_due_until_a_second_payment(): void
    {
        $bill = $this->bills()->first();
        $e = app(Receipts::class)->expected($bill);
        $half = number_format(floor((float) $e['received'] / 2), 2, '.', '');
        $this->actingAs($this->accounts);
        $this->enter([$bill->id => ['pick' => 1] + array_merge($e, ['received' => $half])])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('payments.approve', PaymentReceipt::query()->firstOrFail()))->assertSessionHasNoErrors();
        $this->assertSame('submitted', $bill->fresh()->status, 'part paid stays open');
        $this->get(route('payments.index', ['tab' => 'outstanding']))->assertOk()->assertSee($bill->invoice_no);

        // The second payment: the deductions were already taken, so only the rest is suggested.
        $rest = app(Receipts::class)->expected($bill->fresh());
        $this->assertSame('0.00', $rest['tds_it']);
        $this->assertEqualsWithDelta((float) $e['received'] - (float) $half, (float) $rest['received'], 0.001);
        $this->actingAs($this->accounts);
        $this->enter([$bill->id => ['pick' => 1] + $rest], ['utr_no' => 'SECOND1'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('payments.approve', PaymentReceipt::query()->where('utr_no', 'SECOND1')->firstOrFail()))->assertSessionHasNoErrors();
        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertSame('submitted', TaxInvoice::query()->where('gem_contract_id', $this->contract->id)->value('status'), 'the other sub-bill of the tax invoice is unpaid');
    }

    public function test_wrong_entries_are_refused(): void
    {
        $bills = $this->bills();
        $this->actingAs($this->accounts);
        $this->enter($this->lines($bills), ['amount' => '1.00'])->assertSessionHasErrors('receipt');   // amount ≠ total received
        $this->enter($this->lines([$bills[0]], ['received' => (string) ((float) $bills[0]->rounded_total + 50)]))->assertSessionHasErrors('receipt');   // more than due
        $this->enter($this->lines([$bills[0]], ['other_deduction' => '100.00']))->assertSessionHasErrors('receipt');   // reason missing
        $other = Invoice::query()->where('gem_contract_id', '!=', $this->contract->id)->firstOrFail();
        $this->enter([$other->id => ['pick' => 1, 'received' => '10']])->assertSessionHasErrors('receipt');   // another work order's bill
        $this->enter($this->lines([$bills[0]]), ['mode' => 'cheque', 'utr_no' => ''])->assertSessionHasErrors(['cheque_no', 'cheque_date']);
        $this->enter($this->lines([$bills[0]]), ['credit_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('credit_date');
        $this->assertSame(0, PaymentReceipt::query()->count());

        // A Super Admin cannot approve a payment they entered (four-eyes).
        $this->actingAs($this->owner);
        $this->enter($this->lines([$bills[0]]))->assertSessionHasNoErrors();
        $this->post(route('payments.approve', PaymentReceipt::query()->firstOrFail()))->assertSessionHasErrors('receipt');
        $this->actingAs($this->secondOwner)->post(route('payments.approve', PaymentReceipt::query()->firstOrFail()))->assertSessionHasNoErrors();
    }

    public function test_return_change_and_delete(): void
    {
        $bills = $this->bills();
        $this->actingAs($this->accounts);
        $this->enter($this->lines($bills))->assertSessionHasNoErrors();
        $receipt = PaymentReceipt::query()->firstOrFail();
        $this->actingAs($this->owner)->post(route('payments.return', $receipt), ['note' => 'UTR does not match the statement'])->assertSessionHasNoErrors();
        $this->assertSame('returned', $receipt->fresh()->status);

        $this->actingAs($this->accounts)->get(route('payments.edit', $receipt))->assertOk()->assertSee('UTR does not match the statement');
        $this->put(route('payments.update', $receipt), ['mode' => 'neft', 'utr_no' => 'FIXED123', 'credit_date' => '2026-11-21',
            'amount' => number_format(collect($this->lines($bills))->sum(fn ($l) => (float) $l['received']), 2, '.', ''), 'lines' => $this->lines($bills)])->assertSessionHasNoErrors();
        $this->assertSame('pending', $receipt->fresh()->status);
        $this->assertSame('FIXED123', $receipt->fresh()->utr_no);

        $this->actingAs($this->owner)->post(route('payments.approve', $receipt))->assertSessionHasNoErrors();
        $this->assertSame('paid', $bills[0]->fresh()->status);

        // Paid sub-bills with a payment cannot be deleted; deleting the payment (Super Admin only) reopens them.
        $this->actingAs($this->accounts)->delete(route('payments.destroy', $receipt))->assertForbidden();
        $this->actingAs($this->owner)->delete(route('payments.destroy', $receipt))->assertRedirect(route('payments.index'));
        $this->assertSame(0, PaymentReceipt::query()->count());
        $this->assertSame('submitted', $bills[0]->fresh()->status);
        $this->assertNull($bills[0]->fresh()->paid_on);
        $this->assertSame('submitted', TaxInvoice::query()->where('gem_contract_id', $this->contract->id)->value('status'));
    }

    public function test_a_sub_bill_with_a_payment_cannot_be_deleted_or_reset(): void
    {
        $bill = $this->bills()->first();
        $this->actingAs($this->accounts);
        $this->enter($this->lines([$bill]))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->delete(route('invoices.destroy', $bill))->assertSessionHasErrors('invoice');
        $this->assertNotNull($bill->fresh());
        $this->expectException(\RuntimeException::class);
        app(\App\Services\Attendance\AttendanceService::class)->reset($bill->sheet);
    }

    public function test_registers_tds_ticks_and_downloads(): void
    {
        $bills = $this->bills();
        $this->actingAs($this->accounts);
        $this->enter($this->lines($bills))->assertSessionHasNoErrors();
        $receipt = PaymentReceipt::query()->firstOrFail();
        $this->actingAs($this->owner)->post(route('payments.approve', $receipt));

        $q = ['from' => '2026-11-01', 'to' => '2026-11-30'];
        $this->get(route('payments.index', $q))->assertOk()->assertSee('SBIN226001234567')->assertSee('Total of approved payments (1)');
        $this->get(route('payments.index', $q + ['tab' => 'tds']))->assertOk()->assertSee($bills[0]->invoice_no);
        $line = $receipt->lines()->first();
        $this->post(route('payments.checks', $receipt), ['form16a' => [$line->id], 'gstr7a' => []])->assertSessionHasNoErrors();
        $this->assertTrue($line->fresh()->form16a_seen);
        $this->assertFalse($line->fresh()->gstr7a_accepted);
        $this->get(route('payments.index', $q + ['tab' => 'tds', 'pending' => 1]))->assertOk()->assertSee($bills[0]->invoice_no);

        foreach (['receipts', 'outstanding', 'tds'] as $tab) {
            $this->get(route('payments.export', ['tab' => $tab, 'format' => 'pdf'] + $q))->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->get(route('payments.export', ['tab' => $tab, 'format' => 'xlsx'] + $q))->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        $xlsx = $this->get(route('payments.export', ['tab' => 'receipts', 'format' => 'xlsx'] + $q))->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'pr').'.xlsx';
        file_put_contents($path, $xlsx);
        $ws = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);
        $this->assertSame('SBIN226001234567', $ws->getCell('E4')->getValue());
        $this->assertEqualsWithDelta((float) $receipt->amount, (float) $ws->getCell('M4')->getValue(), 0.001);
        $this->get(route('payments.export', ['tab' => 'nope', 'format' => 'pdf']))->assertNotFound();
    }

    public function test_logins_without_billing_rights_are_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'supervisor']));
        $this->get(route('payments.index'))->assertForbidden();
        $this->get(route('payments.create'))->assertForbidden();
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'accounts', 'agency_id' => $swh->id]))
            ->get(route('payments.create', ['contract' => $this->contract->id]))->assertNotFound();
    }
}
