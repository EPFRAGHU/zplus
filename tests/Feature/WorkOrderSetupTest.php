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
        $this->actingAs($this->maker)->get(route('contracts.create'))->assertOk()->assertSee('Projects, Project In-charge, posts and staff')->assertSee('Dr. Known');

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

    public function test_one_pi_for_several_projects_is_saved_once(): void
    {
        ProjectIncharge::query()->create(['agency_id' => $this->swh->id, 'name' => 'Dr. Saved PI', 'status' => 'active']);
        $this->actingAs($this->maker)->post(route('contracts.store'), $this->form([
            ['code' => 'MPH', 'name' => 'MPH', 'pi_name' => 'Dr. New  PI', 'pi_mobile' => '9123456789'],
            ['code' => 'DBT', 'name' => 'DBT', 'pi_name' => 'dr. new pi'],
            ['code' => 'NRL', 'name' => 'NRL', 'pi_name' => 'Dr. Saved PI'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, ProjectIncharge::query()->count());   // the new PI once, the saved PI reused
        $contract = GemContract::query()->firstOrFail();
        $byCode = $contract->projects()->pluck('project_incharge_id', 'code');
        $this->assertSame($byCode['MPH'], $byCode['DBT']);
        $this->assertSame(ProjectIncharge::query()->where('name', 'Dr. Saved PI')->value('id'), $byCode['NRL']);
    }

    public function test_existing_work_order_gets_more_projects_on_the_same_page(): void
    {
        $contract = GemContract::query()->create(['agency_id' => $this->swh->id, 'client_id' => $this->client->id, 'bid_no' => 'B', 'contract_no' => 'GEMC-9']);
        $contract->projects()->create(['name' => 'MPH', 'code' => 'MPH']);
        $this->actingAs($this->maker)->get(route('contracts.show', $contract))->assertOk()->assertSee('Save projects, posts and staff');

        $this->post(route('contracts.setup', $contract), ['setup' => [['code' => 'mph', 'name' => 'Again']]])->assertSessionHasErrors('setup.0.code');
        $this->post(route('contracts.setup', $contract), ['setup' => [['code' => 'DBT', 'name' => 'DBT',
            'posts' => [['designation' => 'DEO', 'sanctioned_qty' => 1, 'rate' => '18000']], 'staff' => [['name' => 'Ravi', 'post' => 0]]]]])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Added 1 project, 1 post and 1 staff. New staff stay Inactive until a Super Admin approves them.');
        $this->assertSame(['DBT', 'MPH'], $contract->projects()->orderBy('code')->pluck('code')->all());
        $this->assertSame(1, Employee::query()->count());
    }

    public function test_project_in_charge_page_filters_to_one_pi(): void
    {
        $a = ProjectIncharge::query()->create(['agency_id' => $this->swh->id, 'name' => 'Dr. Alpha', 'status' => 'active']);
        ProjectIncharge::query()->create(['agency_id' => $this->swh->id, 'name' => 'Dr. Beta', 'status' => 'active']);
        $zps = Agency::query()->create(['code' => 'ZPS', 'name' => 'Z Plus']);
        $other = ProjectIncharge::query()->create(['agency_id' => $zps->id, 'name' => 'Dr. Other', 'status' => 'active']);

        $this->actingAs($this->maker)->get(route('incharges.index'))->assertSee('Dr. Alpha')->assertSee('Dr. Beta')
            ->assertSee('All Project In-charges')->assertDontSee('Dr. Other');
        $html = $this->get(route('incharges.index', ['pi' => $a->id]))->assertOk()->getContent();
        $this->assertStringContainsString('<option value="'.$a->id.'" selected>Dr. Alpha', $html);
        $this->assertSame(1, substr_count(strip_tags(preg_replace('#<select.*?</select>#s', '', $html)), 'Dr. Alpha'));
        $this->assertStringNotContainsString('Dr. Beta', strip_tags(preg_replace('#<select.*?</select>#s', '', $html)));
        $this->get(route('incharges.index', ['pi' => $other->id]))->assertOk()->assertDontSee('Dr. Other');   // another agency's PI is never shown
    }

    public function test_project_in_charge_without_projects_can_be_deleted_from_its_edit_page(): void
    {
        $free = ProjectIncharge::query()->create(['agency_id' => $this->swh->id, 'name' => 'Dr. Free', 'status' => 'active']);
        $busy = ProjectIncharge::query()->create(['agency_id' => $this->swh->id, 'name' => 'Dr. Busy', 'status' => 'active']);
        $contract = GemContract::query()->create(['agency_id' => $this->swh->id, 'client_id' => $this->client->id, 'bid_no' => 'B', 'contract_no' => 'GEMC-7']);
        $contract->projects()->create(['name' => 'MPH', 'code' => 'MPH', 'project_incharge_id' => $busy->id]);

        $this->actingAs($this->maker)->get(route('incharges.edit', $free))->assertSee('Delete this Project In-charge')->assertSee('can be deleted');
        $this->get(route('incharges.edit', $busy))->assertSee('Untick them under');   // button shown disabled with the reason
        $this->delete(route('incharges.destroy', $busy))->assertSessionHasErrors('delete');
        $this->delete(route('incharges.destroy', $free))->assertRedirect(route('incharges.index'));
        $this->assertNull(ProjectIncharge::query()->find($free->id));
        $this->assertNotNull(ProjectIncharge::query()->find($busy->id));

        $this->actingAs(User::factory()->create(['role' => 'supervisor', 'agency_id' => $this->swh->id]))
            ->get(route('incharges.edit', $busy))->assertDontSee('Delete this Project In-charge');
    }
}
