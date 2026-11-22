@php($cls = ['pending' => 'text-bg-warning', 'approved' => 'text-bg-success', 'returned' => 'text-bg-danger'][$r->status] ?? 'text-bg-light')
<span class="badge {{ $cls }}">{{ \App\Models\PaymentReceipt::STATUSES[$r->status] ?? $r->status }}</span>
