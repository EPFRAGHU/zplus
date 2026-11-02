<?php

namespace App\Services\MasterImport;

use App\Models\Agency;
use App\Support\Ifsc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class AgencySheet extends MasterSheet
{
    private Collection $agencies;

    public function type(): string
    {
        return 'agencies';
    }

    public function title(): string
    {
        return 'Agencies';
    }

    public function description(): string
    {
        return 'Your agencies with their letterhead, GSTIN and bank details. Upload this first.';
    }

    public function columns(): array
    {
        return [
            new Column('code', 'Agency code', true, help: 'Short code used in bill and letter numbers, e.g. ZPS or SWH. Letters and digits only.', example: 'ZPS', width: 12, identifies: true),
            new Column('name', 'Agency name', true, example: 'M/S Z Plus Surakhya Seva', width: 32),
            new Column('tagline', 'Tagline', example: 'A leading Organization of Manpower & Security Services', width: 30),
            new Column('address', 'Address', example: 'Plot No.-1468/5398, Gandamunda, Bhubaneswar-751030', width: 40),
            new Column('gstin', 'GSTIN', help: '15 characters', example: '21AAAFZ5965B1ZD', asText: true),
            new Column('pan', 'PAN', example: 'AAAFZ5965B', width: 13, asText: true),
            new Column('email', 'E-mail', example: 'office@example.com', width: 26),
            new Column('phone', 'Phone', example: '9437071819', width: 14, asText: true),
            new Column('phones', 'Mobiles on letterhead', help: 'Separate with commas', example: '9437071819, 7008024050', width: 22, asText: true),
            new Column('website', 'Website', example: 'www.example.com', width: 22),
            new Column('letterhead_emails', 'E-mails on letterhead', help: 'Separate with commas', width: 30),
            new Column('registrations', 'Registration lines', help: 'One registration per line (Alt+Enter for a new line). For the SWACHHA layout write "Label: value".', example: 'EPF Registration - OR/14330', width: 40),
            new Column('signatory_name', 'Signatory name', width: 22),
            new Column('signatory_title', 'Signatory title', example: 'Managing Partner', width: 18),
            new Column('bank_name', 'Bank name', example: 'State Bank of India', width: 22),
            new Column('bank_account_no', 'Bank account no.', help: 'Digits only; printed on tax invoices', width: 20, asText: true),
            new Column('bank_ifsc', 'Bank IFSC', example: 'SBIN0001234', width: 14, asText: true),
            new Column('bank_branch', 'Bank branch', width: 20),
            new Column('letterhead_style', 'Letterhead layout', type: 'list', options: ['classic' => 'Classic', 'banner' => 'Banner'],
                help: 'Classic = centred with footer (Z Plus). Banner = logo, registrations column and address box (SWACHHA).', example: 'classic', width: 16),
        ];
    }

    public function lists(): array
    {
        return ['letterhead_style' => ['classic', 'banner']];
    }

    public function prepare(): void
    {
        parent::prepare();
        $this->agencies = Agency::query()->get()->keyBy(fn ($a) => strtoupper($a->code));
    }

    protected function check(array $v, int $row): RowResult
    {
        $code = strtoupper((string) $v['code']);
        $v['code'] = $code ?: null;
        $v['gstin'] = $v['gstin'] ? strtoupper($v['gstin']) : null;
        $v['pan'] = $v['pan'] ? strtoupper($v['pan']) : null;
        $v['bank_ifsc'] = Ifsc::normalize($v['bank_ifsc']);
        $errors = $this->validate($v, [
            'code' => ['nullable', 'alpha_num', 'max:10'],
            'name' => ['nullable', 'max:255'],
            'email' => ['nullable', 'email'],
            'gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}\d{4}[A-Z]$/'],
            'bank_account_no' => ['nullable', 'regex:/^\d{6,20}$/'],
        ], ['gstin.regex' => 'GSTIN must have 15 characters, e.g. 21AAAFZ5965B1ZD.', 'pan.regex' => 'PAN must look like ABCDE1234F.',
            'bank_account_no.regex' => 'Bank account no. must be 6 to 20 digits.']);
        if ($v['bank_ifsc'] && ! Ifsc::isValid($v['bank_ifsc'])) {
            $errors[] = 'Bank IFSC must have 11 characters: 4 letters, a zero, then 6 letters or digits.';
        }
        $data = collect($v)->only(['code', 'name', 'tagline', 'address', 'gstin', 'pan', 'email', 'phone', 'phones', 'website', 'letterhead_emails',
            'registrations', 'signatory_name', 'signatory_title', 'bank_name', 'bank_account_no', 'bank_ifsc', 'bank_branch', 'letterhead_style'])->all();

        return $this->result($row, $code, trim("{$code} · ".($v['name'] ?? '')), $data, $this->agencies[$code] ?? null, $errors);
    }

    protected function newModel(): Model
    {
        return new Agency(['letterhead_style' => 'classic']);
    }

    public function export(): iterable
    {
        foreach (Agency::query()->orderBy('code')->get() as $a) {
            yield $a->only(['code', 'name', 'tagline', 'address', 'gstin', 'pan', 'email', 'phone', 'phones', 'website', 'letterhead_emails',
                'registrations', 'signatory_name', 'signatory_title', 'bank_name', 'bank_account_no', 'bank_ifsc', 'bank_branch', 'letterhead_style']);
        }
    }
}
