@extends($layout)
@section('title', $report->title())

@php
    use App\Services\Reports\ReportResult as R;
    $query = collect($filters)->filter(fn ($v) => $v !== null && $v !== '' && $v !== false)->map(fn ($v) => $v === true ? 1 : $v)->all();
    $columns = $result->columns;
@endphp

@section('content')
<x-page-header :title="$report->title()" :breadcrumbs="[__('Reports') => route($prefix.'.index'), $report->title() => null]">
    <x-slot:actions>
        @if($canExport)
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-download me-1"></i>{{ __('Export') }}</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route($prefix.'.export', [$report->key()] + $query + ['format' => 'xlsx']) }}"><i class="fa-solid fa-file-excel me-2 text-success"></i>Excel (.xlsx)</a></li>
                    <li><a class="dropdown-item" href="{{ route($prefix.'.export', [$report->key()] + $query + ['format' => 'csv']) }}"><i class="fa-solid fa-file-csv me-2 text-secondary"></i>CSV</a></li>
                    <li><a class="dropdown-item" href="{{ route($prefix.'.export', [$report->key()] + $query + ['format' => 'pdf']) }}"><i class="fa-solid fa-file-pdf me-2 text-danger"></i>PDF</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li class="px-3 small text-body-secondary">{{ __('Prepare in the background') }}</li>
                    @foreach(['xlsx' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF'] as $fmt => $label)
                        <li>
                            <form method="POST" action="{{ route($prefix.'.queue', $report->key()) }}">@csrf
                                @foreach($query as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                                <input type="hidden" name="format" value="{{ $fmt }}">
                                <button class="dropdown-item"><i class="fa-regular fa-clock me-2"></i>{{ $label }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </x-slot:actions>
</x-page-header>

@if($report->filters())
    <form method="GET" action="{{ route($prefix.'.show', $report->key()) }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            @foreach($report->filters() as $spec)
                @php($name = $spec['name'])
                <div class="col-sm-6 col-lg-3">
                    @if($spec['type'] === 'bool')
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="f_{{ $name }}" name="{{ $name }}" value="1" @checked(! empty($filters[$name]))>
                            <label class="form-check-label small" for="f_{{ $name }}">{{ __($spec['label']) }}</label>
                        </div>
                    @else
                        <label class="form-label small mb-1" for="f_{{ $name }}">{{ __($spec['label']) }}</label>
                        @if($spec['type'] === 'select')
                            <select class="form-select form-select-sm" id="f_{{ $name }}" name="{{ $name }}">
                                <option value="">{{ __($spec['empty'] ?? 'All') }}</option>
                                @foreach($report->optionsOf($spec) as $value => $label)
                                    <option value="{{ $value }}" @selected((string) ($filters[$name] ?? '') === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @elseif($spec['type'] === 'date')
                            <input type="date" class="form-control form-control-sm" id="f_{{ $name }}" name="{{ $name }}" value="{{ $filters[$name] ?? '' }}">
                        @elseif($spec['type'] === 'month')
                            <input type="month" class="form-control form-control-sm" id="f_{{ $name }}" name="{{ $name }}" value="{{ $filters[$name] ?? '' }}">
                        @elseif($spec['type'] === 'number')
                            <input type="number" min="0" class="form-control form-control-sm" id="f_{{ $name }}" name="{{ $name }}" value="{{ $filters[$name] ?? '' }}">
                        @else
                            <input type="search" class="form-control form-control-sm" id="f_{{ $name }}" name="{{ $name }}" value="{{ $filters[$name] ?? '' }}" maxlength="100">
                        @endif
                    @endif
                </div>
            @endforeach
            <div class="col-auto"><button class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-filter me-1"></i>{{ __('Show') }}</button></div>
        </div>
    </form>
@endif

@if($subtitle)<div class="text-body-secondary small mb-2">{{ $subtitle }}</div>@endif

@if($summary)
    <div class="row g-2 mb-3">
        @foreach($summary as $label => $value)
            <div class="col-6 col-md-3"><div class="card card-body py-2"><div class="small text-body-secondary">{{ $label }}</div>
                <div class="fw-semibold">{{ is_numeric($value) ? number_format((float) $value, floor((float) $value) == (float) $value ? 0 : 2) : $value }}</div></div></div>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="card-header bg-transparent small text-body-secondary d-flex justify-content-between">
        <span>{{ number_format($total) }} {{ __('rows') }}</span>
        @if($total > $limit)<span>{{ __('Showing the first :n — export to get all rows.', ['n' => number_format($limit)]) }}</span>@endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light"><tr>
                @foreach($columns as $c)<th @class(['text-end' => $result->isNumeric($c), 'text-nowrap'])>{{ $c['label'] }}</th>@endforeach
            </tr></thead>
            <tbody>
                @forelse($result->rows as $row)
                    <tr>@foreach($columns as $c)<td @class(['text-end' => $result->isNumeric($c)])>{{ R::display($row[$c['key']] ?? null, $c['type']) }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" class="text-center text-body-secondary py-4">{{ __('Nothing to show for these filters.') }}</td></tr>
                @endforelse
            </tbody>
            @if($totals)
                <tfoot><tr class="fw-semibold table-light">
                    @foreach($columns as $i => $c)
                        <td @class(['text-end' => $result->isNumeric($c)])>@if(array_key_exists($c['key'], $totals)){{ R::display($totals[$c['key']], $c['type']) }}@elseif($i === 0){{ __('Total') }}@endif</td>
                    @endforeach
                </tr></tfoot>
            @endif
        </table>
    </div>
</div>
@foreach($notes as $note)<div class="small text-body-secondary mt-2">{{ $note }}</div>@endforeach
@endsection
