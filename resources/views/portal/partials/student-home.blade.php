@if(! $student)
    <div class="alert alert-warning">{{ __('Your login is not linked to a student record. Please contact the school office.') }}</div>
@else
    @php($en = $student->currentEnrollment)
    <div class="dp blue mb-4">
        <div class="dp-head"><h2><i class="fa-solid fa-id-card me-1"></i> {{ __('My class') }}</h2><span class="sub">{{ $en?->session?->name }}</span></div>
        <div class="dp-body">
            @if($en)
                <div class="row g-3 small">
                    <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Student ID') }}</div><strong>{{ $student->student_code }}</strong></div>
                    <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Class') }}</div><strong>{{ $en->schoolClass?->name }}</strong>@if($en->studyGroup) ({{ $en->studyGroup->name }})@endif</div>
                    <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Section / Roll') }}</div><strong>{{ $en->section?->name }} / {{ bn_digits($en->roll_no) }}</strong></div>
                    <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Attendance') }}</div><strong>{{ $attendance['pct'] !== null ? bn_digits($attendance['pct']).'%' : '—' }}</strong>
                        <div class="text-body-secondary">{{ __('Present') }} {{ bn_digits(($attendance['counts']['present'] ?? 0) + ($attendance['counts']['late'] ?? 0)) }} · {{ __('Absent') }} {{ bn_digits($attendance['counts']['absent'] ?? 0) }}</div></div>
                </div>
            @else
                <p class="text-body-secondary mb-0">{{ __('You are not enrolled in the current session.') }}</p>
            @endif
        </div>
    </div>

    @if($upcoming->isNotEmpty())
        <div class="dp orange mb-4">
            <div class="dp-head"><h2><i class="fa-solid fa-id-badge me-1"></i> {{ __('Upcoming exams') }}</h2></div>
            <div class="dp-body">
                @foreach($upcoming as $exam)
                    <div class="d-flex align-items-center border-bottom py-2">
                        <div class="me-auto"><strong>{{ $exam->name }}</strong><div class="small text-body-secondary">{{ $exam->starts_on?->format('d M Y') }}@if($exam->ends_on) – {{ $exam->ends_on->format('d M Y') }}@endif</div></div>
                        <a class="btn btn-sm btn-brand" href="{{ route('portal.student.admit-card', $exam) }}" target="_blank" rel="noopener">{{ __('Admit card') }}</a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="dp green">
        <div class="dp-head"><h2><i class="fa-solid fa-square-poll-vertical me-1"></i> {{ __('My results') }}</h2>
            <a href="{{ route('portal.student.results') }}" class="sub text-decoration-none">{{ __('All results') }}</a></div>
        <div class="dp-body">
            @include('portal.partials.result-rows', ['results' => $results, 'marksheetRoute' => 'portal.student.results.show'])
        </div>
    </div>
@endif
