<div class="no-print text-center my-3 d-flex justify-content-center flex-wrap gap-2">
    <button type="button" class="btn btn-brand btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
    <a href="{{ $back ?? url()->previous() }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
    {{ $slot ?? '' }}
</div>
