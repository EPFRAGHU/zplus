<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Approval;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\TaxInvoiceBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Maker–checker (v2.14.0): from October 2026 the owner (Super Admin) approves attendance, bills and salary payouts. */
class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $owner2;

    private User $operator;

    private User $accounts;

    private array $r;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = User::factory()->create(['role' => 'admin', 'name' => 'Owner One']);
        $this->makeDeveloper($this->owner);
        $this->owner2 = User::factory()->create(['role' => 'admin', 'name' => 'Owner Two']);
        $this->owner2->forceFill(['is_super_admin' => true])->save();
        $this->operator = User::factory()->create(['role' => 'supervisor', 'name' => 'Operator']);
        $this->accounts = User::factory()->create(['role' => 'accounts', 'name' => 'Accounts']);
        $this->actingAs($this->owner);
        $agency = Agency::query()->create(['code' => 'SWH', 'name' => 'SWACHHA']);
        $client = Client::query()->create(['agency_id' => $agency->id, 'name' => 'ICMR', 'short_code' => 'ICMR', 'email' => 'icmr@example.gov.in']);
        $contract = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => 'GEM/1', 'contract_no' => 'GEMC-1',
            'epf_percent' => '13', 'esic_percent' => '3.25', 'service_charge_percent' => '3.85', 'gst_percent' => '18']);
        $project = $contract->projects()->create(['name' => 'Lab', 'code' => 'LAB']);
        $post = $project->posts()->create(['designation' => 'Analyst', 'sanctioned_qty' => 1, 'rate' => 30000]);
        $employee = Employee::query()->create(['emp_code' => 'SW-1001', 'name' => 'Staff One', 'agency_id' => $agency->id, 'client_id' => $client->id,
            'post_id' => $post->id, 'status' => 'active', 'bank_name' => 'SBI', 'account_number' => '12345678901', 'ifsc' => 'SBIN0000001']);
        $this->r = compact('agency', 'client', 'contract', 'project', 'post', 'employee');
    }

    private function lockAs(User $user, string $month)
    {
        $sheet = app(AttendanceService::class)->open($this->r['project'], CarbonImmutable::parse($month));
        $this->actingAs($user)->post(route('attendance.lock', $sheet), ['certificate' => UploadedFile::fake()->create('signed.pdf', 20, 'application/pdf')])->assertRedirect();

        return $sheet->fresh();
    }

    /** Operator locks October → owner approves → sub-bill made → Accounts sends it → owner approves → it can go out. */
    private function approvedBill(): Invoice
    {
        $sheet = $this->lockAs($this->operator, '2026-10-01');
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->latest('id')->first()));
        $bill = Invoice::query()->where('attendance_sheet_id', $sheet->id)->firstOrFail();
        $this->actingAs($this->accounts)->post(route('approvals.send.invoice', $bill));
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->latest('id')->first()));

        return $bill->fresh();
    }

    public function test_locked_october_month_waits_for_the_owner_and_approval_makes_the_sub_bill(): void
    {
        $sheet = $this->lockAs($this->operator, '2026-10-01');
        $this->assertSame(0, Invoice::query()->count());
        $approval = Approval::query()->sole();
        $this->assertSame(['attendance', 'pending', $this->operator->id], [$approval->type, $approval->status, $approval->submitted_by]);
        $this->actingAs($this->accounts)->post(route('invoices.store'), ['attendance_sheet_id' => $sheet->id])->assertSessionHasErrors('invoice');
        $this->assertSame(0, Invoice::query()->count());

        $this->actingAs($this->owner)->get(route('approvals.index'))->assertOk()->assertSee('Waiting for your approval')->assertSee('Attendance Lab (LAB), October 2026');
        $this->actingAs($this->accounts)->post(route('approvals.approve', $approval))->assertForbidden();   // not an owner
        $this->actingAs($this->owner)->post(route('approvals.approve', $approval))->assertSessionHas('status', fn ($s) => str_contains($s, 'Sub-bill'));
        $this->assertSame('approved', $approval->fresh()->status);
        $this->assertSame(1, Invoice::query()->where('attendance_sheet_id', $sheet->id)->count());
        $this->assertTrue(\App\Models\ActivityLog::query()->where('section', 'Approvals')->where('note', 'like', 'Approved:%')->exists());
    }

    public function test_returned_month_opens_again_with_the_reason_and_can_be_sent_again(): void
    {
        $sheet = $this->lockAs($this->operator, '2026-10-01');
        $approval = Approval::query()->sole();
        $this->actingAs($this->owner)->post(route('approvals.return', $approval), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->owner)->post(route('approvals.return', $approval), ['reason' => 'Two days wrong for SW-1001'])->assertSessionHasNoErrors();
        $this->assertSame('draft', $sheet->fresh()->status);
        $this->assertSame('returned', $approval->fresh()->status);
        $this->actingAs($this->operator)->get(route('approvals.index'))->assertSee('Two days wrong for SW-1001');
        $this->actingAs($this->operator)->get(route('attendance.show', $sheet))->assertOk();

        $this->lockAs($this->operator, '2026-10-01');
        $this->assertSame(2, Approval::query()->count());
        $this->assertSame('pending', Approval::query()->latest('id')->first()->status);
    }

    public function test_strict_four_eyes_the_sender_cannot_approve(): void
    {
        $this->lockAs($this->owner, '2026-10-01');   // the owner locked (sent) it himself
        $approval = Approval::query()->sole();
        $this->actingAs($this->owner)->post(route('approvals.approve', $approval))->assertSessionHasErrors('approval');
        $this->assertSame('pending', $approval->fresh()->status);
        $this->actingAs($this->owner2)->post(route('approvals.approve', $approval))->assertSessionHasNoErrors();
        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_sub_bill_cannot_be_submitted_or_emailed_until_approved_and_again_after_a_change(): void
    {
        Mail::fake();
        config(['agency_mail.use_default_mailer' => true, 'agency_mail.agencies.SWH' => ['username' => 'swh@gmail.com', 'password' => 'x']]);
        $sheet = $this->lockAs($this->operator, '2026-10-01');
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->sole()));
        $bill = Invoice::query()->where('attendance_sheet_id', $sheet->id)->firstOrFail();

        $this->actingAs($this->accounts)->post(route('invoices.status', $bill), ['status' => 'approved']);
        $this->actingAs($this->accounts)->post(route('invoices.status', $bill->fresh()), ['status' => 'submitted'])->assertSessionHasErrors('invoice');
        $this->actingAs($this->accounts)->post(route('invoices.email', $bill), ['to' => 'icmr@example.gov.in', 'subject' => 's', 'message' => 'm'])->assertSessionHasErrors('email');
        Mail::assertNothingSent();

        $this->actingAs($this->accounts)->get(route('invoices.show', $bill))->assertSee('Not sent yet')->assertSee('Send for approval');
        $this->actingAs($this->accounts)->post(route('approvals.send.invoice', $bill))->assertSessionHas('status');
        $this->actingAs($this->accounts)->post(route('approvals.send.invoice', $bill))->assertSessionHasErrors('approval');   // nothing changed
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->latest('id')->first()))->assertSessionHasNoErrors();
        $this->actingAs($this->accounts)->post(route('invoices.email', $bill), ['to' => 'icmr@example.gov.in', 'subject' => 's', 'message' => 'm'])->assertSessionHasNoErrors();
        Mail::assertSentCount(1);

        // Figures changed after approval: must be approved again.
        $bill->forceFill(['rounded_total' => (float) $bill->rounded_total + 100])->save();
        $this->actingAs($this->accounts)->get(route('invoices.show', $bill))->assertSee('Changed after approval');
        $this->actingAs($this->accounts)->post(route('invoices.status', $bill->fresh()), ['status' => 'submitted'])->assertSessionHasErrors('invoice');
    }

    public function test_tax_invoice_needs_its_sub_bills_approved_first(): void
    {
        $sheet = $this->lockAs($this->operator, '2026-10-01');
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->sole()));
        $bill = Invoice::query()->where('attendance_sheet_id', $sheet->id)->firstOrFail();
        $tax = app(TaxInvoiceBuilder::class)->create($this->r['contract'], CarbonImmutable::parse('2026-10-01'), [$bill->id], $this->accounts);
        $this->actingAs($this->accounts)->post(route('approvals.send.tax-invoice', $tax));
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->latest('id')->first()))
            ->assertSessionHasErrors(['approval' => 'Approve its sub-bills first: '.$bill->invoice_no.'.']);
        $this->actingAs($this->accounts)->post(route('tax-invoices.status', $tax), ['status' => 'submitted'])->assertSessionHasErrors('tax');

        $this->actingAs($this->accounts)->post(route('approvals.send.invoice', $bill));
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->where('type', 'sub_bill')->latest('id')->first()));
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->where('type', 'tax_invoice')->latest('id')->first()))->assertSessionHasNoErrors();
        $this->actingAs($this->accounts)->post(route('tax-invoices.status', $tax), ['status' => 'submitted'])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $tax->fresh()->status);
    }

    public function test_salary_payout_and_bank_letters_need_approval_and_again_after_a_bank_change(): void
    {
        $sheet = $this->approvedBill()->sheet;
        $pdf = ['agency' => $this->r['agency']->id, 'month' => '2026-10'];
        $this->actingAs($this->accounts)->get(route('bank-letters.pdf', $pdf))->assertForbidden();
        $this->actingAs($this->accounts)->get(route('attendance.payouts', $sheet))->assertForbidden();
        $this->actingAs($this->accounts)->get(route('attendance.bank-letter', $sheet))->assertForbidden();
        $this->actingAs($this->owner)->get(route('bank-letters.pdf', $pdf))->assertOk();   // the owner may look at it to approve

        $this->actingAs($this->accounts)->get(route('bank-letters.index', $pdf))->assertSee('Not sent yet');
        $this->actingAs($this->accounts)->post(route('approvals.send.payout'), $pdf)->assertSessionHas('status');
        $this->actingAs($this->owner)->post(route('approvals.approve', Approval::query()->where('type', 'payout')->sole()))->assertSessionHasNoErrors();
        $this->actingAs($this->accounts)->get(route('bank-letters.pdf', $pdf))->assertOk();
        $this->actingAs($this->accounts)->get(route('attendance.payouts', $sheet))->assertOk();

        $this->r['employee']->update(['account_number' => '99999999999']);   // bank account changed after approval
        $this->actingAs($this->accounts)->get(route('bank-letters.pdf', $pdf))->assertForbidden();
        $this->actingAs($this->accounts)->get(route('attendance.bank-letter', $sheet))->assertForbidden();
    }

    public function test_months_before_october_2026_and_already_submitted_bills_are_left_as_they_were(): void
    {
        $sheet = $this->lockAs($this->operator, '2026-09-01');
        $this->assertSame(0, Approval::query()->count());
        $bill = Invoice::query()->where('attendance_sheet_id', $sheet->id)->firstOrFail();   // made straight away, as before
        $this->actingAs($this->accounts)->get(route('bank-letters.pdf', ['agency' => $this->r['agency']->id, 'month' => '2026-09']))->assertOk();
        $this->actingAs($this->accounts)->get(route('invoices.show', $bill))->assertDontSee('Owner approval');

        $oct = app(AttendanceService::class)->open($this->r['project'], CarbonImmutable::parse('2026-10-01'));
        app(AttendanceService::class)->lock($oct, $this->owner, null);
        $old = app(\App\Services\Billing\InvoiceGenerator::class)->generate($oct->fresh(), $this->owner);   // made before approvals began
        $old->forceFill(['status' => 'submitted'])->save();
        $this->assertTrue(app(\App\Services\Approvals\Approvals::class)->cleared('sub_bill', $old->fresh()));
        $this->assertTrue(app(\App\Services\Approvals\Approvals::class)->cleared('attendance', $oct->fresh()));
    }

    public function test_only_a_super_admin_can_make_a_second_owner(): void
    {
        $owner2 = User::factory()->create(['role' => 'admin', 'name' => 'New Owner']);
        $form = fn ($extra = []) => array_merge(['name' => 'New Owner', 'email' => $owner2->email, 'role' => 'admin', 'active' => 1, 'make_super_admin' => 1], $extra);
        $this->actingAs($this->owner)->put(route('users.update', $owner2), $form(['agency_id' => $this->r['agency']->id]))->assertSessionHasErrors('role');
        $this->assertFalse($owner2->fresh()->is_super_admin);

        $plainAdmin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($plainAdmin)->put(route('users.update', $owner2), $form())->assertForbidden();
        $this->assertFalse($owner2->fresh()->is_super_admin);

        $this->actingAs($this->owner)->get(route('users.edit', $owner2))->assertSee('Make this login a Super Admin');
        $this->actingAs($this->owner)->put(route('users.update', $owner2), $form())->assertSessionHasNoErrors();
        $this->assertTrue($owner2->fresh()->is_super_admin);
    }

    public function test_pi_cannot_send_and_approvals_are_listed_for_the_owner_only(): void
    {
        $sheet = $this->lockAs($this->operator, '2026-10-01');
        $this->actingAs($this->operator)->get(route('approvals.index'))->assertOk()->assertDontSee('Waiting for your approval');
        $this->actingAs($this->owner)->get(route('dashboard'))->assertSee('Approvals');
        $pi = User::factory()->create(['role' => 'pi']);
        $this->actingAs($pi)->post(route('approvals.send.attendance', $sheet))->assertStatus(403);
    }
}
