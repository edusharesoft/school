{{-- Date-range / as-of filter bar for accounting reports. $mode: range | asof --}}
<div class="card mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            {{ $slot ?? '' }}
            @if(($mode ?? 'range') === 'asof')
                <div class="col-md-3"><label class="form-label small" for="f_asof">{{ __('As of') }}</label><input id="f_asof" type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="form-control form-control-sm"></div>
            @else
                <div class="col-md-3"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
                <div class="col-md-3"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
            @endif
            @foreach($extra ?? [] as $html){!! $html !!}@endforeach
            <div class="col-auto"><button class="btn btn-sm btn-brand">{{ __('Show') }}</button></div>
            <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button></div>
        </form>
    </div>
</div>
