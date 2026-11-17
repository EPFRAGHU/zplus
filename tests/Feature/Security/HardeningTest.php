<?php

namespace Tests\Feature\Security;

use App\Models\Agency;
use App\Models\Client;
use App\Models\GemContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Security audit Phase 2 (v2.15.1): each test proves one hole from SECURITY_AUDIT.md is closed. */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private array $r;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'admin']);
        $this->owner->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($this->owner);
        $agency = Agency::query()->create(['code' => 'SWH', 'name' => 'SWACHHA']);
        $client = Client::query()->create(['agency_id' => $agency->id, 'name' => 'ICMR', 'short_code' => 'ICMR']);
        $contract = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => 'GEM/1', 'contract_no' => 'GEMC-1']);
        $this->r = compact('agency', 'client', 'contract');
    }

    /** H2: a project name can no longer break out of the "Delete project" pop-up into JavaScript. */
    public function test_project_name_cannot_inject_script_into_delete_popups(): void
    {
        $evil = "X'); fetch('/approvals'); ('";
        $project = $this->r['contract']->projects()->create(['name' => $evil, 'code' => 'EVIL']);
        foreach ([route('contracts.show', $this->r['contract']), route('attendance.index')] as $url) {
            $html = $this->withSession(['delete_mode' => true])->get($url)->assertOk()->getContent();
            $this->assertStringContainsString(route('projects.destroy', $project), $html, $url);
            $this->assertStringNotContainsString("confirm('Delete project", $html, $url);
            $this->assertStringNotContainsString("fetch('/approvals')", $html, $url);   // only ever escaped
            $this->assertStringContainsString('data-confirm="Delete project X&#039;); fetch(&#039;/approvals&#039;)', $html, $url);
        }
    }
}
