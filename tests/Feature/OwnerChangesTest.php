<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Project;
use App\Models\ProjectIncharge;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\BillLine;
use App\Services\Billing\BillNumbers;
use App\Services\Billing\GemBillCalculator;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\TaxInvoiceBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner's November 2026 changes: work orders, numbering, service charge incl. GST, PI Allotment, bank letter, Employee Management. */
class OwnerChangesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    /** SWACHHA work order 8030 with HTA and CCRH projects, one employee each (as in the SWACHHA October bills). */
    private function swachha(): array
    {
        $agency = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH', 'number_prefix' => 'SW']);
        $client = Client::query()->create(['agency_id' => $agency->id, 'name' => 'ICMR-NIHR', 'short_code' => 'ICMR', 'state_code' => '21']);
        $contract = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => 'GEM/2026/B/7972720', 'contract_no' => 'GEMC-511687731718030']);
        $people = [];
        foreach (['HTA' => [80400, 'SBIN0000001'], 'CCRH' => [21600, 'CNRB0000001']] as $code => [$rate, $ifsc]) {
            $project = $contract->projects()->create(['name' => $code, 'code' => $code]);
            $post = $project->posts()->create(['designation' => 'Staff', 'sanctioned_qty' => 1, 'rate' => $rate]);
            $people[$code] = Employee::query()->create(['emp_code' => Employee::nextCode($agency), 'name' => "Person {$code}", 'agency_id' => $agency->id,
                'client_id' => $client->id, 'post_id' => $post->id, 'status' => 'active', 'joining_date' => '2026-04-01',
                'bank_name' => $code === 'HTA' ? 'State Bank of India' : 'Canara Bank', 'account_holder' => "PERSON {$code}",
                'account_number' => $code === 'HTA' ? '11112222333' : '99998888777', 'ifsc' => $ifsc, 'pf_applicable' => false, 'esic_applicable' => false]);
        }

        return [$agency, $client, $contract, $people];
    }

    private function billMonth(GemContract $contract, string $month): array
    {
        $service = app(AttendanceService::class);
        $bills = [];
        foreach ($contract->projects as $project) {
            $sheet = $service->open($project, CarbonImmutable::parse($month));
            $service->lock($sheet, $this->admin, null);
            $bills[$project->code] = app(InvoiceGenerator::class)->generate($sheet->fresh(), $this->admin);
        }

        return $bills;
    }

    public function test_service_charge_including_gst_is_amount_times_rate_divided_by_1_18(): void
    {
        $line = fn () => [BillLine::monthly('x', '21600.00', 31, '31.0', null), BillLine::monthly('y', '24000.00', 31, '31.0', null)];
        // SWACHHA CCRH October: 45,600 x 3.85% / 1.18 = 1,487.80; + GST 18% = 55,563.60 -> 55,564
        $new = (new GemBillCalculator('3.85', '18', serviceChargeIncludesGst: true))->monthly($line());
        $this->assertSame('1487.80', $new->serviceCharge);
        $this->assertSame('55564.00', $new->roundedTotal);
        // Bills made before the change keep the old rule: 3.85% on the amount.
        $old = (new GemBillCalculator('3.85', '18'))->monthly($line());
        $this->assertSame('1755.60', $old->serviceCharge);
    }

    public function test_new_bills_use_the_new_rule_and_say_so(): void
    {
        [, , $contract] = $this->swachha();
        $bill = $this->billMonth($contract->load('projects'), '2026-10-01')['CCRH'];
        $this->assertTrue($bill->service_charge_includes_gst);
        $this->assertSame('704.75', $bill->service_charge);   // 21,600 x 3.85 / 118 = 704.75
        $this->assertStringContainsString('3.85% (incl. 18% GST) ÷ 1.18 = 3.26% (excluding GST)', $bill->serviceChargeLabel());
        $this->get("/invoices/{$bill->id}")->assertOk()->assertSee('÷ 1.18 = 3.26%');
    }

    public function test_sub_bills_share_the_work_orders_serial_and_the_tax_invoice_takes_it(): void
    {
        [, , $contract] = $this->swachha();
        $oct = $this->billMonth($contract->load('projects'), '2026-10-01');
        $this->assertSame('SW/001/26-27(HTA)', $oct['HTA']->invoice_no);
        $this->assertSame('SW/001/26-27(CCRH)', $oct['CCRH']->invoice_no);

        $tax = app(TaxInvoiceBuilder::class)->create($contract, CarbonImmutable::parse('2026-10-01'), collect($oct)->pluck('id')->all(), $this->admin);
        $this->assertSame('SW/001/26-27', $tax->number);
        $this->assertSame($contract->id, $tax->gem_contract_id);

        $nov = $this->billMonth($contract, '2026-11-01');
        $this->assertSame('SW/002/26-27(HTA)', $nov['HTA']->invoice_no);
        $html = $this->get("/tax-invoices/{$tax->id}/pdf")->assertOk();
        $pdfView = view('pdf.tax-invoice', \App\Http\Controllers\TaxInvoiceController::pdfData($tax))->render();
        $this->assertStringContainsString('GEM CONTRACT NO-GEMC-511687731718030', $pdfView);
        $this->assertStringContainsString('Invoice No- SW/001/26-27', $pdfView);
        $this->assertStringContainsString('<td>STAFF</td>', $pdfView);                        // DES: the post only, as on the agencies' invoice
    }

    public function test_serial_restarts_each_financial_year_and_each_agency_has_its_own(): void
    {
        $this->assertSame('26-27', BillNumbers::financialYear(CarbonImmutable::parse('2027-03-01')));
        $this->assertSame('27-28', BillNumbers::financialYear(CarbonImmutable::parse('2027-04-01')));
        [, , $contract] = $this->swachha();
        $this->billMonth($contract->load('projects'), '2027-03-01');
        $april = $this->billMonth($contract, '2027-04-01');
        $this->assertSame('SW/001/27-28(HTA)', $april['HTA']->invoice_no);

        $zp = Agency::query()->create(['name' => 'Z Plus', 'code' => 'ZPS']);
        $this->assertSame('ZP', $zp->numberPrefix());
        $this->assertSame('ZP-1001', Employee::nextCode($zp));
    }

    public function test_employee_ids_follow_the_agency_series_and_old_ids_stay(): void
    {
        [$agency] = $this->swachha();
        $this->assertSame(['SW-1001', 'SW-1002'], Employee::query()->orderBy('id')->pluck('emp_code')->all());
        Employee::query()->create(['emp_code' => 'EMP-0007', 'name' => 'Old', 'agency_id' => $agency->id, 'status' => 'active']);
        $this->post('/employees', ['agency_id' => $agency->id, 'name' => 'New Joiner', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('SW-1003', Employee::query()->where('name', 'New Joiner')->value('emp_code'));
        $this->assertSame('EMP-0007', Employee::query()->where('name', 'Old')->value('emp_code'));
        $this->get('/employees/create')->assertOk()->assertSee('SW-1004');   // the next one
    }

    public function test_codes_accept_slash_and_hyphen_and_letter_code_has_16_characters(): void
    {
        $agency = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH']);
        $base = ['agency_id' => $agency->id, 'name' => 'C', 'state_code' => '21', 'district' => 'Khordha', 'payment_term_days' => 30, 'status' => 'active'];
        $this->post('/clients', $base + ['short_code' => 'ICMR/NIHR-01', 'letter_code' => 'NIHR/RMRC-BBSR16'])->assertSessionHasNoErrors();
        $this->assertSame('NIHR/RMRC-BBSR16', Client::query()->value('letter_code'));
        $this->post('/clients', $base + ['short_code' => 'X2', 'letter_code' => 'NIHR/RMRC-BBSR-17'])->assertSessionHasErrors('letter_code');
        $this->post('/clients', $base + ['short_code' => 'X 3'])->assertSessionHasErrors('short_code');
        $this->post('/clients', array_merge($base, ['short_code' => 'X4', 'state_code' => '99']))->assertSessionHasErrors('state_code');
        $this->get('/clients/create')->assertOk()->assertSee('Odisha (21)')->assertSee('district-list', false)->assertSee('Khordha');
    }

    public function test_offline_work_order_needs_no_bid_but_online_does(): void
    {
        $agency = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH']);
        $client = Client::query()->create(['agency_id' => $agency->id, 'name' => 'C', 'short_code' => 'C']);
        $base = ['agency_id' => $agency->id, 'client_id' => $client->id, 'billing_type' => 'monthly', 'service_charge_percent' => 3.85,
            'gst_percent' => 18, 'epf_percent' => 13, 'esic_percent' => 3.25, 'status' => 'active'];
        $this->post('/contracts', $base + ['contract_type' => 'online', 'contract_no' => 'WO-1'])->assertSessionHasErrors('bid_no');
        $this->post('/contracts', $base + ['contract_type' => 'offline', 'contract_no' => 'WO/2026/1'])->assertSessionHasNoErrors();
        $k = GemContract::query()->firstOrFail();
        $this->assertSame('offline', $k->contract_type);
        $this->assertTrue($k->service_charge_includes_gst);
        $this->get('/contracts')->assertOk()->assertSee('Work Orders')->assertSee('Offline');
        $this->get("/contracts/{$k->id}")->assertOk()->assertSee('Sub-bills and tax invoice');
    }

    public function test_work_order_page_makes_sub_bills_then_the_tax_invoice(): void
    {
        [, , $contract] = $this->swachha();
        $service = app(AttendanceService::class);
        foreach ($contract->projects as $p) {
            $service->lock($service->open($p, CarbonImmutable::parse('2026-10-01')), $this->admin, null);
        }
        $page = $this->get("/contracts/{$contract->id}?month=2026-10")->assertOk()->assertSee('Add sub-bill')->getContent();
        $this->assertStringContainsString('SW/001/26-27', $page);
        foreach ($contract->projects as $p) {
            $this->post('/invoices', ['attendance_sheet_id' => $p->attendanceSheets()->value('id')])->assertSessionHasNoErrors();
        }
        $this->get("/contracts/{$contract->id}?month=2026-10")->assertSee('SW/001/26-27(HTA)')->assertSee('Add tax invoice SW/001/26-27');
        $ids = \App\Models\Invoice::query()->pluck('id')->all();
        $this->post('/tax-invoices', ['gem_contract_id' => $contract->id, 'month' => '2026-10', 'sub_bills' => $ids, 'invoice_date' => '2026-11-05'])->assertRedirect();
        $this->get("/contracts/{$contract->id}?month=2026-10")->assertSee('Tax invoice Oct 2026');   // footer row once made
    }

    public function test_pi_allotment_maps_one_pi_to_projects_under_different_work_orders(): void
    {
        [$agency, $client, $contract] = $this->swachha();
        $other = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => 'B2', 'contract_no' => 'GEMC-2']);
        $mph = $other->projects()->create(['name' => 'MPH', 'code' => 'MPH']);
        $hta = Project::query()->where('code', 'HTA')->firstOrFail();
        $login = User::factory()->create(['role' => 'pi', 'agency_id' => $agency->id]);

        $this->post('/incharges', ['agency_id' => $agency->id, 'name' => 'Dr. D. Sample', 'designation' => 'Scientist-E', 'mobile' => '9876543210',
            'email' => 'pi@example.com', 'status' => 'active', 'user_id' => $login->id, 'projects' => [$hta->id, $mph->id]])->assertSessionHasNoErrors();
        $pi = ProjectIncharge::query()->firstOrFail();
        $this->assertSame($pi->id, $hta->fresh()->project_incharge_id);
        $this->assertSame('Dr. D. Sample', $mph->fresh()->pi_name);       // printed name follows the master
        $this->assertEqualsCanonicalizing([$hta->id, $mph->id], $login->projects()->pluck('projects.id')->all());   // PI portal follows

        $this->put("/incharges/{$pi->id}", ['agency_id' => $agency->id, 'name' => 'Dr. D. Sample-Renamed', 'status' => 'active', 'user_id' => $login->id, 'projects' => [$mph->id]]);
        $this->assertNull($hta->fresh()->project_incharge_id);
        $this->assertSame('Dr. D. Sample-Renamed', $mph->fresh()->pi_name);
        $this->assertSame([$mph->id], $login->projects()->pluck('projects.id')->all());
        $this->get('/incharges')->assertOk()->assertSee('Dr. D. Sample-Renamed')->assertSee('9876543210')->assertSee('GEMC-2');

        // Accounts can keep the master but cannot hand a login access to projects.
        $this->actingAs(User::factory()->create(['role' => 'accounts', 'agency_id' => $agency->id]));
        $this->put("/incharges/{$pi->id}", ['agency_id' => $agency->id, 'name' => 'Dr. D. Sample-Renamed', 'status' => 'active', 'user_id' => '', 'projects' => [$mph->id, $hta->id]])->assertSessionHasNoErrors();
        $this->assertSame($login->id, $pi->fresh()->user_id);
        $this->assertSame([$mph->id], $login->projects()->pluck('projects.id')->all());
    }

    public function test_bank_letter_lists_sbi_by_emp_id_and_other_banks_by_neft(): void
    {
        [, , $contract, $people] = $this->swachha();
        $project = Project::query()->where('code', 'HTA')->firstOrFail();
        $service = app(AttendanceService::class);
        $sheet = $service->open($project, CarbonImmutable::parse('2026-10-01'));
        $service->lock($sheet, $this->admin, null);
        // Move the CCRH person onto HTA too, so one letter has both banks
        $people['CCRH']->update(['post_id' => $people['HTA']->post_id]);
        $sheet2 = $service->open($project, CarbonImmutable::parse('2026-11-01'));
        $service->lock($sheet2, $this->admin, null);

        $this->get("/attendance/{$sheet2->id}/bank-letter")->assertOk()->assertHeader('content-type', 'application/pdf');
        $html = view('pdf.bank-letter', $this->bankLetterData($sheet2))->render();
        $this->assertStringContainsString('Accounts in State Bank of India', $html);
        $this->assertStringContainsString('SW-1001', $html);
        $this->assertStringContainsString('Accounts in other banks (NEFT)', $html);
        $this->assertStringContainsString('CNRB0000001', $html);
        $this->assertStringContainsString('99998888777', $html);

        $this->actingAs(User::factory()->create(['role' => 'supervisor']));
        $this->get("/attendance/{$sheet2->id}/bank-letter")->assertForbidden();
    }

    private function bankLetterData($sheet): array
    {
        $lines = app(\App\Services\Payroll\SalaryPayoutSheet::class)->lines($sheet->fresh());
        [$sbi, $neft] = $lines->partition(fn ($l) => str_starts_with($l['employee']->ifsc, 'SBIN'));

        return ['sheet' => $sheet->fresh()->load('project.contract.client'), 'agency' => $sheet->project->contract->agency, 'sbi' => $sbi->values(), 'neft' => $neft->values(),
            'missing' => collect(), 'total' => $lines->sum('net'), 'totalWords' => 'x', 'reference' => 'SW/BANK/HTA/11/26-27'];
    }

    public function test_employee_management_shows_month_wise_attendance_and_payments(): void
    {
        [, , $contract, $people] = $this->swachha();
        $this->billMonth($contract->load('projects'), '2026-10-01');
        $this->get('/staff')->assertOk()->assertSee('Person HTA')->assertSee('SW-1002');
        $this->get('/staff?employee='.$people['HTA']->id)->assertOk()
            ->assertSee('Month-wise attendance and payments')->assertSee('Oct 2026')->assertSee('₹80,400.00')->assertSee('SW/001/26-27(HTA)')->assertSee('11112222333');
        $this->actingAs(User::factory()->create(['role' => 'supervisor']));
        $this->get('/staff?employee='.$people['HTA']->id)->assertOk()->assertDontSee('11112222333')->assertSee('••••2333');
    }

    /** The SWACHHA tax invoice of October 2026 (GEMC-511687731718030), figure for figure. */
    public function test_tax_invoice_reproduces_the_swachha_october_invoice_exactly(): void
    {
        $agency = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH', 'number_prefix' => 'SW']);
        $client = Client::query()->create(['agency_id' => $agency->id, 'name' => 'ICMR-NIHR', 'short_code' => 'ICMR', 'state_code' => '21']);
        $contract = GemContract::query()->create(['agency_id' => $agency->id, 'client_id' => $client->id, 'bid_no' => 'GEM/2026/B/7972720',
            'contract_no' => 'GEMC-511687731718030', 'contract_date' => '2026-09-30']);
        $plan = ['HTA' => [['PRS-II', 80400, 2], ['PRS-III', 93600, 1], ['SR ECON', 147600, 1], ['ADMIN OFFICER', 40000, 1], ['FO', 30000, 1]],
            'MPH' => [['APA', 31000, 1], ['APM', 55000, 1]], 'CCRH' => [['PTS-I', 21600, 1], ['PTS-II', 24000, 1]]];
        $n = 0;
        foreach ($plan as $code => $posts) {
            $project = $contract->projects()->create(['name' => $code, 'code' => $code]);
            foreach ($posts as [$designation, $rate, $count]) {
                $post = $project->posts()->create(['designation' => $designation, 'sanctioned_qty' => $count, 'rate' => $rate]);
                for ($i = 0; $i < $count; $i++) {
                    Employee::query()->create(['emp_code' => 'T-'.(++$n), 'name' => "Person {$n}", 'agency_id' => $agency->id, 'client_id' => $client->id,
                        'post_id' => $post->id, 'status' => 'active', 'joining_date' => '2026-04-01']);
                }
            }
        }
        $bills = $this->billMonth($contract->load('projects'), '2026-10-01');
        $tax = app(TaxInvoiceBuilder::class)->create($contract, CarbonImmutable::parse('2026-10-01'), collect($bills)->pluck('id')->all(), $this->admin, null, '2026-11-05');

        $this->assertSame('SW/001/26-27', $tax->number);
        $this->assertSame('623293.73', $tax->taxable);
        $this->assertSame('56096.44', $tax->cgst);
        $this->assertSame('735486.60', $tax->total);           // exact sum, as in Excel (not 623293.73 + 2 x 56096.44)
        $this->assertSame('735487.00', $tax->rounded_total);

        $html = view('pdf.tax-invoice', \App\Http\Controllers\TaxInvoiceController::pdfData($tax))->render();
        foreach (['Invoice No- SW/001/26-27', 'Date:- 05.11.2026', 'GEM CONTRACT NO-GEMC-511687731718030, Dated- 30.09.2026', 'Month- OCT-2026',
            '2593.55', '160800.00', '4761.29', '603600.00', '19693.73', '623293.73', '56096.44', '735486.60', '735487.00',
            'Rupees Seven Lakh Thirty Five Thousand Four Hundred Eighty Seven Only', 'Service Charge @ 3.85% on sub total (Excluding GST)'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        // 10 persons, 310 working days. (The Excel sheet showed 248: its total formula =SUM(F13:F19) leaves out the PTS-I and PTS-II rows.)
        $this->assertMatchesRegularExpression('/<td class="c">10<\/td><td class="c">310<\/td>/', $html);
    }

    public function test_old_approved_bills_can_be_recalculated_with_the_current_rules(): void
    {
        [$agency, , $contract] = $this->swachha();
        $contract->update(['service_charge_includes_gst' => false]);   // as bills were made before 16 Nov 2026
        $bills = $this->billMonth($contract->load('projects'), '2026-10-01');
        $old = $bills['HTA'];
        $old->forceFill(['invoice_no' => 'SW26271/HTA/01/2026', 'status' => 'approved'])->save();
        $this->assertSame('3095.40', $old->service_charge);                       // 80,400 x 3.85%
        $tax = app(TaxInvoiceBuilder::class)->create($contract, CarbonImmutable::parse('2026-10-01'), [$old->id], $this->admin);
        $submitted = $bills['CCRH'];
        $submitted->forceFill(['status' => 'submitted'])->save();
        $paid = $this->billMonth($contract, '2026-11-01')['HTA'];
        $paid->forceFill(['status' => 'paid'])->save();
        $contract->update(['service_charge_includes_gst' => true]);

        $this->get('/invoices')->assertSee('made with the old rules')->assertSee('SW26271/HTA/01/2026');
        $this->post('/invoices-recalculate-outdated')->assertSessionHasNoErrors();

        $old->refresh();
        $this->assertSame('2623.22', $old->service_charge);                       // 80,400 x 3.85 / 118
        $this->assertTrue($old->service_charge_includes_gst);
        $this->assertSame('approved', $old->status);                              // status kept
        $this->assertSame('SW/001/26-27(HTA)', $old->invoice_no);                 // new-style number
        $this->assertSame('97967.00', $old->rounded_total);                     // (80,400 + 2,623.22) x 1.18 = 97,967.40
        $this->assertSame('97967.00', $tax->fresh()->rounded_total);              // its draft tax invoice follows
        $this->assertTrue($submitted->fresh()->service_charge_includes_gst);     // submitted bills too (owner, 16 Nov 2026)
        $this->assertSame('submitted', $submitted->fresh()->status);
        $this->assertFalse($paid->fresh()->service_charge_includes_gst);         // paid bills are never touched
        $this->assertSame(3, \App\Models\ActivityLog::query()->where('note', 'like', 'Recalculated with current rules%')->count());   // Oct HTA, Oct CCRH, Nov CCRH (draft); Nov HTA is paid

        $this->post("/invoices/{$paid->id}/recalculate")->assertSessionHasErrors('invoice');
        $this->actingAs(User::factory()->create(['role' => 'supervisor', 'agency_id' => $agency->id]));
        $this->post("/invoices/{$old->id}/recalculate")->assertForbidden();
    }

    public function test_old_tax_invoice_gets_the_work_order_number_when_recalculated(): void
    {
        [, , $contract] = $this->swachha();
        $contract->update(['service_charge_includes_gst' => false]);
        $bills = $this->billMonth($contract->load('projects'), '2026-10-01');
        $tax = app(TaxInvoiceBuilder::class)->create($contract, CarbonImmutable::parse('2026-10-01'), collect($bills)->pluck('id')->all(), $this->admin);
        // As made before 16 Nov 2026: client-style number, no work order, bills submitted
        $tax->forceFill(['number' => 'SW26271/TI/01/2026', 'gem_contract_id' => null, 'status' => 'submitted'])->save();
        foreach ($bills as $b) {
            $b->forceFill(['invoice_no' => 'OLD/'.$b->id, 'status' => 'submitted'])->save();
        }
        $contract->update(['service_charge_includes_gst' => true]);

        $this->get("/tax-invoices/{$tax->id}")->assertOk()->assertSee('Renumber and recalculate');
        $this->get('/invoices')->assertSee('SW26271/TI/01/2026 (tax invoice)');
        $this->post('/invoices-recalculate-outdated')->assertSessionHasNoErrors();   // the tax invoice is renumbered with its bills

        $tax->refresh();
        $this->assertSame('SW/001/26-27', $tax->number);
        $this->assertSame($contract->id, $tax->gem_contract_id);
        $this->assertSame('submitted', $tax->status);
        $this->assertSame(['SW/001/26-27(CCRH)', 'SW/001/26-27(HTA)'], \App\Models\Invoice::query()->orderBy('invoice_no')->pluck('invoice_no')->all());
        $html = view('pdf.tax-invoice', \App\Http\Controllers\TaxInvoiceController::pdfData($tax))->render();
        $this->assertStringContainsString('Invoice No- SW/001/26-27', $html);

        $tax->forceFill(['status' => 'paid', 'number' => 'OLD/TI'])->save();
        $this->post("/tax-invoices/{$tax->id}/recalculate")->assertSessionHasErrors('tax');
        $this->assertSame('OLD/TI', $tax->fresh()->number);
    }

    public function test_project_incharge_module_shows_projects_employees_and_their_sub_bills(): void
    {
        [$agency, , $contract, $people] = $this->swachha();
        $pi = ProjectIncharge::query()->create(['agency_id' => $agency->id, 'name' => 'Dr. D. Sample', 'mobile' => '9876543210']);
        Project::query()->where('code', 'HTA')->update(['project_incharge_id' => $pi->id]);
        $this->billMonth($contract->load('projects'), '2026-10-01');

        $this->get('/')->assertSee('Project In-charge');                                  // sidebar module
        $this->get('/incharges')->assertOk()
            ->assertSee('Dr. D. Sample')->assertSee('HTA')->assertSee('SW-1001')->assertSee('Person HTA')   // PI → project → employees
            ->assertSee('SW/001/26-27(HTA)')                                                 // the sub-bill marked to this PI
            ->assertSee('1 project without a Project In-charge');                            // CCRH

        // The sub-bill PDF names the PI ("Kindly Attention … (P.I)") in the agencies' layout.
        $bill = \App\Models\Invoice::query()->where('invoice_no', 'SW/001/26-27(HTA)')->firstOrFail()->load('lines', 'project.incharge', 'contract.agency', 'contract.client');
        $html = view('pdf.invoice', ['invoice' => $bill, 'agency' => $bill->contract->agency, 'client' => $bill->contract->client, 'project' => $bill->project, 'contract' => $bill->contract])->render();
        foreach (['SL No:-SW/001/26-27(HTA)', 'Total Monthly Wages', 'Service charge @ 3.26% (excluding GST', '18% GST (as per GST Rule By the GOI)', 'Grand Total Round Up', 'Kindly Attention Dr. D. Sample (P.I)', 'Authorized Signatory'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }
}
