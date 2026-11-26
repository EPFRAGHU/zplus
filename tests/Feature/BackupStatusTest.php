<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Database backup status reported by the backup scripts, shown to the developer only (owner, 26 Nov 2026). */
class BackupStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_scripts_record_backups_and_the_card_turns_green_amber_or_red(): void
    {
        $this->assertSame('red', BackupRun::health('primary'), 'no backup yet');

        $this->artisan('backups:record', ['--location' => 'primary', '--server' => 'srv1962767', '--file' => '/root/zplus-backups/db-2026-11-27.sql.gz', '--size' => '812345'])
            ->assertSuccessful();
        $run = BackupRun::query()->firstOrFail();
        $this->assertSame('db-2026-11-27.sql.gz', $run->file, 'file name only, never the path');
        $this->assertSame('793 KB', $run->sizeLabel());
        $this->assertSame('green', BackupRun::health('primary'));
        $this->assertSame('red', BackupRun::health('secondary'));

        $this->travel(30)->hours();
        $this->assertSame('amber', BackupRun::health('primary'), 'older than 26 hours');

        $this->artisan('backups:record', ['--location' => 'primary', '--server' => 'srv1962767', '--status' => 'failed', '--note' => 'dump did not finish'])->assertSuccessful();
        $this->assertSame('red', BackupRun::health('primary'), 'the latest run failed');
        $this->assertSame($run->id, BackupRun::lastGood('primary')->id, 'the last good backup is still shown');

        $this->artisan('backups:record', ['--location' => 'elsewhere', '--server' => 'x'])->assertFailed();
        $this->artisan('backups:record', ['--location' => 'primary'])->assertFailed();   // server name needed
    }

    public function test_only_the_developer_sees_the_card_and_the_backups_page(): void
    {
        $this->artisan('backups:record', ['--location' => 'primary', '--server' => 'srv1962767', '--file' => 'db-2026-11-27.sql.gz', '--size' => '812345']);
        $this->artisan('backups:record', ['--location' => 'secondary', '--server' => 'RAGHU-DELL', '--file' => 'db-2026-11-27.sql.gz', '--size' => '812345']);
        $dev = $this->makeDeveloper(User::factory()->create(['role' => 'admin']));

        $this->actingAs($dev)->get('/')->assertOk()->assertSee('Database backup')->assertSee('srv1962767')->assertSee('RAGHU-DELL')->assertSee('Backed up')
            ->assertSee(route('backups.index'));
        $this->get(route('backups.index'))->assertOk()->assertSee('db-2026-11-27.sql.gz')->assertSee('Primary (on the server)');

        // The other Super Admin and Accounts do not see it.
        $other = User::factory()->create(['role' => 'admin']);
        $other->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($other)->get('/')->assertOk()->assertDontSee('Database backup');
        $this->get(route('backups.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'accounts']))->get(route('backups.index'))->assertForbidden();
    }
}
