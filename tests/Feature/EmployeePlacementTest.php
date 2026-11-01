<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePlacementTest extends TestCase
{
    use RefreshDatabase;

    private function base(array $over = []): array
    {
        return $over + ['emp_code' => 'EMP-2001', 'name' => 'New Joiner', 'status' => 'active'];
    }

    public function test_form_shows_agency_client_and_post_dropdowns(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/employees/create')->assertOk()
            ->assertSee('id="f-agency_id"', false)->assertSee('id="f-client_id"', false)->assertSee('id="f-post_id"', false)
            ->assertSee('data-agency=', false)->assertSee('data-client=', false);
    }

    public function test_agency_is_required_and_client_can_wait(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();

        $this->actingAs($admin)->post('/employees', $this->base())->assertSessionHasErrors(['agency_id' => 'Choose the agency that employs this person.']);
        $this->post('/employees', $this->base(['agency_id' => $swh->id]))->assertSessionHasNoErrors();
        $e = Employee::query()->where('emp_code', 'EMP-2001')->firstOrFail();
        $this->assertSame($swh->id, $e->agency_id);
        $this->assertNull($e->client_id);
        $this->get('/employees/'.$e->id)->assertSee('SWACHHA')->assertSee('Not assigned to a client yet');
    }

    public function test_client_and_post_must_belong_to_the_chosen_agency(): void
    {
        $this->seed(DemoSeeder::class);   // the MPH posts are under Z Plus's ICMR record
        $admin = User::factory()->create(['role' => 'admin']);
        $zps = Agency::query()->where('code', 'ZPS')->firstOrFail();
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $swhIcmr = Client::query()->where('agency_id', $swh->id)->firstOrFail();
        $zpsIcmr = Client::query()->where('agency_id', $zps->id)->firstOrFail();
        $mphPost = Post::query()->firstOrFail();

        $this->actingAs($admin)->post('/employees', $this->base(['agency_id' => $zps->id, 'client_id' => $swhIcmr->id]))->assertSessionHasErrors('client_id');
        $this->post('/employees', $this->base(['agency_id' => $swh->id, 'post_id' => $mphPost->id]))->assertSessionHasErrors('post_id');

        // A valid post fills in the client even if it was left blank.
        $this->post('/employees', $this->base(['agency_id' => $zps->id, 'post_id' => $mphPost->id]))->assertSessionHasNoErrors();
        $this->assertSame($zpsIcmr->id, Employee::query()->where('emp_code', 'EMP-2001')->value('client_id'));
    }

    public function test_employee_list_filters_by_agency_and_client(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        Employee::query()->create(['emp_code' => 'EMP-3001', 'name' => 'Swachha Person', 'agency_id' => $swh->id]);

        $this->actingAs($admin)->get('/employees')->assertSee('<th>Agency</th><th>Client</th>', false)
            ->assertSee('id="f-agency"', false)->assertSee('data-agency=', false)->assertSee('Not assigned yet');
        $this->get('/employees?agency='.$swh->id)->assertSee('Swachha Person')->assertDontSee('Amit Kumar (sample)');
        $zpsIcmr = Client::query()->whereHas('agency', fn ($q) => $q->where('code', 'ZPS'))->firstOrFail();
        $this->get('/employees?client='.$zpsIcmr->id)->assertSee('Amit Kumar (sample)')->assertDontSee('Swachha Person');
    }

    public function test_agency_options_show_code_and_post_count(): void
    {
        $this->seed(DemoSeeder::class);   // demo: Z Plus has the 2 MPH posts, SWACHHA has none
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/employees/create')->assertOk()->getContent();

        $this->assertStringContainsString('M/S Z Plus Surakhya Seva (ZPS) · 2 posts', $html);
        $this->assertStringContainsString('SWACHHA (SWH) · 0 posts', $html);
        $this->assertStringContainsString('id="no-posts"', $html);
    }

    public function test_employee_list_shows_and_filters_by_contract_and_project(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $contract = GemContract::query()->firstOrFail();
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        Employee::query()->create(['emp_code' => 'EMP-3002', 'name' => 'Unplaced Person', 'agency_id' => $swh->id]);

        $this->actingAs($admin)->get('/employees')->assertOk()
            ->assertSee('<th>GeM contract</th><th>Project</th><th>Post</th>', false)
            ->assertSee('GEM/2026/B/7972720</a>', false)->assertSee('PI: Dr. S. Kanungo')
            ->assertSee('Not placed on a post')->assertSee('id="f-contract"', false);
        $this->get('/employees')->assertDontSee('@else')->assertDontSee('@endif');   // no stray template code
        $this->get('/employees?contract='.$contract->id)->assertSee('Amit Kumar (sample)')->assertDontSee('Unplaced Person')
            ->assertDontSee('Not placed on a post');
    }

    public function test_employee_list_is_split_into_agency_blocks(): void
    {
        $this->seed(DemoSeeder::class);   // two Z Plus employees
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        foreach (['Aaron Swachha', 'Zara Swachha', 'Mid Swachha'] as $i => $n) {
            Employee::query()->create(['emp_code' => 'EMP-40'.$i, 'name' => $n, 'agency_id' => $swh->id]);
        }

        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/employees')->assertOk()->getContent();
        $zpsHead = strpos($html, '<b>M/S Z Plus Surakhya Seva</b>');
        $swhHead = strpos($html, '<b>SWACHHA</b>');
        $this->assertNotFalse($zpsHead);
        $this->assertNotFalse($swhHead);
        $this->assertStringContainsString('2 employees', $html);
        $this->assertStringContainsString('3 employees', $html);
        // Each agency's people sit under its own heading, not mixed alphabetically.
        $this->assertGreaterThan($zpsHead, strpos($html, 'Amit Kumar (sample)'));
        $this->assertLessThan($swhHead, strpos($html, 'Ritu Panda (sample)'));
        $this->assertGreaterThan($swhHead, strpos($html, 'Aaron Swachha'));
        $this->assertSame(1, substr_count($html, '<b>SWACHHA</b>'));
    }
}
