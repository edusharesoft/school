{{-- Correction request modal. Buttons open it with data-record, data-type (student|staff), data-name, data-status. --}}
<div class="modal fade" id="correctionModal" tabindex="-1" aria-labelledby="correctionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ $correctionRoute }}" class="modal-content">
            @csrf
            <input type="hidden" name="record_id" value="">
            <input type="hidden" name="type" value="">
            <div class="modal-header"><h2 class="modal-title h6" id="correctionModalLabel">{{ __('Request attendance correction') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12"><strong data-name></strong> · {{ __('current') }}: <span data-current></span></div>
                <div class="col-md-6"><label class="form-label" for="requested_status">{{ __('Correct status') }}</label>
                    <select id="requested_status" name="requested_status" class="form-select" required>
                        @foreach(App\Enums\AttendanceStatus::cases() as $st)<option value="{{ $st->value }}">{{ $st->label() }}</option>@endforeach
                    </select></div>
                @if($withTimes)
                    <div class="col-md-3"><label class="form-label" for="requested_check_in">{{ __('In') }}</label>
                        <input id="requested_check_in" type="time" name="requested_check_in" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label" for="requested_check_out">{{ __('Out') }}</label>
                        <input id="requested_check_out" type="time" name="requested_check_out" class="form-control"></div>
                @endif
                <div class="col-12"><label class="form-label" for="corr_reason">{{ __('Reason') }}</label>
                    <textarea id="corr_reason" name="reason" rows="3" class="form-control" required minlength="5" maxlength="1000" placeholder="{{ __('e.g. Student arrived late with a note; marked absent by mistake.') }}"></textarea></div>
                <div class="col-12 small text-body-secondary">{{ __('The record changes only after an authorised person approves the request. Every change is kept in the audit log.') }}</div>
            </div>
            <div class="modal-footer"><button class="btn btn-brand">{{ __('Submit request') }}</button></div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('correctionModal')?.addEventListener('show.bs.modal', (ev) => {
    const b = ev.relatedTarget, m = ev.currentTarget;
    m.querySelector('[name=record_id]').value = b.dataset.record;
    m.querySelector('[name=type]').value = b.dataset.type;
    m.querySelector('[data-name]').textContent = b.dataset.name;
    const sel = m.querySelector('[name=requested_status]');
    m.querySelector('[data-current]').textContent = sel.querySelector('option[value="' + b.dataset.status + '"]')?.textContent || b.dataset.status;
    sel.value = b.dataset.status === 'absent' ? 'present' : 'absent';
});
</script>
@endpush
