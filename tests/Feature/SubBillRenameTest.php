<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TaxInvoice;
use App\Models\User;
use App\Services\Approvals\Approvals;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A new short project name renames the project's open sub-bills, keeping the serial (owner, 22 Nov 2026). */
class SubBillRenameTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private Project $mph;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->dev = $this->makeDeveloper(User::factory()->create(['role' => 'admin']));
        $this->artisan('demo:full-month', ['month' => '2026-10', '--force' => true])->assertSuccessful();   // ZP/001/26-27(MPH) + tax invoice
        $this->mph = Project::query()->where('code', 'MPH')->firstOrFail();
    }

    private function rename(string $code)
    {
        return $this->actingAs($this->dev)->put(route('projects.update', $this->mph), ['name' => $this->mph->name, 'code' => $code]);
    }

    public function test_open_sub_bill_takes_the_new_short_name_and_keeps_its_serial(): void
    {
        $bill = Invoice::query()->where('project_id', $this->mph->id)->firstOrFail();
        $this->assertSame('ZP/001/26-27(MPH)', $bill->invoice_no);

        $this->rename('mphacad')->assertSessionHas('status', fn ($s) => str_contains($s, 'Sub-bills renamed: ZP/001/26-27(MPH) → ZP/001/26-27(MPHACAD)'));
        $bill->refresh();
        $this->assertSame('ZP/001/26-27(MPHACAD)', $bill->invoice_no);
        $this->assertNotNull($bill->tax_invoice_id, 'still in its tax invoice');
        $this->assertSame('ZP/001/26-27', TaxInvoice::query()->findOrFail($bill->tax_invoice_id)->number, 'tax invoice number unchanged');
        $this->assertTrue(ActivityLog::query()->where('note', 'like', 'Sub-bill renamed: ZP/001/26-27(MPH) → ZP/001/26-27(MPHACAD)%')->exists());
        // Its approval covered the old number, so it needs approving again.
        $this->assertSame('changed', app(Approvals::class)->state('sub_bill', $bill));
    }

    public function test_paid_bills_keep_their_number(): void
    {
        $bill = Invoice::query()->where('project_id', $this->mph->id)->firstOrFail();
        $bill->update(['status' => 'paid']);

        $this->rename('NEWCODE')->assertSessionHas('status', fn ($s) => str_contains($s, 'Paid bills keep their number: ZP/001/26-27(MPH)'));
        $this->assertSame('ZP/001/26-27(MPH)', $bill->fresh()->invoice_no);
    }

    public function test_short_name_with_a_slash_is_refused(): void
    {
        $this->rename('SW/007/26-27')->assertSessionHasErrors('code');
        $this->assertSame('MPH', $this->mph->fresh()->code);
        $this->assertSame('ZP/001/26-27(MPH)', Invoice::query()->where('project_id', $this->mph->id)->value('invoice_no'));
    }

    public function test_saving_without_changing_the_short_name_renames_nothing(): void
    {
        $this->rename('MPH')->assertSessionHas('status', 'Project saved.');
        $this->assertSame('ZP/001/26-27(MPH)', Invoice::query()->where('project_id', $this->mph->id)->value('invoice_no'));
    }
}
