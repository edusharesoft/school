@extends($layout)
@section('title', __('Audit log'))

@section('content')
<x-page-header :title="__('Audit log')" :breadcrumbs="[__('Audit log') => null]" />

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            @if($branches->isNotEmpty())
                <div class="col-md-3">
                    <select name="branch_id" class="form-select form-select-sm" aria-label="{{ __('Branch') }}"><option value="">{{ __('All branches') }}</option>
                        @foreach($branches as $id => $name)<option value="{{ $id }}" @selected((int) ($filters['branch_id'] ?? 0) === $id)>{{ $name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-3"><input name="event" value="{{ $filters['event'] ?? '' }}" class="form-control form-control-sm" placeholder="{{ __('Event, e.g. auth. or updated') }}" aria-label="{{ __('Event') }}"></div>
            <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm" aria-label="{{ __('From') }}"></div>
            <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm" aria-label="{{ __('To') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button></div>
        </form>
    </div>
    @if($logs->isEmpty())
        <x-empty-state icon="journal-text" :title="__('No audit entries match these filters')" />
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('User') }}</th><th>{{ __('Event') }}</th><th>{{ __('Record') }}</th><th>{{ __('Branch') }}</th><th>{{ __('IP') }}</th><th>{{ __('Changes') }}</th></tr></thead>
                <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td class="small text-nowrap">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                        <td class="small">{{ $log->user?->name ?? __('System') }}</td>
                        <td><span class="badge text-bg-light border">{{ $log->event }}</span></td>
                        <td class="small">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                        <td class="small">{{ $log->branch?->name ?? '—' }}</td>
                        <td class="small text-body-secondary">{{ $log->ip_address }}</td>
                        <td class="small" style="max-width: 360px">
                            @if($log->new_values || $log->old_values)
                                <details><summary>{{ __('view') }}</summary>
                                    <pre class="small mb-0 text-wrap">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
