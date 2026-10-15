{{-- Issue a staff certificate. Needs $employee. --}}
@can('staff-certificates.issue')
<div class="modal fade" id="staffCertModal" tabindex="-1" aria-labelledby="staffCertModalLabel" aria-hidden="true">
    <div class="modal-dialog"><form method="POST" action="{{ route('branch.hr.employees.certificates.store', $employee) }}" class="modal-content">@csrf
        <div class="modal-header"><h2 class="modal-title h6" id="staffCertModalLabel">{{ __('Issue a certificate') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12">
                <label class="form-label" for="sc_type">{{ __('Certificate') }}</label>
                <select class="form-select" id="sc_type" name="type" required>
                    @if($employee->isSeparated())<option value="experience">{{ __('Experience Certificate') }}</option>@endif
                    @if($employee->isActive())<option value="service">{{ __('Service Certificate') }}</option>@endif
                </select>
                @if(! $employee->isSeparated() && ! $employee->isActive())<div class="form-text text-danger">{{ __('No certificate can be issued while the staff member is :status.', ['status' => __($employee->status)]) }}</div>@endif
            </div>
            <div class="col-md-6"><label class="form-label" for="sc_conduct">{{ __('Conduct') }}</label><input class="form-control" id="sc_conduct" name="conduct" maxlength="100" placeholder="{{ __('Good') }}"></div>
            <div class="col-md-6"><label class="form-label" for="sc_purpose">{{ __('Purpose') }}</label><input class="form-control" id="sc_purpose" name="purpose" maxlength="200" placeholder="{{ __('e.g. visa, bank, new job') }}"></div>
            <div class="col-12"><label class="form-label" for="sc_remarks">{{ __('Remarks') }}</label><textarea class="form-control" id="sc_remarks" name="remarks" rows="2" maxlength="500"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-brand" @disabled(! $employee->isSeparated() && ! $employee->isActive())>{{ __('Issue') }}</button></div>
    </form></div>
</div>
@endcan
