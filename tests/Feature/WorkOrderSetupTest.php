<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Approval;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\ProjectIncharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner, 21 Nov 2026: work order, projects, PI, posts and staff entered on one page. */
class WorkOrderSetupTest extends TestCase
{
    use RefreshDatabase;

    private Agency $swh;

    private Client $client;

    private User $maker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->swh = Agency::query()->create(['code' => 'SWH', 'name' => 'SWACHHA', 'number_prefix' => 'SW']);
        $this->client = Client::query()->create(['agency_id' => $this->swh->id, 'name' => 'Administrative Officer NIHR', 'short_code' => 'NIHR']);
        $this->maker = User::factory()->create(['role' => 'accounts', 'agency_id' => $this->swh->id]);
    }

    private function form(array $projects): array
    {
        return ['agency_id' => $this->swh->id, 'client_id' => $this->client->id, 'status' => 'active', 'contract_type' => 'online',
            'bid_no' => 'GEM/2026/B/1', 'contract_no' => 'GEMC-1', 'billing_type' => 'monthly', 'service_charge_percent' => '3.85',
            'gst_percent' => '18', 'epf_percent' => '13', 'esic_percent' => '3.25', 'setup' => $projects];
    }

    public function test_one_page_saves_work_order_projects_pi_posts_and_staff(): void
    {
        $known = ProjectIncharge::query()->create(['agency_id' => $this->swh->id, 'name' => 'Dr. Known', 'status' => 'active']);
        $this->actingAs($this->maker)->get(route('contracts.create'))->assertOk()->assertSee('Projects, posts and staff')->assertSee('Dr. Known');

        $this->post(route('contracts.store'), $this->form([
            ['code' => 'mph', 'name' => 'MPH Academic Programme', 'project_incharge_id' => $known->id,
                'posts' => [['designation' => 'Project Technical Support-II', 'sanctioned_qty' => 2, 'rate' => '25000'], ['designation' => '', 'rate' => '']],
                'staff' => [['name' => 'Asha Das', 'post' => 0, 'joining_date' => '2026-10-01', 'mobile' => '9876543210', 'pf' => '1', 'tds' => '1'],
                    ['name' => 'Ravi Sahu', 'post' => 0], ['name' => '', 'mobile' => '']]],
            ['code' => 'DBT', 'name' => 'DBT Project', 'pi_name' => 'Dr. New PI', 'pi_mobile' => '9123456789',
                'posts' => [['designation' => 'Data Entry Operator', 'sanctioned_qty' => 1, 'rate' => '18000']]],
            ['code' => '', 'name' => ''],
        ]))->assertSessionHasNoErrors()->assertSessionHas('status', 'Work order added with 2 projects, 2 posts and 2 staff. New staff stay Inactive until a Super Admin approves them.');

        $contract = GemContract::query()->where('contract_no', 'GEMC-1')->firstOrFail();
        $this->assertSame(['DBT', 'MPH'], $contract->projects()->orderBy('code')->pluck('code')->all());
        $mph = $contract->projects()->where('code', 'MPH')->firstOrFail();
        $this->assertSame('Dr. Known', $mph->pi_name);
        $this->assertSame('Dr. New PI', $contract->projects()->where('code', 'DBT')->value('pi_name'));
        $this->assertSame('9123456789', ProjectIncharge::query()->where('name', 'Dr. New PI')->value('mobile'));

        $staff = Employee::query()->orderBy('emp_code')->get();
        $this->assertSame(['SW-1001', 'SW-1002'], $staff->pluck('emp_code')->all());
        $this->assertSame(['inactive', 'inactive'], $staff->pluck('status')->all());   // until a Super Admin approves
        $this->assertTrue($staff[0]->pf_applicable && $staff[0]->tds_applicable && ! $staff[0]->esic_applicable);
        $this->assertSame($mph->posts()->value('id'), $staff[0]->post_id);
        $this->assertSame($this->client->id, $staff[0]->client_id);
        $this->assertSame(2, Approval::query()->where('status', 'pending')->count());
    }

    public function test_a_staff_post_must_be_in_the_same_project_and_nothing_is_saved_on_error(): void
    {
        $this->actingAs($this->maker)->post(route('contracts.store'), $this->form([
            ['code' => 'MPH', 'name' => 'MPH', 'posts' => [['designation' => 'Assistant', 'sanctioned_qty' => 1, 'rate' => '1000']],
                'staff' => [['name' => 'Asha', 'post' => 3]]],
        ]))->assertSessionHasErrors('setup.0.staff');
        $this->assertSame(0, GemContract::query()->count());

        $this->post(route('contracts.store'), $this->form([['code' => 'MPH', 'name' => 'A'], ['code' => 'mph', 'name' => 'B']]))
            ->assertSessionHasErrors('setup.1.code');
        $this->assertSame(0, GemContract::query()->count());
    }

    public function test_the_other_agencys_project_in_charge_is_refused(): void
    {
        $zps = Agency::query()->create(['code' => 'ZPS', 'name' => 'Z Plus']);
        $theirs = ProjectIncharge::query()->create(['agency_id' => $zps->id, 'name' => 'Dr. Other', 'status' => 'active']);
        $this->actingAs($this->maker)->post(route('contracts.store'), $this->form([['code' => 'MPH', 'name' => 'MPH', 'project_incharge_id' => $theirs->id]]))
            ->assertSessionHasErrors('setup.0.project_incharge_id');

        $both = $this->makeDeveloper(User::factory()->create());
        $this->actingAs($both)->post(route('contracts.store'), $this->form([['code' => 'MPH', 'name' => 'MPH', 'project_incharge_id' => $theirs->id]]))
            ->assertSessionHasErrors('project_incharge_id');
        $this->assertSame(0, GemContract::query()->count());
    }
}
