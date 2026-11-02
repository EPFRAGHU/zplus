<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'employee_id', 'employee_name', 'designation', 'rate', 'rate_type', 'days_in_month', 'paid_days', 'amount', 'epf', 'esic'])]
class InvoiceLine extends Model
{
    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'amount' => 'decimal:2', 'epf' => 'decimal:2', 'esic' => 'decimal:2', 'paid_days' => 'decimal:1'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
