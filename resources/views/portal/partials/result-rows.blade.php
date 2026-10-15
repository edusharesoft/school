@forelse($results as $r)
    <div class="d-flex align-items-center border-bottom py-2 gap-2">
        <div class="me-auto">
            <strong>{{ $r->examination?->name }}</strong>
            <div class="small text-body-secondary">{{ $r->schoolClass?->name }} · {{ __('Merit') }} {{ $r->class_position ?? '—' }}</div>
        </div>
        <div class="text-center"><div class="small text-body-secondary">{{ __('GPA') }}</div><strong>{{ $r->gpaLabel() }}</strong></div>
        <span class="badge text-bg-{{ $r->tone() }}">{{ $r->grade ?? __(ucfirst($r->status)) }}</span>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route($marksheetRoute, $r) }}" target="_blank" rel="noopener">{{ __('Mark sheet') }}</a>
        <a class="btn btn-sm btn-outline-danger" href="{{ route(str_replace('.show', '.pdf', $marksheetRoute), $r) }}" title="{{ __('Download as PDF') }}" aria-label="{{ __('Download as PDF') }}"><i class="fa-solid fa-file-pdf"></i> PDF</a>
    </div>
@empty
    <p class="text-body-secondary mb-0">{{ __('No published results yet.') }}</p>
@endforelse
