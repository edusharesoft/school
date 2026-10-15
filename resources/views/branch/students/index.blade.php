@extends('layouts.branch')
@section('title', __('Students'))

@section('content')
<x-page-header :title="__('Students')" :breadcrumbs="[$currentBranch->name => route('home.redirect'), __('Students') => null]">
    <x-slot:actions>
        @can('export', App\Models\Students\Student::class)
            <a href="{{ route('branch.students.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-filetype-csv me-1"></i>{{ __('Export CSV') }}</a>
        @endcan
        @can('import', App\Models\Students\Student::class)
            <a href="{{ route('branch.students.import') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upload me-1"></i>{{ __('Import') }}</a>
        @endcan
        @can('promote', App\Models\Students\Student::class)
            <a href="{{ route('branch.students.promotion') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-up-circle me-1"></i>{{ __('Promotion') }}</a>
        @endcan
        @can('create', App\Models\Students\Student::class)
            <a href="{{ route('branch.students.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-person-plus me-1"></i>{{ __('New admission') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2" x-data>
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Name, student ID, birth reg., mobile') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-6 col-md-2">
                <select name="session_id" class="form-select form-select-sm" aria-label="{{ __('Session') }}">
                    @foreach($sessions as $s)<option value="{{ $s->id }}" @selected($sessionId === $s->id)>{{ $s->name }}{{ $s->is_current ? ' ★' : '' }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="class_id" class="form-select form-select-sm" aria-label="{{ __('Class') }}" x-on:change="$el.form.section_id && ($el.form.section_id.value = ''); $el.form.submit()">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach($classes as $c)<option value="{{ $c->id }}" @selected((int) request('class_id') === $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <select name="section_id" class="form-select form-select-sm" aria-label="{{ __('Section') }}" @disabled($sections->isEmpty())>
                    <option value="">{{ __('Sec.') }}</option>
                    @foreach($sections as $s)<option value="{{ $s->id }}" @selected((int) request('section_id') === $s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-1"><input type="number" min="1" name="roll_no" value="{{ request('roll_no') }}" class="form-control form-control-sm" placeholder="{{ __('Roll') }}" aria-label="{{ __('Roll') }}"></div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                    @foreach(array_merge(['active'], array_diff(App\Models\Students\Student::STATUSES, ['active']), ['all']) as $st)
                        <option value="{{ $st }}" @selected(request('status', 'active') === $st)>{{ __(ucfirst($st)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button></div>
        </form>
    </div>

    @if($students->isEmpty())
        <x-empty-state icon="people" :title="__('No students match these filters')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Student') }}</th><th>{{ __('ID') }}</th><th>{{ __('Class') }}</th><th>{{ __('Section') }}</th><th>{{ __('Roll') }}</th><th>{{ __('Gender') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($students as $st)
                    @php($en = $st->enrollments->first())
                    <tr>
                        <td class="d-flex align-items-center gap-2">
                            <span class="avatar">{{ $st->initials() }}</span>
                            <div><a href="{{ route('branch.students.show', $st) }}" class="fw-semibold text-decoration-none">{{ $st->name }}</a>
                                <div class="small text-body-secondary bn">{{ $st->name_bn }}</div></div>
                        </td>
                        <td><code>{{ $st->student_code }}</code></td>
                        <td>{{ $en?->schoolClass?->name ?? '—' }}{{ $en?->studyGroup ? ' ('.$en->studyGroup->name.')' : '' }}</td>
                        <td>{{ $en?->section?->name ?? '—' }}</td>
                        <td>{{ $en ? bn_digits($en->roll_no) : '—' }}</td>
                        <td>{{ __(ucfirst($st->gender)) }}</td>
                        <td><x-status-badge :status="$st->status" /></td>
                        <td class="text-end"><a href="{{ route('branch.students.show', $st) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Open') }}"><i class="bi bi-chevron-right"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex align-items-center">
            <span class="small text-body-secondary me-auto">{{ __('Total') }}: {{ bn_digits($students->total()) }}</span>
            {{ $students->links() }}
        </div>
    @endif
</div>
@endsection
