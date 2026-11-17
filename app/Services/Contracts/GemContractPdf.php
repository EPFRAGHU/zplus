<?php

namespace App\Services\Contracts;

use Carbon\CarbonImmutable;
use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * Reads a GeM "Manpower Outsourcing Services - Fixed Remuneration" contract PDF (owner, 17 Nov 2026): contract no.,
 * bid no., dates, total value and every post (designation, basic monthly pay, number of resources, service charge %).
 * Nothing is saved here: the work order page shows the result for the operator to check before posts are added.
 */
final class GemContractPdf
{
    /** @return array{contract_no: ?string, bid_no: ?string, contract_date: ?string, start_date: ?string, end_date: ?string, contract_value: ?string, seller: ?string, posts: list<array>, check: array} */
    public function read(string $path): array
    {
        try {
            $text = (new Parser)->parseFile($path)->getText();
        } catch (\Throwable $e) {
            throw new RuntimeException('Could not read this PDF. Upload the contract PDF downloaded from GeM.');
        }

        return $this->parse($text);
    }

    /** Parses the text of the PDF (public for tests). */
    public function parse(string $text): array
    {
        // Keep only printable ASCII (the Hindi labels come out as junk) and put everything on one line.
        $flat = preg_replace('/[^\x20-\x7E]+/', ' ', str_replace(['<>', "\u{2013}", "\u{2014}", "\u{2012}"], [' ', '-', '-', '-'], $text));
        $flat = trim(preg_replace('/\s+/', ' ', $flat));

        $find = fn (string $re) => preg_match($re, $flat, $m) ? trim($m[1]) : null;
        $date = fn (?string $v) => $v && ($d = CarbonImmutable::createFromFormat('!d-M-Y', $v)) ? $d->toDateString() : null;

        $result = [
            'contract_no' => $find('/Contract No:\s*(GEMC-\d+)/'),
            'bid_no' => $find('#Bid/RA/PBP No\.:\s*(GEM/\d{4}/[A-Z]/\d+)#'),
            'contract_date' => $date($find('/Contract Generated Date\s*:\s*(\d{2}-[A-Za-z]{3}-\d{4})/')),
            'start_date' => $date($find('/Service Start Date \(latest by\):\s*(\d{2}-[A-Za-z]{3}-\d{4})/')),
            'end_date' => $date($find('/Service End Date\s*:\s*(\d{2}-[A-Za-z]{3}-\d{4})/')),
            'contract_value' => $find('/Total Contract Value Including All Duties and Taxes\(INR\)\s*([\d.]+)/'),
            'seller' => ($seller = $find('/Company Name\s*:\s*([^|]+?)\s*\|/')) ? preg_replace('/\s+[A-Za-z]$/', '', $seller) : null,
            'posts' => [],
        ];

        // Each post ends with its "Additional Details  Designation : <name>": the text between two of them is one post (inside a
        // post the PDF text is not always in page order, so labels are searched). The buyer's and paying authority's
        // "Designation" come before the first post ("Type of Function") and are skipped.
        $body = preg_split('/Amount of Contract|Total Contract Value Including/', $flat)[0];
        $first = strpos($body, 'Type of Function');
        preg_match_all('/Designation\s*:\s*([^`|]+?)\s*(?=`|\||Title for Optional|$)/', $body, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $marks = array_values(array_filter($found, fn ($m) => $first !== false && $m[0][1] > $first));
        $from = 0;
        foreach ($marks as $mark) {
            $post = $this->post(substr($body, $from, $mark[0][1] - $from), trim($mark[1][0], " `"));
            $from = $mark[0][1] + strlen($mark[0][0]);
            if ($post) {
                $result['posts'][] = $post;
            }
        }
        $result['check'] = $this->check($result);

        return $result;
    }

    private function post(string $b, string $designation): ?array
    {
        $num = fn (string $re) => preg_match($re, $b, $m) ? $m[1] : null;
        $basic = $num('/Basic monthly pay \(INR\) exclusive of GST\s+(\d+(?:\.\d+)?)/');
        if ($basic === null) {
            return null;
        }
        $extras = 0.0;
        foreach (['Bonus', 'EDLI', 'EPF Admin Charges', 'Optional Allowances 1', 'Optional Allowances 2', 'Optional Allowances 3', 'Provident Fund', 'ESI'] as $label) {
            $extras += (float) ($num('/'.preg_quote($label, '/').' \(INR Monthly\)\s+(\d+(?:\.\d+)?)/') ?? 0);
        }
        $months = $num('/Tenure\/ Duration of Employment \(In Months\)\s+(\d+)(?!\s*\*)/');
        // "Number of Resources to be hired" and "Percentage of Service charge" print as e.g. "3 3.85" somewhere in the post.
        preg_match('/(?<![\d.])(\d{1,4})\s+(\d{1,2}\.\d{1,4})(?![\d.])/', $b, $q);
        $profile = trim((string) $num('/List of Profiles\s+(.+?)\s+Educational Qualification/'));

        return [
            'designation' => $designation !== '' ? $designation : $profile,
            'profile' => $profile,
            'rate' => $basic,
            'qty' => isset($q[1]) ? (int) $q[1] : null,
            'service_charge' => $q[2] ?? null,
            'months' => $months ? (int) $months : null,
            'extras' => $extras,   // PF / ESI / EDLI / bonus / allowances printed in the contract (0 in the ICMR contracts)
        ];
    }

    /** Works the GeM formula back from the posts and compares with the printed total: proves nothing was misread. */
    private function check(array $r): array
    {
        $total = 0.0;
        $missing = false;
        foreach ($r['posts'] as $p) {
            if (! $p['qty'] || ! $p['service_charge']) {
                $missing = true;

                continue;
            }
            $m = (float) $p['rate'] + $p['extras'];
            $total += ($m * 1.18 + $m * (float) $p['service_charge'] / 100) * ($p['months'] ?: 12) * $p['qty'];
        }
        $printed = $r['contract_value'] !== null ? (float) $r['contract_value'] : null;

        return ['worked_out' => round($total, 2), 'printed' => $printed,
            'matches' => ! $missing && $printed !== null && abs($total - $printed) < 1.0, 'missing' => $missing];
    }
}
