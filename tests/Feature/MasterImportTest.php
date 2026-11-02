<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use App\Services\MasterImport\MasterImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MasterImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    /** Fill the blank template of $type with rows (values by heading, without the *), and return it as an upload. */
    private function filled(string $type, array $rows): UploadedFile
    {
        $book = app(MasterImporter::class)->template($type);
        $ws = $book->getSheet(0);
        $headings = [];
        foreach ($ws->getRowIterator(1, 1)->current()->getCellIterator() as $cell) {
            $headings[rtrim(trim((string) $cell->getValue()), ' *')] = $cell->getColumn();
        }
        foreach ($rows as $i => $row) {
            foreach ($row as $heading => $value) {
                $this->assertArrayHasKey($heading, $headings, "template {$type} has a {$heading} column");
                $ws->setCellValueExplicit($headings[$heading].($i + 2), (string) $value, DataType::TYPE_STRING);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, "{$type}.xlsx", null, null, true);
    }

    private function upload(string $type, array $rows)
    {
        return $this->post("/data-upload/{$type}/check", ['file' => $this->filled($type, $rows)]);
    }

    public function test_page_lists_all_templates_and_templates_download(): void
    {
        $this->get('/data-upload')->assertOk()->assertSee('Agencies')->assertSee('GeM contracts')->assertSee('Employees')->assertSee('Check file');
        foreach (MasterImporter::ORDER as $type) {
            $response = $this->get("/data-upload/{$type}/template")->assertOk();
            $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
        }
    }

    public function test_full_load_in_order_from_agencies_to_employees(): void
    {
        $this->upload('agencies', [['Agency code' => 'zps', 'Agency name' => 'M/S Z Plus Surakhya Seva', 'GSTIN' => '21AAAFZ5965B1ZD', 'Bank account no.' => '00123456789', 'Bank IFSC' => 'sbin0001234']])
            ->assertOk()->assertSee('1 new');
        $this->post('/data-upload/agencies/save')->assertRedirect('/data-upload')->assertSessionHas('status', 'Agencies: 1 added, 0 updated.');
        $agency = Agency::query()->where('code', 'ZPS')->firstOrFail();
        $this->assertSame('00123456789', $agency->bank_account_no);   // leading zeros kept
        $this->assertSame('SBIN0001234', $agency->bank_ifsc);
        $this->assertSame('classic', $agency->letterhead_style);

        $this->upload('clients', [['Agency code' => 'ZPS', 'Client code' => 'ICMR', 'Client name' => 'ICMR-NIHR (RMRCBB), Bhubaneswar', 'Letter code' => 'NIHR']])->assertSee('1 new');
        $this->post('/data-upload/clients/save');
        $client = Client::query()->where('short_code', 'ICMR')->firstOrFail();
        $this->assertSame($agency->id, $client->agency_id);
        $this->assertSame('21', $client->state_code);   // default when blank

        $this->upload('contracts', [['Agency code' => 'ZPS', 'Client code' => 'ICMR', 'GeM contract no.' => 'GEMC-511687731718030', 'GeM bid no.' => 'GEM/2026/B/7972720',
            'Contract date' => '01-10-2026', 'Billing type' => 'Monthly', 'Inter-state (IGST)' => 'No']])->assertSee('1 new');
        $this->post('/data-upload/contracts/save');
        $contract = GemContract::query()->firstOrFail();
        $this->assertSame('2026-10-01', $contract->contract_date->toDateString());
        $this->assertEquals(3.85, (float) $contract->service_charge_percent);

        $this->upload('projects', [['GeM contract no.' => 'GEMC-511687731718030', 'Project code' => 'MPH', 'Project name' => 'MPH', 'PI name' => 'Dr. S. Kanungo']])->assertSee('1 new');
        $this->post('/data-upload/projects/save');
        $this->upload('posts', [
            ['GeM contract no.' => 'GEMC-511687731718030', 'Project code' => 'MPH', 'Designation' => 'Academic Programme Manager', 'Sanctioned posts' => '1', 'Rate (₹)' => '55,000'],
            ['GeM contract no.' => 'GEMC-511687731718030', 'Project code' => 'MPH', 'Designation' => 'Academic Programme Assistant', 'Sanctioned posts' => '1', 'Rate (₹)' => '31000'],
        ])->assertSee('2 new');
        $this->post('/data-upload/posts/save');
        $this->assertSame(2, Post::count());
        $this->assertSame('55000.00', (string) Post::query()->where('designation', 'Academic Programme Manager')->value('rate'));

        $this->upload('employees', [
            ['Name' => 'Test Person One', 'Agency code' => 'ZPS', 'Client code' => 'ICMR', 'GeM contract no.' => 'GEMC-511687731718030', 'Project code' => 'MPH',
                'Designation (post)' => 'academic programme manager', 'Mobile' => '9876543210', 'Joining date' => '01/10/2026', 'Bank account no.' => '0012345678901',
                'IFSC' => 'CNRB0002957', 'Aadhaar' => '1234 5678 9012', 'EPF' => 'Yes', 'ESIC' => 'No'],
            ['Name' => 'Test Person Two', 'Agency code' => 'ZPS'],
        ])->assertSee('2 new');
        $this->post('/data-upload/employees/save')->assertSessionHas('status', 'Employees: 2 added, 0 updated.');
        $one = Employee::query()->where('name', 'Test Person One')->firstOrFail();
        $this->assertSame('EMP-1001', $one->emp_code);
        $this->assertSame('EMP-1002', Employee::query()->where('name', 'Test Person Two')->value('emp_code'));
        $this->assertSame($client->id, $one->client_id);
        $this->assertSame('Academic Programme Manager', $one->post->designation);
        $this->assertSame('0012345678901', $one->account_number);
        $this->assertSame('8901', $one->account_last4);
        $this->assertSame('9012', $one->aadhaar_last4);
        $this->assertTrue($one->pf_applicable);
        $this->assertSame('2026-10-01', $one->joining_date->toDateString());
    }

    public function test_reupload_updates_only_changed_cells_and_blank_keeps_values(): void
    {
        $this->upload('agencies', [['Agency code' => 'ZPS', 'Agency name' => 'Z Plus', 'Phone' => '9437071819']]);
        $this->post('/data-upload/agencies/save');

        $this->upload('agencies', [['Agency code' => 'ZPS', 'Agency name' => 'Z Plus']])->assertSee('1 no change');
        $this->upload('agencies', [['Agency code' => 'ZPS', 'Agency name' => 'M/S Z Plus Surakhya Seva']])->assertSee('1 to update')->assertSee('Changes: Agency name');
        $this->post('/data-upload/agencies/save')->assertSessionHas('status', 'Agencies: 0 added, 1 updated.');
        $a = Agency::query()->firstOrFail();
        $this->assertSame('M/S Z Plus Surakhya Seva', $a->name);
        $this->assertSame('9437071819', $a->phone);   // blank cell kept it
        $this->assertSame(1, Agency::count());
    }

    public function test_errors_are_listed_and_skipped_while_good_rows_save(): void
    {
        $this->upload('agencies', [['Agency code' => 'ZPS', 'Agency name' => 'Z Plus']]);
        $this->post('/data-upload/agencies/save');

        $this->upload('clients', [
            ['Agency code' => 'ZPS', 'Client code' => 'ICMR', 'Client name' => 'ICMR'],
            ['Agency code' => 'XYZ', 'Client code' => 'AIIMS', 'Client name' => 'AIIMS'],
            ['Agency code' => 'ZPS', 'Client name' => 'No code', 'GSTIN' => 'BAD'],
            ['Agency code' => 'ZPS', 'Client code' => 'ICMR', 'Client name' => 'ICMR again'],
        ])->assertOk()->assertSee('1 new')->assertSee('3 with errors')
            ->assertSee('Agency code XYZ is not in the app')->assertSee('Client code is required.')->assertSee('GSTIN must have 15 characters')
            ->assertSee('Same record as row 2 of this file');
        $this->post('/data-upload/clients/save')->assertSessionHas('status', fn ($s) => str_contains($s, '1 added') && str_contains($s, '3 rows with errors were skipped'));
        $this->assertSame(1, Client::count());

        $this->upload('employees', [['Name' => 'X', 'Agency code' => 'ZPS', 'Mobile' => '12345', 'Project code' => 'MPH', 'Date of birth' => '31-31-2000']])
            ->assertSee('Mobile must be 10 digits')->assertSee('fill all three')->assertSee('is not a date');
    }

    public function test_wrong_file_is_refused_with_a_clear_message(): void
    {
        $this->from('/data-upload')->post('/data-upload/agencies/check', ['file' => $this->filled('posts', [])])
            ->assertRedirect('/data-upload')->assertSessionHasErrors('file_agencies');
        $this->from('/data-upload')->post('/data-upload/agencies/check', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');
    }

    public function test_download_with_current_data_never_includes_bank_account_or_aadhaar(): void
    {
        $agency = Agency::query()->create(['code' => 'ZPS', 'name' => 'Z Plus']);
        Employee::query()->create(['emp_code' => 'EMP-1001', 'name' => 'Secret Holder', 'agency_id' => $agency->id, 'status' => 'active',
            'account_number' => '998877665544', 'aadhaar' => '123412341234']);

        $response = $this->get('/data-upload/employees/template?data=1')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'dl').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $ws = IOFactory::load($path)->getSheet(0);
        $text = json_encode($ws->toArray());
        $this->assertStringContainsString('Secret Holder', $text);
        $this->assertStringContainsString('EMP-1001', $text);
        $this->assertStringNotContainsString('998877665544', $text);
        $this->assertStringNotContainsString('123412341234', $text);
    }

    public function test_only_admins_can_use_data_upload(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'accounts']));
        $this->get('/data-upload')->assertForbidden();
        $this->get('/data-upload/agencies/template')->assertForbidden();
    }

    public function test_updating_an_existing_record_needs_only_its_code(): void
    {
        $agency = Agency::query()->create(['code' => 'ZPS', 'name' => 'Z Plus']);
        Employee::query()->create(['emp_code' => 'EMP-1001', 'name' => 'Existing Person', 'agency_id' => $agency->id, 'status' => 'active']);

        $this->upload('employees', [['Employee code' => 'emp-1001', 'Mobile' => '9876543210', 'EPF' => 'Yes']])
            ->assertSee('1 to update')->assertDontSee('Name is required');
        $this->post('/data-upload/employees/save');
        $e = Employee::query()->firstOrFail();
        $this->assertSame('Existing Person', $e->name);
        $this->assertSame('9876543210', $e->mobile);
        $this->assertTrue($e->pf_applicable);

        $this->upload('employees', [['Mobile' => '9876543211']])->assertSee('Name is required')->assertSee('Agency code is required');
        $this->upload('agencies', [['Agency name' => 'No code']])->assertSee('Agency code is required');
    }
}
