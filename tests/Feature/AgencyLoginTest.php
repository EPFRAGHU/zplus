<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AgencyLoginTest extends TestCase
{
    use RefreshDatabase;

    private Agency $zps;

    private Agency $swh;

    private User $owner;

    private Employee $swhEmployee;

    private GemContract $swhContract;

    private Invoice $swhInvoice;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $this->zps = Agency::query()->where('code', 'ZPS')->firstOrFail();
        $this->swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $this->owner = User::factory()->create(['role' => 'admin', 'name' => 'Owner']);
        $this->owner->forceFill(['is_super_admin' => true])->save();

        // A SWACHHA contract with one project, post, employee, locked month and bill.
        $swhClient = Client::query()->where('agency_id', $this->swh->id)->firstOrFail();
        $this->swhContract = GemContract::query()->create(['agency_id' => $this->swh->id, 'client_id' => $swhClient->id,
            'bid_no' => 'GEM/2026/B/1111', 'contract_no' => 'GEMC-SWH-0001']);
        $project = $this->swhContract->projects()->create(['name' => 'SWHPROJ', 'code' => 'SWP']);
        $post = $project->posts()->create(['designation' => 'Field Worker', 'sanctioned_qty' => 1, 'rate' => 20000]);
        $this->swhEmployee = Employee::query()->create(['emp_code' => 'EMP-5001', 'name' => 'Swachha Staffer', 'agency_id' => $this->swh->id,
            'client_id' => $swhClient->id, 'post_id' => $post->id, 'status' => 'active']);
        $sheet = app(AttendanceService::class)->open($project, CarbonImmutable::parse('2026-10-01'));
        app(AttendanceService::class)->lock($sheet, $this->owner, null);
        $this->swhInvoice = app(InvoiceGenerator::class)->generate($sheet->fresh(), $this->owner);
    }

    private function agencyAdmin(Agency $agency): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => $agency->code.' Admin', 'agency_id' => $agency->id]);
    }

    public function test_agency_login_sees_only_its_own_agency_everywhere(): void
    {
        $this->actingAs($this->agencyAdmin($this->zps));
        $this->get('/employees')->assertOk()->assertSee('Amit Kumar')->assertDontSee('Swachha Staffer');
        $this->get('/contracts')->assertOk()->assertSee('GEMC-511687731718030')->assertDontSee('GEMC-SWH-0001');
        $this->get('/agencies')->assertOk()->assertSee('Z Plus')->assertDontSee('SWACHHA');
        $this->get('/invoices')->assertOk()->assertDontSee($this->swhInvoice->invoice_no);
        $this->get('/attendance?month=2026-10')->assertOk()->assertDontSee('SWHPROJ');
        $this->get('/posts')->assertOk()->assertDontSee('Field Worker');
        $this->get('/')->assertOk()->assertDontSee('SWHPROJ')->assertSee('Admin · ZPS')->assertSee('You are working for')->assertSee('M/S Z Plus Surakhya Seva');
        $this->get('/data-upload/employees/template?data=1')->assertOk();

        foreach (["/employees/{$this->swhEmployee->id}", "/contracts/{$this->swhContract->id}", "/agencies/{$this->swh->id}/edit",
            "/invoices/{$this->swhInvoice->id}", "/invoices/{$this->swhInvoice->id}/pdf", "/attendance/{$this->swhInvoice->attendance_sheet_id}"] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_tampered_forms_cannot_touch_the_other_agency(): void
    {
        $swhProjectId = $this->swhContract->projects()->value('id');
        $this->actingAs($this->agencyAdmin($this->zps));
        $this->post('/clients', ['agency_id' => $this->swh->id, 'name' => 'Sneaky', 'short_code' => 'SNK', 'state_code' => '21', 'payment_term_days' => 30, 'status' => 'active'])
            ->assertNotFound();
        $this->post('/attendance', ['project_id' => $swhProjectId, 'month' => '2026-11'])->assertNotFound();
        $this->post("/invoices/{$this->swhInvoice->id}/status", ['status' => 'approved'])->assertNotFound();
        $this->get('/agencies/create')->assertForbidden();
        $this->assertSame('draft', $this->swhInvoice->fresh()->status);
    }

    public function test_both_agency_staff_and_the_super_admin_see_everything(): void
    {
        $both = User::factory()->create(['role' => 'accounts']);
        foreach ([$both, $this->owner] as $user) {
            $this->actingAs($user)->get('/employees')->assertSee('Amit Kumar')->assertSee('Swachha Staffer');
            $this->get("/invoices/{$this->swhInvoice->id}")->assertOk();
        }
        $this->actingAs($this->owner)->get('/agencies/create')->assertOk();
        $this->get('/')->assertSee('You are working for')->assertSee('Both agencies');
    }

    public function test_agency_admin_manages_only_their_own_agencys_logins(): void
    {
        $zpsAdmin = $this->agencyAdmin($this->zps);
        $swhAdmin = $this->agencyAdmin($this->swh);
        $this->actingAs($zpsAdmin)->get('/users')->assertOk()->assertSee('ZPS Admin')->assertDontSee('SWH Admin')->assertDontSee('Owner');
        $this->get("/users/{$swhAdmin->id}/edit")->assertNotFound();

        $this->post('/users', ['name' => 'Ravi', 'email' => 'ravi@example.com', 'role' => 'supervisor', 'active' => '1', 'password' => 'Temp1234'])->assertRedirect('/users');
        $this->assertSame($this->zps->id, User::query()->where('email', 'ravi@example.com')->value('agency_id'));
        $this->post('/users', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'supervisor', 'active' => '1', 'password' => 'Temp1234', 'agency_id' => $this->swh->id])
            ->assertNotFound();

        // A both-agency Admin who is not the Super Admin must pick one agency.
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($other)->post('/users', ['name' => 'Y', 'email' => 'y@example.com', 'role' => 'accounts', 'active' => '1', 'password' => 'Temp1234'])
            ->assertSessionHasErrors('agency_id');
        // The Super Admin can create a login for both agencies.
        $this->actingAs($this->owner)->post('/users', ['name' => 'Both', 'email' => 'both@example.com', 'role' => 'accounts', 'active' => '1', 'password' => 'Temp1234'])
            ->assertRedirect('/users');
        $this->assertNull(User::query()->where('email', 'both@example.com')->value('agency_id'));
    }

    public function test_data_upload_and_new_codes_respect_the_other_agency(): void
    {
        $this->actingAs($this->agencyAdmin($this->zps));
        $upload = function (string $type, array $row) {
            $book = app(\App\Services\MasterImport\MasterImporter::class)->template($type);
            $ws = $book->getSheet(0);
            foreach ($ws->getRowIterator(1, 1)->current()->getCellIterator() as $cell) {
                $h = rtrim(trim((string) $cell->getValue()), ' *');
                if (isset($row[$h])) {
                    $ws->setCellValueExplicit($cell->getColumn().'2', $row[$h], DataType::TYPE_STRING);
                }
            }
            $path = tempnam(sys_get_temp_dir(), 'u').'.xlsx';
            (new Xlsx($book))->save($path);

            return $this->post("/data-upload/{$type}/check", ['file' => new UploadedFile($path, 'f.xlsx', null, null, true)]);
        };
        $upload('employees', ['Name' => 'X', 'Agency code' => 'SWH'])->assertSee('Agency code SWH is not in the app');
        $upload('contracts', ['Agency code' => 'ZPS', 'Client code' => 'ICMR', 'GeM contract no.' => 'GEMC-SWH-0001', 'GeM bid no.' => 'B'])->assertSee('belongs to another agency');
        $upload('employees', ['Employee code' => 'EMP-5001', 'Name' => 'Clash', 'Agency code' => 'ZPS'])->assertSee('already used by another agency');
        $upload('agencies', ['Agency code' => 'NEW', 'Agency name' => 'New One'])->assertSee('You can only update your own agency');

        // New employee codes continue after the highest code of either agency.
        $this->assertSame('EMP-5002', Employee::nextCode());
    }

    public function test_agency_logins_get_their_agency_filled_in_on_forms(): void
    {
        $this->actingAs($this->agencyAdmin($this->zps));
        $this->get('/clients/create')->assertOk()->assertSee('You work for this agency');
        $this->post('/clients', ['agency_id' => '', 'name' => 'AIIMS Bhubaneswar', 'short_code' => 'AIIMS', 'state_code' => '21', 'payment_term_days' => 30, 'status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->zps->id, Client::query()->where('short_code', 'AIIMS')->value('agency_id'));

        $this->actingAs($this->owner)->get('/clients/create')->assertOk()->assertSee('Choose agency')->assertDontSee('You work for this agency');
    }
}
