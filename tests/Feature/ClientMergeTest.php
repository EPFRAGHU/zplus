<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\User;
use App\Services\Masters\ClientMerge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_developer_merges_an_agencys_clients_into_the_nihr_client(): void
    {
        $swh = Agency::query()->create(['code' => 'SWH', 'name' => 'SWACHHA']);
        $zps = Agency::query()->create(['code' => 'ZPS', 'name' => 'M/S Z Plus Surakhya Seva']);
        $icmr = Client::query()->create(['agency_id' => $swh->id, 'name' => 'ICMR-NIHR (RMRCBB), Bhubaneswar', 'short_code' => 'ICMR']);
        $ccrh = Client::query()->create(['agency_id' => $swh->id, 'name' => 'CCRH GOVT OF INDIA', 'short_code' => 'CCRH']);
        $hta = Client::query()->create(['agency_id' => $swh->id, 'name' => 'Regional Resource Hub for HTA', 'short_code' => 'HTA']);
        $zClient = Client::query()->create(['agency_id' => $zps->id, 'name' => 'Z client', 'short_code' => 'ZC']);
        foreach ([[$icmr, 'GEMC-1'], [$ccrh, 'GEMC-2'], [$hta, 'GEMC-3']] as [$c, $no]) {
            GemContract::query()->create(['agency_id' => $swh->id, 'client_id' => $c->id, 'bid_no' => 'B-'.$no, 'contract_no' => $no]);
        }
        Employee::query()->create(['emp_code' => 'SW-1001', 'name' => 'Staff', 'agency_id' => $swh->id, 'client_id' => $ccrh->id, 'status' => 'active']);

        $dev = $this->makeDeveloper(User::factory()->create());
        $this->actingAs($dev)->get(route('clients.merge'))->assertOk()->assertSee('CCRH GOVT OF INDIA')->assertSee(ClientMerge::NAME, false);
        $this->get(route('clients.index'))->assertSee('Merge into one client');

        $form = ['agency_id' => $swh->id, 'target_id' => $icmr->id, 'name' => ClientMerge::NAME];
        $this->post(route('clients.merge.store'), $form + ['confirm' => 'merge'])->assertSessionHasErrors('confirm');
        $this->assertSame(3, Client::query()->where('agency_id', $swh->id)->count());

        $this->post(route('clients.merge.store'), $form + ['confirm' => 'MERGE'])->assertRedirect(route('clients.merge'))
            ->assertSessionHas('status', 'SWACHHA: now one client. Moved 2 work orders, 1 employees and 0 tax invoices.');
        $this->assertSame([ClientMerge::NAME], Client::query()->where('agency_id', $swh->id)->pluck('name')->all());
        $this->assertSame([$icmr->id], GemContract::query()->distinct()->pluck('client_id')->all());
        $this->assertSame($icmr->id, Employee::query()->value('client_id'));
        $this->assertSame('Z client', $zClient->fresh()->name);   // the other agency is untouched
        $this->assertDatabaseHas('activity_logs', ['action' => 'client_merged']);
    }

    public function test_only_the_developer_can_merge(): void
    {
        $swh = Agency::query()->create(['code' => 'SWH', 'name' => 'SWACHHA']);
        $c = Client::query()->create(['agency_id' => $swh->id, 'name' => 'C', 'short_code' => 'C']);
        $owner2 = User::factory()->create(['role' => 'admin']);
        $owner2->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($owner2)->get(route('clients.merge'))->assertForbidden();
        $this->post(route('clients.merge.store'), ['agency_id' => $swh->id, 'target_id' => $c->id, 'name' => 'X', 'confirm' => 'MERGE'])->assertForbidden();
        $this->assertSame('C', $c->fresh()->name);
    }
}
