{{-- Closes a work order's group on the Sub-bills list: the total of every column (owner, 22 Nov 2026). $bill: last bill of the group, $t: totals row, $igst, $money from the list. --}}
@if($t)
<tr class="bills-group-total">
  <td colspan="3">Total {{ $bill->contract->contract_no }} · {{ $t->bills }} {{ \Illuminate\Support\Str::plural('sub-bill', $t->bills) }}</td>
  <td class="num">{{ $money($t->wages) }}</td><td class="num">{{ $money($t->epf) }}</td><td class="num">{{ $money($t->esic) }}</td>
  <td class="num">{{ $money($t->service_charge) }}</td><td class="num">{{ $money($t->taxable) }}</td>
  <td class="num">{{ $money($t->cgst) }}</td><td class="num">{{ $money($t->sgst) }}</td>@if($igst)<td class="num">{{ $money($t->igst) }}</td>@endif
  <td class="num">{{ $money($t->billed) }}</td><td></td>
</tr>
@endif
