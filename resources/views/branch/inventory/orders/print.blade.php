<!doctype html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $order->po_no }}</title>
<style>body{font:14px/1.5 system-ui,"Noto Sans Bengali",sans-serif;color:#111;margin:24px}h1{font-size:20px;margin:0}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{border:1px solid #999;padding:6px 8px;text-align:left}th.r,td.r{text-align:right}.meta{display:flex;justify-content:space-between;margin-top:12px}.sign{display:flex;justify-content:space-between;margin-top:64px}.sign div{border-top:1px solid #333;padding-top:4px;width:30%;text-align:center}@media print{.no-print{display:none}}</style></head>
<body>
<p class="no-print"><button onclick="window.print()">{{ __('Print') }}</button></p>
<h1>{{ $branch?->displayName() }}</h1><div>{{ $branch?->address }}</div>
<h2 style="margin:16px 0 0">{{ __('Purchase order') }} {{ $order->po_no }}</h2>
<div class="meta"><div><strong>{{ __('Supplier') }}:</strong> {{ $order->vendor?->name }}<br>{{ $order->vendor?->address }}<br>{{ $order->vendor?->phone }}</div>
<div><strong>{{ __('Order date') }}:</strong> {{ $order->order_date->format('d M Y') }}<br><strong>{{ __('Expected on') }}:</strong> {{ $order->expected_on?->format('d M Y') ?? '—' }}</div></div>
<table><thead><tr><th>#</th><th>{{ __('Item') }}</th><th class="r">{{ __('Quantity') }}</th><th class="r">{{ __('Unit cost (৳)') }}</th><th class="r">{{ __('Amount (৳)') }}</th></tr></thead>
<tbody>@foreach($order->items as $i => $l)<tr><td>{{ $i + 1 }}</td><td>{{ $l->item?->name }}</td><td class="r">{{ rtrim(rtrim(number_format($l->quantity, 3, '.', ''), '0'), '.') }} {{ $l->item?->unit }}</td><td class="r">{{ number_format($l->unit_cost, 2) }}</td><td class="r">{{ number_format($l->quantity * $l->unit_cost, 2) }}</td></tr>@endforeach</tbody>
<tfoot><tr><th colspan="4" class="r">{{ __('Total') }}</th><th class="r">{{ number_format($order->total, 2) }}</th></tr></tfoot></table>
@if($order->notes)<p>{{ $order->notes }}</p>@endif
<div class="sign"><div>{{ __('Prepared by') }}</div><div>{{ __('Approved by') }}</div><div>{{ __('Supplier') }}</div></div>
</body></html>
