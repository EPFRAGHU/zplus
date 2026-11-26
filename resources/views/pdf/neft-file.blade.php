{{-- NEFT intimation file for the bank (owner, 26 Nov 2026): the same 12 columns as the bulk upload sheet, for printing
     or sending with the bank letter. No cell spans rows. --}}
<html><head><meta charset="utf-8">
<style>
  @page { margin: 22px 22px 26px; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #000; }
  table { width: 100%; border-collapse: collapse; }
  td, th { border: 1px solid #000; padding: 4px 3px; vertical-align: middle; }
  th { font-weight: bold; text-align: left; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  .r { text-align: right; white-space: nowrap; } .nw { white-space: nowrap; }
  .foot { margin-top: 6px; font-size: 8.5px; }
</style></head><body>
<table>
  <thead><tr>@foreach(\App\Services\Payroll\NeftFile::HEAD as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
  <tbody>
  @foreach($rows as $r)
    <tr><td>{{ $r['sn'] }}</td><td class="nw">{{ $r['corp_id'] }}</td><td class="nw">{{ $r['corp_name'] }}</td><td class="nw">{{ $r['corp_acct'] }}</td><td>{{ $r['product'] }}</td>
      <td>{{ $r['name'] }}</td><td class="r">{{ $r['account'] }}</td><td>{{ $r['amount'] }}</td><td class="nw">{{ $r['ifsc'] }}</td><td class="nw">{{ $r['remarks'] }}</td><td>{{ $r['currency'] }}</td><td class="r">{{ $r['address'] }}</td></tr>
  @endforeach
  </tbody>
</table>
<div class="foot"><b>{{ $agency->name }}</b> · salary for {{ $month->format('F Y') }} · {{ count($rows) }} transfers · total Rs. {{ number_format(array_sum(array_column($rows, 'amount'))) }}
  (DCR {{ collect($rows)->where('product', 'DCR')->count() }}, NEFT {{ collect($rows)->where('product', 'NEFT')->count() }}) · Ref: {{ $reference }}</div>
</body></html>
