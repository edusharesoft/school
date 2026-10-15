@if(! $guardian)
    <div class="alert alert-warning">{{ __('Your login is not linked to a guardian record. Please contact the school office.') }}</div>
@else
    <div class="dp blue">
        <div class="dp-head"><h2><i class="fa-solid fa-children me-1"></i> {{ __('My children') }}</h2></div>
        <div class="dp-body">
            @forelse($children as $row)
                @php($s = $row['student'])
                @php($en = $s->currentEnrollment)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="avatar">{{ $s->initials() }}</span>
                        <div class="me-auto">
                            <div class="fw-semibold">{{ $s->name }} <span class="small text-body-secondary">{{ $s->student_code }}</span></div>
                            <div class="small text-body-secondary">@if($en){{ $en->schoolClass?->name }} · {{ __('Section') }} {{ $en->section?->name }} · {{ __('Roll') }} {{ bn_digits($en->roll_no) }}@else{{ __('Not enrolled in the current session.') }}@endif</div>
                        </div>
                        <div class="text-center px-2"><div class="small text-body-secondary">{{ __('Attendance') }}</div><strong>{{ $row['attendance']['pct'] !== null ? bn_digits($row['attendance']['pct']).'%' : '—' }}</strong></div>
                        <div class="text-center px-2"><div class="small text-body-secondary">{{ __('Latest GPA') }}</div><strong>{{ $row['result']?->gpaLabel() ?? '—' }}</strong></div>
                        <div class="text-center px-2"><div class="small text-body-secondary">{{ __('Fees due') }}</div><strong @class(['text-danger' => $row['due'] > 0])>৳{{ number_format($row['due'], 2) }}</strong></div>
                        <a href="{{ route('portal.parent.results', $s) }}" class="btn btn-sm btn-outline-primary">{{ __('Results') }}</a>
                        <a href="{{ route('portal.parent.fees', $s) }}" class="btn btn-sm btn-outline-success">{{ __('Fees') }}</a>
                        <a href="{{ route('portal.parent.learning', $s) }}" class="btn btn-sm btn-outline-warning">{{ __('Homework') }}</a>
                        <a href="{{ route('portal.parent.library', $s) }}" class="btn btn-sm btn-outline-success">{{ __('Library') }}</a>
                        <a href="{{ route('portal.parent.facilities', $s) }}" class="btn btn-sm btn-outline-secondary">{{ __('Transport & hostel') }}</a>
                        <a href="{{ route('portal.parent.online-classes', $s) }}" class="btn btn-sm btn-outline-primary">{{ __('Classes') }}</a>
                        <a href="{{ route('portal.parent.quizzes', $s) }}" class="btn btn-sm btn-outline-secondary">{{ __('Quizzes') }}</a>
                    </div>
                    @if($row['result'])
                        <div class="small mt-2">{{ $row['result']->examination?->name }}: <span class="badge text-bg-{{ $row['result']->tone() }}">{{ $row['result']->grade ?? __(ucfirst($row['result']->status)) }}</span>
                            · {{ __('Merit') }} {{ $row['result']->class_position ?? '—' }}
                            · <a href="{{ route('portal.parent.results.show', $row['result']) }}" target="_blank" rel="noopener">{{ __('Mark sheet') }}</a>
                            · <a href="{{ route('portal.parent.results.pdf', $row['result']) }}">PDF</a></div>
                    @endif
                </div>
            @empty
                <p class="text-body-secondary mb-0">{{ __('No children are linked to your account yet. Please contact the school office.') }}</p>
            @endforelse
        </div>
    </div>
@endif
