<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Release;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_versions_are_valid_newest_first_and_start_on_the_project_start_date(): void
    {
        $all = Release::all();
        $versions = array_column($all, 'version');
        foreach ($versions as $v) {
            $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $v);
        }
        $sorted = $versions;
        usort($sorted, fn ($a, $b) => version_compare($b, $a));
        $this->assertSame($sorted, $versions, 'releases must be listed newest first');
        $this->assertSame(count($versions), count(array_unique($versions)), 'versions must be unique');
        $this->assertSame('2026-11-01', Release::started()->toDateString());
        $this->assertSame('2026-11-01', end($all)['date']->toDateString());
        foreach ($all as $r) {
            $this->assertTrue($r['date']->gte(Release::started()));
            $this->assertNotEmpty($r['notes']);
        }
    }

    public function test_version_shows_in_the_sidebar_and_on_the_release_notes_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'supervisor']));
        $v = Release::version();

        $this->get('/')->assertOk()->assertSee("v{$v}")->assertSee('Since 01 Nov 2026')->assertSee(route('releases'));
        $this->get('/releases')->assertOk()->assertSee('Version history')->assertSee("v{$v}")->assertSee('v0.1.0')
            ->assertSee('Project started')->assertSee('Sunday, 01 November 2026');
    }

    public function test_login_page_shows_the_version(): void
    {
        $this->get('/login')->assertOk()->assertSee('ZPlus Manpower v'.Release::version());
    }
}
