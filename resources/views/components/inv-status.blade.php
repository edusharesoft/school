@props(['status'])
@php
    $tone = match ($status) {
        'pending', 'partial', 'draft' => 'warning', 'approved', 'ordered', 'open' => 'primary', 'received', 'posted', 'issued', 'completed', 'paid' => 'success',
        'rejected' => 'danger', default => 'secondary', // cancelled, reversed
    };
@endphp
<span class="badge text-bg-{{ $tone }}">{{ __(ucfirst($status)) }}</span>
