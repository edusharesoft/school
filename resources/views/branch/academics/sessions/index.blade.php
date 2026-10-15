@extends('layouts.branch')
@section('title', __('Academic sessions'))

@section('content')
<x-page-header :title="__('Academic sessions')" :breadcrumbs="[__('Academics') => null, __('Sessions') => null]">
    <x-slot:actions>
        @can('create', App\Models\Academic\AcademicSession::class)
            <a href="{{ route('branch.academics.sessions.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New session') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    @if($sessions->isEmpty())
        <x-empty-state icon="calendar3" :title="__('No academic sessions yet')" :message="__('Create a session (e.g. 2026) and mark it current before admitting students.')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Session') }}</th><th>{{ __('Academic year') }}</th><th>{{ __('Period') }}</th><th>{{ __('Terms') }}</th><th>{{ __('Enrollments') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($sessions as $s)
                    <tr>
                        <td class="fw-semibold">{{ $s->name }} @if($s->is_current)<span class="badge text-bg-success ms-1">{{ __('Current') }}</span>@endif</td>
                        <td>{{ $s->academicYear?->name }}</td>
                        <td class="small">{{ $s->starts_on->format('d M Y') }} – {{ $s->ends_on->format('d M Y') }}</td>
                        <td class="small">{{ $s->terms->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td>{{ bn_digits($s->enrollments_count) }}</td>
                        <td><x-status-badge :status="$s->status === 'open' ? 'active' : 'inactive'" /></td>
                        <td><x-row-actions :edit="route('branch.academics.sessions.edit', $s)" :can-edit="auth()->user()->can('update', $s)" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $sessions->links() }}</div>
    @endif
</div>
@endsection
