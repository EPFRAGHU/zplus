<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\GemContract;
use App\Models\Project;
use App\Models\User;
use App\Rules\ExistsInMyAgency;
use App\Services\Attendance\AttendanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SharedDeviceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
    }

    public function test_signed_in_pages_and_pdfs_are_never_cached_and_sign_out_wipes_the_browser(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sheet = app(AttendanceService::class)->open(Project::query()->where('code', 'MPH')->firstOrFail(), CarbonImmutable::parse('2026-10-01'));
        $this->actingAs($admin);
        foreach (['/employees', '/', "/attendance/{$sheet->id}/certificate"] as $url) {
            $this->assertStringContainsString('no-store', $this->get($url)->assertOk()->headers->get('cache-control'), $url);
        }
        $logout = $this->post('/logout')->assertRedirect('/login');
        $this->assertSame('"cache", "cookies", "storage"', $logout->headers->get('clear-site-data'));
        $this->assertStringContainsString('no-store', $logout->headers->get('cache-control'));
        $this->assertGuest();
    }

    public function test_there_is_no_keep_me_signed_in(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Keep me signed in')->assertSee('always click');
        $user = User::factory()->create(['role' => 'admin', 'password' => 'Secret123']);
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'Secret123', 'remember' => '1']);
        $this->assertNull(collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_')));
    }

    public function test_a_password_change_signs_out_other_sessions_but_keeps_your_own(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'password' => 'Secret123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123']);
        $this->get('/employees')->assertOk();

        // Password changed elsewhere (another device, or reset by an Admin): this session ends.
        $user->forceFill(['password' => 'Changed456'])->save();
        auth()->forgetGuards();   // a real request loads the user afresh from the database
        $this->get('/employees')->assertRedirect('/login');
        $this->assertGuest();

        // Changing your own password keeps you signed in on this device.
        $this->post('/login', ['email' => $user->email, 'password' => 'Changed456']);
        $this->put('/account/password', ['current_password' => 'Changed456', 'password' => 'NewPass789', 'password_confirmation' => 'NewPass789'])->assertRedirect();
        auth()->forgetGuards();
        $this->get('/employees')->assertOk();
    }

    public function test_validation_only_accepts_records_of_your_own_agency(): void
    {
        $zps = Agency::query()->where('code', 'ZPS')->firstOrFail();
        $swhClient = Client::query()->whereHas('agency', fn ($q) => $q->where('code', 'SWH'))->firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'agency_id' => $zps->id]));
        $this->assertTrue(Validator::make(['c' => $swhClient->id], ['c' => [new ExistsInMyAgency(Client::class)]])->fails());
        $own = Client::query()->firstOrFail();
        $this->assertFalse(Validator::make(['c' => $own->id], ['c' => [new ExistsInMyAgency(Client::class)]])->fails());
    }

    public function test_database_refuses_rows_that_mix_agencies(): void
    {
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $zpsClient = Client::query()->whereHas('agency', fn ($q) => $q->where('code', 'ZPS'))->firstOrFail();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('A GeM contract must belong to the same agency as its client');
        GemContract::query()->create(['agency_id' => $swh->id, 'client_id' => $zpsClient->id, 'bid_no' => 'X', 'contract_no' => 'GEMC-MIXED']);
    }
}
