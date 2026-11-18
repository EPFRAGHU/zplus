<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use App\Support\Agency\AgencyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner, 18 Nov 2026: after signing in, a login for both agencies chooses SWACHHA, Z Plus or both. */
class AgencyChoiceTest extends TestCase
{
    use RefreshDatabase;

    private Agency $zps;

    private Agency $swh;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zps = Agency::query()->create(['code' => 'ZPS', 'name' => 'M/S Z Plus Surakhya Seva']);
        $this->swh = Agency::query()->create(['code' => 'SWH', 'name' => 'SWACHHA']);
        Client::query()->create(['agency_id' => $this->zps->id, 'name' => 'Z client', 'short_code' => 'ZCL']);
        Client::query()->create(['agency_id' => $this->swh->id, 'name' => 'S client', 'short_code' => 'SCL']);
        $this->owner = $this->makeDeveloper(User::factory()->create(['email' => 'owner@example.test', 'password' => 'Secret-123']));
    }

    public function test_super_admin_chooses_the_company_after_signing_in(): void
    {
        $this->post('/login', ['email' => 'owner@example.test', 'password' => 'Secret-123'])->assertRedirect(route('agency.choose'));
        $this->get(route('agency.choose'))->assertOk()->assertSee('Which company do you want to work in?')
            ->assertSee('SWACHHA')->assertSee('M/S Z Plus Surakhya Seva')->assertSee('Both agencies');

        // SWACHHA: only SWACHHA records, agency shown in the header, other-agency writes refused.
        $this->post(route('agency.choose.store'), ['agency' => (string) $this->swh->id])->assertRedirect(route('dashboard'));
        $this->get(route('clients.index'))->assertOk()->assertSee('S client')->assertDontSee('Z client');
        $this->get('/')->assertSee('Change company');
        $this->assertSame(['SCL'], Client::query()->pluck('short_code')->all());
        $this->assertFalse(\Illuminate\Support\Facades\Gate::allows('gem-import'));   // needs both agencies

        // Switch to Z Plus, then back to both.
        $this->post(route('agency.choose.store'), ['agency' => (string) $this->zps->id])->assertRedirect();
        $this->get(route('clients.index'))->assertSee('Z client')->assertDontSee('S client');
        $this->post(route('agency.choose.store'), ['agency' => 'both'])->assertRedirect();
        $this->get(route('clients.index'))->assertSee('Z client')->assertSee('S client');
        $this->assertTrue(\Illuminate\Support\Facades\Gate::allows('gem-import'));
    }

    public function test_agency_logins_and_pis_cannot_switch_and_go_straight_in(): void
    {
        $maker = User::factory()->create(['role' => 'accounts', 'agency_id' => $this->swh->id, 'email' => 'maker@example.test', 'password' => 'Secret-123']);
        $this->post('/login', ['email' => 'maker@example.test', 'password' => 'Secret-123'])->assertRedirect(route('dashboard'));
        $this->get(route('agency.choose'))->assertForbidden();
        $this->post(route('agency.choose.store'), ['agency' => (string) $this->zps->id])->assertForbidden();
        $this->get(route('clients.index'))->assertSee('S client')->assertDontSee('Z client');
        $this->get('/')->assertDontSee('Change company');
    }

    public function test_unknown_company_is_refused_and_choice_ends_with_sign_out(): void
    {
        $this->actingAs($this->owner)->post(route('agency.choose.store'), ['agency' => '999'])->assertSessionHasErrors('agency');
        $this->post(route('agency.choose.store'), ['agency' => (string) $this->swh->id]);
        $this->assertSame($this->swh->id, AgencyContext::id());
        $this->post('/logout');
        $this->actingAs($this->owner);
        $this->assertNull(AgencyContext::id());
    }
}
