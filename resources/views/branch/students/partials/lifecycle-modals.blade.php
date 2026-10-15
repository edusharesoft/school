{{-- Student lifecycle actions: certificates, transfer to another branch, re-admission. --}}
@if($certificates->isNotEmpty())
<div class="card mt-3">
    <div class="card-header bg-transparent"><h2 class="h6 mb-0"><i class="fa-solid fa-award me-1 text-primary"></i> {{ __('Certificates') }}</h2></div>
    <ul class="list-group list-group-flush">
        @foreach($certificates as $c)
            <li class="list-group-item d-flex align-items-center gap-2">
                <span class="me-auto"><strong>{{ $c->title() }}</strong> · <code>{{ $c->serial_no }}</code> · {{ $c->issued_on->format('d M Y') }} · {{ $c->issuer?->name }}</span>
                @if($c->isRevoked())<span class="badge text-bg-danger">{{ __('Revoked') }}</span>@endif
                <a href="{{ route('branch.certificates.show', $c) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" aria-label="{{ __('Print') }}"><i class="bi bi-printer"></i></a>
                <a href="{{ route('branch.certificates.pdf', $c) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" aria-label="{{ __('PDF') }}"><i class="bi bi-file-earmark-pdf"></i></a>
            </li>
        @endforeach
    </ul>
</div>
@endif

@can('certificates.issue')
<div class="modal fade" id="certModal" tabindex="-1" aria-labelledby="certModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('branch.students.certificates.store', $student) }}" class="modal-content" x-data="{ type: 'study' }">
            @csrf
            <div class="modal-header"><h2 class="modal-title h6" id="certModalLabel">{{ __('Issue certificate') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12"><label class="form-label" for="c_type">{{ __('Certificate') }}</label>
                    <select id="c_type" name="type" class="form-select" x-model="type">
                        <option value="study">{{ __('Study Certificate') }}</option>
                        <option value="character">{{ __('Character Certificate') }}</option>
                        <option value="transfer">{{ __('Transfer Certificate') }}</option>
                        <option value="achievement">{{ __('Certificate of Achievement') }}</option>
                    </select></div>
                <template x-if="type === 'achievement'">
                    <div class="col-12"><label class="form-label" for="c_title">{{ __('Awarded for') }}</label>
                        <input id="c_title" name="title" class="form-control" maxlength="150" required placeholder="{{ __('e.g. First position in the inter-school science fair') }}"></div>
                </template>
                <template x-if="type === 'achievement'">
                    <div class="col-12"><label class="form-label" for="c_details">{{ __('Details') }}</label>
                        <textarea id="c_details" name="details" class="form-control" rows="2" maxlength="1000"></textarea></div>
                </template>
                <div class="col-md-6"><label class="form-label" for="c_conduct">{{ __('Conduct') }}</label>
                    <input id="c_conduct" name="conduct" class="form-control" value="{{ __('Good') }}" maxlength="100"></div>
                <template x-if="type === 'transfer'">
                    <div class="col-md-6"><label class="form-label" for="c_leaving">{{ __('Leaving date') }}</label>
                        <input id="c_leaving" type="date" name="leaving_date" class="form-control" value="{{ now()->toDateString() }}"></div>
                </template>
                <template x-if="type === 'transfer'">
                    <div class="col-12"><label class="form-label" for="c_reason">{{ __('Reason for leaving') }}</label>
                        <input id="c_reason" name="reason" class="form-control" maxlength="500" placeholder="{{ __('e.g. Family moved to Chattogram') }}"></div>
                </template>
                @if($student->isActive())
                    <template x-if="type === 'transfer'">
                        <div class="col-12 form-check ms-2">
                            <input class="form-check-input" type="checkbox" name="mark_left" value="1" id="c_markleft" required>
                            <label class="form-check-label" for="c_markleft">{{ __('Mark the student as withdrawn from the leaving date') }}</label>
                        </div>
                    </template>
                @endif
                <div class="col-12"><label class="form-label" for="c_remarks">{{ __('Remarks') }}</label>
                    <input id="c_remarks" name="remarks" class="form-control" maxlength="500"></div>
                <div class="col-12 small text-body-secondary">{{ __('Each certificate gets a serial number and a verification code that anyone can check on this branch’s website.') }}</div>
            </div>
            <div class="modal-footer"><button class="btn btn-brand">{{ __('Issue & print') }}</button></div>
        </form>
    </div>
</div>
@endcan

@if($student->isActive() && $otherBranches->isNotEmpty())
@can('students.transfer')
<div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('branch.students.transfer', $student) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h2 class="modal-title h6" id="transferModalLabel">{{ __('Transfer to another branch') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12"><label class="form-label" for="t_branch">{{ __('Target branch') }}</label>
                    <select id="t_branch" name="to_branch_id" class="form-select" required>
                        @foreach($otherBranches as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select></div>
                <div class="col-12"><label class="form-label" for="t_reason">{{ __('Reason') }}</label>
                    <textarea id="t_reason" name="reason" rows="2" class="form-control" required minlength="5" maxlength="1000"></textarea></div>
                <div class="col-12 small text-body-secondary">{{ __('The target branch accepts the request and places the student in a class there. This record then becomes “transferred”; its history stays here.') }}</div>
            </div>
            <div class="modal-footer"><button class="btn btn-brand">{{ __('Send request') }}</button></div>
        </form>
    </div>
</div>
@endcan
@endif

@if(! $student->isActive() && $student->status !== 'graduated')
@can('changeStatus', $student)
<div class="modal fade" id="readmitModal" tabindex="-1" aria-labelledby="readmitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('branch.students.readmit', $student) }}" class="modal-content"
              x-data="{ cls: '{{ $current?->school_class_id ?? $classes->first()?->id }}', classes: @js($classes->mapWithKeys(fn ($c) => [$c->id => ['groups' => (bool) $c->has_groups, 'sections' => $c->sections->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()]])) }">
            @csrf
            <div class="modal-header"><h2 class="modal-title h6" id="readmitModalLabel">{{ __('Re-admit student') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-6"><label class="form-label" for="r_session">{{ __('Session') }}</label>
                    <select id="r_session" name="academic_session_id" class="form-select" required>
                        @foreach($sessions as $s)<option value="{{ $s->id }}" @selected($s->is_current)>{{ $s->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-6"><label class="form-label" for="r_class">{{ __('Class') }}</label>
                    <select id="r_class" name="school_class_id" class="form-select" x-model="cls" required>
                        @foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-6"><label class="form-label" for="r_section">{{ __('Section') }}</label>
                    <select id="r_section" name="section_id" class="form-select" required>
                        <template x-for="s in (classes[cls]?.sections || [])" :key="s.id"><option :value="s.id" x-text="s.name"></option></template>
                    </select></div>
                <div class="col-md-6" x-show="classes[cls]?.groups"><label class="form-label" for="r_group">{{ __('Group') }}</label>
                    <select id="r_group" name="study_group_id" class="form-select">
                        <option value="">—</option>
                        @foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                    </select></div>
                <div class="col-12 small text-body-secondary">{{ __('The student keeps the same ID. Returning within the same session reopens the previous class, section and roll.') }}</div>
            </div>
            <div class="modal-footer"><button class="btn btn-brand">{{ __('Re-admit') }}</button></div>
        </form>
    </div>
</div>
@endcan
@endif
