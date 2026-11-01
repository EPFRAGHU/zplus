<?php

namespace App\Support;

use App\Models\Agency;
use Illuminate\Support\Facades\Storage;

/**
 * Letterhead details read from M/S Z Plus Surakhya Seva's printed letterhead.
 * Used to fill the agency record once; after that the Agencies page is the source.
 */
final class KnownLetterheads
{
    public const ZPS = [
        'name' => 'M/S Z Plus Surakhya Seva',
        'tagline' => 'A leading Organization of Manpower & Security Services',
        'registrations' => "PSARA - Sl No. 368/PSA/OISF Dt. 03.12.2019, CL License No: KHU/L/CL(R&A)-2119/2013\n"
            ."S & CE Regd. No.II-2493/2003 • IGR Regd No. 309/2010 • EPF Registration - OR/14330\n"
            .'ESIC Registration - 44000073600001018 • PAN Registration No. AAAFZ5965B',
        'address' => 'Plot No.-1468/5398, Bhaktamadhu Nagar, Lake Vally, Lane-1, Gandamunda, Bhubaneswar-751030',
        'gstin' => '21AAAFZ5965B1ZD',
        'pan' => 'AAAFZ5965B',
        'website' => 'www.zplussurakhyaseva.com',
        'letterhead_emails' => 'zplussurakhyaseva@yahoo.in, zplussurakhyaseva@gmail.com',
        'phones' => '9437071819, 7008024050',
        'email' => 'zplussurakhyaseva@gmail.com',
        'phone' => '9437071819',
        'signatory_title' => 'Managing Partner',
    ];

    /** Fill empty letterhead fields of the Z Plus agency (code ZPS) and give it the seal from the letterhead. */
    public static function applyZplus(Agency $agency): void
    {
        foreach (self::ZPS as $field => $value) {
            if ($field !== 'name' && blank($agency->{$field})) {
                $agency->{$field} = $value;
            }
        }
        if (blank($agency->logo_path) && is_file($src = database_path('seeders/assets/zps-logo.png'))) {
            Storage::disk('local')->put('letterheads/zps-logo.png', file_get_contents($src));
            $agency->logo_path = 'letterheads/zps-logo.png';
        }
        $agency->save();
    }
}
