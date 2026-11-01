<?php

namespace App\Services\Import;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Employee;
use App\Models\GemContract;
use App\Support\Ifsc;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads the GEM PROJECT DETAILS workbook:
 *  - agency sheets (SWACHHA, ZPLUS): blocks headed "GEM BID NO" listing contract, PI, project, posts, candidates
 *  - PI sheets: blocks headed "NAME OF THE EMPLOYER" listing staff with salary and bank details
 * Staff on PI sheets are matched to posts by the candidate names on the agency sheets.
 */
final class GemWorkbookImporter
{
    private const AGENCIES = [
        'SWACHHA' => ['SWACHHA', 'SWH'],
        'ZPLUS' => ['M/S Z Plus Surakhya Seva', 'ZPS'],
    ];

    private const BANKS = [
        'SBIN' => 'State Bank of India', 'CNRB' => 'Canara Bank', 'PUNB' => 'Punjab National Bank', 'UBIN' => 'Union Bank of India',
        'BKID' => 'Bank of India', 'ICIC' => 'ICICI Bank', 'HDFC' => 'HDFC Bank', 'UTIB' => 'Axis Bank', 'IDIB' => 'Indian Bank',
        'UCBA' => 'UCO Bank', 'KKBK' => 'Kotak Mahindra Bank', 'BARB' => 'Bank of Baroda', 'IOBA' => 'Indian Overseas Bank',
    ];

    public function __construct(private readonly int $bidYear = 2026) {}

    public function analyse(string $path): ImportPlan
    {
        $book = IOFactory::load($path);
        $plan = new ImportPlan;

        foreach ($book->getAllSheets() as $sheet) {
            if ($this->findHeaderRows($sheet, 'gem bid no')) {
                $this->readAgencySheet($sheet, $plan);
            }
        }
        foreach ($book->getAllSheets() as $sheet) {
            if ($this->findHeaderRows($sheet, 'name of the employer')) {
                $this->readPiSheet($sheet, $plan);
            }
        }
        $this->matchStaffToPosts($plan);
        $this->collectIssues($plan);

        return $plan;
    }

    public function commit(ImportPlan $plan, Client $client): array
    {
        $created = ['contracts' => 0, 'posts' => 0, 'employees' => 0, 'skipped_contracts' => 0];

        DB::transaction(function () use ($plan, $client, &$created) {
            $postIds = [];   // plan post key => Post id
            foreach ($plan->contracts as $c) {
                if (GemContract::query()->where('contract_no', $c['contract_no'])->exists()) {
                    $created['skipped_contracts']++;

                    continue;
                }
                [$agencyName, $agencyCode] = $c['agency'];
                $agency = Agency::query()->firstOrCreate(['code' => $agencyCode], ['name' => $agencyName]);
                $contract = GemContract::query()->create([
                    'agency_id' => $agency->id, 'client_id' => $client->id,
                    'bid_no' => $c['bid_no'], 'contract_no' => $c['contract_no'], 'billing_type' => $c['billing_type'],
                ]);
                $created['contracts']++;
                foreach ($c['projects'] as $p) {
                    $project = $contract->projects()->create([
                        'name' => $p['name'], 'code' => $p['code'], 'title' => $p['name'], 'pi_name' => $p['pi'],
                    ]);
                    foreach ($p['posts'] as $post) {
                        $postIds[$post['key']] = $project->posts()->create([
                            'designation' => $post['designation'], 'sanctioned_qty' => $post['qty'], 'rate' => $post['rate'],
                        ])->id;
                        $created['posts']++;
                    }
                }
            }

            foreach ($plan->staff as $s) {
                $postId = $s['post_key'] !== null ? ($postIds[$s['post_key']] ?? null) : null;
                if ($s['post_key'] !== null && $postId === null) {
                    continue;   // its contract already existed, so it was imported before
                }
                Employee::query()->create([
                    'emp_code' => Employee::nextCode(),
                    'post_id' => $postId,
                    'name' => $s['name'],
                    'status' => 'active',
                    'rate_override' => $s['rate_override'],
                    'bank_name' => $s['bank_name'],
                    'account_holder' => $s['account'] ? strtoupper($s['name']) : null,
                    'account_number' => $s['account'],
                    'ifsc' => Ifsc::isValid($s['ifsc']) ? $s['ifsc'] : null,
                ]);
                $created['employees']++;
            }
        });

        return $created;
    }

    // ---------------------------------------------------------------- agency sheets

    private function readAgencySheet(Worksheet $sheet, ImportPlan $plan): void
    {
        $agency = self::AGENCIES[strtoupper(trim($sheet->getTitle()))]
            ?? [trim($sheet->getTitle()), strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $sheet->getTitle()), 0, 3))];
        $rows = $sheet->toArray(null, false, false, false);

        foreach ($this->findHeaderRows($sheet, 'gem bid no') as $h) {
            $cols = $this->mapColumns($rows[$h], [
                'bid' => 'gem bid no', 'contract' => 'gem contct no', 'pi' => 'name of the pi', 'project' => 'name of the project',
                'post' => 'post', 'qty' => 'quatity', 'rate' => ['per head', 'rate per day'], 'filled' => 'quantity',
                'names' => 'name of the candidates', 'total' => 'total',
            ]);
            $daily = str_contains($this->norm($rows[$h][$cols['rate']] ?? ''), 'per day');
            $namesCol = $cols['names'] >= 0 ? $cols['names'] : ($cols['total'] >= 0 ? $cols['total'] + 1 : null);

            $contract = null;
            $pi = $project = null;
            for ($r = $h + 1; $r < count($rows); $r++) {
                $row = $rows[$r];
                if ($this->isHeader($row, 'gem bid no')) {
                    break;
                }
                $label = $this->norm($row[$cols['project']] ?? '');
                if (Str::startsWith($label, ['service charge', 'sub total', 'gst', 'total'])) {
                    continue;
                }
                $bid = $this->scalar($row[$cols['bid']] ?? null);
                $gemc = $this->scalar($row[$cols['contract']] ?? null);
                if ($bid !== null && $gemc !== null) {
                    $key = 'GEMC-'.$gemc;
                    $plan->contracts[$key] ??= [
                        'agency' => $agency, 'sheet' => $sheet->getTitle(),
                        'bid_no' => str_contains($bid, '/') ? $bid : "GEM/{$this->bidYear}/B/{$bid}",
                        'contract_no' => $key, 'billing_type' => $daily ? 'daily' : 'monthly', 'projects' => [],
                    ];
                    $contract = $key;
                }
                $post = trim((string) $this->scalar($row[$cols['post']] ?? null));
                $rate = $row[$cols['rate']] ?? null;
                if ($contract === null || $post === '' || ! is_numeric($rate)) {
                    if ($contract !== null && $namesCol !== null && ($extra = $this->scalar($row[$namesCol] ?? null)) && $post === '') {
                        $this->appendCandidates($plan, $contract, $project, $extra);   // a name spilled onto the next row
                    }

                    continue;
                }
                $pi = $this->scalar($row[$cols['pi']] ?? null) ?? $pi;
                $projectName = $this->scalar($row[$cols['project']] ?? null) ?? $project;
                $project = trim(preg_replace('/\s+/', ' ', $projectName));
                $plan->contracts[$contract]['projects'][$project] ??= [
                    'name' => $project, 'code' => $this->code($project), 'pi' => $pi ? trim(preg_replace('/\s+/', ' ', $pi)) : null, 'posts' => [],
                ];
                $postKey = $contract.'|'.$project.'|'.count($plan->contracts[$contract]['projects'][$project]['posts']);
                $plan->contracts[$contract]['projects'][$project]['posts'][] = [
                    'key' => $postKey, 'designation' => $post, 'qty' => (int) ($row[$cols['qty']] ?? 1),
                    'rate' => number_format((float) $rate, 2, '.', ''),
                    'filled' => $cols['filled'] >= 0 && is_numeric($row[$cols['filled']] ?? null) ? (int) $row[$cols['filled']] : null,
                    'candidates' => [],
                ];
                if ($namesCol !== null && ($names = $this->scalar($row[$namesCol] ?? null))) {
                    $this->appendCandidates($plan, $contract, $project, $names);
                }
            }
        }
    }

    private function appendCandidates(ImportPlan $plan, string $contract, ?string $project, string $names): void
    {
        if ($project === null || empty($plan->contracts[$contract]['projects'][$project]['posts'])) {
            return;
        }
        $posts = &$plan->contracts[$contract]['projects'][$project]['posts'];
        $last = array_key_last($posts);
        foreach (preg_split('/[\r\n,]+/', $names) as $n) {
            $n = trim(preg_replace('/\s+/', ' ', $n));
            if ($n !== '' && ! is_numeric($n)) {
                $offline = (bool) preg_match('/\(\s*off\s*line\s*\)/i', $n);
                $posts[$last]['candidates'][] = ['name' => trim(preg_replace('/\(.*?\)/', '', $n)), 'offline' => $offline];
            }
        }
    }

    // ---------------------------------------------------------------- PI sheets

    private function readPiSheet(Worksheet $sheet, ImportPlan $plan): void
    {
        $rows = $sheet->toArray(null, false, false, false);
        foreach ($this->findHeaderRows($sheet, 'name of the employer') as $h) {
            $cols = $this->mapColumns($rows[$h], [
                'name' => 'name of the employer', 'designation' => ['desiganation', 'designation'], 'salary' => 'salary',
                'sbi' => 'sbi', 'other' => 'other bank', 'ifsc' => ['ifsc code', 'ifsc'],
            ]);
            for ($r = $h + 1; $r < count($rows); $r++) {
                $row = $rows[$r];
                if ($this->isHeader($row, 'name of the employer') || Str::startsWith($this->norm($row[0] ?? ''), 'name of the p')) {
                    break;
                }
                $name = $this->scalar($row[$cols['name']] ?? null);
                if ($name === null) {
                    continue;
                }
                $sbi = $this->scalar($row[$cols['sbi']] ?? null);
                $other = $this->scalar($row[$cols['other']] ?? null);
                $ifsc = Ifsc::normalize($this->scalar($row[$cols['ifsc']] ?? null));
                $salary = $row[$cols['salary']] ?? null;
                $plan->staff[] = [
                    'name' => trim(preg_replace('/\s+/', ' ', $name)),
                    'sheet' => $sheet->getTitle(), 'row' => $r + 1,
                    'designation' => $this->scalar($row[$cols['designation']] ?? null),
                    'salary' => is_numeric($salary) ? number_format((float) $salary, 2, '.', '') : null,
                    'account' => $sbi ?? $other,
                    'bank_name' => $sbi ? self::BANKS['SBIN'] : ($ifsc ? (self::BANKS[substr($ifsc, 0, 4)] ?? null) : null),
                    'is_sbi' => $sbi !== null,
                    'ifsc' => $ifsc,
                    'post_key' => null, 'rate_override' => null,
                ];
            }
        }
    }

    // ---------------------------------------------------------------- matching and checks

    private function matchStaffToPosts(ImportPlan $plan): void
    {
        $candidates = [];   // nameKey => ['post' => post, 'name' => listed name]
        foreach ($plan->posts() as $post) {
            foreach ($post['candidates'] as $c) {
                $candidates[$this->nameKey($c['name'])] ??= ['post' => $post, 'name' => $c['name']];
            }
        }
        $used = [];
        $link = function (int $i, string $key) use ($plan, $candidates, &$used) {
            $post = $candidates[$key]['post'];
            $plan->staff[$i]['post_key'] = $post['key'];
            $used[$key] = true;
            $salary = $plan->staff[$i]['salary'];
            if ($salary !== null && $salary !== $post['rate']) {
                $plan->staff[$i]['rate_override'] = $salary;
            }
        };

        // 1. exact name match
        foreach ($plan->staff as $i => $s) {
            $key = $this->nameKey($s['name']);
            if (isset($candidates[$key]) && ! isset($used[$key])) {
                $link($i, $key);
            }
        }
        // 2. close spelling (e.g. "Prashantajyoti" / "Prasantajyoti"): flagged for the user to confirm
        foreach ($plan->staff as $i => $s) {
            if ($plan->staff[$i]['post_key'] !== null) {
                continue;
            }
            $key = $this->nameKey($s['name']);
            $best = null;
            $bestDist = PHP_INT_MAX;
            foreach ($candidates as $cKey => $c) {
                if (isset($used[$cKey])) {
                    continue;
                }
                $d = levenshtein($key, $cKey);
                if ($d < $bestDist) {
                    [$best, $bestDist] = [$cKey, $d];
                }
            }
            if ($best !== null && $bestDist <= max(2, intdiv(strlen($key), 6))) {
                $link($i, $best);
                $plan->staff[$i]['fuzzy_match'] = $candidates[$best]['name'];
            }
        }
        // 3. candidates listed on a post but missing from every PI sheet: import without bank details
        foreach ($candidates as $cKey => $c) {
            if (! isset($used[$cKey])) {
                $plan->staff[] = ['name' => $c['name'], 'sheet' => null, 'row' => null, 'designation' => $c['post']['designation'],
                    'salary' => null, 'account' => null, 'bank_name' => null, 'is_sbi' => false, 'ifsc' => null,
                    'post_key' => $c['post']['key'], 'rate_override' => null, 'no_pi_row' => true];
            }
        }
    }

    private function collectIssues(ImportPlan $plan): void
    {
        $where = fn (array $s) => $s['sheet'] ? "{$s['sheet']}, row {$s['row']}" : 'agency sheet';
        $bad = $missingSbi = $missingOther = $noSalary = $diff = $unmatched = $noBank = $fuzzy = [];
        $posts = [];
        foreach ($plan->posts() as $p) {
            $posts[$p['key']] = $p;
        }
        foreach ($plan->staff as $s) {
            $who = "{$s['name']} ({$where($s)})";
            if ($s['ifsc'] !== null && ! Ifsc::isValid($s['ifsc'])) {
                $bad[] = "{$who}: “{$s['ifsc']}”";
            } elseif ($s['account'] && $s['ifsc'] === null) {
                $s['is_sbi'] ? $missingSbi[] = $who : $missingOther[] = $who;
            }
            if (! empty($s['no_pi_row'])) {
                $noBank[] = $s['name'];
            } elseif ($s['salary'] === null) {
                $noSalary[] = $who;
            }
            if ($s['rate_override'] !== null) {
                $diff[] = "{$who}: PI sheet ₹".number_format((float) $s['salary']).', contract ₹'.number_format((float) $posts[$s['post_key']]['rate']);
            }
            if ($s['post_key'] === null) {
                $unmatched[] = $who;
            }
            if (! empty($s['fuzzy_match'])) {
                $fuzzy[] = "{$who} matched to “{$s['fuzzy_match']}” on the agency sheet";
            }
        }
        $over = $vacant = [];
        foreach ($plan->posts() as $p) {
            $listed = count($p['candidates']);
            $label = explode('|', $p['key'])[1].' · '.$p['designation'];
            if ($listed > $p['qty']) {
                $over[] = "{$label}: {$p['qty']} sanctioned, {$listed} names";
            }
            $filled = $p['filled'] ?? $listed;
            if ($filled < $p['qty']) {
                $vacant[] = "{$label}: ".($p['qty'] - $filled).' vacant';
            }
        }

        $plan->issue('bad', count($bad).' invalid IFSC code(s). An IFSC has 11 characters.', $bad);
        $plan->issue('bad', count($missingSbi).' SBI account(s) without IFSC. Cashfree needs the branch IFSC.', $missingSbi);
        $plan->issue('bad', count($missingOther).' other-bank account(s) without IFSC.', $missingOther);
        $plan->issue('warn', count($noSalary).' staff without salary on the PI sheet. The post rate will be used.', $noSalary);
        $plan->issue('warn', count($diff).' salary(ies) differ from the contract rate. The PI sheet salary will be used.', $diff);
        $plan->issue('warn', count($over).' post(s) with more names than sanctioned.', $over);
        $plan->issue('warn', count($fuzzy).' name(s) matched by close spelling. Check each pair is the same person.', $fuzzy);
        $plan->issue('warn', count($unmatched).' staff on PI sheets not found on any agency sheet. They are imported without a post.', $unmatched);
        $plan->issue('info', count($noBank).' candidate(s) on agency sheets with no PI-sheet row (no bank details yet).', $noBank);
        $plan->issue('info', count($vacant).' post(s) with vacancies.', $vacant);
    }

    // ---------------------------------------------------------------- helpers

    /** @return int[] zero-based row indexes */
    private function findHeaderRows(Worksheet $sheet, string $needle): array
    {
        $found = [];
        foreach ($sheet->toArray(null, false, false, false) as $i => $row) {
            if ($this->isHeader($row, $needle)) {
                $found[] = $i;
            }
        }

        return $found;
    }

    private function isHeader(array $row, string $needle): bool
    {
        foreach ($row as $cell) {
            if (is_string($cell) && $this->norm($cell) === $needle) {
                return true;
            }
        }

        return false;
    }

    /** Map logical names to column indexes by header text; first match wins, "quantity" must be exact. */
    private function mapColumns(array $header, array $wanted): array
    {
        $cols = [];
        foreach ($wanted as $key => $needles) {
            foreach ((array) $needles as $needle) {
                foreach ($header as $i => $cell) {
                    $text = $this->norm((string) $cell);
                    if ($text !== '' && ($text === $needle || (strlen($needle) > 4 && str_starts_with($text, $needle)))) {
                        $cols[$key] ??= $i;
                    }
                }
            }
        }
        foreach (array_keys($wanted) as $key) {
            $cols[$key] ??= -1;   // missing column: lookups read null
        }

        return $cols;
    }

    private function norm(mixed $v): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $v)));
    }

    private function scalar(mixed $v): ?string
    {
        if ($v === null || (is_string($v) && (trim($v) === '' || str_starts_with($v, '=')))) {
            return null;
        }
        if (is_float($v) && floor($v) === $v) {
            return number_format($v, 0, '', '');
        }

        return trim((string) $v);
    }

    private function nameKey(string $name): string
    {
        $n = strtolower($name);
        $n = preg_replace('/\b(mr|mrs|ms|miss|dr|md)\b\.?/', ' ', $n);

        return preg_replace('/[^a-z]/', '', $n);
    }

    private function code(string $project): string
    {
        $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $project));

        return substr($c, 0, 10) ?: 'PRJ';
    }
}
