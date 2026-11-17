<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\MonthlyPaper;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\TaxInvoiceBuilder;
use App\Services\Contracts\GemContractPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** v2.13.0: work order period/value, import of posts from the GeM contract PDF, monthly papers, bill pack, reminders. */
class ContractToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $r;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        $agency = Agency::query()->create(['code' => 'SWH', 'name' => 'SWACHHA']);
        $client = Client::query()->create(['agency_id' => $agency->id, 'name' => 'ICMR', 'short_code' => 'ICMR']);
        $contract = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => 'GEM/2026/B/1000001', 'contract_no' => 'GEMC-500000000000001', 'epf_percent' => '13', 'esic_percent' => '3.25', 'service_charge_percent' => '3.85', 'gst_percent' => '18']);
        $project = $contract->projects()->create(['name' => 'Lab', 'code' => 'LAB']);
        $post = $project->posts()->create(['designation' => 'Data Analyst', 'sanctioned_qty' => 1, 'rate' => 38036]);
        Employee::query()->create(['emp_code' => 'SW-1001', 'name' => 'Staff One', 'agency_id' => $agency->id, 'client_id' => $client->id, 'post_id' => $post->id, 'status' => 'active', 'pf_applicable' => true]);
        $service = app(AttendanceService::class);
        $sheet = $service->open($project, CarbonImmutable::parse('2026-10-01'));
        $service->lock($sheet, $this->admin, null);
        $bill = app(InvoiceGenerator::class)->generate($sheet->fresh(), $this->admin);
        $tax = app(TaxInvoiceBuilder::class)->create($contract, CarbonImmutable::parse('2026-10-01'), [$bill->id], $this->admin);
        $this->r = compact('agency', 'client', 'contract', 'project', 'post', 'bill', 'tax');
    }

    /** A PDF laid out like a GeM manpower contract (made here; no real contract is stored in the repository). */
    private function contractPdf(string $contractNo = 'GEMC-500000000000001'): UploadedFile
    {
        $post = fn ($profile, $basic, $qty, $designation) => "<p>Type of Function Healthcare</p><p>List of Profiles {$profile}</p><p>Educational Qualification Graduate</p>
            <p>Basic monthly pay (INR) exclusive of GST {$basic}</p><p>Bonus (INR Monthly) 0</p><p>EDLI (INR Monthly) 0</p><p>EPF Admin Charges (INR Monthly) 0</p>
            <p>Provident Fund (INR Monthly) 0</p><p>ESI (INR Monthly) 0</p><p>Tenure/ Duration of Employment (In Months) 12</p><p>{$qty} 3.85</p>
            <p>Total Value Including Addons(INR) 1</p><p>Additional Details</p><p>Designation : {$designation}</p><p>Title for Optional Allowance 1 : 0</p>";
        $html = "<html><body><p>Contract No: {$contractNo}</p><p>Contract Generated Date : 30-Sep-2026</p><p>Bid/RA/PBP No.: GEM/2026/B/1000001</p>
            <p>Designation : Administrative Officer</p><p>Company Name : SWACHHA</p><p>| Contact No.</p>
            <p>Service Start Date (latest by): 01-Oct-2026</p><p>Service End Date : 30-Sep-2027</p>"
            .$post('Lab Technician', 25000, 2, 'Sr. Laboratory Technician').$post('Data Analyst', 38036, 1, 'Data Analyst')
            // (25000 x 2 + 38036) x 1.2185 x 12 = 1287262.39 (GeM formula)
            .'<p>Amount of Contract</p><p>Total Contract Value Including All Duties and Taxes(INR) 1287262.39</p></body></html>';
        $path = tempnam(sys_get_temp_dir(), 'gemc');
        file_put_contents($path, Pdf::loadHTML($html)->output());

        return new UploadedFile($path, 'contract.pdf', 'application/pdf', null, true);
    }

    public function test_contract_pdf_is_read_and_checked_against_the_printed_total(): void
    {
        $read = app(GemContractPdf::class)->read($this->contractPdf()->getRealPath());
        $this->assertSame(['GEMC-500000000000001', 'GEM/2026/B/1000001', '2026-09-30', '2026-10-01', '2027-09-30', '1287262.39', 'SWACHHA'],
            [$read['contract_no'], $read['bid_no'], $read['contract_date'], $read['start_date'], $read['end_date'], $read['contract_value'], $read['seller']]);
        $this->assertSame([['Sr. Laboratory Technician', 'Lab Technician', '25000', 2, '3.85', 12], ['Data Analyst', 'Data Analyst', '38036', 1, '3.85', 12]],
            array_map(fn ($p) => [$p['designation'], $p['profile'], $p['rate'], $p['qty'], $p['service_charge'], $p['months']], $read['posts']));
        $this->assertTrue($read['check']['matches']);
    }

    public function test_import_adds_ticked_posts_fills_dates_and_skips_posts_already_there(): void
    {
        $c = $this->r['contract'];
        $this->post(route('contracts.import', $c), ['contract_pdf' => $this->contractPdf()])->assertRedirect(route('contracts.import.show', $c));
        $this->get(route('contracts.import.show', $c))->assertOk()->assertSee('Read correctly')->assertSee('Sr. Laboratory Technician')->assertSee('In LAB');
        $this->post(route('contracts.import.store', $c), [
            'project_id' => $this->r['project']->id, 'fill' => ['start_date', 'end_date', 'contract_value'],
            'posts' => [['add' => 1, 'designation' => 'Sr. Laboratory Technician', 'sanctioned_qty' => 2, 'rate' => 25000],
                ['add' => 1, 'designation' => 'Data Analyst', 'sanctioned_qty' => 1, 'rate' => 38036]],
        ])->assertRedirect(route('contracts.show', $c))->assertSessionHas('status', fn ($s) => str_contains($s, '1 post added') && str_contains($s, 'Data Analyst'));
        $this->assertSame(2, $this->r['project']->posts()->count());
        $c->refresh();
        $this->assertSame(['2026-10-01', '2027-09-30', '1287262.39'], [$c->start_date->toDateString(), $c->end_date->toDateString(), (string) $c->contract_value]);
        $this->assertNull($c->contract_date);   // not ticked: unchanged
    }

    public function test_import_refuses_another_contracts_pdf_and_projects_of_other_work_orders(): void
    {
        $c = $this->r['contract'];
        $this->post(route('contracts.import', $c), ['contract_pdf' => $this->contractPdf('GEMC-599999999999999')])->assertSessionHasErrors('contract_pdf');
        $other = GemContract::query()->create(['agency_id' => $this->r['agency']->id, 'client_id' => $this->r['client']->id, 'bid_no' => 'X', 'contract_no' => 'GEMC-2']);
        $otherProject = $other->projects()->create(['name' => 'Other', 'code' => 'OTH']);
        $this->post(route('contracts.import', $c), ['contract_pdf' => $this->contractPdf()]);
        $this->post(route('contracts.import.store', $c), ['project_id' => $otherProject->id, 'posts' => [['add' => 1, 'designation' => 'X', 'sanctioned_qty' => 1, 'rate' => 1]]])
            ->assertSessionHasErrors('project_id');
        $this->assertSame(0, $otherProject->posts()->count());
        $this->actingAs(User::factory()->create(['role' => 'supervisor']));   // Operator: may not add posts
        $this->post(route('contracts.import', $c), ['contract_pdf' => $this->contractPdf()])->assertForbidden();
    }

    public function test_service_period_is_validated_and_expiry_is_shown(): void
    {
        $c = $this->r['contract'];
        $base = ['agency_id' => $c->agency_id, 'client_id' => $c->client_id, 'contract_type' => 'online', 'bid_no' => $c->bid_no, 'contract_no' => $c->contract_no,
            'billing_type' => 'monthly', 'service_charge_percent' => '3.85', 'gst_percent' => '18', 'epf_percent' => '13', 'esic_percent' => '3.25', 'status' => 'active'];
        $this->put(route('contracts.update', $c), $base + ['start_date' => '2026-10-01', 'end_date' => '2026-09-01'])->assertSessionHasErrors('end_date');
        $this->put(route('contracts.update', $c), $base + ['start_date' => '2025-12-01', 'end_date' => '2026-11-30', 'contract_value' => '1287262.39'])->assertSessionHasNoErrors();
        $this->get(route('contracts.index'))->assertSee('Ends in 20 days');
        $this->get(route('dashboard'))->assertSee('Ends in 20 days')->assertSee('ask the client about extension');
        $this->get(route('contracts.show', $c))->assertSee('30.11.2026')->assertSee('12,87,262');
    }

    public function test_papers_checklist_upload_permissions_and_bill_pack(): void
    {
        $this->withoutOwnerApproval();   // billing rules before owner approvals; see ApprovalTest
        $c = $this->r['contract'];
        $page = $this->get(route('contracts.show', ['contract' => $c, 'month' => '2026-10']))->assertOk();
        $page->assertSee('Monthly papers, October 2026')->assertSee('Missing');
        $this->get(route('dashboard'))->assertSee('Oct 2026 wages: due 07 Nov (3 days overdue)', false)->assertSee('EPF / ESIC: due 15 Nov (in 5 days)', false);

        $this->post(route('papers.store', $c), ['month' => '2026-10', 'kind' => 'salary_proof', 'paper' => [UploadedFile::fake()->createWithContent('sbi.pdf', '%PDF-bank')]])
            ->assertSessionHasNoErrors();
        $this->post(route('papers.store', $c), ['month' => '2026-10', 'kind' => 'epf_challan', 'paper' => [UploadedFile::fake()->createWithContent('ecr.pdf', '%PDF-ecr')]]);
        $this->post(route('papers.store', $c), ['month' => '2026-10', 'kind' => 'logbook', 'paper' => [UploadedFile::fake()->create('virus.exe', 5)]])->assertSessionHasErrors('paper.0');
        $this->assertSame(2, MonthlyPaper::query()->count());
        $this->get(route('dashboard'))->assertSee('proof uploaded for all')->assertSee('challans uploaded for all');   // ESIC: nobody switched on

        $res = $this->get(route('contracts.bill-pack', ['contract' => $c, 'month' => '2026-10']))->assertOk();
        $zip = new \ZipArchive;
        $zip->open($res->baseResponse->getFile()->getPathname());
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->sort()->values()->all();
        $this->assertContains('4-papers/salary_proof-1.pdf', $names);
        $this->assertContains('4-papers/epf_challan-1.pdf', $names);
        $this->assertTrue(collect($names)->contains(fn ($n) => str_starts_with($n, '1-tax-invoice/tax-invoice-')));
        $this->assertTrue(collect($names)->contains(fn ($n) => str_starts_with($n, '2-sub-bills/covering-letter-')));

        // Operator: no papers at all. Accounts: upload and open, but cannot remove. Admin: remove.
        $paper = MonthlyPaper::query()->where('kind', 'epf_challan')->first();
        $this->actingAs(User::factory()->create(['role' => 'supervisor']));
        $this->get(route('papers.show', $paper))->assertForbidden();
        $this->get(route('contracts.bill-pack', ['contract' => $c, 'month' => '2026-10']))->assertForbidden();
        $this->post(route('papers.store', $c), ['month' => '2026-10', 'kind' => 'logbook', 'paper' => [UploadedFile::fake()->create('l.pdf', 5)]])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'accounts']));
        $this->get(route('papers.show', $paper))->assertOk();
        $this->delete(route('papers.destroy', $paper))->assertForbidden();
        $this->actingAs($this->admin);
        $this->delete(route('papers.destroy', $paper))->assertSessionHas('status');
        $this->assertSame(1, MonthlyPaper::query()->count());
        Storage::disk('local')->assertMissing($paper->path);
    }

    public function test_dashboard_lists_posts_with_more_staff_than_sanctioned(): void
    {
        $this->r['post']->update(['sanctioned_qty' => 0]);
        $this->get(route('dashboard'))->assertSee('sanctioned only 0', false);
    }
}
