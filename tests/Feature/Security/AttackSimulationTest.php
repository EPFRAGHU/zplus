<?php

namespace Tests\Feature\Security;

use App\Models\Agency;
use App\Models\Approval;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Security audit Phase 3: attacks tried against a LOCAL copy (never the live site). Each one must fail.
 * (Cross-agency writes are covered action by action in CrossAgencyWriteTest.)
 */
class AttackSimulationTest extends TestCase
{
    use RefreshDatabase;

    private array $z;

    private array $s;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = User::factory()->create(['role' => 'admin']);
        $this->owner->forceFill(['is_super_admin' => true])->save();
        $this->z = $this->agency('ZPS');
        $this->s = $this->agency('SWH');
    }

    private function agency(string $code): array
    {
        $this->actingAs($this->owner);
        $agency = Agency::query()->create(['code' => $code, 'name' => "Agency {$code}"]);
        $client = Client::query()->create(['agency_id' => $agency->id, 'name' => "ICMR {$code}", 'short_code' => 'ICMR']);
        $contract = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => "GEM/{$code}", 'contract_no' => "GEMC-{$code}",
            'epf_percent' => '13', 'esic_percent' => '3.25', 'service_charge_percent' => '3.85', 'gst_percent' => '18']);
        $project = $contract->projects()->create(['name' => "Project {$code}", 'code' => "P{$code}"]);
        $post = $project->posts()->create(['designation' => 'Analyst', 'sanctioned_qty' => 1, 'rate' => 30000]);
        $employee = Employee::query()->create(['emp_code' => "{$code}-1", 'name' => "Secret Staff {$code}", 'agency_id' => $agency->id, 'client_id' => $client->id,
            'post_id' => $post->id, 'status' => 'active', 'pan' => $code === 'SWH' ? 'SWHPN1234S' : 'ZPSPN1234Z', 'account_number' => '5555666677778888']);
        $doc = $employee->documents()->create(['type' => 'pan', 'path' => "docs/{$code}.pdf", 'original_name' => "{$code}.pdf"]);
        $sheet = app(AttendanceService::class)->open($project, CarbonImmutable::parse('2026-09-01'));
        app(AttendanceService::class)->lock($sheet, $this->owner, null);
        $invoice = app(InvoiceGenerator::class)->generate($sheet->fresh(), $this->owner);
        $admin = User::factory()->create(['role' => 'admin', 'agency_id' => $agency->id]);
        $accounts = User::factory()->create(['role' => 'accounts', 'agency_id' => $agency->id]);
        $operator = User::factory()->create(['role' => 'supervisor', 'agency_id' => $agency->id]);
        $pi = User::factory()->create(['role' => 'pi', 'agency_id' => $agency->id]);
        $pi->projects()->attach($project->id);

        return compact('agency', 'client', 'contract', 'project', 'post', 'employee', 'doc', 'sheet', 'invoice', 'admin', 'accounts', 'operator', 'pi');
    }

    /** Attack 1: change the ID in the URL to read the other agency's records and files. */
    public function test_reading_the_other_agencys_records_by_changing_ids_fails(): void
    {
        $s = $this->s;
        $this->actingAs($this->z['admin']);
        foreach (["/employees/{$s['employee']->id}", "/invoices/{$s['invoice']->id}", "/invoices/{$s['invoice']->id}/pdf", "/invoices/{$s['invoice']->id}/covering-letter",
            "/attendance/{$s['sheet']->id}", "/attendance/{$s['sheet']->id}/salary-payouts", "/attendance/{$s['sheet']->id}/bank-letter", "/attendance/{$s['sheet']->id}/certificate",
            "/documents/{$s['doc']->id}", "/contracts/{$s['contract']->id}", "/contracts/{$s['contract']->id}/bill-pack?month=2026-09",
            "/clients/{$s['client']->id}/edit", "/agencies/{$s['agency']->id}/edit", "/bank-letters/pdf?agency={$s['agency']->id}&month=2026-09"] as $url) {
            $status = $this->get($url)->status();
            $this->assertContains($status, [302, 403, 404], "{$url} returned {$status}");
        }
        foreach (['/employees', '/staff', '/invoices', '/approvals', '/'] as $list) {
            $this->get($list)->assertDontSee('Secret Staff SWH')->assertDontSee('SWHPN1234S');
        }
    }

    /** Attack 2: an Operator calls Admin / owner actions directly (they are hidden in the menus, but are they blocked?). */
    public function test_operator_calling_admin_and_owner_actions_directly_is_blocked(): void
    {
        $z = $this->z;
        $this->actingAs($z['operator']);
        $before = DB::table('users')->count();
        $this->post('/users', ['name' => 'Hacker', 'email' => 'h@x.in', 'role' => 'admin', 'password' => 'Hacker123', 'active' => 1])->assertForbidden();
        $this->assertSame($before, DB::table('users')->count());
        $this->post("/attendance/{$z['sheet']->id}/unlock")->assertForbidden();
        $this->post("/attendance/{$z['sheet']->id}/reset", ['confirm' => 'RESET'])->assertForbidden();
        $this->get('/activity')->assertForbidden();
        $this->get('/activity/export')->assertForbidden();
        $this->post("/invoices/{$z['invoice']->id}/status", ['status' => 'submitted'])->assertForbidden();
        $this->delete("/employees/{$z['employee']->id}")->assertForbidden();
        $this->get("/attendance/{$z['sheet']->id}/salary-payouts")->assertForbidden();
        $this->get("/documents/{$z['doc']->id}")->assertForbidden();
        $approval = Approval::query()->create(['agency_id' => $z['agency']->id, 'type' => 'sub_bill', 'invoice_id' => $z['invoice']->id, 'month' => '2026-10-01',
            'label' => 'x', 'fingerprint' => 'x', 'status' => 'pending', 'submitted_by' => $z['accounts']->id, 'submitted_at' => now()]);
        $this->post("/approvals/{$approval->id}/approve")->assertForbidden();
        $this->actingAs($z['admin'])->post("/approvals/{$approval->id}/approve")->assertForbidden();   // an agency Admin is not an owner either
        $this->assertSame('pending', $approval->fresh()->status);
    }

    /** Attack 3: a PI tries agency pages and another project's sheet. */
    public function test_pi_cannot_reach_agency_pages_or_other_projects(): void
    {
        $this->actingAs($this->z['pi']);
        $this->get('/employees')->assertRedirect(route('pi.home'));
        $this->post('/invoices', ['attendance_sheet_id' => $this->z['sheet']->id])->assertForbidden();
        $this->get("/pi/attendance/{$this->s['sheet']->id}")->assertNotFound();
        $this->get("/pi/bills/{$this->s['invoice']->id}/pdf")->assertNotFound();
    }

    /** Attack 4: mass assignment – an agency Admin tries to make someone Super Admin or move a login to the other agency. */
    public function test_admin_cannot_grant_super_admin_or_switch_agency_through_the_form(): void
    {
        $z = $this->z;
        $victim = $z['accounts'];
        $this->actingAs($z['admin'])->put("/users/{$victim->id}", ['name' => 'X', 'email' => $victim->email, 'role' => 'accounts', 'active' => 1,
            'is_super_admin' => 1, 'make_super_admin' => 1, 'agency_id' => $this->s['agency']->id]);
        $victim->refresh();
        $this->assertFalse((bool) $victim->is_super_admin);
        $this->assertSame($z['agency']->id, (int) $victim->agency_id);
        // and cannot edit the owner's own account
        $this->assertContains($this->put("/users/{$this->owner->id}", ['name' => 'X', 'email' => 'owner@x.in', 'role' => 'admin', 'active' => 0])->status(), [403, 404]);
        $this->assertTrue((bool) $this->owner->fresh()->active);
    }

    /** Attack 5: malicious input – SQL in search boxes, script in names, a PHP file disguised as a PDF. */
    public function test_malicious_input_is_harmless(): void
    {
        $this->actingAs($this->z['admin']);
        foreach (["' OR 1=1 --", "x%' UNION SELECT password FROM users --", '"; DROP TABLE employees; --'] as $sql) {
            $this->get('/employees?q='.urlencode($sql))->assertOk()->assertDontSee('Secret Staff SWH');
            $this->get('/contracts?q='.urlencode($sql))->assertOk();
            $this->get('/incharges?q='.urlencode($sql))->assertOk();
        }
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('employees'));

        $this->z['employee']->update(['name' => '<script>alert(1)</script>']);
        $this->get("/employees/{$this->z['employee']->id}")->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        $php = UploadedFile::fake()->createWithContent('payslip.pdf.php', "<?php system(\$_GET['c']); ?>");
        $this->post("/employees/{$this->z['employee']->id}/documents", ['type' => 'pan', 'file' => $php])->assertSessionHasErrors('file');
        // PHP code saved with a .pdf name: a real file, so the type is checked from its content like a real upload
        $path = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($path, "<?php system(\$_GET['c']); ?>");
        $fake = new UploadedFile($path, 'payslip.pdf', 'application/pdf', null, true);
        $this->post("/employees/{$this->z['employee']->id}/documents", ['type' => 'pan', 'file' => $fake])->assertSessionHasErrors('file');
        $this->post("/contracts/{$this->z['contract']->id}/papers", ['month' => '../../etc', 'kind' => 'logbook', 'paper' => [UploadedFile::fake()->create('a.pdf', 5)]])
            ->assertSessionHasErrors('month');
    }

    /** Attack 6: skip sign-in, or keep using a switched-off account. */
    public function test_skipping_sign_in_and_switched_off_accounts_fail(): void
    {
        auth()->logout();
        foreach (['/', '/employees', "/invoices/{$this->z['invoice']->id}/pdf", '/approvals', '/pi'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->post('/users', ['name' => 'x'])->assertRedirect(route('login'));
        $op = $this->z['operator'];
        $op->forceFill(['active' => false])->save();
        $this->actingAs($op)->get('/')->assertRedirect(route('login'));
    }
}
