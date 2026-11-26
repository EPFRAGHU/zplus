<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** NEFT intimation file for the bank's bulk upload, in the owner's sheet layout (owner, 26 Nov 2026). Made-up data only. */
class NeftFileTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->owner = $this->makeDeveloper(User::factory()->create(['role' => 'admin']));
        $this->agency = Agency::query()->where('code', 'ZPS')->firstOrFail();
        $this->agency->update(['bank_corp_id' => '123456', 'bank_corp_name' => 'zpss', 'bank_account_no' => '10000000001', 'bank_address_code' => 'bbsr']);
        $staff = Employee::query()->where('agency_id', $this->agency->id)->whereNotNull('post_id')->orderBy('emp_code')->get();
        $this->assertGreaterThanOrEqual(2, $staff->count());
        Employee::query()->where('agency_id', $this->agency->id)->update(['account_number' => null, 'ifsc' => null]);
        $staff[0]->forceFill(['account_holder' => 'Test  Person One', 'account_number' => '20000000001', 'ifsc' => 'SBIN0000001', 'bank_name' => 'SBI'])->save();
        $staff[1]->forceFill(['account_holder' => 'Test Person Two', 'account_number' => '012345678901234567', 'ifsc' => 'cnrb0000002', 'bank_name' => 'Canara'])->save();
        $this->artisan('demo:full-month', ['month' => '2026-10', '--force' => true])->assertSuccessful();   // locks the month, payout approved "(demo)"
    }

    public function test_excel_has_the_owners_columns_and_one_row_per_paid_employee(): void
    {
        $q = ['agency' => $this->agency->id, 'month' => '2026-10'];
        $this->actingAs($this->owner)->get(route('bank-letters.index', $q))->assertOk()
            ->assertSee('NEFT file (Excel)')->assertSee('NEFT file (PDF)')->assertSee(route('bank-letters.neft-excel'));

        $response = $this->get(route('bank-letters.neft-excel', $q))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $ws = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('Sheet1', $ws->getTitle());
        $this->assertSame(['sn', 'corp id', 'corp name', 'corp acct no', 'product code', 'benf name', 'benf acct no', 'amount', 'ifsc', 'remarks', 'CURRENCY', 'ADDRESS'],
            $ws->rangeToArray('A1:L1')[0]);
        $this->assertTrue($ws->getStyle('A1')->getFont()->getBold());

        $letter = app(\App\Services\Payroll\BankLetter::class);
        $data = $letter->build($this->agency, \Carbon\CarbonImmutable::parse('2026-10-01'), $letter->sheets($this->agency, \Carbon\CarbonImmutable::parse('2026-10-01'))[0]);
        $this->assertSame(2, $data['sbi']->count() + $data['neft']->count(), 'only staff with bank details');

        // SBI: DCR, no IFSC, account as a number like the sample.
        $this->assertSame([1, 123456, 'ZPSS', 10000000001, 'DCR', 'TEST PERSON ONE', 20000000001], array_slice($ws->rangeToArray('A2:L2', null, false, false)[0], 0, 7));
        $this->assertNull($ws->getCell('I2')->getValue());
        $this->assertSame(['SAL OCT 2026', 'INR', 'BBSR'], [$ws->getCell('J2')->getValue(), $ws->getCell('K2')->getValue(), $ws->getCell('L2')->getValue()]);
        $this->assertSame((int) round($data['sbi']->first()['net'] / 100), $ws->getCell('H2')->getValue(), 'whole rupees as on the payout sheet');
        // Other bank: NEFT with IFSC; an 18-digit account with a leading zero stays exact as text.
        $this->assertSame('NEFT', $ws->getCell('E3')->getValue());
        $this->assertSame('012345678901234567', $ws->getCell('G3')->getValue());
        $this->assertSame('CNRB0000002', $ws->getCell('I3')->getValue());
        $this->assertNull($ws->getCell('A4')->getValue(), 'no total row: the bank reads every row');

        $pdf = $this->get(route('bank-letters.neft-pdf', $q))->assertOk()->assertHeader('content-type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $html = view('pdf.neft-file', ['rows' => app(\App\Services\Payroll\NeftFile::class)->rows($data), 'agency' => $this->agency, 'month' => $data['month'], 'reference' => $data['reference']])->render();
        foreach (['corp acct no', 'TEST PERSON TWO', 'CNRB0000002', 'SAL OCT 2026', '2 transfers', 'DCR 1, NEFT 1'] as $t) {
            $this->assertStringContainsString($t, $html);
        }
    }

    public function test_agency_codes_are_entered_on_the_agency_and_checked(): void
    {
        $this->actingAs($this->owner)->get(route('agencies.edit', $this->agency))->assertOk()->assertSee('Bank salary upload (NEFT file)');
        $this->put(route('agencies.update', $this->agency), ['name' => $this->agency->name, 'code' => 'ZPS', 'bank_corp_id' => '12 34;=cmd'])->assertSessionHasErrors('bank_corp_id');
        $this->agency->update(['bank_corp_id' => null]);
        $this->get(route('bank-letters.index', ['agency' => $this->agency->id, 'month' => '2026-10']))->assertSee('The NEFT file needs the agency');
    }

    public function test_same_rules_as_the_bank_letter(): void
    {
        $q = ['agency' => $this->agency->id, 'month' => '2026-10'];
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'supervisor', 'agency_id' => $this->agency->id]))->get(route('bank-letters.neft-excel', $q))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'accounts', 'agency_id' => $swh->id]));
        $this->get(route('bank-letters.neft-excel', $q))->assertSessionHasErrors('agency');
        $this->get(route('bank-letters.neft-pdf', $q))->assertSessionHasErrors('agency');
        // Not approved yet: refused like the bank letter.
        \App\Models\Approval::query()->withoutGlobalScopes()->where('type', 'payout')->delete();
        $this->actingAs(User::factory()->create(['role' => 'accounts', 'agency_id' => $this->agency->id]))->get(route('bank-letters.neft-excel', $q))->assertForbidden();
    }
}
