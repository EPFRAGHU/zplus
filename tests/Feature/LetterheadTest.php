<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LetterheadTest extends TestCase
{
    use RefreshDatabase;

    public function test_zplus_gets_its_printed_letterhead_details_and_seal(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $zps = Agency::query()->where('code', 'ZPS')->firstOrFail();

        $this->assertSame('A leading Organization of Manpower & Security Services', $zps->tagline);
        $this->assertSame('21AAAFZ5965B1ZD', $zps->gstin);
        $this->assertSame('AAAFZ5965B', $zps->pan);
        $this->assertSame('9437071819, 7008024050', $zps->phones);
        $this->assertStringContainsString('PSARA - Sl No. 368/PSA/OISF', $zps->registrations);
        $this->assertStringStartsWith('data:image/png;base64,', $zps->logoDataUri());
    }

    public function test_bill_and_letters_print_the_letterhead(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(AttendanceService::class);
        $sheet = $service->open(Project::query()->where('code', 'MPH')->firstOrFail(), CarbonImmutable::parse('2026-10-01'));
        $service->lock($sheet, $admin, null);
        $invoice = app(InvoiceGenerator::class)->generate($sheet->fresh(), $admin)->load('lines', 'contract.agency', 'contract.client', 'project');
        $data = ['invoice' => $invoice, 'agency' => $invoice->contract->agency, 'client' => $invoice->contract->client, 'project' => $invoice->project, 'contract' => $invoice->contract];

        foreach (['pdf.invoice', 'pdf.covering-letter'] as $view) {
            $html = view($view, $data)->render();
            $this->assertStringContainsString('M/S Z PLUS SURAKHYA SEVA', $html, $view);
            $this->assertStringContainsString('(A leading Organization of Manpower &amp; Security Services)', $html, $view);
            $this->assertStringContainsString('GSTIN No.-21AAAFZ5965B1ZD', $html, $view);
            $this->assertStringContainsString('Website: www.zplussurakhyaseva.com', $html, $view);
            $this->assertStringContainsString('Mob. 9437071819, 7008024050', $html, $view);
            $this->assertStringContainsString('<img src="data:image/png;base64,', $html, $view);
        }

        $employee = Employee::query()->with('post.project.contract.agency', 'post.project.contract.client')->firstOrFail();
        $p = $employee->post->project;
        $letter = view('pdf.appointment-letter', ['employee' => $employee, 'project' => $p, 'contract' => $p->contract, 'agency' => $p->contract->agency,
            'number' => 'ZPS/NIHR/MPH/01/26', 'rate' => '55000', 'rateWords' => 'Rupees Fifty Five Thousand Only'])->render();
        $this->assertStringContainsString('M/S Z PLUS SURAKHYA SEVA', $letter);
        $this->assertStringNotContainsString('O/o The', $letter);   // the letterhead replaces the plain "From" address

        $this->actingAs($admin)->get("/invoices/{$invoice->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_agency_without_letterhead_details_keeps_the_plain_header(): void
    {
        $swh = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH', 'address' => 'Gandamunda, Bhubaneswar']);
        $html = view('pdf._letterhead', ['agency' => $swh])->render();
        $this->assertStringContainsString('SWACHHA', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_letterhead_and_logo_can_be_edited_on_the_agency_form(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $swh = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH']);

        $this->actingAs($admin)->get("/agencies/{$swh->id}/edit")->assertOk()->assertSee('Letterhead')->assertSee('Registration lines');
        $this->put("/agencies/{$swh->id}", ['name' => 'SWACHHA', 'code' => 'SWH', 'tagline' => 'Manpower services',
            'phones' => '9000000000', 'logo' => UploadedFile::fake()->image('seal.png', 200, 200)])->assertSessionHasNoErrors();

        $swh->refresh();
        $this->assertSame('Manpower services', $swh->tagline);
        $this->assertNotNull($swh->logo_path);
        Storage::disk('local')->assertExists($swh->logo_path);
        $this->get("/agencies/{$swh->id}/logo")->assertOk();

        $this->put("/agencies/{$swh->id}", ['name' => 'SWACHHA', 'code' => 'SWH', 'logo' => UploadedFile::fake()->create('big.png', 2048, 'image/png')])
            ->assertSessionHasErrors('logo');
    }

    public function test_swachha_gets_its_banner_letterhead(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();

        $this->assertSame('banner', $swh->letterhead_style);
        $this->assertSame('21AADAS8034Q1Z6', $swh->gstin);   // the printed GSTIN has an extra "A"
        $this->assertSame([['IGR Regd No', '2710/300'], ['PAN No', 'AADAS8034Q'], ['GST No', '21AADAS8034Q1Z6'],
            ['ESIC No', '44000594740000999'], ['EPF No', 'ORBBS2612050000']], $swh->registrationPairs());

        $html = view('pdf._letterhead', ['agency' => $swh])->render().view('pdf._letterhead-footer', ['agency' => $swh])->render();
        $this->assertStringContainsString('&ldquo;SWACHHA&rdquo;', $html);
        $this->assertStringContainsString('Since-1972', $html);
        $this->assertStringContainsString(':21AADAS8034Q1Z6', $html);
        $this->assertStringContainsString('Regd. Office Address:', $html);
        $this->assertStringContainsString('hr.swachha@gmail.com, swachha38@yahoo.com', $html);
        $this->assertStringContainsString('Mob No: 9337219868', $html);
        $this->assertStringContainsString('<img src="data:image/png;base64,', $html);
        $this->assertStringNotContainsString('position:fixed', $html);   // no Z Plus style footer

        // Z Plus keeps its own layout.
        $zps = Agency::query()->where('code', 'ZPS')->firstOrFail();
        $this->assertSame('classic', $zps->letterhead_style);
        $this->assertStringNotContainsString('Regd. Office Address:', view('pdf._letterhead', ['agency' => $zps])->render());
    }

    public function test_letterhead_layout_can_be_changed_on_the_agency_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agency = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWH']);
        $this->actingAs($admin)->put("/agencies/{$agency->id}", ['name' => 'SWACHHA', 'code' => 'SWH', 'letterhead_style' => 'banner'])->assertSessionHasNoErrors();
        $this->assertSame('banner', $agency->fresh()->letterhead_style);
        $this->put("/agencies/{$agency->id}", ['name' => 'SWACHHA', 'code' => 'SWH', 'letterhead_style' => 'fancy'])->assertSessionHasErrors('letterhead_style');
    }

    public function test_swachha_tax_invoice_prints_the_banner_letterhead(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $contract = \App\Models\GemContract::query()->firstOrFail();
        $contract->update(['agency_id' => $swh->id, 'client_id' => \App\Models\Client::query()->where('agency_id', $swh->id)->value('id')]);
        $service = app(AttendanceService::class);
        $sheet = $service->open(Project::query()->where('code', 'MPH')->firstOrFail(), CarbonImmutable::parse('2026-10-01'));
        $service->lock($sheet, $admin, null);
        $invoice = app(InvoiceGenerator::class)->generate($sheet->fresh(), $admin);
        $tax = app(\App\Services\Billing\TaxInvoiceBuilder::class)->create($contract->fresh()->client, CarbonImmutable::parse('2026-10-01'), [$invoice->id], $admin);

        $tax->load('agency', 'client', 'subBills.project', 'subBills.contract');
        $html = view('pdf.tax-invoice', ['tax' => $tax, 'agency' => $tax->agency, 'client' => $tax->client])->render();
        $this->assertStringContainsString('&ldquo;SWACHHA&rdquo;', $html);
        $this->assertStringContainsString('Regd. Office Address:', $html);
        $this->assertStringContainsString(':21AADAS8034Q1Z6', $html);
        $this->assertStringContainsString('TAX INVOICE', $html);

        $this->actingAs($admin)->get("/tax-invoices/{$tax->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_swachha_switches_to_banner_even_when_details_were_typed_in(): void
    {
        $agency = Agency::query()->create(['name' => 'SWACHHA', 'code' => 'SWA', 'tagline' => 'Manpower services', 'letterhead_style' => 'classic']);
        (require database_path('migrations/2026_11_04_000200_use_banner_letterhead_for_swachha.php'))->up();
        $agency->refresh();
        $this->assertSame('banner', $agency->letterhead_style);
        $this->assertSame('Manpower services', $agency->tagline);   // typed details are kept
        $this->assertSame('21AADAS8034Q1Z6', $agency->gstin);       // empty ones are filled
    }

    public function test_attendance_certificate_prints_the_agency_letterhead(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $sheet = app(AttendanceService::class)->open(Project::query()->where('code', 'MPH')->firstOrFail(), CarbonImmutable::parse('2026-10-01'));
        $sheet->load('project.contract.agency', 'project.contract.client', 'entries.employee.post');

        $html = view('pdf.attendance-certificate', ['sheet' => $sheet, 'showBank' => false])->render();
        $this->assertStringContainsString('GSTIN No.-21AAAFZ5965B1ZD', $html);   // Z Plus letterhead

        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $sheet->project->contract->update(['agency_id' => $swh->id]);
        $sheet->load('project.contract.agency');
        $html = view('pdf.attendance-certificate', ['sheet' => $sheet, 'showBank' => false])->render();
        $this->assertStringContainsString('&ldquo;SWACHHA&rdquo;', $html);
        $this->assertStringContainsString('Regd. Office Address:', $html);
        $this->assertStringContainsString('Monthly Attendance and Working Certificate', $html);

        $this->actingAs($admin)->get("/attendance/{$sheet->id}/certificate")->assertOk();
    }

    public function test_swachha_appointment_letter_prints_the_banner_letterhead_on_one_page(): void
    {
        Storage::fake('local');
        $this->seed(DemoSeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $employee = Employee::query()->with('post.project.contract')->firstOrFail();
        $employee->post->project->contract->update(['agency_id' => $swh->id]);

        $response = $this->actingAs($admin)->get("/employees/{$employee->id}/appointment-letter")->assertOk();
        $pdf = $response->getContent();
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf));   // fits on one page

        $p = $employee->fresh()->post->project;
        $html = view('pdf.appointment-letter', ['employee' => $employee, 'project' => $p, 'contract' => $p->contract, 'agency' => $swh->fresh(),
            'number' => 'SWH/NIHR/MPH/01/26', 'rate' => '55000', 'rateWords' => 'Rupees Fifty Five Thousand Only'])->render();
        $this->assertStringContainsString('&ldquo;SWACHHA&rdquo;', $html);
        $this->assertStringContainsString('Regd. Office Address:', $html);
        $this->assertStringContainsString('Ref:- SWH/NIHR/MPH/01/26', $html);
        $this->assertStringNotContainsString('O/o The', $html);
    }
}
