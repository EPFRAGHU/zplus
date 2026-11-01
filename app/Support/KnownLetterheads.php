<?php

namespace App\Support;

use App\Models\Agency;
use Illuminate\Support\Facades\Storage;

/**
 * Letterhead details read from the agencies' printed letterheads (Z Plus and SWACHHA).
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

    /**
     * SWACHHA's letterhead (appointment letter header). Registration lines are "Label: value"; the GST line is
     * printed from the GSTIN field. The letterhead prints 21AAADAS8034Q1Z6, which has 16 characters; the valid
     * GSTIN for PAN AADAS8034Q is 21AADAS8034Q1Z6 (its check character, 6, matches).
     */
    public const SWH = [
        'name' => 'SWACHHA',
        'tagline' => 'Since-1972',
        'registrations' => "IGR Regd No: 2710/300\nPAN No: AADAS8034Q\nESIC No: 44000594740000999\nEPF No: ORBBS2612050000",
        'address' => "Plot No- 1468/5398/7693, Bhaktamadhu Nagar,\nGandamunda, Bhubaneswar-751030",
        'gstin' => '21AADAS8034Q1Z6',
        'pan' => 'AADAS8034Q',
        'letterhead_emails' => 'hr.swachha@gmail.com, swachha38@yahoo.com',
        'phones' => '9337219868',
        'email' => 'hr.swachha@gmail.com',
        'phone' => '9337219868',
    ];

    /** Fill empty letterhead fields of the Z Plus agency (code ZPS) and give it the seal from the letterhead. */
    public static function applyZplus(Agency $agency): void
    {
        self::apply($agency, self::ZPS, 'zps-logo.png', 'classic');
    }

    /** Fill empty letterhead fields of SWACHHA (code SWH), give it the sun logo and the banner layout. */
    public static function applySwachha(Agency $agency): void
    {
        self::apply($agency, self::SWH, 'swh-logo.png', 'banner');
    }

    private static function apply(Agency $agency, array $details, string $logo, string $style): void
    {
        $fresh = blank($agency->tagline) && blank($agency->registrations);   // never touched by hand
        foreach ($details as $field => $value) {
            if ($field !== 'name' && blank($agency->{$field})) {
                $agency->{$field} = $value;
            }
        }
        if (blank($agency->logo_path) && is_file($src = database_path('seeders/assets/'.$logo))) {
            Storage::disk('local')->put('letterheads/'.$logo, file_get_contents($src));
            $agency->logo_path = 'letterheads/'.$logo;
        }
        if ($fresh || blank($agency->letterhead_style)) {
            $agency->letterhead_style = $style;
        }
        $agency->save();
    }
}
