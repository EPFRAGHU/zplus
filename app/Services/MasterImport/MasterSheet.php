<?php

namespace App\Services\MasterImport;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * One master-data template (Agencies, Clients, ...): its columns, how a row is checked against the live data,
 * and how it is saved. Rows are matched to existing records by a natural key (agency code, contract no, ...);
 * on an existing record a blank cell keeps the stored value.
 */
abstract class MasterSheet
{
    /** Keys seen earlier in the same file, to catch duplicates. */
    protected array $seen = [];

    /** Required columns left blank in the row being checked. @var Column[] */
    protected array $missing = [];

    abstract public function type(): string;

    abstract public function title(): string;

    /** One sentence for the upload page: what the template holds and what must exist first. */
    abstract public function description(): string;

    /** @return Column[] */
    abstract public function columns(): array;

    /** Check one row (values keyed by column key, already cleaned). */
    abstract protected function check(array $v, int $row): RowResult;

    /** @return iterable<array<string, mixed>> current records as rows, for "download with current data" */
    abstract public function export(): iterable;

    /** Live dropdown lists this template uses: name => values. */
    public function lists(): array
    {
        return [];
    }

    /** Load lookups once before checking rows. */
    public function prepare(): void
    {
        $this->seen = [];
    }

    public function analyse(array $v, int $row): RowResult
    {
        $errors = [];
        $this->missing = array_filter($this->columns(), fn (Column $c) => $c->required && trim((string) ($v[$c->key] ?? '')) === '');
        foreach ($this->columns() as $c) {
            $raw = $v[$c->key] ?? null;
            if ($raw === null || $raw === '') {
                $v[$c->key] = null;

                continue;
            }
            try {
                $v[$c->key] = $this->cast($c, $raw);
            } catch (Throwable $e) {
                $errors[] = "{$c->header}: {$e->getMessage()}";
                $v[$c->key] = null;
            }
        }
        $result = $this->check($v, $row);
        if ($errors) {
            $result->errors = array_merge($errors, $result->errors);
            $result->status = RowResult::ERROR;
        }

        return $result;
    }

    /** Save a checked row. */
    public function save(RowResult $r): Model
    {
        $model = $r->model ?? $this->newModel();
        $model->fill(array_filter($r->data, fn ($value) => $value !== null))->save();   // blank cells keep stored values / defaults

        return $model;
    }

    abstract protected function newModel(): Model;

    /** Turn a cell value into a clean PHP value for its column type. */
    protected function cast(Column $c, mixed $raw): mixed
    {
        if (is_string($raw)) {
            $raw = trim(str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $raw));
        }
        if ($raw === '') {
            return null;
        }

        return match ($c->type) {
            'date' => $this->date($raw),
            'bool' => $this->bool($raw),
            'number' => $this->number($raw),
            'list' => $this->choice($c, $raw),
            default => is_float($raw) && floor($raw) == $raw ? sprintf('%.0f', $raw) : (string) $raw,
        };
    }

    protected function date(mixed $raw): string
    {
        if (is_numeric($raw)) {
            return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $raw))->toDateString();
        }
        foreach (['d-m-Y', 'd/m/Y', 'd.m.Y', 'Y-m-d', 'd-M-Y', 'd M Y'] as $f) {
            try {
                $d = CarbonImmutable::createFromFormat('!'.$f, (string) $raw);
                if ($d && $d->format($f) === (string) $raw) {
                    return $d->toDateString();
                }
            } catch (Throwable) {
            }
        }
        throw new \InvalidArgumentException("\"{$raw}\" is not a date; use DD-MM-YYYY");
    }

    protected function bool(mixed $raw): bool
    {
        $s = strtolower(trim((string) $raw));
        if (in_array($s, ['yes', 'y', '1', 'true', 'on'], true)) {
            return true;
        }
        if (in_array($s, ['no', 'n', '0', 'false', 'off'], true)) {
            return false;
        }
        throw new \InvalidArgumentException("write Yes or No, not \"{$raw}\"");
    }

    protected function number(mixed $raw): string
    {
        $s = str_replace([',', '₹', ' '], '', (string) $raw);
        if (! is_numeric($s)) {
            throw new \InvalidArgumentException("\"{$raw}\" is not a number");
        }

        return (string) (0 + $s);
    }

    protected function choice(Column $c, mixed $raw): string
    {
        $options = is_array($c->options) ? $c->options : [];
        foreach ($options as $value => $label) {
            $value = is_int($value) ? $label : $value;
            if (strcasecmp((string) $raw, (string) $value) === 0 || strcasecmp((string) $raw, (string) $label) === 0) {
                return (string) $value;
            }
        }
        throw new \InvalidArgumentException("choose one of: ".implode(', ', array_map(fn ($k, $l) => is_int($k) ? $l : $k, array_keys($options), $options)));
    }

    /** Required cells and the record's own field rules; returns error messages. */
    protected function validate(array $v, array $rules, array $messages = []): array
    {
        $errors = [];
        $headers = collect($this->columns())->mapWithKeys(fn (Column $c) => [$c->key => $c->header])->all();
        $validator = Validator::make($v, $rules, $messages, $headers);

        return array_merge($errors, $validator->errors()->all());
    }

    /** New or update (or unchanged), comparing with the existing record; blank cells keep stored values. */
    protected function result(int $row, string $key, string $label, array $data, ?Model $existing, array $errors): RowResult
    {
        // Required cells: all of them for a new record; only the identifying ones when updating (blank = keep).
        $missing = array_filter($this->missing, fn (Column $c) => ! $existing || $c->identifies);
        $errors = array_merge(array_map(fn (Column $c) => "{$c->header} is required.", array_values($missing)), $errors);
        if (isset($this->seen[$key])) {
            $errors[] = "Same record as row {$this->seen[$key]} of this file; keep only one row for it.";
        } else {
            $this->seen[$key] = $row;
        }
        if ($errors) {
            return new RowResult($row, RowResult::ERROR, $label, errors: $errors);
        }
        if (! $existing) {
            return new RowResult($row, RowResult::NEW, $label, $data);
        }
        $data = array_filter($data, fn ($value) => $value !== null);
        $probe = (clone $existing)->fill($data);
        $changes = array_keys($probe->getDirty());

        return new RowResult($row, $changes ? RowResult::UPDATE : RowResult::UNCHANGED, $label, $data, $existing, changes: $changes);
    }

    protected static function yesNo(?bool $v): string
    {
        return $v ? 'Yes' : 'No';
    }

    protected static function dmy(mixed $date): string
    {
        return $date ? CarbonImmutable::parse($date)->format('d-m-Y') : '';
    }
}
