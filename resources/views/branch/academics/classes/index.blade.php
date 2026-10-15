@extends('layouts.branch')
@section('title', __('Classes & sections'))

@section('content')
<x-page-header :title="__('Classes & sections')" :breadcrumbs="[__('Academics') => null, __('Classes') => null]">
    <x-slot:actions>
        @can('create', App\Models\Academic\SchoolClass::class)
            <a href="{{ route('branch.academics.sections.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New section') }}</a>
            <a href="{{ route('branch.academics.classes.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New class') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<p class="small text-body-secondary">
    {{ $session ? __('Student counts are for the current session: :name', ['name' => $session->name]) : __('No current session is set — student counts include all sessions.') }}
</p>

@if($classes->isEmpty())
    <div class="card"><x-empty-state icon="building" :title="__('No classes yet')" :message="__('Add classes in promotion order (e.g. Six → Ten, XI, XII).')" /></div>
@else
    <div class="row g-3">
        @foreach($classes as $class)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 {{ $class->is_active ? '' : 'opacity-75' }}">
                    <div class="card-header bg-transparent d-flex align-items-center">
                        <div>
                            <div class="fw-semibold">{{ $class->displayName() }} <code class="small">{{ $class->code }}</code></div>
                            <div class="small text-body-secondary">{{ App\Models\Academic\SchoolClass::levelLabel($class->level) }} · {{ __('Order') }} {{ $class->numeric_order }}
                                @if($class->has_groups) · <span class="badge text-bg-info">{{ __('Groups') }}</span>@endif</div>
                        </div>
                        <x-row-actions :edit="route('branch.academics.classes.edit', $class)" :destroy="route('branch.academics.classes.destroy', $class)"
                            :can-edit="auth()->user()->can('update', $class)" :can-delete="auth()->user()->can('delete', $class)" class="ms-auto" />
                    </div>
                    <ul class="list-group list-group-flush">
                        @forelse($class->sections as $section)
                            <li class="list-group-item d-flex align-items-center small">
                                <span class="fw-semibold me-2">{{ $section->name }}</span>
                                <span class="text-body-secondary">{{ $section->classTeacher?->name ?? __('No class teacher') }}{{ $section->room ? ' · '.$section->room->name : '' }}</span>
                                <span class="ms-auto me-2 text-body-secondary">{{ __('Cap.') }} {{ $section->capacity }}</span>
                                @can('update', $section)
                                    <a href="{{ route('branch.academics.sections.edit', $section) }}" class="btn btn-sm btn-link p-0" aria-label="{{ __('Edit section') }}"><i class="bi bi-pencil"></i></a>
                                @endcan
                            </li>
                        @empty
                            <li class="list-group-item small text-body-secondary">{{ __('No sections yet.') }}
                                <a href="{{ route('branch.academics.sections.create', ['class' => $class->id]) }}">{{ __('Add one') }}</a></li>
                        @endforelse
                    </ul>
                    <div class="card-footer bg-transparent d-flex small">
                        <span>{{ __('Students') }}: <strong>{{ bn_digits($class->students_count) }}</strong></span>
                        <a href="{{ route('branch.academics.classes.subjects.index', $class) }}" class="ms-auto">{{ __('Subjects') }} ({{ $class->class_subjects_count }}) <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
