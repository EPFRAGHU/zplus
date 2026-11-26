<?php

namespace App\Services\Payroll;

use App\Models\Agency;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * NEFT intimation file for the bank's bulk salary upload (owner, 26 Nov 2026), in the exact layout of the owner's
 * sheet (SBI corporate internet banking): sn, corp id, corp name, corp acct no, product code, benf name, benf acct no,
 * amount, ifsc, remarks, CURRENCY, ADDRESS. One row per employee of the bank letter (same people, same net pay in whole
 * rupees). SBI accounts go as DCR (direct credit, IFSC left blank as in the sample); other banks as NEFT with the IFSC.
 */
final class NeftFile
{
    public const HEAD = ['sn', 'corp id', 'corp name', 'corp acct no', 'product code', 'benf name', 'benf acct no', 'amount', 'ifsc', 'remarks', 'CURRENCY', 'ADDRESS'];

    /** Column widths of the owner's sheet. */
    private const WIDTHS = ['A' => 3, 'B' => 9.14, 'C' => 10.29, 'D' => 12, 'E' => 12.57, 'F' => 22.14, 'G' => 13.71, 'H' => 9.14, 'I' => 9.14, 'J' => 12.86, 'K' => 10.57, 'L' => 9.14];

    /**
     * Rows from a built bank letter (BankLetter::build): SBI and NEFT staff together in Emp ID order, as on the page.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(array $letter): array
    {
        $agency = $letter['agency'];
        $remarks = 'SAL '.strtoupper($letter['month']->format('M Y'));
        $rows = [];
        foreach ($letter['sbi']->concat($letter['neft'])->sortBy(fn ($l) => $l['employee']->emp_code, SORT_NATURAL)->values() as $i => $l) {
            $e = $l['employee'];
            $sbi = str_starts_with(strtoupper((string) $e->ifsc), 'SBIN');
            $rows[] = [
                'sn' => $i + 1,
                'corp_id' => (string) $agency->bank_corp_id,
                'corp_name' => strtoupper((string) $agency->bank_corp_name),
                'corp_acct' => (string) $agency->bank_account_no,
                'product' => $sbi ? 'DCR' : 'NEFT',
                'name' => strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($e->account_holder ?: $e->name)))),
                'account' => preg_replace('/\s+/', '', (string) $e->account_number),
                'amount' => (int) round($l['net'] / 100),   // whole rupees, as on the payout sheet
                'ifsc' => $sbi ? '' : strtoupper((string) $e->ifsc),
                'remarks' => $remarks,
                'currency' => 'INR',
                'address' => strtoupper((string) $agency->bank_address_code),
            ];
        }

        return $rows;
    }

    /** The upload sheet as an .xlsx file (temporary path). */
    public function xlsx(array $rows): string
    {
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet()->setTitle('Sheet1');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        foreach (self::WIDTHS as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        foreach (self::HEAD as $c => $h) {
            $ws->setCellValueExplicit([$c + 1, 1], $h, DataType::TYPE_STRING);
        }
        $ws->getStyle('A1:L1')->getFont()->setBold(true);
        $ws->getStyle('G1:H1')->getFont()->setName('Times New Roman');
        $ws->getStyle('G1')->getNumberFormat()->setFormatCode('@');

        $r = 2;
        foreach ($rows as $row) {
            $ws->setCellValue([1, $r], $row['sn']);
            $this->number($ws, 2, $r, $row['corp_id']);
            $ws->setCellValueExplicit([3, $r], $row['corp_name'], DataType::TYPE_STRING);
            $this->number($ws, 4, $r, $row['corp_acct']);
            $ws->setCellValueExplicit([5, $r], $row['product'], DataType::TYPE_STRING);
            $ws->setCellValueExplicit([6, $r], $row['name'], DataType::TYPE_STRING);
            $this->number($ws, 7, $r, $row['account']);
            $ws->setCellValue([8, $r], $row['amount']);
            if ($row['ifsc'] !== '') {
                $ws->setCellValueExplicit([9, $r], $row['ifsc'], DataType::TYPE_STRING);
            }
            $ws->setCellValueExplicit([10, $r], $row['remarks'], DataType::TYPE_STRING);
            $ws->setCellValueExplicit([11, $r], $row['currency'], DataType::TYPE_STRING);
            $ws->setCellValueExplicit([12, $r], $row['address'], DataType::TYPE_STRING);
            $ws->getRowDimension($r)->setRowHeight(27.75);
            $r++;
        }
        $last = max(1, $r - 1);
        $ws->getStyle("A1:L{$last}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $ws->getStyle("A1:J{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        if ($last > 1) {
            $ws->getStyle("K2:L{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $ws->getStyle("F2:H{$last}")->getFont()->setSize(10);
            $ws->getStyle("F2:F{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $ws->getStyle("G2:G{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $ws->getStyle("L2:L{$last}")->getFont()->setSize(9);
            $ws->getStyle("L2:L{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $ws->getStyle("L2:L{$last}")->getNumberFormat()->setFormatCode('0');
        }
        $file = tempnam(sys_get_temp_dir(), 'neft');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);

        return $file;
    }

    public function fileName(Agency $agency, CarbonImmutable $month, string $ext): string
    {
        return 'neft-file-'.$agency->numberPrefix().'-'.$month->format('Y-m').'.'.$ext;
    }

    /**
     * Digits as a number like the sample (corp id, account numbers), but as text when Excel would spoil them:
     * a leading zero, more than 15 digits, or anything that is not digits.
     */
    private function number($ws, int $col, int $row, string $value): void
    {
        if ($value === '') {
            return;
        }
        if (preg_match('/^[1-9]\d{0,14}$/', $value)) {
            $ws->setCellValueExplicit([$col, $row], $value, DataType::TYPE_NUMERIC);
        } else {
            $ws->setCellValueExplicit([$col, $row], $value, DataType::TYPE_STRING);
        }
    }
}
