<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Reports\PostSummary;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Post-wise summary of a work order's sub-bills for GeM returns: filters, totals matching the bills, Excel, PDF (owner, 22 Nov 2026). */
class PostSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private GemContract $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $first = GemContract::query()->firstOrFail();
        $person = Employee::query()->whereNotNull('post_id')->firstOrFail();
        // A work order with two projects: "Field Worker" on both, plus a "Driver" on the first.
        $this->second = GemContract::query()->create(['agency_id' => $first->agency_id, 'client_id' => $first->client_id, 'bid_no' => 'GEM/2', 'contract_no' => 'GEMC-000000000000002']);
        foreach (['AAA', 'BBB'] as $i => $code) {
            $project = $this->second->projects()->create(['name' => 'Project '.$code, 'code' => $code]);
            $posts = [$project->posts()->create(['designation' => 'Field Worker', 'sanctioned_qty' => 1, 'rate' => 20000])];
            if ($code === 'AAA') {
                $posts[] = $project->posts()->create(['designation' => 'Driver', 'sanctioned_qty' => 1, 'rate' => 18500]);
            }
            foreach ($posts as $j => $post) {
                Employee::query()->create(['emp_code' => 'ZP-8'.$i.$j, 'name' => 'Worker '.$code.$j, 'agency_id' => $person->agency_id, 'client_id' => $person->client_id,
                    'post_id' => $post->id, 'status' => 'active', 'pf_applicable' => $j === 0, 'esic_applicable' => true]);
            }
        }
        $this->dev = $this->makeDeveloper(User::factory()->create(['role' => 'admin']));
        $this->artisan('demo:full-month', ['month' => '2026-10', '--force' => true])->assertSuccessful();
    }

    private function build(?int $bill = null, ?string $post = null): array
    {
        return app(PostSummary::class)->build(CarbonImmutable::parse('2026-10-01'), null, $this->second->id, $bill, $post);
    }

    public function test_posts_add_up_to_the_sub_bills(): void
    {
        $data = $this->build();
        $this->assertSame(['Driver', 'Field Worker'], array_column($data['rows'], 'post'));
        $field = $data['rows'][1];
        $this->assertSame(2, $field['staff'], 'the same post on two sub-bills is one row');
        $this->assertStringContainsString('(AAA)', $field['bills']);
        $this->assertStringContainsString('(BBB)', $field['bills']);
        $bills = Invoice::query()->where('gem_contract_id', $this->second->id)->get();
        foreach (['wages', 'epf', 'esic', 'subtotal'] as $f) {
            $this->assertSame(number_format((float) $bills->sum($f), 2, '.', ''), $data['total'][$f], $f.' adds up exactly');
        }
        foreach (['service_charge', 'taxable', 'cgst', 'sgst', 'total'] as $f) {
            $this->assertEqualsWithDelta((float) $bills->sum($f), (float) $data['total'][$f], 0.02 * $bills->count(), $f.' matches the bills to the paisa');
            $this->assertSame(number_format((float) $bills->sum($f), 2, '.', ''), $data['stored'][$f]);
        }
        $this->assertGreaterThan(0, (float) $data['total']['service_charge']);
    }

    public function test_one_sub_bill_and_one_post(): void
    {
        $aaa = Invoice::query()->where('gem_contract_id', $this->second->id)->where('invoice_no', 'like', '%(AAA)')->firstOrFail();
        $one = $this->build($aaa->id);
        $this->assertSame(2, $one['total']['staff']);
        $this->assertSame(number_format((float) $aaa->subtotal, 2, '.', ''), $one['total']['subtotal']);

        $field = $this->build(null, 'field  worker');   // spelling and spacing of the post name do not matter
        $this->assertCount(1, $field['rows']);
        $this->assertSame(2, $field['total']['staff']);
    }

    public function test_page_excel_and_pdf(): void
    {
        $q = ['month' => '2026-10', 'contract' => $this->second->id];
        $this->actingAs($this->dev)->get(route('post-summary.show', $q))->assertOk()
            ->assertSee('Post-wise summary – October 2026')->assertSee('Field Worker')->assertSee('Driver')
            ->assertSee('As on the sub-bills (stored figures)')->assertSee(route('post-summary.excel', $q))->assertSee(route('post-summary.pdf', $q));
        $this->get(route('post-summary.show', ['month' => '2026-10']))->assertOk()->assertSee('Choose the work order')->assertSee('GEMC-000000000000002');

        $xlsx = $this->get(route('post-summary.excel', $q))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'ps').'.xlsx';
        file_put_contents($path, $xlsx);
        $ws = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);
        $this->assertSame('Driver', $ws->getCell('B6')->getValue());
        $this->assertSame('Field Worker', $ws->getCell('B7')->getValue());
        $this->assertSame('Total', $ws->getCell('B8')->getValue());
        $this->assertSame('Wages (Rs.)', $ws->getCell('F5')->getValue());
        $this->assertEqualsWithDelta((float) Invoice::query()->where('gem_contract_id', $this->second->id)->sum('wages'), (float) $ws->getCell('F8')->getValue(), 0.001);

        $pdf = $this->get(route('post-summary.pdf', $q))->assertOk()->assertHeader('content-type', 'application/pdf')->getContent();
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf));

        $this->get('/invoices?month=2026-10')->assertSee(route('post-summary.show', ['contract' => $this->second->id, 'month' => '2026-10']))->assertSee('Post-wise summary for GeM');
    }

    public function test_other_agency_and_other_work_orders_bills_are_refused(): void
    {
        $other = Invoice::query()->where('gem_contract_id', '!=', $this->second->id)->firstOrFail();
        $this->actingAs($this->dev)->get(route('post-summary.show', ['contract' => $this->second->id, 'month' => '2026-10', 'bill' => $other->id]))->assertNotFound();

        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $swhAccounts = User::factory()->create(['role' => 'accounts', 'agency_id' => $swh->id]);
        $this->actingAs($swhAccounts)->get(route('post-summary.show', ['contract' => $this->second->id, 'month' => '2026-10']))->assertNotFound();
        $this->get(route('post-summary.excel', ['contract' => $this->second->id, 'month' => '2026-10']))->assertNotFound();
        $this->get(route('post-summary.pdf', ['contract' => $this->second->id, 'month' => '2026-10']))->assertNotFound();
        $this->get(route('post-summary.show', ['month' => '2026-10']))->assertOk()->assertDontSee('GEMC-000000000000002');

        $this->actingAs(User::factory()->create(['role' => 'supervisor']))->get(route('post-summary.show'))->assertForbidden();
    }
}
