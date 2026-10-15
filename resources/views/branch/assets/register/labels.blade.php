<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><title>{{ __('Asset tags') }}</title>
<style>
    body { font-family: sans-serif; margin: 12mm; color: #000; }
    .toolbar { margin-bottom: 8mm; } .toolbar button { padding: 6px 14px; }
    .grid { display: flex; flex-wrap: wrap; gap: 4mm; }
    .label { width: 64mm; border: 1px dashed #888; padding: 3mm; text-align: center; page-break-inside: avoid; }
    .label .h { font-size: 8pt; color: #333; } .label .t { font-size: 9pt; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .label .s { font-size: 8pt; color: #333; } .label svg { max-width: 100%; height: auto; }
    @media print { .toolbar { display: none; } .label { border-color: #ccc; } }
</style></head><body>
<div class="toolbar"><button onclick="window.print()">{{ __('Print') }}</button> <span>{{ __(':n tags', ['n' => $assets->count()]) }}@if($assets->count() >= 300) — {{ __('first 300 shown; filter by category for more') }}@endif</span></div>
<div class="grid">
@foreach($assets as $a)
    <div class="label"><div class="h">{{ $branch?->displayName() }}</div><div class="t">{{ $a->displayName() }}</div><div class="s">{{ $a->category?->name }}@if($a->serial_no) · {{ $a->serial_no }}@endif</div>{!! \App\Support\Code128::svg($a->code, 40, 2, true) !!}</div>
@endforeach
</div></body></html>
