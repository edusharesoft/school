<div class="toast-container position-fixed top-0 end-0 p-3">
    @foreach (['success' => 'check-circle', 'status' => 'info-circle', 'warning' => 'exclamation-triangle'] as $key => $icon)
        @if (session($key))
            <div class="toast align-items-center text-bg-{{ $key === 'status' ? 'primary' : $key }} border-0" role="status" aria-live="polite" aria-atomic="true" data-autoshow>
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-{{ $icon }} me-2"></i>{{ session($key) }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
                </div>
            </div>
        @endif
    @endforeach
</div>
