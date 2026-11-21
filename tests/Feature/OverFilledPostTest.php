<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\GemContract;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Posts with more staff placed than sanctioned are highlighted in red (owner, 22 Nov 2026). */
class OverFilledPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_over_filled_posts_are_highlighted_and_repeated_names_flagged(): void
    {
        $this->seed(DemoSeeder::class);
        $first = Employee::query()->whereNotNull('post_id')->orderBy('id')->firstOrFail();
        // The same person entered a second time, in capitals with a title: the post now has one more than sanctioned.
        Employee::query()->create(['emp_code' => 'ZP-9999', 'name' => 'Dr. '.strtoupper($first->name), 'agency_id' => $first->agency_id,
            'client_id' => $first->client_id, 'post_id' => $first->post_id, 'status' => 'active']);
        $contract = GemContract::query()->firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/posts')->assertOk()
            ->assertSee('Over-filled (1)')->assertSee('class="wo-over"', false)
            ->assertSee('<span class="badge text-bg-danger">1 over</span>', false)
            ->assertSee('1 more staff than sanctioned');
        $this->get('/posts?tab=over')->assertOk()->assertSee($first->post->designation)->assertSee('Over-filled (1)');

        $this->get('/contracts')->assertOk()->assertSee('wo-fill over', false)->assertSee('1 over');
        $this->get('/contracts?staffing=over')->assertOk()->assertSee($contract->contract_no);

        $this->get(route('contracts.show', $contract))->assertOk()
            ->assertSee('class="wo-over"', false)->assertSee('same name?');
    }

    public function test_posts_within_their_sanction_are_not_highlighted(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/posts')->assertOk()->assertSee('Over-filled (0)')->assertDontSee('class="wo-over"', false)->assertDontSee('more staff than sanctioned');
        $this->get(route('contracts.show', GemContract::query()->firstOrFail()))->assertDontSee('same name?')->assertDontSee('class="wo-over"', false);
    }
}
