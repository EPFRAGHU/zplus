@props(['value'])
@php($map = ['active' => 'success', 'paid' => 'success', 'locked' => 'success', 'approved' => 'primary', 'submitted' => 'info', 'draft' => 'secondary', 'notice' => 'warning', 'hold' => 'warning', 'inactive' => 'secondary', 'closed' => 'secondary'])
<span class="badge rounded-pill text-bg-{{ $map[$value] ?? 'secondary' }}">{{ ucfirst($value) }}</span>
