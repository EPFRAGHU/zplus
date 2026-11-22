<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Agency\ScopedToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A payment received from the client (owner, 22 Nov 2026): one bank credit or cheque, split over the sub-bills it pays
 * with the client's deductions per sub-bill. Entered by Accounts, approved by a Super Admin (never the one who entered it);
 * only approved receipts count, and a sub-bill turns Paid when its approved receipts settle it in full.
 */
#[Fillable(['agency_id', 'client_id', 'gem_contract_id', 'mode', 'utr_no', 'transfer_date', 'cheque_no', 'cheque_date', 'cheque_bank',
    'credit_date', 'bank_account', 'amount', 'remarks', 'advice_path', 'advice_name', 'status', 'entered_by', 'approved_by', 'approved_at', 'return_note'])]
class PaymentReceipt extends Model
{
    use LogsActivity, ScopedToAgency;

    public const MODES = ['neft' => 'NEFT', 'rtgs' => 'RTGS', 'cheque' => 'Cheque', 'dd' => 'Demand draft', 'other' => 'Other'];

    public const STATUSES = ['pending' => 'Waiting for approval', 'approved' => 'Approved', 'returned' => 'Returned'];

    protected static function booted(): void
    {
        static::deleting(fn (self $r) => $r->advice_path ? Storage::disk('local')->delete($r->advice_path) : null);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transfer_date' => 'date', 'cheque_date' => 'date', 'credit_date' => 'date', 'approved_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PaymentReceiptLine::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(GemContract::class, 'gem_contract_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** UTR for transfers, cheque / DD number otherwise. */
    public function reference(): string
    {
        return in_array($this->mode, ['cheque', 'dd'], true) ? (string) $this->cheque_no : (string) $this->utr_no;
    }

    /** Short name in lists and the Activity log. */
    public function label(): string
    {
        return (self::MODES[$this->mode] ?? $this->mode).' '.($this->reference() ?: '#'.$this->id).' · ₹'.number_format((float) $this->amount, 2);
    }
}
