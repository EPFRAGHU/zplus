<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\TaxInvoiceBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proof that a Z Plus login can never add, change or delete SWACHHA data, and the other way round:
 * every action that writes data is tried against the other agency, and not one row may change.
 */
class CrossAgencyWriteTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = ['agencies', 'clients', 'gem_contracts', 'projects', 'posts', 'employees', 'employee_documents',
        'attendance_sheets', 'attendance_entries', 'invoices', 'invoice_lines', 'tax_invoices', 'users', 'project_user'];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->owner = User::factory()->create(['role' => 'admin', 'name' => 'Owner']);
        $this->owner->forceFill(['is_super_admin' => true])->save();
    }

    /** One agency with a full chain of records. */
    private function agency(string $code, string $name): array
    {
        $this->actingAs($this->owner);
        $agency = Agency::query()->create(['code' => $code, 'name' => $name]);
        $client = Client::query()->create(['agency_id' => $agency->id, 'name' => "ICMR via {$code}", 'short_code' => 'ICMR']);
        $contract = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => "GEM/{$code}", 'contract_no' => "GEMC-{$code}"]);
        $project = $contract->projects()->create(['name' => "P{$code}", 'code' => "P{$code}"]);
        $post = $project->posts()->create(['designation' => "Officer {$code}", 'sanctioned_qty' => 2, 'rate' => 20000]);
        $employee = Employee::query()->create(['emp_code' => "EMP-{$code}", 'name' => "Staff {$code}", 'agency_id' => $agency->id,
            'client_id' => $client->id, 'post_id' => $post->id, 'status' => 'active']);
        $document = $employee->documents()->create(['type' => 'pan', 'path' => "docs/{$code}.pdf", 'original_name' => "{$code}.pdf"]);
        $service = app(AttendanceService::class);
        $open = $service->open($project, CarbonImmutable::parse('2026-10-01'));
        $locked = $service->open($project, CarbonImmutable::parse('2026-09-01'));
        $service->lock($locked, $this->owner, null);
        $invoice = app(InvoiceGenerator::class)->generate($locked->fresh(), $this->owner);
        $second = $service->open($project, CarbonImmutable::parse('2026-08-01'));
        $service->lock($second, $this->owner, null);
        $invoice2 = app(InvoiceGenerator::class)->generate($second->fresh(), $this->owner);
        $tax = app(TaxInvoiceBuilder::class)->create($client, CarbonImmutable::parse('2026-08-01'), [$invoice2->id], $this->owner);
        $admin = User::factory()->create(['role' => 'admin', 'name' => "{$code} Admin", 'agency_id' => $agency->id]);
        $pi = User::factory()->create(['role' => 'pi', 'name' => "{$code} PI", 'agency_id' => $agency->id]);
        $pi->projects()->attach($project->id);

        return compact('agency', 'client', 'contract', 'project', 'post', 'employee', 'document', 'open', 'locked', 'invoice', 'tax', 'admin', 'pi');
    }

    private function fingerprint(): string
    {
        return md5(json_encode(collect(self::TABLES)->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()])->all()));
    }

    /** Every write action, aimed at the other agency's records ($o). */
    private function attacks(array $o): array
    {
        $codes = $o['open']->entries()->get()->mapWithKeys(fn ($e) => [$e->id => array_fill(1, 31, 'A')])->all();
        $pdf = fn () => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf');

        return [
            'edit their agency' => fn () => $this->put("/agencies/{$o['agency']->id}", ['name' => 'Hacked', 'code' => $o['agency']->code]),
            'add client under their agency' => fn () => $this->post('/clients', ['agency_id' => $o['agency']->id, 'name' => 'X', 'short_code' => 'XX', 'state_code' => '21', 'payment_term_days' => 30, 'status' => 'active']),
            'edit their client' => fn () => $this->put("/clients/{$o['client']->id}", ['agency_id' => $o['agency']->id, 'name' => 'Hacked', 'short_code' => 'ICMR', 'state_code' => '21', 'payment_term_days' => 30, 'status' => 'active']),
            'add contract for their client' => fn () => $this->post('/contracts', ['agency_id' => $o['agency']->id, 'client_id' => $o['client']->id, 'bid_no' => 'B', 'contract_no' => 'NEW-1', 'billing_type' => 'monthly', 'service_charge_percent' => 3.85, 'gst_percent' => 18, 'epf_percent' => 13, 'esic_percent' => 3.25, 'status' => 'active']),
            'edit their contract' => fn () => $this->put("/contracts/{$o['contract']->id}", ['agency_id' => $o['agency']->id, 'client_id' => $o['client']->id, 'bid_no' => 'Hacked', 'contract_no' => $o['contract']->contract_no, 'billing_type' => 'monthly', 'service_charge_percent' => 3.85, 'gst_percent' => 18, 'epf_percent' => 13, 'esic_percent' => 3.25, 'status' => 'active']),
            'add project to their contract' => fn () => $this->post("/contracts/{$o['contract']->id}/projects", ['name' => 'X', 'code' => 'X']),
            'edit their project' => fn () => $this->put("/projects/{$o['project']->id}", ['name' => 'Hacked', 'code' => 'H']),
            'add post to their project' => fn () => $this->post("/projects/{$o['project']->id}/posts", ['designation' => 'X', 'sanctioned_qty' => 1, 'rate' => 1]),
            'quick-add post under their contract' => fn () => $this->post('/posts/quick', ['agency_id' => $o['agency']->id, 'client_id' => $o['client']->id, 'contract_mode' => 'existing', 'gem_contract_id' => $o['contract']->id, 'project_mode' => 'existing', 'project_id' => $o['project']->id, 'designation' => 'X', 'sanctioned_qty' => 1, 'rate' => 1]),
            'edit their post' => fn () => $this->put("/posts/{$o['post']->id}", ['designation' => 'Hacked', 'sanctioned_qty' => 9, 'rate' => 1]),
            'delete their post' => fn () => $this->delete("/posts/{$o['post']->id}"),
            'add employee on their post' => fn () => $this->post('/employees', ['emp_code' => 'EMP-NEW', 'agency_id' => $o['agency']->id, 'client_id' => $o['client']->id, 'post_id' => $o['post']->id, 'name' => 'X', 'status' => 'active']),
            'add own-agency employee on their post' => fn () => $this->post('/employees', ['emp_code' => 'EMP-NEW2', 'agency_id' => '', 'post_id' => $o['post']->id, 'name' => 'X', 'status' => 'active']),
            'edit their employee' => fn () => $this->put("/employees/{$o['employee']->id}", ['emp_code' => $o['employee']->emp_code, 'agency_id' => $o['agency']->id, 'name' => 'Hacked', 'status' => 'active']),
            'switch EPF on their employee' => fn () => $this->patch("/employees/{$o['employee']->id}/statutory", ['pf_applicable' => '1']),
            'upload document to their employee' => fn () => $this->post("/employees/{$o['employee']->id}/documents", ['type' => 'pan', 'file' => $pdf()]),
            'delete their employee document' => fn () => $this->delete("/documents/{$o['document']->id}"),
            'start a month for their project' => fn () => $this->post('/attendance', ['project_id' => $o['project']->id, 'month' => '2026-11']),
            'change their attendance' => fn () => $this->put("/attendance/{$o['open']->id}", ['codes' => $codes]),
            'lock their month' => fn () => $this->post("/attendance/{$o['open']->id}/lock", ['certificate' => $pdf()]),
            'unlock their month' => fn () => $this->post("/attendance/{$o['locked']->id}/unlock"),
            'make a bill from their month' => fn () => $this->post('/invoices', ['attendance_sheet_id' => $o['open']->id]),
            'approve their bill' => fn () => $this->post("/invoices/{$o['invoice']->id}/status", ['status' => 'approved']),
            'delete their bill' => fn () => $this->delete("/invoices/{$o['invoice']->id}"),
            'make a tax invoice from their bill' => fn () => $this->post('/tax-invoices', ['client_id' => $o['client']->id, 'month' => '2026-09', 'sub_bills' => [$o['invoice']->id]]),
            'change their tax invoice status' => fn () => $this->post("/tax-invoices/{$o['tax']->id}/status", ['status' => 'submitted']),
            'delete their tax invoice' => fn () => $this->delete("/tax-invoices/{$o['tax']->id}"),
            'add a login for their agency' => fn () => $this->post('/users', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'supervisor', 'active' => '1', 'password' => 'Temp1234', 'agency_id' => $o['agency']->id]),
            'add a PI for their project' => fn () => $this->post('/users', ['name' => 'Y', 'email' => 'y@example.com', 'role' => 'pi', 'active' => '1', 'password' => 'Temp1234', 'projects' => [$o['project']->id]]),
            'edit their admin login' => fn () => $this->put("/users/{$o['admin']->id}", ['name' => 'Hacked', 'email' => $o['admin']->email, 'role' => 'admin', 'active' => '0']),
        ];
    }

    public function test_neither_agency_can_write_the_others_data(): void
    {
        $zps = $this->agency('ZPS', 'M/S Z Plus Surakhya Seva');
        $swh = $this->agency('SWH', 'SWACHHA');
        $results = [];

        foreach ([[$zps, $swh, 'Z Plus → SWACHHA'], [$swh, $zps, 'SWACHHA → Z Plus']] as [$me, $other, $direction]) {
            foreach ($this->attacks($other) as $what => $attack) {
                $this->actingAs($me['admin']);
                $before = $this->fingerprint();
                $status = $attack()->getStatusCode();
                $changed = $before !== $this->fingerprint();
                $results[] = sprintf('%-18s %-38s HTTP %d %s', $direction, $what, $status, $changed ? 'DATA CHANGED' : 'no change');
                $this->assertFalse($changed, "{$direction}: \"{$what}\" changed data (HTTP {$status})");
                $this->assertContains($status, [302, 403, 404, 422], "{$direction}: \"{$what}\" returned HTTP {$status}");
            }
            // Their PI cannot touch the other agency's attendance either.
            $this->actingAs($me['pi']);
            $before = $this->fingerprint();
            $this->put("/pi/attendance/{$other['open']->id}", ['codes' => []])->assertNotFound();
            $this->post("/pi/attendance/{$other['open']->id}/submit")->assertNotFound();
            $this->post("/pi/projects/{$other['project']->id}/months", ['month' => '2026-11'])->assertNotFound();
            $this->assertSame($before, $this->fingerprint());
        }
        fwrite(STDERR, "\n".implode("\n", $results)."\n");

        // The same actions on their own data still work (sanity check that the test is not trivially passing).
        $this->actingAs($zps['admin'])->put("/projects/{$zps['project']->id}", ['name' => 'Renamed', 'code' => 'PZPS'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $zps['project']->fresh()->name);
        $this->post('/clients', ['name' => 'Own client', 'short_code' => 'OWN', 'state_code' => '21', 'payment_term_days' => 30, 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame($zps['agency']->id, Client::query()->where('short_code', 'OWN')->value('agency_id'));
    }
}
