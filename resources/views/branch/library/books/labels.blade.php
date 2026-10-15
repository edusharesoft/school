<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><title>{{ __('Barcode labels') }} — {{ $book->displayTitle() }}</title>
<style>
    body { font-family: sans-serif; margin: 12mm; color: #000; }
    .toolbar { margin-bottom: 8mm; } .toolbar button { padding: 6px 14px; }
    .grid { display: flex; flex-wrap: wrap; gap: 4mm; }
    .label { width: 62mm; border: 1px dashed #888; padding: 3mm; text-align: center; page-break-inside: avoid; }
    .label .t { font-size: 9pt; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .label .s { font-size: 8pt; color: #333; } .label svg { max-width: 100%; height: auto; }
    @media print { .toolbar { display: none; } .label { border-color: #ccc; } }
</style></head><body>
<div class="toolbar"><button onclick="window.print()">{{ __('Print') }}</button></div>
<div class="grid">
@foreach($copies as $copy)
    <div class="label"><div class="t">{{ $book->displayTitle() }}</div><div class="s">{{ $book->shelf ? __('Shelf').' '.$book->shelf : '' }}</div>{!! \App\Support\Code128::svg($copy->barcode, 40, 2, true) !!}</div>
@endforeach
</div></body></html>
