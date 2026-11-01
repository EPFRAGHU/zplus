<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\GemContract;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickPostTest extends TestCase
{
    use RefreshDatabase;

    private function swachha(): array
    {
        $this->seed(DemoSeeder::class);   // SWACHHA's ICMR record has no contract yet
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();

        return [$swh, Client::query()->where('agency_id', $swh->id)->firstOrFail(), User::factory()->create(['role' => 'admin'])];
    }

    public function test_add_post_with_a_new_contract_and_project_in_one_step(): void
    {
        [$swh, $icmr, $admin] = $this->swachha();

        $res = $this->actingAs($admin)->postJson('/posts/quick', [
            'agency_id' => $swh->id, 'client_id' => $icmr->id,
            'contract_mode' => 'new', 'bid_no' => 'GEM/2026/B/8004647', 'contract_no' => 'GEMC-511687702194133', 'billing_type' => 'monthly',
            'project_mode' => 'new', 'project_name' => 'Snakebite', 'pi_name' => 'Dr. S. Kanungo',
            'designation' => 'PTS-III', 'sanctioned_qty' => 2, 'rate' => 33600,
        ])->assertCreated();

        $post = Post::query()->findOrFail($res->json('post.id'));
        $this->assertSame('PTS-III', $post->designation);
        $this->assertSame('SNAKEBITE', $post->project->code);
        $this->assertSame($swh->id, $post->project->contract->agency_id);
        $this->assertSame($icmr->id, $res->json('post.client_id'));
        $this->assertStringContainsString('GEM/2026/B/8004647 · Snakebite · PTS-III', $res->json('post.label'));
    }

    public function test_add_post_to_an_existing_contract_and_project(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $contract = GemContract::query()->firstOrFail();
        $project = $contract->projects()->firstOrFail();

        $this->actingAs($admin)->postJson('/posts/quick', [
            'agency_id' => $contract->agency_id, 'client_id' => $contract->client_id,
            'contract_mode' => 'existing', 'gem_contract_id' => $contract->id,
            'project_mode' => 'existing', 'project_id' => $project->id,
            'designation' => 'Data Entry Operator', 'sanctioned_qty' => 1, 'rate' => 20000,
        ])->assertCreated();
        $this->assertSame(3, $project->posts()->count());
    }

    public function test_mismatched_contract_and_missing_fields_are_explained(): void
    {
        [$swh, $icmr, $admin] = $this->swachha();
        $zpsContract = GemContract::query()->firstOrFail();   // belongs to Z Plus's ICMR record

        $this->actingAs($admin)->postJson('/posts/quick', [
            'agency_id' => $swh->id, 'client_id' => $icmr->id, 'contract_mode' => 'existing', 'gem_contract_id' => $zpsContract->id,
            'project_mode' => 'new', 'project_name' => 'X', 'designation' => 'Y', 'sanctioned_qty' => 1, 'rate' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['gem_contract_id' => 'This GeM contract is with a different client.']);

        $this->postJson('/posts/quick', ['agency_id' => $swh->id, 'client_id' => $icmr->id, 'contract_mode' => 'new', 'project_mode' => 'new'])
            ->assertStatus(422)->assertJsonValidationErrors(['bid_no', 'contract_no', 'project_name', 'designation', 'rate']);
        $this->assertSame(2, Post::query()->count());
    }

    public function test_posts_page_and_employee_form_offer_add_post_to_masters_users_only(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/posts')->assertOk()->assertSee('Academic Programme Manager')->assertSee('id="addPostModal"', false);
        $this->get('/employees/create')->assertOk()->assertSee('Add new post')->assertSee('id="qa-form"', false);

        $sup = User::factory()->create(['role' => 'supervisor']);
        $this->actingAs($sup)->get('/posts')->assertOk()->assertDontSee('id="addPostModal"', false);
        $this->postJson('/posts/quick', [])->assertForbidden();
    }
}
