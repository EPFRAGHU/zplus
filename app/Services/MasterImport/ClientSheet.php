<?php

namespace App\Services\MasterImport;

use App\Models\Agency;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ClientSheet extends MasterSheet
{
    private Collection $agencies;

    private Collection $clients;

    public function type(): string
    {
        return 'clients';
    }

    public function title(): string
    {
        return 'Clients';
    }

    public function description(): string
    {
        return 'Clients (e.g. ICMR-NIHR) under each agency. A client served by two agencies gets one row per agency. Needs the agencies first.';
    }

    public function columns(): array
    {
        return [
            new Column('agency_code', 'Agency code', true, 'text', null, 'Must already be in the app (Agencies).', 'ZPS', 12, identifies: true),
            new Column('short_code', 'Client code', true, help: 'Short code used in bill numbers, e.g. ICMR', example: 'ICMR', width: 12, identifies: true),
            new Column('name', 'Client name', true, example: 'ICMR-NIHR (RMRCBB), Bhubaneswar', width: 34),
            new Column('letter_code', 'Letter code', help: 'Used in appointment letter numbers, e.g. NIHR', example: 'NIHR', width: 12),
            new Column('addressee', 'Addressee', example: 'The Administrative Officer', width: 26),
            new Column('billing_address', 'Billing address', example: 'ICMR, RMRCBB, Department of Health Research', width: 40),
            new Column('contact_person', 'Contact person', width: 20),
            new Column('phone', 'Phone', width: 14, asText: true),
            new Column('email', 'E-mail', width: 24),
            new Column('district', 'District', example: 'Khordha', width: 14),
            new Column('state_code', 'State code', help: '2-digit GST state code; Odisha = 21. Blank = 21.', example: '21', width: 10, asText: true),
            new Column('gstin', 'GSTIN', width: 18, asText: true),
            new Column('payment_term_days', 'Payment days', type: 'number', help: 'Days allowed for payment. Blank = 30.', example: '30', width: 12),
            new Column('status', 'Status', type: 'list', options: ['active' => 'Active', 'hold' => 'Hold', 'inactive' => 'Inactive'], help: 'Blank = Active', example: 'active', width: 11),
        ];
    }

    public function lists(): array
    {
        return ['agency_code' => Agency::query()->orderBy('code')->pluck('code')->all(), 'status' => ['active', 'hold', 'inactive']];
    }

    public function prepare(): void
    {
        parent::prepare();
        $this->agencies = Agency::query()->get()->keyBy(fn ($a) => strtoupper($a->code));
        $this->clients = Client::query()->get()->keyBy(fn ($c) => $c->agency_id.'|'.strtoupper($c->short_code));
    }

    protected function check(array $v, int $row): RowResult
    {
        $agencyCode = strtoupper((string) $v['agency_code']);
        $code = strtoupper((string) $v['short_code']);
        $v['gstin'] = $v['gstin'] ? strtoupper($v['gstin']) : null;
        $errors = $this->validate($v, [
            'short_code' => ['nullable', 'alpha_num', 'max:12'],
            'letter_code' => ['nullable', 'alpha_num', 'max:12'],
            'email' => ['nullable', 'email'],
            'state_code' => ['nullable', 'digits:2'],
            'gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/'],
            'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ], ['gstin.regex' => 'GSTIN must have 15 characters.']);
        $agency = $this->agencies[$agencyCode] ?? null;
        if ($agencyCode && ! $agency) {
            $errors[] = "Agency code {$agencyCode} is not in the app. Upload or add the agency first.";
        }
        $data = [
            'agency_id' => $agency?->id, 'short_code' => $code ?: null, 'name' => $v['name'], 'letter_code' => $v['letter_code'] ? strtoupper($v['letter_code']) : null,
            'addressee' => $v['addressee'], 'billing_address' => $v['billing_address'], 'contact_person' => $v['contact_person'], 'phone' => $v['phone'],
            'email' => $v['email'], 'district' => $v['district'], 'state_code' => $v['state_code'], 'gstin' => $v['gstin'],
            'payment_term_days' => $v['payment_term_days'] !== null ? (int) $v['payment_term_days'] : null, 'status' => $v['status'],
        ];
        $existing = $agency ? ($this->clients[$agency->id.'|'.$code] ?? null) : null;

        return $this->result($row, "{$agencyCode}|{$code}", "{$agencyCode} · {$code} · ".($v['name'] ?? ''), $data, $existing, $errors);
    }

    protected function newModel(): Model
    {
        return new Client(['state_code' => '21', 'payment_term_days' => 30, 'status' => 'active']);
    }

    public function export(): iterable
    {
        foreach (Client::query()->with('agency')->get()->sortBy(fn ($c) => $c->agency?->code.'|'.$c->short_code) as $c) {
            yield ['agency_code' => $c->agency?->code] + $c->only(['short_code', 'name', 'letter_code', 'addressee', 'billing_address', 'contact_person',
                'phone', 'email', 'district', 'state_code', 'gstin', 'payment_term_days', 'status']);
        }
    }
}
