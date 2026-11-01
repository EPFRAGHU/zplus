<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAgencyTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $over = []): array
    {
        return $over + ['name' => 'ICMR-NIHR Bhubaneswar', 'short_code' => 'ICMR', 'state_code' => '21', 'payment_term_days' => 30, 'status' => 'active'];
    }

    public function test_client_has_one_agency_chosen_from_a_dropdown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $swh = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH']);

        $this->actingAs($admin)->get('/clients/create')->assertOk()->assertSee('<select id="f-agency_id" name="agency_id"', false);
        $this->post('/clients', $this->form())->assertSessionHasErrors(['agency_id' => 'Choose the agency this client works with.']);
        $this->post('/clients', $this->form(['agency_id' => $swh->id]))->assertSessionHasNoErrors();
        $this->assertSame($swh->id, Client::query()->firstOrFail()->agency_id);
    }

    public function test_same_client_can_be_added_once_per_agency(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $swh = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH']);
        $zps = Agency::query()->create(['name' => 'Z Plus', 'code' => 'ZPS']);

        $this->actingAs($admin)->post('/clients', $this->form(['agency_id' => $swh->id]))->assertSessionHasNoErrors();
        $this->post('/clients', $this->form(['agency_id' => $swh->id]))->assertSessionHasErrors(['short_code' => 'This agency already has a client with this bill code.']);

        $first = Client::query()->firstOrFail();
        $this->get('/clients/create?copy='.$first->id)->assertOk()->assertSee('Copied from')->assertSee('ICMR-NIHR Bhubaneswar');
        $this->post('/clients', $this->form(['agency_id' => $zps->id]))->assertSessionHasNoErrors();
        $this->assertSame(2, Client::query()->where('short_code', 'ICMR')->count());
        $this->get('/clients')->assertSee('SWH')->assertSee('ZPS');
    }

    public function test_contract_form_shows_agency_and_client_and_they_must_match(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $zps = Agency::query()->create(['name' => 'Z Plus', 'code' => 'ZPS']);
        $swh = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH']);
        $client = Client::query()->create(['name' => 'ICMR', 'short_code' => 'ICMR', 'agency_id' => $zps->id]);
        $form = ['client_id' => $client->id, 'bid_no' => 'GEM/2026/B/1', 'contract_no' => 'GEMC-1', 'billing_type' => 'monthly',
            'service_charge_percent' => '3.85', 'gst_percent' => '18', 'epf_percent' => '13', 'esic_percent' => '3.25', 'status' => 'active'];

        $this->actingAs($admin)->get('/contracts/create')->assertOk()
            ->assertSee('id="f-agency_id"', false)->assertSee('id="f-client_id"', false)->assertSee('data-agency="'.$zps->id.'"', false);
        $this->post('/contracts', $form)->assertSessionHasErrors(['agency_id' => 'Choose the agency that raises bills on this contract.']);
        $this->post('/contracts', $form + ['agency_id' => $swh->id])->assertSessionHasErrors('client_id');
        $this->post('/contracts', $form + ['agency_id' => $zps->id])->assertSessionHasNoErrors();
        $this->assertSame($zps->id, GemContract::query()->firstOrFail()->agency_id);
        $this->get('/contracts')->assertSee('ICMR');
    }

    public function test_contract_agency_and_client_are_fixed_once_billed(): void
    {
        $this->seed(DemoSeeder::class);
        $user = User::factory()->create(['role' => 'admin']);
        $contract = GemContract::query()->firstOrFail();
        $sheet = app(AttendanceService::class)->open($contract->projects()->first(), CarbonImmutable::parse('2026-10-01'));
        app(AttendanceService::class)->lock($sheet, $user, null);
        app(InvoiceGenerator::class)->generate($sheet->fresh(), $user);

        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $swhClient = Client::query()->where('agency_id', $swh->id)->firstOrFail();
        $this->actingAs($user)->get("/contracts/{$contract->id}/edit")->assertSee('Bills exist on this contract');
        $this->put("/contracts/{$contract->id}", ['agency_id' => $swh->id, 'client_id' => $swhClient->id, 'bid_no' => $contract->bid_no,
            'contract_no' => $contract->contract_no, 'billing_type' => 'monthly', 'service_charge_percent' => '3.85', 'gst_percent' => '18',
            'epf_percent' => '13', 'esic_percent' => '3.25', 'status' => 'active'])
            ->assertSessionHasErrors(['agency_id' => 'Bills have already been made on this contract, so its agency and client cannot change.']);
        $this->assertSame($contract->agency_id, $contract->fresh()->agency_id);
    }

    public function test_agency_cannot_change_once_the_client_has_contracts(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $icmr = Client::query()->whereHas('contracts')->firstOrFail();
        $other = Agency::query()->whereKeyNot($icmr->agency_id)->value('id');

        // Use a fresh bill code so the only reason to refuse is the existing contracts.
        $this->actingAs($admin)->put("/clients/{$icmr->id}", $this->form(['name' => $icmr->name, 'short_code' => 'ICMRX', 'agency_id' => $other]))
            ->assertSessionHasErrors('agency_id');
        $this->assertNotSame($other, $icmr->fresh()->agency_id);
    }

    public function test_each_agency_numbers_its_bills_separately(): void
    {
        $user = User::factory()->create(['role' => 'accounts']);
        $numbers = [];
        foreach (['SWH', 'ZPS'] as $i => $code) {
            $agency = Agency::query()->create(['name' => $code, 'code' => $code]);
            $client = Client::query()->create(['name' => 'ICMR', 'short_code' => 'ICMR', 'agency_id' => $agency->id]);
            $contract = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => "B{$i}", 'contract_no' => "C{$i}"]);
            $project = $contract->projects()->create(['name' => 'Sickle Cell', 'code' => 'SICKLECELL']);
            $post = $project->posts()->create(['designation' => 'PTS-I', 'sanctioned_qty' => 1, 'rate' => 21600]);
            Employee::query()->create(['emp_code' => "EMP-{$i}", 'name' => "Staff {$i}", 'post_id' => $post->id]);

            $sheet = app(AttendanceService::class)->open($project, CarbonImmutable::parse('2026-10-01'));
            app(AttendanceService::class)->lock($sheet, $user, null);
            $numbers[] = app(InvoiceGenerator::class)->generate($sheet->fresh(), $user)->invoice_no;
        }

        $this->assertSame(['ICMR/SICKLECELL/01/2026', 'ICMR/SICKLECELL/01/2026'], $numbers);
    }

    public function test_contracts_page_shows_and_filters_by_agency_and_client(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $zps = Agency::query()->where('code', 'ZPS')->firstOrFail();

        $this->actingAs($admin)->get('/contracts')->assertOk()
            ->assertSee('id="f-agency"', false)->assertSee('id="f-client"', false)
            ->assertSee('<span class="text-secondary">Agency:</span>', false)->assertSee('M/S Z Plus Surakhya Seva')
            ->assertSee('GEM/2026/B/7972720');
        $this->get('/contracts?agency='.$swh->id)->assertOk()->assertDontSee('GEM/2026/B/7972720');
        $zpsIcmr = Client::query()->where('agency_id', $zps->id)->firstOrFail();
        $this->get('/contracts?client='.$zpsIcmr->id)->assertOk()->assertSee('GEM/2026/B/7972720');
    }

    public function test_agencies_page_lists_each_agencys_clients(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();

        $html = $this->actingAs($admin)->get('/agencies')->assertOk()->assertSee('<th>Clients</th>', false)->getContent();
        $this->assertSame(2, substr_count($html, 'ICMR-NIHR (RMRCBB), Bhubaneswar</a>'));   // listed under both agencies
        $this->assertStringContainsString('· 1 contract', $html);
        $this->assertStringContainsString('· 0 contracts', $html);

        // "Add client" from an agency row opens the form with that agency chosen.
        $this->get('/clients/create?agency='.$swh->id)->assertOk()->assertSee('<option value="'.$swh->id.'" selected>', false);
    }

    public function test_clients_page_lists_contracts_and_employee_counts(): void
    {
        $this->seed(DemoSeeder::class);
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/clients')->assertOk()
            ->assertSee('<th>GeM contracts</th><th class="num">Employees</th>', false)->getContent();

        $this->assertStringContainsString('GEM/2026/B/7972720</a>', $html);   // Z Plus's ICMR contract
        $this->assertStringContainsString('· 1 project', $html);
        $this->assertStringContainsString('No contracts yet', $html);          // SWACHHA's ICMR record
        $this->assertMatchesRegularExpression('#/employees\?agency=\d+&amp;client=\d+">2</a>#', $html);
    }

    public function test_clients_page_is_split_into_agency_blocks(): void
    {
        $this->seed(DemoSeeder::class);
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        Client::query()->create(['agency_id' => $swh->id, 'name' => 'AAA First Client', 'short_code' => 'AAA']);

        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/clients')->assertOk()->getContent();
        $zpsHead = strpos($html, '<b class="fs-6">M/S Z Plus Surakhya Seva</b>');
        $swhHead = strpos($html, '<b class="fs-6">SWACHHA</b>');
        $this->assertNotFalse($zpsHead);
        $this->assertNotFalse($swhHead);
        $this->assertStringContainsString('2 clients · 0 contracts', $html);          // SWACHHA block totals
        $this->assertGreaterThan($swhHead, strpos($html, 'AAA First Client'));       // under SWACHHA, not first alphabetically
        $this->assertStringContainsString('Add client to SWH', $html);
    }

    public function test_contracts_page_is_split_into_agency_blocks(): void
    {
        $this->seed(DemoSeeder::class);
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $swhClient = Client::query()->where('agency_id', $swh->id)->firstOrFail();
        GemContract::query()->create(['agency_id' => $swh->id, 'client_id' => $swhClient->id, 'bid_no' => 'GEM/2026/B/0000001', 'contract_no' => 'GEMC-1']);

        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/contracts')->assertOk()->getContent();
        $zps = strpos($html, '<b class="fs-6">M/S Z Plus Surakhya Seva</b> <span class="badge text-bg-dark">ZPS</span>');
        $swhPos = strpos($html, '<b class="fs-6">SWACHHA</b> <span class="badge text-bg-dark">SWH</span>');
        $this->assertNotFalse($zps);
        $this->assertNotFalse($swhPos);
        $this->assertStringContainsString('1 contract · 1 project · 2 / 2 posts filled', $html);
        $this->assertGreaterThan($swhPos, strpos($html, 'GEM/2026/B/0000001'));   // SWACHHA's contract under its block, even though its number sorts first
        $this->assertStringContainsString('Client: <b>ICMR-NIHR (RMRCBB), Bhubaneswar</b>', $html);
    }
}
