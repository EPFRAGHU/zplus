<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Agency\ScopedToAgency;
use App\Services\Billing\GemBillCalculator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['agency_id', 'client_id', 'bid_no', 'contract_no', 'contract_date', 'billing_type', 'sac_code', 'service_charge_percent', 'gst_percent', 'epf_percent', 'esic_percent', 'inter_state', 'status'])]
class GemContract extends Model
{
    use LogsActivity, ScopedToAgency;

    protected function casts(): array
    {
        return ['contract_date' => 'date', 'inter_state' => 'boolean'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(Post::class, Project::class);
    }

    public function isDaily(): bool
    {
        return $this->billing_type === 'daily';
    }

    public function calculator(): GemBillCalculator
    {
        return new GemBillCalculator(
            serviceChargePercent: self::dec($this->service_charge_percent),
            gstPercent: self::dec($this->gst_percent),
            interState: (bool) $this->inter_state,
            epfPercent: self::dec($this->epf_percent),
            esicPercent: self::dec($this->esic_percent),
        );
    }

    /** "3.8500" -> "3.85" */
    private static function dec(mixed $v): string
    {
        $s = (string) $v;

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }
}
