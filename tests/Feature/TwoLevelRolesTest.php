<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Approval;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Owner, 19 Nov 2026: two levels only. Makers (Accounts, one agency each) enter; Super Admins approve, return and
 * delete for both agencies; the developer (also a Super Admin) alone keeps Users, the Activity log and imports.
 */
class TwoLevelRolesTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    private User $owner2;

    private User $zMaker;

    private User $sMaker;

    private array $r;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->developer = $this->makeDeveloper(User::factory()->create(['name' => 'Developer']));
        $this->owner2 = User::factory()->create(['role' => 'admin', 'name' => 'Second owner']);
        $this->owner2->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($this->developer);
        $zps = Agency::query()->create(['code' => 'ZPS', 'name' => 'M/S Z Plus Surakhya Seva']);
        $swh = Agency::query()->create(['code' => 'SWH', 'name' => 'SWACHHA']);
        $client = Client::query()->create(['agency_id' => $zps->id, 'name' => 'ICMR', 'short_code' => 'ICMR']);
        $contract = GemContract::query()->create(['agency_id' => $zps->id, 'client_id' => $client->id, 'bid_no' => 'GEM/1', 'contract_no' => 'GEMC-1',
            'epf_percent' => '13', 'esic_percent' => '3.25', 'service_charge_percent' => '3.85', 'gst_percent' => '18']);
        $project = $contract->projects()->create(['name' => 'DBT', 'code' => 'DBT']);
        $post = $project->posts()->create(['designation' => 'Field Worker', 'sanctioned_qty' => 1, 'rate' => 20000]);
        Employee::query()->create(['emp_code' => 'ZP-1001', 'name' => 'Staff', 'agency_id' => $zps->id, 'client_id' => $client->id, 'post_id' => $post->id, 'status' => 'active']);
        $this->zMaker = User::factory()->create(['role' => 'accounts', 'agency_id' => $zps->id, 'name' => 'Z Plus maker']);
        $this->sMaker = User::factory()->create(['role' => 'accounts', 'agency_id' => $swh->id, 'name' => 'SWACHHA maker']);
        $this->r = compact('zps', 'swh', 'client', 'contract', 'project');
    }

    public function test_maker_enters_and_sends_but_cannot_approve_or_use_owner_tools(): void
    {
        $sheet = app(AttendanceService::class)->open($this->r['project'], CarbonImmutable::parse('2026-10-01'));
        $this->actingAs($this->zMaker)->post(route('attendance.lock', $sheet), ['certificate' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf')])->assertRedirect();
        $approval = Approval::query()->sole();
        $this->assertSame($this->zMaker->id, $approval->submitted_by);
        $this->post(route('approvals.approve', $approval))->assertForbidden();
        foreach (['/users', '/activity', '/data-upload', '/import'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('delete-mode'))->assertForbidden();
        $this->get('/')->assertDontSee('Activity log')->assertDontSee('Show delete options');
        // The SWACHHA maker cannot even see the Z Plus month.
        $this->actingAs($this->sMaker)->get(route('attendance.show', $sheet))->assertNotFound();
    }

    public function test_second_super_admin_approves_and_deletes_but_has_no_developer_tools(): void
    {
        $sheet = app(AttendanceService::class)->open($this->r['project'], CarbonImmutable::parse('2026-10-01'));
        $this->actingAs($this->zMaker)->post(route('attendance.lock', $sheet), ['certificate' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf')]);
        $this->actingAs($this->owner2)->post(route('approvals.approve', Approval::query()->sole()))->assertSessionHasNoErrors();
        $this->assertSame('approved', Approval::query()->sole()->status);
        $this->assertTrue($this->owner2->can('delete-records'));
        $this->get('/')->assertSee('Show delete options');
        foreach (['/users', '/activity', '/data-upload', '/import'] as $url) {
            $this->get($url)->assertForbidden();
        }
        // Cannot make anyone a Super Admin.
        $this->put(route('users.update', $this->zMaker), ['name' => 'x', 'email' => $this->zMaker->email, 'role' => 'admin', 'active' => 1, 'make_super_admin' => 1])->assertForbidden();
        $this->assertFalse($this->zMaker->fresh()->is_super_admin);
    }

    public function test_developer_keeps_users_activity_and_imports_and_can_reset_the_second_owners_login(): void
    {
        foreach (['/users', '/activity', '/data-upload', '/import'] as $url) {
            $this->actingAs($this->developer)->get($url)->assertOk();
        }
        $this->get(route('users.edit', $this->owner2))->assertOk();
        $this->put(route('users.update', $this->owner2), ['name' => 'Second owner', 'email' => $this->owner2->email, 'role' => 'admin', 'active' => 1, 'password' => 'NewTemp123'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->owner2->fresh()->must_change_password);
        $this->assertTrue($this->owner2->fresh()->is_super_admin);    // still a Super Admin
        $this->assertFalse($this->owner2->fresh()->isDeveloper());
    }

    public function test_sub_bills_have_two_levels_only_no_internal_approve_step(): void
    {
        config(['approvals.start' => '2100-01-01']);   // this test is about the status steps only
        $sheet = app(AttendanceService::class)->open($this->r['project'], CarbonImmutable::parse('2026-09-01'));
        $this->actingAs($this->zMaker)->post(route('attendance.lock', $sheet), ['certificate' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf')]);
        $bill = \App\Models\Invoice::query()->sole();
        $this->get(route('invoices.show', $bill))->assertDontSee('>Approve<', false)->assertSee('Mark submitted to client');
        $this->post(route('invoices.status', $bill), ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->post(route('invoices.status', $bill), ['status' => 'submitted'])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $bill->fresh()->status);
    }
}
