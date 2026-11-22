{{-- A count (or any label) that opens a small list on click, e.g. the staff on a post or the projects of a Project In-charge (owner, 22 Nov 2026).
     Click outside or press Esc to close; the script in layouts/app.blade.php wires every [data-pop].
     $items: list of ['text' => ..., 'url' => ?, 'sub' => ?]. $title: heading of the list. --}}
@props(['items' => [], 'title' => '', 'empty' => 'No one placed yet'])
@php
  $list = collect($items);
  $html = $list->isEmpty()
      ? '<div class="text-secondary small">'.e($empty).'</div>'
      : '<ul class="wo-pop-list">'.$list->map(fn ($i) => '<li>'.(! empty($i['url']) ? '<a href="'.e($i['url']).'">'.e($i['text']).'</a>' : '<span>'.e($i['text']).'</span>')
          .(! empty($i['sub']) ? ' <span class="wo-pop-sub">'.e($i['sub']).'</span>' : '').'</li>')->implode('').'</ul>';
@endphp
<button type="button" {{ $attributes->merge(['class' => 'wo-pop-btn']) }} data-pop data-bs-title="{{ $title }}" data-bs-content="{{ $html }}" aria-label="{{ $title }}">{{ $slot }}</button>
