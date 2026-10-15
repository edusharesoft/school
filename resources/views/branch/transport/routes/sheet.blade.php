<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><title>{{ __('Route sheet') }} {{ $route->code }}</title>
<style>
    body { font-family: sans-serif; margin: 10mm; color: #000; font-size: 10pt; } h1 { font-size: 15pt; margin: 0; } h2 { font-size: 11pt; margin: 6mm 0 2mm; border-bottom: 1px solid #444; }
    table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #999; padding: 1.5mm 2mm; text-align: left; font-size: 9pt; } th { background: #eee; }
    .meta { margin: 2mm 0 4mm; } .toolbar { margin-bottom: 6mm; } @media print { .toolbar { display: none; } }
</style></head><body>
<div class="toolbar"><button onclick="window.print()">{{ __('Print') }}</button></div>
<h1>{{ tenant()->branch()?->displayName() }} — {{ __('Route sheet') }}</h1>
<div class="meta"><strong>{{ $route->code }} — {{ $route->name }}</strong><br>
    {{ __('Vehicle') }}: {{ $route->vehicle?->label() ?? '—' }} · {{ __('Driver') }}: {{ $route->driver?->name ?? '—' }} {{ $route->driver?->phone }} · {{ __('Helper') }}: {{ $route->helper?->name ?? '—' }} {{ $route->helper?->phone }}<br>
    @if($route->driver?->emergency_contact_phone){{ __('Driver emergency contact') }}: {{ $route->driver->emergency_contact_name }} {{ $route->driver->emergency_contact_phone }}@endif</div>
@foreach($stops as $stop)
    <h2>{{ $stop->sequence }}. {{ $stop->name }} <small>({{ __('Pickup') }} {{ $stop->pickup_time ? substr($stop->pickup_time, 0, 5) : '—' }} · {{ __('Drop') }} {{ $stop->drop_time ? substr($stop->drop_time, 0, 5) : '—' }})</small></h2>
    @php($list = $riders->get($stop->id, collect()))
    @if($list->isEmpty())<p>—</p>@else
    <table><thead><tr><th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Guardian') }}</th><th>{{ __('Phone') }}</th></tr></thead><tbody>
    @foreach($list as $a)
        @php($g = $a->student?->guardians?->first())
        <tr><td>{{ $a->student?->name }} ({{ $a->student?->student_code }})</td><td>{{ $a->student?->currentEnrollment?->schoolClass?->name }} {{ $a->student?->currentEnrollment?->section?->name }}</td><td>{{ $g?->name ?? '—' }}</td><td>{{ $g?->phone ?? $a->student?->phone ?? '—' }}</td></tr>
    @endforeach</tbody></table>@endif
@endforeach
</body></html>
