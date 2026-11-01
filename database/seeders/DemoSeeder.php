<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Support\KnownLetterheads;
use Illuminate\Database\Seeder;

/**
 * Sample data: the October 2026 MPH bill (ICMR/MPH/01/2026, Rs 1,05,387) with placeholder staff.
 * php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $zplus = Agency::query()->firstOrCreate(['code' => 'ZPS'], ['name' => 'M/S Z Plus Surakhya Seva']);
        KnownLetterheads::applyZplus($zplus);   // letterhead details from the printed letterhead
        Agency::query()->firstOrCreate(['code' => 'SWH'], ['name' => 'SWACHHA', 'email' => 'hr.swachha@gmail.com',
            'address' => "Plot No- 1468/5398/7693, Bhaktamadhu Nagar,\nGandamunda, Bhubaneswar-751030"]);
        $swh = Agency::query()->where('code', 'SWH')->firstOrFail();
        $details = [
            'name' => 'ICMR-NIHR (RMRCBB), Bhubaneswar', 'letter_code' => 'NIHR', 'addressee' => 'The Administrative Officer',
            'billing_address' => "ICMR, RMRCBB, Department of Health Research,\nMinistry of Health & Family Welfare, Govt. of India",
            'district' => 'Khordha', 'state_code' => '21',
        ];
        // ICMR works with both agencies, so it is entered once under each.
        $icmr = Client::query()->firstOrCreate(['agency_id' => $zplus->id, 'short_code' => 'ICMR'], $details);
        Client::query()->firstOrCreate(['agency_id' => $swh->id, 'short_code' => 'ICMR'], $details);
        $contract = GemContract::query()->firstOrCreate(['contract_no' => 'GEMC-511687731718030'], [
            'agency_id' => $zplus->id, 'client_id' => $icmr->id, 'bid_no' => 'GEM/2026/B/7972720', 'contract_date' => '2026-10-01',
        ]);
        $mph = $contract->projects()->firstOrCreate(['code' => 'MPH'], [
            'name' => 'MPH', 'title' => 'MPH Academic Programme', 'pi_name' => 'Dr. S. Kanungo', 'location' => 'ICMR-NIHR, Bhubaneswar',
        ]);
        $apm = $mph->posts()->firstOrCreate(['designation' => 'Academic Programme Manager'], ['sanctioned_qty' => 1, 'rate' => 55000]);
        $apa = $mph->posts()->firstOrCreate(['designation' => 'Academic Programme Assistant'], ['sanctioned_qty' => 1, 'rate' => 31000]);

        foreach ([[$apm, 'Amit Kumar (sample)'], [$apa, 'Ritu Panda (sample)']] as [$post, $name]) {
            Employee::query()->firstOrCreate(['name' => $name], [
                'emp_code' => Employee::nextCode(), 'agency_id' => $zplus->id, 'client_id' => $icmr->id, 'post_id' => $post->id, 'joining_date' => '2026-10-01', 'status' => 'active',
                'bank_name' => 'Canara Bank', 'account_holder' => strtoupper($name), 'account_number' => '0000000000'.random_int(100, 999), 'ifsc' => 'CNRB0002957',
            ]);
        }
        $this->command?->info('Demo data added. Open Attendance, pick October 2026 and start the MPH sheet with holidays 2, 19, 20.');
    }
}
