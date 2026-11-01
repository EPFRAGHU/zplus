<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AttendanceSheet;
use App\Models\Employee;
use App\Models\GeneratedDocument;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $user = $this->user('admin');
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_every_page_renders_for_admin(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = $this->user('admin');
        $employee = Employee::query()->first();

        foreach (['/', '/agencies', '/agencies/create', '/clients', '/clients/create', '/contracts', '/contracts/create', '/contracts/1',
            '/contracts/1/edit', '/employees', '/employees/create', "/employees/{$employee->id}", "/employees/{$employee->id}/edit",
            '/attendance', '/invoices', '/import'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_full_october_flow_through_the_screens(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $accounts = $this->user('accounts');

        // Start the sheet with the October holidays.
        $this->actingAs($accounts)->post('/attendance', ['project_id' => 1, 'month' => '2026-10', 'holidays' => '2, 19, 20'])
            ->assertRedirect('/attendance/1');
        $sheet = AttendanceSheet::query()->firstOrFail();
        $this->get('/attendance/1')->assertOk()->assertSee('Amit Kumar (sample)');
        $this->get('/attendance/1/certificate')->assertOk()->assertHeader('content-type', 'application/pdf');

        // Locking needs the signed certificate.
        $this->post('/attendance/1/lock')->assertSessionHasErrors('certificate');
        $this->post('/attendance/1/lock', ['certificate' => UploadedFile::fake()->create('signed.pdf', 200, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $this->assertTrue($sheet->fresh()->isLocked());

        // Make the bill.
        $this->post('/invoices', ['attendance_sheet_id' => $sheet->id])->assertRedirect('/invoices/1');
        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('ICMR/MPH/01/2026', $invoice->invoice_no);
        $this->assertSame('105387.00', $invoice->rounded_total);
        $this->get('/invoices/1')->assertOk()->assertSee('₹1,05,387.00')->assertSee('One Lakh Five Thousand Three Hundred Eighty Seven');
        $this->get('/invoices/1/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/invoices/1/covering-letter')->assertOk()->assertHeader('content-type', 'application/pdf');

        // Draft -> approved -> submitted -> paid; skipping a step is refused.
        $this->post('/invoices/1/status', ['status' => 'paid'])->assertSessionHasErrors('status');
        $this->post('/invoices/1/status', ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->post('/invoices/1/status', ['status' => 'submitted'])->assertSessionHasNoErrors();
        $this->post('/invoices/1/status', ['status' => 'paid', 'paid_on' => '2026-12-10'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_supervisor_cannot_bill_import_or_edit_masters(): void
    {
        $this->seed(DemoSeeder::class);
        $sup = $this->user('supervisor');

        $this->actingAs($sup)->get('/import')->assertForbidden();
        $this->get('/employees/create')->assertForbidden();
        $this->post('/clients', ['name' => 'X', 'short_code' => 'X', 'state_code' => '21', 'payment_term_days' => 30, 'status' => 'active'])->assertForbidden();
        $this->post('/invoices', ['attendance_sheet_id' => 1])->assertForbidden();
        // Supervisors do run attendance.
        $this->post('/attendance', ['project_id' => 1, 'month' => '2026-10'])->assertRedirect('/attendance/1');
    }

    public function test_bank_account_number_is_never_sent_back_to_the_browser(): void
    {
        $this->seed(DemoSeeder::class);
        $employee = Employee::query()->firstOrFail();
        $employee->update(['account_number' => '2957101004245']);

        $html = $this->actingAs($this->user('admin'))->get("/employees/{$employee->id}/edit")->assertOk()->getContent();
        $this->assertStringNotContainsString('2957101004245', $html);
        $this->assertStringContainsString('••••4245', $html);

        // Saving the form with the field left blank keeps the stored number.
        $this->put("/employees/{$employee->id}", ['emp_code' => $employee->emp_code, 'agency_id' => $employee->agency_id, 'name' => $employee->name, 'status' => 'active', 'account_number' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame('2957101004245', $employee->fresh()->account_number);
    }

    public function test_invalid_ifsc_is_rejected_with_a_clear_message(): void
    {
        $this->seed(DemoSeeder::class);
        $employee = Employee::query()->firstOrFail();
        $this->actingAs($this->user('admin'))
            ->put("/employees/{$employee->id}", ['emp_code' => $employee->emp_code, 'agency_id' => $employee->agency_id, 'name' => $employee->name, 'status' => 'active', 'ifsc' => 'BARB0VJNIVI00000'])
            ->assertSessionHasErrors(['ifsc' => 'An IFSC has 11 characters: 4 letters, a zero, then 6 letters or digits.']);
    }

    public function test_appointment_letter_gets_a_number_once(): void
    {
        $this->seed(DemoSeeder::class);
        $employee = Employee::query()->firstOrFail();
        $admin = $this->user('admin');

        $this->actingAs($admin)->get("/employees/{$employee->id}/appointment-letter")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/employees/{$employee->id}/appointment-letter")->assertOk();
        $this->get("/employees/{$employee->id}")->assertSee('ZPS/NIHR/MPH/01/26');
        $this->assertSame(1, GeneratedDocument::query()->count());
    }

    public function test_empty_bills_page_explains_how_to_make_a_bill_and_lists_unlocked_months(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/invoices')->assertOk()->assertSee('How a bill is made')->assertSee('No attendance has been started yet');

        $this->post('/attendance', ['project_id' => 1, 'month' => '2026-10']);
        $this->get('/invoices')->assertOk()->assertSee('Attendance started but not locked yet')->assertSee('Open and lock');
    }

    public function test_attendance_shows_and_filters_by_agency_and_client(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = $this->user('admin');
        $zps = Agency::query()->where('code', 'ZPS')->firstOrFail();
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();

        $this->actingAs($admin)->get('/attendance?month=2026-10')->assertOk()
            ->assertSee('id="f-agency"', false)->assertSee('id="f-client"', false)->assertSee('MPH')->assertSee('ZPS');
        $this->get('/attendance?month=2026-10&agency='.$swh->id)->assertOk()->assertDontSee('MPH');
        $this->get('/attendance?month=2026-10&agency='.$zps->id)->assertOk()->assertSee('MPH');

        $this->post('/attendance', ['project_id' => 1, 'month' => '2026-10']);
        $this->get('/attendance/1')->assertSee('Agency:')->assertSee('M/S Z Plus Surakhya Seva')->assertSee('ICMR-NIHR (RMRCBB), Bhubaneswar');
        $this->get('/attendance/1/certificate')->assertOk();
    }

    public function test_bills_show_and_filter_by_agency_and_client(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = $this->user('admin');
        $zps = Agency::query()->where('code', 'ZPS')->firstOrFail();
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $sheet = app(AttendanceService::class)->open(Project::query()->firstOrFail(), CarbonImmutable::parse('2026-10-01'));
        app(AttendanceService::class)->lock($sheet, $admin, null);

        $this->actingAs($admin)->get('/invoices')->assertOk()->assertSee('Ready to bill')->assertSee('ZPS')->assertSee('ICMR-NIHR (RMRCBB), Bhubaneswar');
        $this->post('/invoices', ['attendance_sheet_id' => $sheet->id]);

        $this->get('/invoices')->assertSee('id="f-agency"', false)->assertSee('id="f-client"', false)->assertSee('ICMR/MPH/01/2026');
        $this->get('/invoices?agency='.$swh->id)->assertDontSee('ICMR/MPH/01/2026');
        $this->get('/invoices?agency='.$zps->id)->assertSee('ICMR/MPH/01/2026');
        $this->get('/invoices/1')->assertSee('Agency:')->assertSee('M/S Z Plus Surakhya Seva')->assertSee('Client:');
    }

    public function test_dashboard_shows_and_filters_by_agency_and_client(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = $this->user('admin');
        $zps = Agency::query()->where('code', 'ZPS')->firstOrFail();
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $sheet = app(AttendanceService::class)->open(Project::query()->firstOrFail(), CarbonImmutable::parse(now()->subMonthNoOverflow()->startOfMonth()->toDateString()));
        app(AttendanceService::class)->lock($sheet, $admin, null);
        app(InvoiceGenerator::class)->generate($sheet->fresh(), $admin);

        $all = $this->actingAs($admin)->get('/')->assertOk()
            ->assertSee('id="f-agency"', false)->assertSee('id="f-client"', false)
            ->assertSee('By agency and client')->assertSee('ZPS')->assertSee('SWH')->assertSee('₹1,05,387');

        // SWACHHA has no staff or bills in the demo data, so its view shows zero billed.
        $this->get('/?agency='.$swh->id)->assertOk()->assertSee('Showing SWACHHA')->assertDontSee('ICMR/MPH/');
        $this->get('/?agency='.$zps->id)->assertOk()->assertSee('ICMR/MPH/')->assertSee('₹1,05,387');
    }

    public function test_bill_pdf_names_the_agency_and_client(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = $this->user('admin');
        $service = app(AttendanceService::class);
        $sheet = $service->open(Project::query()->firstOrFail(), CarbonImmutable::parse('2026-10-01'));
        $service->lock($sheet, $admin, null);
        $invoice = app(InvoiceGenerator::class)->generate($sheet->fresh(), $admin);

        // Render the PDF templates as HTML to check their content.
        $html = view('pdf.invoice', ['invoice' => $invoice->load('lines'), 'agency' => $invoice->contract->agency,
            'client' => $invoice->contract->client, 'project' => $invoice->project, 'contract' => $invoice->contract])->render();
        $this->assertStringContainsString('<th style="width:12%;text-align:left">Agency</th>', $html);
        $this->assertStringContainsString('M/S Z Plus Surakhya Seva</b> (ZPS)', $html);
        $this->assertStringContainsString('ICMR-NIHR (RMRCBB), Bhubaneswar</b> (ICMR)', $html);

        $letter = view('pdf.covering-letter', ['invoice' => $invoice, 'agency' => $invoice->contract->agency,
            'client' => $invoice->contract->client, 'project' => $invoice->project, 'contract' => $invoice->contract])->render();
        $this->assertStringContainsString('<b>Agency:</b> M/S Z Plus Surakhya Seva', $letter);
        $this->assertStringContainsString('<b>Client:</b> ICMR-NIHR (RMRCBB), Bhubaneswar', $letter);

        $this->actingAs($admin)->get("/invoices/{$invoice->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
