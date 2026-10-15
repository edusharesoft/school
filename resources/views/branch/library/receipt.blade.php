<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><title>{{ __('Library fine receipt') }} {{ $fine->receipt_no }}</title>
<style>
    body { font-family: sans-serif; margin: 0; color: #111; } .sheet { width: 148mm; margin: 10mm auto; border: 1px solid #999; padding: 8mm; }
    h1 { font-size: 14pt; margin: 0 0 2mm; } h2 { font-size: 10pt; margin: 0 0 5mm; font-weight: normal; color: #444; }
    table { width: 100%; border-collapse: collapse; font-size: 10pt; } td { padding: 2mm 0; border-bottom: 1px solid #ddd; } td:last-child { text-align: right; }
    .total td { font-weight: bold; font-size: 12pt; border-bottom: 2px solid #111; } .sign { margin-top: 18mm; font-size: 9pt; display: flex; justify-content: space-between; }
    .toolbar { text-align: center; margin: 6mm; } @media print { .toolbar { display: none; } .sheet { border: 0; margin: 0 auto; } }
</style></head><body>
<div class="toolbar"><button onclick="window.print()">{{ __('Print') }}</button></div>
<div class="sheet">
    <h1>{{ tenant()->branch()?->displayName() }}</h1><h2>{{ __('Library fine receipt') }}</h2>
    <table>
        <tr><td>{{ __('Receipt no.') }}</td><td>{{ $fine->receipt_no }}</td></tr>
        <tr><td>{{ __('Date') }}</td><td>{{ $fine->paid_on?->format('d M Y') }}</td></tr>
        <tr><td>{{ __('Member') }}</td><td>{{ $fine->memberModel()?->name }} ({{ \App\Services\Library\LibraryMembers::code($fine->memberModel()) }})</td></tr>
        <tr><td>{{ __('Reason') }}</td><td>{{ __(ucfirst($fine->kind)) }}@if($fine->transaction?->book) — {{ $fine->transaction->book->displayTitle() }}@endif</td></tr>
        <tr><td>{{ __('Method') }}</td><td>{{ __(ucfirst((string) $fine->method)) }}</td></tr>
        <tr class="total"><td>{{ __('Amount received') }}</td><td>৳{{ number_format((float) $fine->amount, 2) }}</td></tr>
    </table>
    <div class="sign"><span>{{ __('Received by') }}: ______________</span><span>{{ __('Member') }}: ______________</span></div>
</div></body></html>
