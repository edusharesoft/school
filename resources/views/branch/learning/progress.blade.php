@extends($layout)
@section('title', __('Learning progress'))

@section('content')
<x-page-header :title="__('Learning progress')" :breadcrumbs="[__('Learning') => null, __('Learning progress') => null]" />

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5"><label class="form-label small mb-1" for="section_id">{{ __('Section') }}</label>
                <select id="section_id" name="section_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($sections as $s)<option value="{{ $s->id }}" @selected($sectionId === $s->id)>{{ $s->schoolClass?->name }} — {{ $s->name }}</option>@endforeach
                </select></div>
        </form>
    </div>
    @if($sections->isEmpty())
        <x-empty-state icon="people" :title="__('No sections to show')" :message="__('Teachers see the sections they teach.')" />
    @elseif($rows->isEmpty())
        <x-empty-state icon="people" :title="__('No students are enrolled in this section yet.')" />
    @else
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>{{ __('Roll') }}</th><th>{{ __('Student') }}</th><th class="text-end">{{ __('Work given') }}</th><th class="text-end">{{ __('Handed in') }}</th><th class="text-end">{{ __('Missing') }}</th><th class="text-end">{{ __('Average marks') }}</th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr>
                    <td>{{ bn_digits($r['roll']) }}</td>
                    <td><a class="fw-semibold text-decoration-none" href="{{ route($routePrefix.'learning.progress.student', $r['student']) }}">{{ $r['student']->name }}</a><div class="text-body-secondary">{{ $r['student']->student_code }}</div></td>
                    <td class="text-end">{{ bn_digits($r['total']) }}</td>
                    <td class="text-end">{{ bn_digits($r['done']) }}</td>
                    <td class="text-end @if($r['missing']) text-danger fw-semibold @endif">{{ bn_digits($r['missing']) }}</td>
                    <td class="text-end">{{ $r['average'] !== null ? bn_digits($r['average']).'%' : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
@endsection
