<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAgencyMappingTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $over = []): array
    {
        return $over + ['name' => 'Puri Municipal Corporation', 'short_code' => 'PMC', 'state_code' => '21', 'payment_term_days' => 30, 'status' => 'active'];
    }

    public function test_new_client_must_be_mapped_to_at_least_one_agency(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $swh = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH']);

        $this->actingAs($admin)->get('/clients/create')->assertOk()->assertSee('Agencies this client works with')->assertSee('SWACHHA');
        $this->post('/clients', $this->form())->assertSessionHasErrors(['agency_ids' => 'Choose at least one agency this client works with.']);

        $this->post('/clients', $this->form(['agency_ids' => [$swh->id]]))->assertSessionHasNoErrors();
        $client = Client::query()->where('short_code', 'PMC')->firstOrFail();
        $this->assertSame([$swh->id], $client->agencies()->pluck('agencies.id')->all());
        $this->get('/clients')->assertSee('SWH');
    }

    public function test_contract_agency_must_be_mapped_to_the_client(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $swh = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH']);
        $zps = Agency::query()->create(['name' => 'Z Plus', 'code' => 'ZPS']);
        $client = Client::query()->create(['name' => 'ICMR', 'short_code' => 'ICMR']);
        $client->agencies()->sync([$swh->id]);
        $contract = ['client_id' => $client->id, 'bid_no' => 'GEM/2026/B/1', 'contract_no' => 'GEMC-1', 'billing_type' => 'monthly',
            'service_charge_percent' => '3.85', 'gst_percent' => '18', 'epf_percent' => '13', 'esic_percent' => '3.25', 'status' => 'active'];

        $this->actingAs($admin)->post('/contracts', $contract + ['agency_id' => $zps->id])->assertSessionHasErrors('agency_id');
        $this->post('/contracts', $contract + ['agency_id' => $swh->id])->assertSessionHasNoErrors();
    }

    public function test_agency_with_contracts_cannot_be_unmapped(): void
    {
        $this->seed(DemoSeeder::class);   // ICMR mapped to ZPS and SWH, with a ZPS contract
        $admin = User::factory()->create(['role' => 'admin']);
        $icmr = Client::query()->where('short_code', 'ICMR')->firstOrFail();
        $swh = Agency::query()->where('code', 'SWH')->value('id');

        $this->actingAs($admin)->get("/clients/{$icmr->id}/edit")->assertOk()->assertSee('has contracts');
        $this->put("/clients/{$icmr->id}", $this->form(['name' => $icmr->name, 'short_code' => 'ICMR', 'agency_ids' => [$swh]]))
            ->assertSessionHasErrors('agency_ids');
        $this->assertSame(2, $icmr->agencies()->count());
    }
}
