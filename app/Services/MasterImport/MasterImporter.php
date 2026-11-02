<?php

namespace App\Services\MasterImport;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Builds the Excel templates for master data and imports filled ones (check first, then save). */
final class MasterImporter
{
    public const ORDER = ['agencies', 'clients', 'contracts', 'projects', 'posts', 'employees'];

    private const MAX_ROWS = 5000;

    /** @return array<string, MasterSheet> in upload order */
    public function sheets(): array
    {
        $all = [new AgencySheet, new ClientSheet, new ContractSheet, new ProjectSheet, new PostSheet, new EmployeeSheet];

        return collect($all)->keyBy(fn (MasterSheet $s) => $s->type())->all();
    }

    public function sheet(string $type): MasterSheet
    {
        return $this->sheets()[$type] ?? throw new InvalidArgumentException("Unknown template {$type}");
    }

    /** The template as a spreadsheet: data sheet (empty, or with current records), instructions and dropdown lists. */
    public function template(string $type, bool $withData = false): Spreadsheet
    {
        $sheet = $this->sheet($type);
        $columns = $sheet->columns();
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('ZPlus Manpower')->setTitle($sheet->title().' template');
        $data = $book->getActiveSheet()->setTitle($sheet->title());
        $last = Coordinate::stringFromColumnIndex(count($columns));
        $maxRow = self::MAX_ROWS + 1;

        foreach ($columns as $i => $c) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $data->setCellValue("{$col}1", $c->label());
            $data->getColumnDimension($col)->setWidth($c->width);
            if ($c->help !== '') {
                $data->getComment("{$col}1")->getText()->createTextRun($c->help);
                $data->getComment("{$col}1")->setWidth('220pt')->setHeight('70pt');
            }
            $range = "{$col}2:{$col}{$maxRow}";
            if ($c->asText) {
                $data->getStyle($range)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            } elseif ($c->type === 'date') {
                $data->getStyle($range)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
            }
        }
        $data->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E23D4E']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        foreach ($columns as $i => $c) {   // required columns a darker red
            if ($c->required) {
                $data->getStyle(Coordinate::stringFromColumnIndex($i + 1).'1')->getFill()->getStartColor()->setRGB('B52838');
            }
        }
        $data->getRowDimension(1)->setRowHeight(32);
        $data->freezePane('A2');
        $data->setAutoFilter("A1:{$last}1");

        // Dropdowns from a hidden Lists sheet (no 255-character limit).
        $lists = $sheet->lists();
        foreach ($columns as $c) {
            if ($c->type === 'list' && ! isset($lists[$c->key])) {
                $lists[$c->key] = array_values(array_map(fn ($k, $l) => is_int($k) ? $l : $k, array_keys($c->options), $c->options));
            }
        }
        if ($lists) {
            $listSheet = $book->createSheet()->setTitle('Lists');
            $n = 0;
            foreach ($columns as $i => $c) {
                $values = array_values(array_filter($lists[$c->key] ?? [], fn ($x) => $x !== null && $x !== ''));
                if (! $values) {
                    continue;
                }
                $lc = Coordinate::stringFromColumnIndex(++$n);
                $listSheet->setCellValue("{$lc}1", $c->header);
                foreach ($values as $r => $value) {
                    $listSheet->setCellValueExplicit($lc.($r + 2), (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $fixed = $c->type === 'list' || $c->type === 'bool';
                $v = new DataValidation;
                $v->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)
                    ->setShowErrorMessage(true)->setErrorStyle($fixed ? DataValidation::STYLE_STOP : DataValidation::STYLE_WARNING)
                    ->setErrorTitle($c->header)->setError($fixed ? 'Choose a value from the list.' : 'This value is not in the app yet. It must be added before this row can be imported.')
                    ->setFormula1("'Lists'!\${$lc}\$2:\${$lc}\$".(count($values) + 1));
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $data->setDataValidation("{$col}2:{$col}{$maxRow}", $v);
            }
            $listSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        }

        if ($withData) {
            $r = 2;
            foreach ($sheet->export() as $record) {
                foreach ($columns as $i => $c) {
                    $value = $c->secret ? null : ($record[$c->key] ?? null);
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $cell = Coordinate::stringFromColumnIndex($i + 1).$r;
                    is_string($value) || $c->asText
                        ? $data->setCellValueExplicit($cell, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING)
                        : $data->setCellValue($cell, $value);
                }
                $r++;
            }
        }

        $this->instructions($book->createSheet(), $sheet);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function instructions(Worksheet $ws, MasterSheet $sheet): void
    {
        $ws->setTitle('Instructions');
        $ws->setCellValue('A1', $sheet->title().' template · ZPlus Manpower');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('E23D4E');
        $ws->setCellValue('A2', $sheet->description());
        $lines = [
            'Fill one row per record on the "'.$sheet->title().'" sheet, starting from row 2. Do not rename the sheet or change the headings.',
            'Columns marked * are required. Hover over a heading to see what to write.',
            'Upload in this order: Agencies → Clients → GeM contracts → Projects → Posts → Employees (each needs the ones before it).',
            'Records already in the app are updated (matched by their code or number). A blank cell keeps what is already stored.',
            'Dates as DD-MM-YYYY. Yes/No columns: write Yes or No.',
            'After uploading, the app shows every row as New, Update, No change or Error. Nothing is saved until you click Save.',
        ];
        foreach ($lines as $i => $line) {
            $ws->setCellValue('A'.($i + 4), '• '.$line);
        }
        $start = count($lines) + 6;
        foreach (['Column', 'Required', 'What to write', 'Example'] as $i => $h) {
            $ws->setCellValue(Coordinate::stringFromColumnIndex($i + 1).$start, $h);
        }
        $ws->getStyle("A{$start}:D{$start}")->applyFromArray(['font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3EFF1']]]);
        foreach ($sheet->columns() as $i => $c) {
            $r = $start + 1 + $i;
            $options = $c->type === 'list' ? ' Choose: '.implode(', ', array_values($c->options)).'.' : ($c->type === 'bool' ? ' Yes or No.' : '');
            $ws->fromArray([$c->header, $c->required ? 'Yes' : '', trim($c->help.$options), $c->example], null, "A{$r}");
            $ws->getCell("D{$r}")->setValueExplicit($c->example, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $ws->getColumnDimension('A')->setWidth(24);
        $ws->getColumnDimension('B')->setWidth(10);
        $ws->getColumnDimension('C')->setWidth(80);
        $ws->getColumnDimension('D')->setWidth(32);
        $ws->getStyle('C:C')->getAlignment()->setWrapText(true);
    }

    /**
     * Read and check every row of an uploaded template.
     *
     * @return array{rows: RowResult[], counts: array<string, int>, problems: string[]}
     */
    public function analyse(string $type, string $path): array
    {
        $sheet = $this->sheet($type);
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $ws = $book->getSheetByName($sheet->title()) ?? $book->getSheet(0);

        // Map headings (with or without the * marker) to columns.
        $byHeader = collect($sheet->columns())->keyBy(fn (Column $c) => $this->norm($c->header));
        $map = [];
        $highest = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        for ($i = 1; $i <= $highest; $i++) {
            $h = $this->norm((string) $ws->getCell([$i, 1])->getValue());
            if (isset($byHeader[$h])) {
                $map[$i] = $byHeader[$h];
            }
        }
        $problems = [];
        $found = collect($map)->map(fn (Column $c) => $c->key)->values()->all();
        foreach ($sheet->columns() as $c) {
            if ($c->required && ! in_array($c->key, $found, true)) {
                $problems[] = "The heading \"{$c->header}\" is missing. Use the {$sheet->title()} template from this page.";
            }
        }
        if ($problems) {
            return ['rows' => [], 'counts' => [], 'problems' => $problems];
        }

        $sheet->prepare();
        $rows = [];
        $lastRow = min($ws->getHighestDataRow(), self::MAX_ROWS + 1);
        for ($r = 2; $r <= $lastRow; $r++) {
            $values = [];
            $empty = true;
            foreach ($map as $i => $c) {
                $value = $ws->getCell([$i, $r])->getValue();
                if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $value = $value->getPlainText();
                }
                if ($value !== null && trim((string) $value) !== '') {
                    $empty = false;
                }
                $values[$c->key] = $value;
            }
            if (! $empty) {
                $rows[] = $sheet->analyse($values, $r);
            }
        }
        if (! $rows) {
            $problems[] = 'The file has no rows to import below the headings.';
        }
        $counts = array_count_values(array_map(fn (RowResult $x) => $x->status, $rows)) + array_fill_keys([RowResult::NEW, RowResult::UPDATE, RowResult::UNCHANGED, RowResult::ERROR], 0);

        return ['rows' => $rows, 'counts' => $counts, 'problems' => $problems];
    }

    /** Save the new and changed rows (rows with errors are skipped). @return array{created: int, updated: int, skipped: int} */
    public function commit(string $type, string $path): array
    {
        $sheet = $this->sheet($type);
        $analysis = $this->analyse($type, $path);

        return DB::transaction(function () use ($sheet, $analysis) {
            $out = ['created' => 0, 'updated' => 0, 'skipped' => 0];
            foreach ($analysis['rows'] as $row) {
                match ($row->status) {
                    RowResult::NEW => [$sheet->save($row), $out['created']++],
                    RowResult::UPDATE => [$sheet->save($row), $out['updated']++],
                    RowResult::ERROR => $out['skipped']++,
                    default => null,
                };
            }

            return $out;
        });
    }

    private function norm(string $header): string
    {
        return mb_strtolower(trim(rtrim(trim($header), '*')));
    }
}
