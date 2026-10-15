@extends('layouts.central')
@section('title', __('Academic years'))

@section('content')
<x-page-header :title="__('Academic years')" :breadcrumbs="[__('Central') => route('central.dashboard'), __('Academic years') => null]">
    <x-slot:actions>
        @can('create', App\Models\Academic\AcademicYear::class)
            <a href="{{ route('central.academic-years.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New academic year') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    @if($years->isEmpty())
        <x-empty-state icon="calendar" :title="__('No academic years yet')" :message="__('Create the year first; each branch then opens its own session inside it.')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Year') }}</th><th>{{ __('Institution') }}</th><th>{{ __('Period') }}</th><th>{{ __('Branch sessions') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($years as $y)
                    <tr>
                        <td class="fw-semibold">{{ $y->name }} @if($y->is_current)<span class="badge text-bg-success ms-1">{{ __('Current') }}</span>@endif</td>
                        <td>{{ $y->institution?->name }}</td>
                        <td class="small">{{ $y->starts_on->format('d M Y') }} – {{ $y->ends_on->format('d M Y') }}</td>
                        <td>{{ $y->sessions_count }}</td>
                        <td><x-row-actions :edit="route('central.academic-years.edit', $y)" :can-edit="auth()->user()->can('update', $y)" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $years->links() }}</div>
    @endif
</div>
@endsection
