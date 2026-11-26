<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Invoice;
use App\Models\TaxInvoice;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Security audit fixes (owner, 26 Nov 2026): M2 agency bank details by Super Admin only; M3 "Mark paid" by Super Admin only. */
class SecurityAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private Agency $zps;

    private User $owner;

    private User $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->zps = Agency::query()->where('code', 'ZPS')->firstOrFail();
        $this->zps->update(['bank_name' => 'Test Bank', 'bank_account_no' => '10000000001', 'bank_ifsc' => 'SBIN0000001', 'bank_corp_id' => '111111']);
        $this->owner = $this->makeDeveloper(User::factory()->create(['role' => 'admin']));
        $this->accounts = User::factory()->create(['role' => 'accounts', 'agency_id' => $this->zps->id]);
    }

    private function agencyForm(array $extra = []): array
    {
        return array_merge(['name' => $this->zps->name, 'code' => 'ZPS', 'address' => 'New address line'], $extra);
    }

    public function test_accounts_can_edit_the_agency_but_not_its_bank_details(): void
    {
        $this->actingAs($this->accounts)->get(route('agencies.edit', $this->zps))->assertOk()
            ->assertSee('can be changed only by a <b>Super Admin</b>', false)->assertSee('<fieldset disabled', false);

        // Other details save; the bank fields are not sent (read-only) and stay as they are.
        $this->put(route('agencies.update', $this->zps), $this->agencyForm())->assertSessionHasNoErrors();
        $fresh = $this->zps->fresh();
        $this->assertSame('New address line', $fresh->address);
        $this->assertSame('10000000001', $fresh->bank_account_no);
        $this->assertSame('111111', $fresh->bank_corp_id);

        // Sending the same values (e.g. an old form) is fine; a different account number is refused and nothing changes.
        $this->put(route('agencies.update', $this->zps), $this->agencyForm(['bank_account_no' => '10000000001']))->assertSessionHasNoErrors();
        $this->put(route('agencies.update', $this->zps), $this->agencyForm(['bank_account_no' => '99999999999', 'address' => 'Changed again']))
            ->assertSessionHasErrors('bank_account_no');
        $this->assertSame('10000000001', $this->zps->fresh()->bank_account_no);
        $this->assertSame('New address line', $this->zps->fresh()->address);
        $this->put(route('agencies.update', $this->zps), $this->agencyForm(['bank_corp_id' => '222222']))->assertSessionHasErrors('bank_corp_id');
        $this->assertSame('111111', $this->zps->fresh()->bank_corp_id);
    }

    public function test_a_super_admin_changes_the_bank_details_and_it_is_logged(): void
    {
        $this->actingAs($this->owner)->get(route('agencies.edit', $this->zps))->assertOk()->assertDontSee('<fieldset disabled', false);
        $this->put(route('agencies.update', $this->zps), $this->agencyForm(['bank_name' => 'Other Bank', 'bank_account_no' => '20000000002', 'bank_ifsc' => 'CNRB0000002']))
            ->assertSessionHasNoErrors();
        $this->assertSame('20000000002', $this->zps->fresh()->bank_account_no);
        $this->assertTrue(\App\Models\ActivityLog::query()->where('action', 'updated')->get()
            ->contains(fn ($l) => is_array($l->changes) && array_key_exists('bank_account_no', $l->changes)), 'the change is in the Activity log');
    }

    public function test_only_a_super_admin_marks_a_bill_paid(): void
    {
        $this->artisan('demo:full-month', ['month' => '2026-10', '--force' => true])->assertSuccessful();
        Invoice::query()->update(['status' => 'submitted']);
        TaxInvoice::query()->update(['status' => 'submitted']);
        $bill = Invoice::query()->firstOrFail();
        $tax = TaxInvoice::query()->firstOrFail();

        $this->actingAs($this->accounts)->get(route('invoices.show', $bill))->assertOk()
            ->assertSee('Only a Super Admin can mark it paid by hand')->assertDontSee('>Mark paid</button>', false);
        $this->post(route('invoices.status', $bill), ['status' => 'paid', 'paid_on' => '2026-11-20'])->assertSessionHasErrors('status');
        $this->post(route('tax-invoices.status', $tax), ['status' => 'paid', 'paid_on' => '2026-11-20'])->assertSessionHasErrors('status');
        $this->assertSame('submitted', $bill->fresh()->status);
        $this->assertSame('submitted', $tax->fresh()->status);

        $this->actingAs($this->owner)->get(route('invoices.show', $bill))->assertSee('Mark paid');
        $this->post(route('tax-invoices.status', $tax), ['status' => 'paid', 'paid_on' => '2026-11-20'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $tax->fresh()->status);
        $this->assertSame('paid', $bill->fresh()->status, 'its sub-bills are settled too');
    }
}
