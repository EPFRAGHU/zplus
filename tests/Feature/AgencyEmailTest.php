<?php

namespace Tests\Feature;

use App\Mail\AgencyDocuments;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\SentEmail;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\TaxInvoiceBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** "E-mail to client" (v2.12.0): sends from the agency's Gmail; the password lives only in the server's environment. */
class AgencyEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $r;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $agency = Agency::query()->create(['code' => 'ZPS', 'name' => 'M/S Z Plus Surakhya Seva']);
        $client = Client::query()->create(['agency_id' => $agency->id, 'name' => 'ICMR', 'short_code' => 'ICMR', 'email' => 'accounts@icmr.example']);
        $contract = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => 'GEM/1', 'contract_no' => 'GEMC-1']);
        $project = $contract->projects()->create(['name' => 'MPH', 'code' => 'MPH']);
        $post = $project->posts()->create(['designation' => 'Officer', 'sanctioned_qty' => 1, 'rate' => 20000]);
        Employee::query()->create(['emp_code' => 'ZP-1001', 'name' => 'Staff', 'agency_id' => $agency->id, 'client_id' => $client->id, 'post_id' => $post->id, 'status' => 'active']);
        $service = app(AttendanceService::class);
        $sheet = $service->open($project, CarbonImmutable::parse('2026-09-01'));
        $service->lock($sheet, $this->admin, null);
        $invoice = app(InvoiceGenerator::class)->generate($sheet->fresh(), $this->admin);
        $sheet2 = $service->open($project, CarbonImmutable::parse('2026-08-01'));
        $service->lock($sheet2, $this->admin, null);
        $invoice2 = app(InvoiceGenerator::class)->generate($sheet2->fresh(), $this->admin);
        $tax = app(TaxInvoiceBuilder::class)->create($contract, CarbonImmutable::parse('2026-08-01'), [$invoice2->id], $this->admin);
        $this->r = compact('agency', 'client', 'invoice', 'invoice2', 'tax');
    }

    private function switchOn(): void
    {
        config(['agency_mail.use_default_mailer' => true,
            'agency_mail.agencies.ZPS' => ['username' => 'zplus.agency@gmail.com', 'password' => 'abcd efgh ijkl mnop']]);
    }

    private function form(array $extra = []): array
    {
        return array_merge(['to' => 'accounts@icmr.example', 'cc' => '', 'subject' => 'Bill', 'message' => 'Please find attached.'], $extra);
    }

    public function test_switched_off_without_gmail_settings_and_nothing_is_sent(): void
    {
        Mail::fake();
        config(['agency_mail.use_default_mailer' => true]);
        $this->get(route('invoices.show', $this->r['invoice']))->assertOk()->assertSee('Switched off for');
        $this->post(route('invoices.email', $this->r['invoice']), $this->form())->assertSessionHasErrors('email');
        Mail::assertNothingSent();
        $this->assertSame(0, SentEmail::query()->count());
    }

    public function test_sub_bill_is_emailed_with_covering_letter_and_recorded(): void
    {
        Mail::fake();
        $this->switchOn();
        $this->get(route('invoices.show', $this->r['invoice']))->assertOk()->assertSee('accounts@icmr.example')->assertSee('not signed')->assertDontSee('abcd efgh');
        $this->post(route('invoices.email', $this->r['invoice']), $this->form(['cc' => 'pi@icmr.example; owner@example.com']))
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        Mail::assertSent(AgencyDocuments::class, function (AgencyDocuments $m) {
            return $m->hasTo('accounts@icmr.example') && $m->hasCc('pi@icmr.example') && $m->hasCc('owner@example.com')
                && $m->hasFrom('zplus.agency@gmail.com') && count($m->documents) === 2
                && str_starts_with($m->documents[0]['data'], '%PDF') && ! $m->documents[0]['signed'];
        });
        $sent = SentEmail::query()->sole();
        $this->assertSame(['sent', 'sub_bill', $this->r['invoice']->id], [$sent->status, $sent->kind, $sent->invoice_id]);
        $this->assertStringNotContainsString('abcd', json_encode($sent->toArray()));
        $this->assertTrue(DB::table('activity_logs')->where('section', 'E-mail')->where('note', 'like', 'E-mailed sub-bill%')->exists());
        $this->get(route('invoices.show', $this->r['invoice']))->assertSee('Sent');
    }

    public function test_signed_copy_is_attached_until_the_bill_changes(): void
    {
        Mail::fake();
        $this->switchOn();
        $invoice = $this->r['invoice'];
        $this->travel(1)->minutes();
        $this->post(route('invoices.signed', $invoice), ['signed' => UploadedFile::fake()->createWithContent('signed.pdf', '%PDF-signed-copy')]);
        $this->post(route('invoices.email', $invoice), $this->form())->assertSessionHasNoErrors();
        Mail::assertSent(AgencyDocuments::class, fn ($m) => $m->documents[0]['signed'] && $m->documents[0]['data'] === '%PDF-signed-copy');

        $this->travel(1)->minutes();
        $invoice->touch();      // changed after signing: the fresh PDF goes instead
        $this->post(route('invoices.email', $invoice), $this->form())->assertSessionHasNoErrors();
        $this->assertFalse(SentEmail::query()->latest('id')->first()->attachments[0]['signed']);
    }

    public function test_tax_invoice_is_emailed_with_its_sub_bills(): void
    {
        Mail::fake();
        $this->switchOn();
        $this->get(route('tax-invoices.show', $this->r['tax']))->assertOk()->assertSee('E-mail to client');
        $this->post(route('tax-invoices.email', $this->r['tax']), $this->form())->assertSessionHasNoErrors();
        Mail::assertSent(AgencyDocuments::class, fn ($m) => count($m->documents) === 2
            && str_starts_with($m->documents[0]['name'], 'tax-invoice-') && $m->documents[1]['name'] === \App\Support\Codes::fileSafe($this->r['invoice2']->invoice_no).'.pdf');
        $this->assertSame($this->r['tax']->id, SentEmail::query()->sole()->tax_invoice_id);
    }

    public function test_bad_addresses_and_header_injection_are_refused(): void
    {
        Mail::fake();
        $this->switchOn();
        $this->post(route('invoices.email', $this->r['invoice']), $this->form(['to' => 'not-an-address']))->assertSessionHasErrors('to');
        $this->post(route('invoices.email', $this->r['invoice']), $this->form(['cc' => 'ok@x.in, bad@']))->assertSessionHasErrors('cc');
        $this->post(route('invoices.email', $this->r['invoice']), $this->form(['to' => implode(',', array_map(fn ($i) => "a{$i}@x.in", range(1, 11)))]))->assertSessionHasErrors('to');
        Mail::assertNothingSent();
        $this->post(route('invoices.email', $this->r['invoice']), $this->form(['subject' => "Bill\r\nBcc: evil@x.in"]))->assertSessionHasNoErrors();
        Mail::assertSent(AgencyDocuments::class, fn ($m) => $m->subjectLine === 'Bill Bcc: evil@x.in' && ! $m->hasBcc('evil@x.in'));
    }

    public function test_gmail_failure_is_recorded_with_a_plain_message_and_no_password(): void
    {
        config(['agency_mail.host' => '127.0.0.1', 'agency_mail.port' => 1, 'agency_mail.timeout' => 2,
            'agency_mail.agencies.ZPS' => ['username' => 'zplus.agency@gmail.com', 'password' => 'secretpass']]);
        $this->post(route('invoices.email', $this->r['invoice']), $this->form())->assertSessionHasErrors('email');
        $sent = SentEmail::query()->sole();
        $this->assertSame('failed', $sent->status);
        $this->assertStringContainsString('Could not reach Gmail', $sent->error);
        $this->assertStringNotContainsString('secretpass', json_encode($sent->toArray()).DB::table('activity_logs')->pluck('note')->implode(' '));
    }

    public function test_only_admin_and_accounts_can_send(): void
    {
        Mail::fake();
        $this->switchOn();
        $this->actingAs(User::factory()->create(['role' => 'supervisor']));
        $this->post(route('invoices.email', $this->r['invoice']), $this->form())->assertForbidden();
        $this->post(route('tax-invoices.email', $this->r['tax']), $this->form())->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_other_agency_login_cannot_send_this_agencys_bill(): void
    {
        Mail::fake();
        $this->switchOn();
        $other = Agency::query()->create(['code' => 'SWH', 'name' => 'SWACHHA']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'agency_id' => $other->id]));
        $this->post(route('invoices.email', $this->r['invoice']), $this->form())->assertNotFound();
        $this->post(route('tax-invoices.email', $this->r['tax']), $this->form())->assertNotFound();
        Mail::assertNothingSent();
        $this->assertSame(0, SentEmail::query()->withoutGlobalScopes()->count());
    }

    public function test_credentials_found_by_prefix_and_spaces_removed(): void
    {
        config(['agency_mail.agencies.SWH' => ['username' => ' swh@gmail.com ', 'password' => 'abcd efgh ijkl mnop']]);
        $agency = Agency::query()->create(['code' => 'SWX', 'name' => 'SWACHHA', 'number_prefix' => 'SW']);
        $this->assertSame(['username' => 'swh@gmail.com', 'password' => 'abcdefghijklmnop'], app(\App\Services\Mail\AgencyMailer::class)->credentials($agency));
    }
}
