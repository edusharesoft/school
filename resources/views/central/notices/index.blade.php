@extends('layouts.central')
@section('title', __('Institution notices'))

@section('content')
<x-page-header :title="__('Institution notices')" :breadcrumbs="[__('Central') => route('central.dashboard'), __('Institution notices') => null]">
    @can('notices.create')
        <x-slot:actions><a href="{{ route('central.notices.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New institution notice') }}</a></x-slot:actions>
    @endcan
</x-page-header>

<div class="alert alert-light border small"><i class="fa-solid fa-circle-info text-primary me-1"></i>
    {{ __('A notice written here is copied to every chosen branch. Each branch shows, distributes and archives its own copy.') }}</div>

<div class="card">
    @if($groups->isEmpty())
        <x-empty-state icon="megaphone" :title="__('No institution notices yet')" />
    @else
        <div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Audience') }}</th><th>{{ __('Branches') }}</th><th class="text-end">{{ __('Read') }}</th></tr></thead>
            <tbody>
            @foreach($groups as $g)
                <tr>
                    <td>@if($g['notice']->is_emergency)<span class="badge text-bg-danger me-1">{{ __('Emergency') }}</span>@endif<span class="fw-semibold">{{ $g['notice']->title }}</span>
                        <div class="text-body-secondary">{{ __(ucfirst($g['notice']->category)) }} · {{ $g['notice']->created_at->format('d M Y H:i') }}</div></td>
                    <td>{{ __(Illuminate\Support\Str::headline($g['notice']->audience)) }}</td>
                    <td>@foreach($g['copies'] as $c)<span class="badge text-bg-{{ $c->tone() }} me-1" title="{{ __(ucfirst($c->displayStatus())) }}">{{ $c->branch?->code }}</span>@endforeach</td>
                    <td class="text-end">{{ $g['copies']->sum('reads_count') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
@endsection
