<?php

namespace App\Services\MasterImport;

use App\Models\Agency;
use App\Models\Client;
use App\Models\GemContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ContractSheet extends MasterSheet
{
    private Collection $agencies;

    private Collection $clients;

    private Collection $contracts;

    public function type(): string
    {
        return 'contracts';
    }

    public function title(): string
    {
        return 'GeM contracts';
    }

    public function description(): string
    {
        return 'GeM contracts with their billing rules. Needs the agency and the client first.';
    }

    public function columns(): array
    {
        return [
            new Column('agency_code', 'Agency code', true, 'text', null, 'Must already be in the app.', 'ZPS', 12),
            new Column('client_code', 'Client code', true, help: 'The client\'s code under that agency, e.g. ICMR', example: 'ICMR', width: 12),
            new Column('contract_no', 'GeM contract no.', true, help: 'Unique; used to find the contract on later uploads', example: 'GEMC-511687731718030', width: 26, asText: true, identifies: true),
            new Column('bid_no', 'GeM bid no.', true, example: 'GEM/2026/B/7972720', width: 22, asText: true),
            new Column('contract_date', 'Contract date', type: 'date', help: 'DD-MM-YYYY', example: '01-10-2026', width: 14),
            new Column('billing_type', 'Billing type', type: 'list', options: ['monthly' => 'Monthly', 'daily' => 'Daily'], help: 'Monthly = rate per month; Daily = rate per day. Blank = Monthly.', example: 'monthly', width: 12),
            new Column('sac_code', 'SAC code', help: 'Blank = 998514', example: '998514', width: 10, asText: true),
            new Column('service_charge_percent', 'Service charge %', type: 'number', help: 'Blank = 3.85', example: '3.85', width: 14),
            new Column('gst_percent', 'GST %', type: 'number', help: 'Blank = 18', example: '18', width: 8),
            new Column('epf_percent', 'Employer EPF %', type: 'number', help: 'Blank = 13', example: '13', width: 13),
            new Column('esic_percent', 'Employer ESIC %', type: 'number', help: 'Blank = 3.25', example: '3.25', width: 13),
            new Column('inter_state', 'Inter-state (IGST)', type: 'bool', help: 'Yes = IGST instead of CGST + SGST. Blank = No.', example: 'No', width: 14),
            new Column('status', 'Status', type: 'list', options: ['active' => 'Active', 'closed' => 'Closed'], help: 'Blank = Active', example: 'active', width: 10),
        ];
    }

    public function lists(): array
    {
        return ['agency_code' => Agency::query()->orderBy('code')->pluck('code')->all(),
            'client_code' => Client::query()->orderBy('short_code')->distinct()->pluck('short_code')->all(),
            'billing_type' => ['monthly', 'daily'], 'inter_state' => ['Yes', 'No'], 'status' => ['active', 'closed']];
    }

    public function prepare(): void
    {
        parent::prepare();
        $this->agencies = Agency::query()->get()->keyBy(fn ($a) => strtoupper($a->code));
        $this->clients = Client::query()->get()->keyBy(fn ($c) => $c->agency_id.'|'.strtoupper($c->short_code));
        $this->contracts = GemContract::query()->get()->keyBy(fn ($c) => strtoupper($c->contract_no));
    }

    protected function check(array $v, int $row): RowResult
    {
        $agencyCode = strtoupper((string) $v['agency_code']);
        $clientCode = strtoupper((string) $v['client_code']);
        $no = strtoupper((string) $v['contract_no']);
        $errors = $this->validate($v, [
            'contract_no' => ['nullable', 'max:40'], 'bid_no' => ['nullable', 'max:40'], 'sac_code' => ['nullable', 'digits_between:4,8'],
            'service_charge_percent' => ['nullable', 'numeric', 'between:0,100'], 'gst_percent' => ['nullable', 'numeric', 'between:0,100'],
            'epf_percent' => ['nullable', 'numeric', 'between:0,100'], 'esic_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        $agency = $this->agencies[$agencyCode] ?? null;
        $client = $agency ? ($this->clients[$agency->id.'|'.$clientCode] ?? null) : null;
        if ($agencyCode && ! $agency) {
            $errors[] = "Agency code {$agencyCode} is not in the app.";
        } elseif ($agency && $clientCode && ! $client) {
            $errors[] = "Client code {$clientCode} is not a client of {$agencyCode}. Upload or add the client first.";
        }
        $existing = $this->contracts[$no] ?? null;
        if (! $existing && $no && \App\Support\Agency\AgencyContext::id()
            && \App\Support\Agency\AgencyContext::unscoped(fn () => GemContract::query()->where('contract_no', $v['contract_no'])->exists())) {
            $errors[] = "Contract {$no} belongs to another agency.";
        }
        if ($existing && $agency && $client && ((int) $existing->agency_id !== (int) $agency->id || (int) $existing->client_id !== (int) $client->id)) {
            $errors[] = "Contract {$no} is already in the app under a different agency or client; change it on the GeM Contracts page.";
        }
        $data = ['agency_id' => $agency?->id, 'client_id' => $client?->id, 'contract_no' => $v['contract_no'], 'bid_no' => $v['bid_no'],
            'contract_date' => $v['contract_date'], 'billing_type' => $v['billing_type'], 'sac_code' => $v['sac_code'],
            'service_charge_percent' => $v['service_charge_percent'], 'gst_percent' => $v['gst_percent'], 'epf_percent' => $v['epf_percent'],
            'esic_percent' => $v['esic_percent'], 'inter_state' => $v['inter_state'], 'status' => $v['status']];

        return $this->result($row, $no, "{$no} · {$agencyCode} · {$clientCode}", $data, $existing, $errors);
    }

    protected function newModel(): Model
    {
        return new GemContract(['billing_type' => 'monthly', 'sac_code' => '998514', 'service_charge_percent' => '3.85', 'gst_percent' => '18',
            'epf_percent' => '13', 'esic_percent' => '3.25', 'inter_state' => false, 'status' => 'active']);
    }

    public function export(): iterable
    {
        foreach (GemContract::query()->with('agency', 'client')->orderBy('contract_no')->get() as $c) {
            yield ['agency_code' => $c->agency?->code, 'client_code' => $c->client?->short_code, 'contract_no' => $c->contract_no, 'bid_no' => $c->bid_no,
                'contract_date' => self::dmy($c->contract_date), 'billing_type' => $c->billing_type, 'sac_code' => $c->sac_code,
                'service_charge_percent' => (float) $c->service_charge_percent, 'gst_percent' => (float) $c->gst_percent,
                'epf_percent' => (float) $c->epf_percent, 'esic_percent' => (float) $c->esic_percent, 'inter_state' => self::yesNo($c->inter_state), 'status' => $c->status];
        }
    }
}
