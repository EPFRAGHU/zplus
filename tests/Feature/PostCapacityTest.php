<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Employee;
use App\Models\Post;
use App\Models\User;
use App\Services\Approvals\Approvals;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** No more staff on a post than sanctioned (owner, 22 Nov 2026). */
class PostCapacityTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    private User $maker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);   // MPH: each post 1 sanctioned, 1 filled
        $this->post = Post::query()->where('designation', 'Academic Programme Manager')->firstOrFail();
        $this->maker = User::factory()->create(['role' => 'accounts', 'agency_id' => $this->post->project->contract->agency_id]);
    }

    private function newStaff(string $code, array $over = []): array
    {
        return $over + ['emp_code' => $code, 'name' => 'Person '.$code, 'status' => 'active',
            'agency_id' => $this->post->project->contract->agency_id, 'post_id' => $this->post->id];
    }

    public function test_full_post_refuses_a_new_employee_with_a_clear_message(): void
    {
        $this->actingAs($this->maker)->post('/employees', $this->newStaff('ZP-5001'))
            ->assertSessionHasErrors(['post_id' => 'Post already filled: Academic Programme Manager (MPH) has 1 sanctioned and 1 already placed or waiting for approval. Raise the sanctioned number on the work order, or move someone off this post / set them Inactive first.']);
        $this->assertFalse(Employee::query()->where('emp_code', 'ZP-5001')->exists());

        $this->post->update(['sanctioned_qty' => 2]);
        $this->post('/employees', $this->newStaff('ZP-5001'))->assertSessionHasNoErrors();
    }

    public function test_staff_waiting_for_approval_take_their_place(): void
    {
        $this->post->update(['sanctioned_qty' => 2]);   // 1 on it, 1 free
        $this->actingAs($this->maker)->post('/employees', $this->newStaff('ZP-5001'))->assertSessionHasNoErrors();   // waits for approval (Inactive)
        $this->assertSame('inactive', Employee::query()->where('emp_code', 'ZP-5001')->value('status'));
        $this->post('/employees', $this->newStaff('ZP-5002'))->assertSessionHasErrors('post_id');   // the waiting one holds the last place
    }

    public function test_approval_refuses_when_the_post_filled_up_meanwhile(): void
    {
        $this->post->update(['sanctioned_qty' => 2]);
        $this->actingAs($this->maker)->post('/employees', $this->newStaff('ZP-5001'))->assertSessionHasNoErrors();
        // Meanwhile someone else is put on the post directly (e.g. before this rule): no place left for the waiting person.
        Employee::query()->create($this->newStaff('ZP-5009') + ['client_id' => $this->post->project->contract->client_id]);
        $approval = Approval::query()->where('type', 'new_employee')->where('status', 'pending')->firstOrFail();
        $checker = $this->makeDeveloper(User::factory()->create(['role' => 'admin']));

        $this->expectExceptionMessage('Post already filled');
        try {
            app(Approvals::class)->approve($approval, $checker);
        } finally {
            $this->assertSame('inactive', Employee::query()->where('emp_code', 'ZP-5001')->value('status'));
        }
    }

    public function test_moving_someone_onto_a_full_post_or_reactivating_them_is_refused(): void
    {
        $other = Post::query()->where('designation', '!=', 'Academic Programme Manager')->firstOrFail();
        $mover = Employee::query()->where('post_id', $other->id)->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);
        $form = fn (Employee $e, array $over) => $over + ['emp_code' => $e->emp_code, 'name' => $e->name, 'status' => $e->status, 'agency_id' => $e->agency_id, 'post_id' => $e->post_id];

        $this->actingAs($admin)->put(route('employees.update', $mover), $form($mover, ['post_id' => $this->post->id]))->assertSessionHasErrors('post_id');

        $holder = Employee::query()->where('post_id', $this->post->id)->firstOrFail();
        $holder->update(['status' => 'inactive']);
        Employee::query()->create($this->newStaff('ZP-5010') + ['client_id' => $this->post->project->contract->client_id]);   // the place is taken again
        $this->put(route('employees.update', $holder), $form($holder->fresh(), ['status' => 'active']))->assertSessionHasErrors('post_id');
    }

    public function test_staff_on_an_already_over_filled_post_can_still_be_edited(): void
    {
        Employee::query()->create($this->newStaff('ZP-5011') + ['client_id' => $this->post->project->contract->client_id]);   // 2 on a 1-post (from before the rule)
        $holder = Employee::query()->where('post_id', $this->post->id)->orderBy('id')->firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('employees.update', $holder),
            ['emp_code' => $holder->emp_code, 'name' => 'Corrected Name', 'status' => $holder->status, 'agency_id' => $holder->agency_id, 'post_id' => $holder->post_id])
            ->assertSessionHasNoErrors();
        $this->assertSame('Corrected Name', $holder->fresh()->name);
    }

    public function test_sanctioned_number_cannot_go_below_the_staff_on_the_post(): void
    {
        $this->post->update(['sanctioned_qty' => 3]);
        Employee::query()->create($this->newStaff('ZP-5012') + ['client_id' => $this->post->project->contract->client_id]);   // 2 on it now
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('posts.update', $this->post), ['designation' => $this->post->designation, 'sanctioned_qty' => 1, 'rate' => $this->post->rate])
            ->assertSessionHasErrors('sanctioned_qty');
        $this->put(route('posts.update', $this->post), ['designation' => $this->post->designation, 'sanctioned_qty' => 2, 'rate' => $this->post->rate])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, (int) $this->post->fresh()->sanctioned_qty);
    }

    public function test_employee_form_marks_full_posts(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/employees/create')->assertOk()
            ->assertSee('— POST FULL')->assertSee('data-full="1" disabled', false);
    }
}
