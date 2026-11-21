<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Release;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_reports_database_without_setting_cookies(): void
    {
        $response = $this->get('/health')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertEmpty($response->headers->getCookies());
    }

    public function test_error_pages_use_the_app_design_and_hide_details(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('Page not found')->assertSee('favicon.svg', false);

        Route::get('/_boom', fn () => throw new \RuntimeException('secret detail'))->middleware('web');
        config(['app.debug' => false]);
        $this->get('/_boom')->assertStatus(500)->assertSee('Something went wrong')->assertDontSee('secret detail');

        $accounts = User::factory()->create(['role' => 'accounts']);
        $this->actingAs($accounts)->get('/users')->assertForbidden()->assertSee('Not allowed');
    }

    public function test_changelog_lists_the_current_version(): void
    {
        $changelog = file_get_contents(base_path('CHANGELOG.md'));
        $this->assertStringContainsString('## '.Release::version().' ', $changelog, 'Run: php artisan releases:changelog');
    }

    public function test_every_page_uses_the_shared_theme(): void
    {
        $this->assertFileExists(public_path('css/app.css'));
        $this->get('/login')->assertSee('family=Inter', false);
        $this->actingAs(User::factory()->create(['role' => 'accounts']))->get('/')->assertSee('css/app.css?v=', false)->assertDontSee('Plus+Jakarta', false);
    }
}
