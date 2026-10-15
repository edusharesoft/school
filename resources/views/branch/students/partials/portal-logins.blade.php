{{-- Student & parent portal logins (student profile). --}}
@php
    $accounts = collect([['kind' => 'student', 'label' => __('Student'), 'name' => $student->name, 'user' => $student->user, 'login' => strtolower($student->student_code), 'action' => route('branch.students.portal.student', $student)]])
    ->concat($student->guardians->map(fn ($g) => ['kind' => 'parent', 'label' => __(ucfirst($g->pivot->relation)), 'name' => $g->name, 'user' => $g->user, 'login' => $g->phone, 'action' => route('branch.students.portal.guardian', [$student, $g])]));
@endphp
<div class="card mb-3">
    <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Portal logins') }}</h2></div>
    <ul class="list-group list-group-flush">
        @foreach($accounts as $a)
            <li class="list-group-item">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="me-auto">
                        <div class="fw-semibold">{{ $a['name'] }} <span class="badge text-bg-light border">{{ $a['label'] }}</span></div>
                        <div class="small text-body-secondary">
                            @if($a['user'])
                                <i class="bi bi-check-circle text-success"></i> {{ __('Signs in with') }} <code>{{ $a['kind'] === 'student' ? $a['user']->username : $a['user']->phone }}</code>
                                · {{ __('Last login') }}: {{ $a['user']->last_login_at?->diffForHumans() ?? __('never') }}
                            @else
                                {{ __('No login yet — will sign in with') }} <code>{{ $a['login'] ?: '—' }}</code>
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ $a['user'] ? route('branch.students.portal.password', [$student, $a['user']]) : $a['action'] }}" class="d-flex gap-2">
                        @csrf
                        <input type="password" name="password" class="form-control form-control-sm" placeholder="{{ $a['user'] ? __('New password') : __('Password') }}" required minlength="8" autocomplete="new-password" aria-label="{{ __('Password') }}" style="width:160px">
                        @unless($a['user'])
                            <input type="email" name="email" class="form-control form-control-sm" placeholder="{{ __('E-mail (optional)') }}" aria-label="{{ __('E-mail') }}" style="width:170px">
                        @endunless
                        <button class="btn btn-sm {{ $a['user'] ? 'btn-outline-secondary' : 'btn-brand' }} text-nowrap" @disabled(! $a['user'] && ! $a['login'])>{{ $a['user'] ? __('Reset password') : __('Create login') }}</button>
                    </form>
                </div>
            </li>
        @endforeach
    </ul>
    @if($errors->hasAny(['password', 'email']))
        <div class="card-body border-top text-danger small">{{ $errors->first('password') ?: $errors->first('email') }}</div>
    @endif
    <div class="card-footer bg-transparent small text-body-secondary">{{ __('Students sign in with their student ID, guardians with their mobile number. At least 8 characters with letters and numbers.') }}</div>
</div>
