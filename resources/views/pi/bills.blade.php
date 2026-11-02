<x-layouts.app title="Sub-bills">
  <div class="z-card">
    <div class="table-responsive"><table class="table">
      <thead><tr><th>Month</th><th>Project</th><th>Sub-bill no.</th><th>Agency</th><th class="num">Amount (₹)</th><th>Status</th><th></th></tr></thead>
      <tbody>
      @forelse($invoices as $i)
        <tr><td>{{ $i->month->format('M Y') }}</td><td>{{ $i->project->name }}</td><td class="mono">{{ $i->invoice_no }}</td>
          <td>{{ $i->project->contract->agency->name }}</td><td class="num">{{ inr($i->rounded_total, true) }}</td><td><x-status :value="$i->status" /></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('pi.bills.pdf', $i) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a></td></tr>
      @empty
        <tr><td colspan="7" class="text-center text-secondary py-4">No sub-bills yet. A sub-bill appears here once the agency approves a month's attendance.</td></tr>
      @endforelse
      </tbody>
    </table></div>
  </div>
</x-layouts.app>
