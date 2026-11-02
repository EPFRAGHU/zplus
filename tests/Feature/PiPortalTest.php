<?php

namespace Tests\Feature;

use App\Models\AttendanceSheet;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LoginEvent;
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

class PiPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Project $mph;

    private Project $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->mph = Project::query()->where('code', 'MPH')->firstOrFail();
        $this->other = $this->mph->contract->projects()->create(['name' => 'OTHER', 'code' => 'OTH', 'pi_name' => 'Dr. Other']);
    }

    private function pi(?Project $project = null): User
    {
        $pi = User::factory()->create(['role' => 'pi', 'name' => 'Dr. S. Kanungo']);
        $pi->projects()->attach(($project ?? $this->mph)->id);

        return $pi;
    }

    private function codes(AttendanceSheet $sheet, callable $edit = null): array
    {
        $out = [];
        foreach ($sheet->entries as $entry) {
            $codes = $entry->codes;
            if ($edit) {
                $codes = $edit($entry, $codes);
            }
            $out[$entry->id] = $codes;
        }

        return $out;
    }

    public function test_admin_adds_a_pi_who_must_set_a_password_and_lands_on_their_portal(): void
    {
        $this->actingAs($this->admin)->get('/users/create')->assertOk()->assertSee('Projects this PI is in charge of');
        $this->post('/users', ['name' => 'Dr. S. Kanungo', 'email' => 'pi@example.com', 'role' => 'pi', 'active' => '1', 'password' => 'Temp1234'])
            ->assertSessionHasErrors('projects');
        $this->post('/users', ['name' => 'Dr. S. Kanungo', 'email' => 'pi@example.com', 'role' => 'pi', 'active' => '1', 'password' => 'Temp1234',
            'projects' => [$this->mph->id]])->assertRedirect('/users');
        $pi = User::query()->where('email', 'pi@example.com')->firstOrFail();
        $this->assertTrue($pi->must_change_password);
        $this->assertSame([$this->mph->id], $pi->projects->pluck('id')->all());
        $this->get('/users')->assertSee('Dr. S. Kanungo')->assertSee('Temporary password');
        $this->post('/logout');

        $this->post('/login', ['email' => 'pi@example.com', 'password' => 'Temp1234'])->assertRedirect('/pi');
        $this->get('/pi')->assertRedirect('/account/password');
        $this->put('/account/password', ['current_password' => 'Temp1234', 'password' => 'MyOwnPass9', 'password_confirmation' => 'MyOwnPass9'])->assertRedirect('/pi');
        $this->assertFalse($pi->fresh()->must_change_password);
        $this->get('/pi')->assertOk()->assertSee('MPH Academic Programme')->assertDontSee('OTHER')->assertSee('PI portal');
        $this->assertSame(1, LoginEvent::query()->where('user_id', $pi->id)->where('success', true)->count());
    }

    public function test_pi_cannot_reach_agency_pages_or_other_projects(): void
    {
        $this->actingAs($pi = $this->pi());
        foreach (['/', '/employees', '/attendance', '/invoices', '/tax-invoices', '/agencies', '/users', '/data-upload'] as $url) {
            $this->get($url)->assertRedirect('/pi');
        }
        $this->post('/invoices', ['attendance_sheet_id' => 1])->assertForbidden();
        $otherSheet = app(AttendanceService::class)->open($this->other, CarbonImmutable::parse('2026-10-01'));
        $this->get("/pi/attendance/{$otherSheet->id}")->assertNotFound();
        $this->post("/pi/projects/{$this->other->id}/months", ['month' => '2026-10'])->assertNotFound();
        $this->get("/pi/attendance/{$otherSheet->id}/pdf")->assertNotFound();

        $sheet = app(AttendanceService::class)->open($this->mph, CarbonImmutable::parse('2026-10-01'));
        $page = $this->get("/pi/attendance/{$sheet->id}")->assertOk()->assertSee('Academic Programme Manager');
        $employee = Employee::query()->whereNotNull('mobile')->first();
        if ($employee) {
            $page->assertDontSee($employee->mobile);
        }
        $page->assertDontSee('Salary payout')->assertDontSee('Bank');
    }

    public function test_pi_marks_submits_uploads_signed_copy_and_agency_corrects_approves_and_bills(): void
    {
        $this->actingAs($pi = $this->pi());
        $this->post("/pi/projects/{$this->mph->id}/months", ['month' => now()->subMonthNoOverflow()->format('Y-m')])->assertRedirect();
        $sheet = AttendanceSheet::query()->where('project_id', $this->mph->id)->firstOrFail()->load('entries.employee');
        $apa = $sheet->entries->first(fn ($e) => $e->employee->post->designation === 'Academic Programme Assistant');
        $firstWorkday = collect($apa->codes)->search('P');

        // PI marks one day CL and saves.
        $codes = $this->codes($sheet, fn ($e, $c) => $e->id === $apa->id ? array_replace($c, [$firstWorkday => 'CL']) : $c);
        $this->put("/pi/attendance/{$sheet->id}", ['codes' => $codes])->assertSessionHasNoErrors();
        $this->assertSame('CL', $apa->fresh()->codes[$firstWorkday]);

        // Signed copy cannot come before submitting.
        $this->post("/pi/attendance/{$sheet->id}/signed", ['signed' => UploadedFile::fake()->create('s.pdf', 50, 'application/pdf')])->assertSessionHasErrors('signed');

        // Submit: frozen for the PI, PDF stored.
        $this->post("/pi/attendance/{$sheet->id}/submit")->assertSessionHasNoErrors();
        $sheet->refresh();
        $this->assertSame('submitted', $sheet->status);
        $this->assertSame($pi->id, $sheet->submitted_by);
        Storage::disk('local')->assertExists($sheet->submitted_pdf_path);
        $this->get("/pi/attendance/{$sheet->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->put("/pi/attendance/{$sheet->id}", ['codes' => $codes])->assertSessionHasErrors('attendance');
        $this->post("/pi/attendance/{$sheet->id}/submit")->assertSessionHasErrors('attendance');

        // PI uploads the signed copy.
        $this->post("/pi/attendance/{$sheet->id}/signed", ['signed' => UploadedFile::fake()->create('signed.pdf', 50, 'application/pdf')])->assertSessionHasNoErrors();
        $this->assertNotNull($sheet->fresh()->certificate_path);
        $this->get('/pi')->assertSee('Submitted · with the agency')->assertSee('Uploaded');

        // Operator sees it, corrects a hand-made change (CL → EL), and the change is recorded.
        $operator = User::factory()->create(['role' => 'supervisor', 'name' => 'Ravi Operator']);
        $this->actingAs($operator)->get('/attendance?month='.$sheet->month->format('Y-m'))->assertSee('Submitted by PI · check');
        $this->get("/attendance/{$sheet->id}")->assertOk()->assertSee('Submitted by the PI')->assertSee('PDF as submitted')->assertSee('Approve and lock');
        $this->get("/attendance/{$sheet->id}/submitted-pdf")->assertOk();
        $codes = $this->codes($sheet->fresh()->load('entries'), fn ($e, $c) => $e->id === $apa->id ? array_replace($c, [$firstWorkday => 'EL']) : $c);
        $this->put("/attendance/{$sheet->id}", ['codes' => $codes])->assertSessionHas('status', fn ($s) => str_contains($s, '1 change recorded'));
        $change = $sheet->changes()->firstOrFail();
        $this->assertSame(['CL', 'EL', $operator->id, $firstWorkday], [$change->old_code, $change->new_code, $change->user_id, $change->day]);
        $this->get("/attendance/{$sheet->id}")->assertSee('Changed by the agency after the PI submitted')->assertSee('Ravi Operator');
        $html = view('pdf.attendance-certificate', ['sheet' => $sheet->fresh()->load('entries.employee.post', 'project.contract.agency', 'project.contract.client'), 'showBank' => false])->render();
        $this->assertStringContainsString('Submitted online', $html);
        $this->assertStringContainsString('Corrected by the agency after submission (1)', $html);
        $this->assertStringContainsString('CL → EL', $html);

        // Approve: locks and makes the sub-bill automatically (signed copy already uploaded by the PI).
        $this->post("/attendance/{$sheet->id}/lock")->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($sheet->fresh()->isLocked());
        $invoice = Invoice::query()->where('attendance_sheet_id', $sheet->id)->firstOrFail();
        $this->assertSame('draft', $invoice->status);

        // PI sees the approval and the sub-bill; the PDF opens; another project's bill does not.
        $this->actingAs($pi)->get("/pi/attendance/{$sheet->id}")->assertSee('Approved by the agency')->assertSee($invoice->invoice_no);
        $this->get('/pi/bills')->assertOk()->assertSee($invoice->invoice_no);
        $this->get("/pi/bills/{$invoice->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->post("/pi/attendance/{$sheet->id}/signed", ['signed' => UploadedFile::fake()->create('again.pdf', 50, 'application/pdf')])->assertSessionHasErrors('signed');
    }

    public function test_pi_cannot_see_another_projects_bill(): void
    {
        $service = app(AttendanceService::class);
        $sheet = $service->open($this->mph, CarbonImmutable::parse('2026-10-01'));
        $service->lock($sheet, $this->admin, null);
        $invoice = app(InvoiceGenerator::class)->generate($sheet->fresh(), $this->admin);

        $this->actingAs($this->pi($this->other));
        $this->get('/pi/bills')->assertOk()->assertDontSee($invoice->invoice_no);
        $this->get("/pi/bills/{$invoice->id}/pdf")->assertNotFound();
    }

    public function test_operator_can_still_mark_and_lock_without_the_pi(): void
    {
        $sheet = app(AttendanceService::class)->open($this->mph, CarbonImmutable::parse('2026-10-01'));
        $this->actingAs(User::factory()->create(['role' => 'supervisor']));
        $this->put("/attendance/{$sheet->id}", ['codes' => $this->codes($sheet)])->assertSessionHas('status', 'Attendance saved.');
        $this->assertSame(0, $sheet->changes()->count());   // not submitted by a PI: nothing to record
        $this->post("/attendance/{$sheet->id}/lock", ['certificate' => UploadedFile::fake()->create('signed.pdf', 50, 'application/pdf')])->assertRedirect();
        $this->assertTrue(Invoice::query()->where('attendance_sheet_id', $sheet->id)->exists());
    }

    public function test_switched_off_accounts_cannot_sign_in_and_admin_cannot_lock_themselves_out(): void
    {
        $pi = $this->pi();
        $pi->forceFill(['password' => 'Secret123', 'active' => false])->save();
        $this->post('/login', ['email' => $pi->email, 'password' => 'Secret123'])->assertSessionHasErrors(['email' => 'This account is switched off. Contact the agency.']);
        $this->assertGuest();
        $this->assertTrue(LoginEvent::query()->where('user_id', $pi->id)->where('success', false)->exists());

        $this->actingAs($this->admin)->put("/users/{$this->admin->id}", ['name' => 'Me', 'email' => $this->admin->email, 'role' => 'accounts', 'active' => '1'])
            ->assertSessionHasErrors('role');
        $this->put("/users/{$pi->id}", ['name' => $pi->name, 'email' => $pi->email, 'role' => 'pi', 'active' => '1', 'projects' => [$this->mph->id], 'password' => 'Reset12345'])
            ->assertRedirect('/users');
        $this->assertTrue($pi->fresh()->active);
        $this->assertTrue($pi->fresh()->must_change_password);

        $this->actingAs(User::factory()->create(['role' => 'accounts']))->get('/users')->assertForbidden();
    }

    public function test_active_session_of_a_switched_off_user_is_ended(): void
    {
        $pi = $this->pi();
        $this->actingAs($pi)->get('/pi')->assertOk();
        $pi->forceFill(['active' => false])->save();
        $this->get('/pi')->assertRedirect('/login');
    }
}
