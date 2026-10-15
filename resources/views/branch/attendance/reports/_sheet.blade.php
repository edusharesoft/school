{{-- Month grid. $sheet = ['days','rows','holidays']; $label(row) returns the name cell HTML-safe string; $code(row) the ID. --}}
<div class="table-responsive">
    <table class="table table-bordered table-sm align-middle mb-0 att-sheet" style="font-size:12px">
        <thead class="text-center">
            <tr>
                <th class="text-start">{{ $firstHead }}</th>
                @foreach($sheet['days'] as $d => $day)
                    <th @class(['bg-body-secondary' => $sheet['holidays'][$day->toDateString()]]) title="{{ $day->format('D') }}">{{ bn_digits($d) }}</th>
                @endforeach
                <th class="text-success">P</th><th class="text-danger">A</th><th class="text-warning">L</th><th>H</th><th class="text-primary">LV</th><th>%</th>
                @isset($extraHead){!! $extraHead !!}@endisset
            </tr>
        </thead>
        <tbody>
        @foreach($sheet['rows'] as $row)
            <tr>
                <td class="text-nowrap">{!! $label($row) !!}</td>
                @foreach($sheet['days'] as $day)
                    @php($st = $row['marks'][$day->toDateString()] ?? null)
                    <td @class(['text-center p-0', 'bg-body-secondary' => $sheet['holidays'][$day->toDateString()]])>
                        @if($st)<span class="text-{{ $st->tone() }} fw-semibold" title="{{ $st->label() }}">{{ $st->short() }}</span>@endif
                    </td>
                @endforeach
                <td class="text-center">{{ bn_digits($row['counts']['present']) }}</td>
                <td class="text-center">{{ bn_digits($row['counts']['absent']) }}</td>
                <td class="text-center">{{ bn_digits($row['counts']['late']) }}</td>
                <td class="text-center">{{ bn_digits($row['counts']['half_day']) }}</td>
                <td class="text-center">{{ bn_digits($row['counts']['leave'] + $row['counts']['excused']) }}</td>
                <td @class(['text-center fw-semibold', 'text-danger' => $row['percentage'] !== null && $row['percentage'] < 75])>{{ $row['percentage'] === null ? '—' : bn_digits($row['percentage']) }}</td>
                @isset($extra){!! $extra($row) !!}@endisset
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="small text-body-secondary mt-2">P = {{ __('Present') }} · A = {{ __('Absent') }} · L = {{ __('Late') }} · H = {{ __('Half-day') }} · LV = {{ __('Leave') }} / E = {{ __('Excused absence') }} · {{ __('Shaded = weekly holiday') }} ·
    {{ __('% = (present + late + ½ half-day) ÷ (marked days − leave − excused)') }}</div>
