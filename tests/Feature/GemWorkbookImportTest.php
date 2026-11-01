<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Services\Import\GemWorkbookImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class GemWorkbookImportTest extends TestCase
{
    use RefreshDatabase;

    /** Synthetic workbook laid out like GEM_PROJECT_DETAILS.xlsx (no real personal data). */
    private function workbook(): string
    {
        $book = new Spreadsheet;
        $agency = $book->getActiveSheet()->setTitle('SWACHHA');
        $head = ['SL NO', 'GEM BID NO', 'GEM CONTCT NO', 'NAME OF THE PI', 'Name of the Project', 'Post', 'Quatity', "Per Head/\nMonthly Rate", "Yearly \nAmount", "As per ICMR \nRequirement post", 'Quantity', 'NAME OF THE CANDIDATES'];
        $agency->fromArray([
            $head,
            [1, 7972720, 511687731718030, 'Dr. A. Pi', 'MPH', 'APA', 1, 31000, '=G2*H2*12', 'APA', 1, 'Dr Test Assistant'],
            [null, null, null, null, null, 'APM', 1, 55000, '=G3*H3*12', 'APM', 1, 'Mr. TestManager'],
            [null, null, null, 'Dr. B. Pi', 'CCRH', 'PTS-I', 2, 21600, '=G4*H4*12', 'PTS-I', 1, "Field Person One\nExtra Person (OFF LINE)\nThird Person"],
            [null, null, null, null, null, null, '=SUM(G2:G4)', null, '=SUM(I2:I4)', null, null, null],
            [null, null, null, null, 'Service Charge @3.85%', null, null, null, '=(I5*3.85%)/1.18', 'As per the condition of Gem', null, null],
            [null, null, null, null, 'Total', null, null, null, '=I5', null, null, null],
            [2, 'GEM BID NO', 'GEM CONTCT NO', 'NAME OF THE PI', 'Name of the Project', 'Post', 'Quatity', 'Rate Per Day', "Yearly \nAmount", 'EPF', 'ESIC', 'TOTAL', null, null],
            [null, 7989556, 511687742040571, 'DEMO', 'DHUMKA', 'MTS', 1, 556, '=(H9*G9)*26*12', '=I9*13%', '=I9*3.25%', '=I9+J9+K9', 'Daily Worker', 1],
        ]);

        $pi = $book->createSheet()->setTitle('Dr. A. Pi');
        $pi->fromArray([
            ['  NAME OF THE P.I Dr. A. Pi'],
            ['NAME OF THE PROJECT :-', 'AGENCY', 'NAME OF THE EMPLOYER', 'DESIGANTION', 'DOJ', 'SALARY', 'SBI', 'OTHER BANK', 'IFSC CODE '],
            ['MPH', 'SWACHHA', 'Dr Test Assistant', 'APA', null, 31000, '20364200000', null, null],
            [null, null, 'Mr. Test Manager', 'APM', null, 55500, null, '2957101000000', 'CNRB0002957'],
            [null, null, 'Field Persn One', 'PTS-I', null, null, null, '49460100001321', 'BARB0VJNIVI00000'],
            [null, null, 'Unknown Person', 'PTS-I', null, 20000, null, '1234', 'CNRB0002957'],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'gem').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    public function test_analyse_reads_contracts_posts_staff_and_reports_problems(): void
    {
        $plan = (new GemWorkbookImporter)->analyse($this->workbook());
        $summary = $plan->summary();

        $this->assertSame(2, $summary['contracts']);
        $this->assertSame(4, $summary['posts']);
        $this->assertSame('daily', $plan->contracts['GEMC-511687742040571']['billing_type']);
        $this->assertSame('GEM/2026/B/7972720', $plan->contracts['GEMC-511687731718030']['bid_no']);

        $titles = implode("\n", array_column($plan->issues, 'title'));
        $this->assertStringContainsString('1 invalid IFSC', $titles);
        $this->assertStringContainsString('1 SBI account(s) without IFSC', $titles);
        $this->assertStringContainsString('1 salary(ies) differ', $titles);          // 55,500 vs 55,000
        $this->assertStringContainsString('1 post(s) with more names', $titles);     // 3 names for 2 PTS-I posts
        $this->assertStringContainsString('1 name(s) matched by close spelling', $titles); // "Field Persn One"
        $this->assertStringContainsString('1 staff on PI sheets not found', $titles);      // Unknown Person
    }

    public function test_commit_creates_records_and_encrypts_bank_numbers(): void
    {
        $client = Client::query()->create(['name' => 'ICMR-NIHR Bhubaneswar', 'short_code' => 'ICMR']);
        $importer = new GemWorkbookImporter;
        $plan = $importer->analyse($path = $this->workbook());

        $result = $importer->commit($plan, $client);

        $this->assertSame(2, $result['contracts']);
        $this->assertSame(2, GemContract::query()->count());
        $this->assertSame(['SWH'], $client->agencies()->pluck('code')->all());   // importer maps the agency to the client
        $manager = Employee::query()->where('name', 'Mr. Test Manager')->firstOrFail();
        $this->assertSame('APM', $manager->post->designation);
        $this->assertSame('55500.00', (string) $manager->rate_override);
        $this->assertSame('0000', $manager->account_last4);
        $this->assertNotSame('2957101000000', $manager->getRawOriginal('account_number'));   // stored encrypted
        $this->assertNull(Employee::query()->where('name', 'Field Persn One')->value('ifsc')); // invalid IFSC not saved

        // Importing the same workbook again does not duplicate anything.
        $again = $importer->commit($importer->analyse($path), $client);
        $this->assertSame(0, $again['contracts']);
        $this->assertSame(2, $again['skipped_contracts']);
        $this->assertSame(1, Employee::query()->where('name', 'Mr. Test Manager')->count());
    }
}
