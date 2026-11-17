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

#[Fillable(['agency_id', 'client_id', 'contract_type', 'bid_no', 'contract_no', 'contract_date', 'start_date', 'end_date', 'contract_value', 'billing_type', 'sac_code', 'service_charge_percent', 'service_charge_includes_gst', 'gst_percent', 'epf_percent', 'esic_percent', 'inter_state', 'status'])]
class GemContract extends Model
{
    use LogsActivity, ScopedToAgency;

    protected function casts(): array
    {
        return ['contract_date' => 'date', 'start_date' => 'date', 'end_date' => 'date', 'contract_value' => 'decimal:2', 'inter_state' => 'boolean', 'service_charge_includes_gst' => 'boolean'];
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

    public const TYPES = ['online' => 'Online (GeM)', 'offline' => 'Offline'];

    public function typeLabel(): string
    {
        return self::TYPES[$this->contract_type] ?? 'Online (GeM)';
    }

    public function papers(): HasMany
    {
        return $this->hasMany(MonthlyPaper::class);
    }

    /** Days until the service end date (negative once ended), or null when no end date is entered. */
    public function daysLeft(): ?int
    {
        return $this->end_date ? (int) now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false) : null;
    }

    /** "ended", "ending" (30 days or less) or null, for active work orders with an end date. */
    public function expiry(): ?string
    {
        $left = $this->daysLeft();
        if ($left === null || $this->status !== 'active') {
            return null;
        }

        return $left < 0 ? 'ended' : ($left <= 30 ? 'ending' : null);
    }

    public function taxInvoices(): HasMany
    {
        return $this->hasMany(TaxInvoice::class);
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
            serviceChargeIncludesGst: (bool) ($this->service_charge_includes_gst ?? true),
        );
    }

    /** "3.8500" -> "3.85" */
    private static function dec(mixed $v): string
    {
        $s = (string) $v;

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }
}
