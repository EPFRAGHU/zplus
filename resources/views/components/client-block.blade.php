{{-- Light blue client heading used inside agency blocks on every list.
     As a table row pass :colspan; without it renders as a block <div>. --}}
@props(['client' => null, 'colspan' => null, 'meta' => null])
@if($colspan)
<tr class="table-info client-block"><td colspan="{{ $colspan }}" class="py-2 ps-4">
@else
<div class="client-block bg-info-subtle border border-info-subtle rounded px-3 py-2 ms-md-3 mb-2">
@endif
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <i class="bi bi-bank text-secondary" aria-hidden="true"></i>
    <span class="small text-secondary">Client</span>
    @if($client)
      <b>{{ $client->name }}</b>
      @if($client->agency)<span class="badge text-bg-light border">{{ $client->agency->code }}</span>@endif
    @else
      <b>Not assigned to a client yet</b>
    @endif
    @if($meta)<span class="small text-secondary">{{ $meta }}</span>@endif
    @if(trim($slot) !== '')<div class="ms-auto d-flex gap-2 align-items-center">{{ $slot }}</div>@endif
  </div>
@if($colspan)
</td></tr>
@else
</div>
@endif
