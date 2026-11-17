<?php

namespace App\Models;

use App\Support\Agency\ScopedToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A supporting paper of a work order for a wage month (GeM asks for these with every monthly bill). */
#[Fillable(['agency_id', 'gem_contract_id', 'month', 'kind', 'path', 'original_name', 'uploaded_by'])]
class MonthlyPaper extends Model
{
    use ScopedToAgency;

    public const KINDS = [
        'salary_proof' => 'Salary payment proof (bank statement)',
        'epf_challan' => 'EPF challan (ECR)',
        'esi_challan' => 'ESIC challan',
        'logbook' => 'Logbook',
        'feedback' => 'Service feedback',
        'other' => 'Other paper',
    ];

    /** Papers every month's bill needs (EPF / ESIC only when someone on the work order has it switched on). */
    public const CHECKLIST = ['salary_proof', 'epf_challan', 'esi_challan', 'logbook', 'feedback'];

    /** Holds staff bank account numbers: only those who may see bank details open it. */
    public const BANK_KINDS = ['salary_proof'];

    protected function month(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($v) => $v === null ? null : \Carbon\CarbonImmutable::parse($v)->startOfDay(),
            set: fn ($v) => $v === null ? null : \Carbon\CarbonImmutable::parse($v)->startOfMonth()->toDateString(),
        );
    }

    protected static function booted(): void
    {
        static::deleting(fn (MonthlyPaper $p) => Storage::disk('local')->delete($p->path));
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(GemContract::class, 'gem_contract_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function label(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
