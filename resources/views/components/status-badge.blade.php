@props(['status'])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $tone = match ($value) { 'active' => 'success', 'suspended' => 'danger', default => 'secondary' };
@endphp
<span class="badge rounded-pill text-bg-{{ $tone }}">{{ __(ucfirst($value)) }}</span>
