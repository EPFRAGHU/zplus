<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner, 22 Nov 2026: a post across projects, on the Posts page and as post-wise sub-bills (screen and PDF). */
class PostWiseTest extends TestCase
{
    use RefreshDatabase;

    private User $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutOwnerApproval();
        $this->seed(DemoSeeder::class);
        $this->accounts = User::factory()->create(['role' => 'accounts']);
        $mph = Project::query()->where('code', 'MPH')->firstOrFail();
        $dbt = $mph->contract->projects()->create(['name' => 'DBT Project', 'code' => 'DBT']);
        $post = $dbt->posts()->create(['designation' => 'academic programme  manager', 'sanctioned_qty' => 2, 'rate' => 50000]);
        Employee::query()->create(['emp_code' => 'ZP-9001', 'name' => 'Field Staff', 'post_id' => $post->id, 'status' => 'active',
            'agency_id' => $mph->contract->agency_id, 'client_id' => $mph->contract->client_id]);
        $service = app(AttendanceService::class);
        foreach ([$mph, $dbt] as $p) {
            $sheet = $service->open($p, CarbonImmutable::parse('2026-10-01'));
            $service->lock($sheet, $this->accounts, null);
            app(InvoiceGenerator::class)->generate($sheet->fresh(), $this->accounts);
        }
    }

    public function test_posts_page_filters_one_post_across_projects(): void
    {
        $this->actingAs($this->accounts)->get(route('posts.index', ['post' => 'Academic Programme Manager']))->assertOk()
            ->assertSee('id="post-summary"', false)->assertSee('in 2 projects')->assertSee('3 sanctioned')
            ->assertDontSee('Academic Programme Assistant</td>', false);
    }

    public function test_post_wise_sub_bills_screen_and_pdf(): void
    {
        $this->actingAs($this->accounts)->get(route('reports.post-wise', ['month' => '2026-10']))->assertOk()
            ->assertSee('Academic Programme Manager')->assertSee('Academic Programme Assistant')->assertSee('ZP/001/26-27(DBT)');
        $html = $this->get(route('reports.post-wise', ['month' => '2026-10', 'post' => 'ACADEMIC PROGRAMME MANAGER']))->assertOk()->getContent();
        $this->assertStringContainsString('2 projects · 2 staff', $html);
        $this->assertStringNotContainsString('Academic Programme Assistant</h2>', $html);
        $this->get(route('reports.post-wise.pdf', ['month' => '2026-10']))->assertOk()->assertHeader('content-type', 'application/pdf');
        // All posts in one PDF, whatever post is chosen on the page (owner, 22 Nov 2026).
        $this->assertStringContainsString('All posts (PDF)', $html);
        $this->assertStringContainsString(e(route('reports.post-wise.pdf', ['month' => '2026-10'])), $html);
        $this->assertStringContainsString('This post (PDF)', $html);
        $view = view('pdf.post-wise', (fn () => $this->postWiseData(request()->merge(['month' => '2026-10']), app(\App\Services\Reports\PostWiseBills::class)))->call(app(\App\Http\Controllers\ReportController::class)))->render();
        $this->assertStringContainsString('ALL POSTS', $view);
        $this->assertStringContainsString('Academic Programme Manager', $view);
        $this->assertStringContainsString('Academic Programme Assistant', $view);
        $this->assertStringNotContainsString('rowspan', $view, 'no cell spans rows, so columns stay in place on every page');

        $this->actingAs(User::factory()->create(['role' => 'supervisor']))->get(route('reports.post-wise'))->assertForbidden();
    }
}
