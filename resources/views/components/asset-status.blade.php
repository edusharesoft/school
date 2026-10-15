@props(['asset'])
@php
    $tone = match ($asset->status) { 'in_use' => 'primary', 'in_stock' => 'success', 'disposed' => 'secondary', default => 'dark' };
    $label = match ($asset->status) { 'in_use' => __('In use'), 'in_stock' => __('Available'), 'disposed' => __('Disposed'), default => __('Cancelled') };
@endphp
<span class="badge text-bg-{{ $tone }}">{{ $label }}</span>@if($asset->is_under_repair) <span class="badge text-bg-warning">{{ __('Under repair') }}</span>@endif @if($asset->is_missing) <span class="badge text-bg-danger">{{ __('Missing') }}</span>@endif
