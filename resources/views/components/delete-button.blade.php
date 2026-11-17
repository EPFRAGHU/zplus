{{-- Super Admin delete button, shown only while "Show delete options" is on. $blocked: why it cannot be deleted (button disabled). --}}
@props(['action', 'label' => 'Delete', 'confirm' => 'Delete this record? This cannot be undone.', 'blocked' => null, 'small' => true])
@can('show-delete')
  @if($blocked)
    <span class="d-inline-block" tabindex="0" title="{{ $blocked }}"><button type="button" class="btn {{ $small ? 'btn-sm' : '' }} btn-outline-danger" disabled><i class="bi bi-trash"></i> {{ $label }}</button></span>
  @else
    <form method="post" action="{{ $action }}" class="d-inline" data-confirm="{{ $confirm }}" onsubmit="return confirm(this.dataset.confirm)">@csrf @method('delete')
      <button class="btn {{ $small ? 'btn-sm' : '' }} btn-outline-danger"><i class="bi bi-trash"></i> {{ $label }}</button></form>
  @endif
@endcan
