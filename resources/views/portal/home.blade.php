@extends('layouts.portal')
@section('title', __(ucfirst($portal).' portal'))

@section('content')
<x-page-header :title="__('Welcome, :name', ['name' => $user->name])" :breadcrumbs="[$currentBranch->displayName() => null]" />

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel panel-body text-center">
            <span class="avatar mb-2" style="width:64px;height:64px;font-size:1.4rem">{{ $user->initials() }}</span>
            <h2 class="h5 mb-0">{{ $user->name }}</h2>
            <div class="text-body-secondary small">{{ $user->roleLabels() }}</div>
            @isset($employee)
                @if($employee)<div class="small mt-1"><code>{{ $employee->employee_code }}</code></div>@endif
            @endisset
            <div class="small mt-2">{{ $user->email }}<br>{{ $user->phone }}</div>
        </div>
    </div>

    <div class="col-lg-8">
        @if($portal === 'teacher')
            <div class="dp green mb-4">
                <div class="dp-head"><h2><i class="fa-solid fa-user-check me-1"></i> {{ __('My class registers today') }}</h2>
                    <span class="sub">{{ now()->format('d M Y') }}</span></div>
                <div class="dp-body">
                    @forelse($sections as $section)
                        <div class="d-flex align-items-center border-bottom py-2">
                            <div class="me-auto"><strong>{{ $section->schoolClass?->name }}</strong> — {{ $section->name }}</div>
                            @if(in_array($section->id, $taken, true))
                                <span class="badge text-bg-success me-2">{{ __('Taken') }}</span>
                            @else
                                <span class="badge text-bg-warning me-2">{{ __('Not taken') }}</span>
                            @endif
                            <a class="btn btn-sm btn-brand" href="{{ route('portal.teacher.attendance.students.register', $section) }}">{{ __('Open register') }}</a>
                        </div>
                    @empty
                        <p class="text-body-secondary mb-0">{{ __('You are not class teacher of any section yet. The branch office assigns class teachers under Academics → Classes & sections.') }}</p>
                    @endforelse
                </div>
            </div>
            <div class="dp blue">
                <div class="dp-head"><h2><i class="fa-solid fa-plane-departure me-1"></i> {{ __('Leave') }}</h2></div>
                <div class="dp-body d-flex align-items-center gap-3">
                    <span>{{ __('Pending applications') }}: <strong>{{ bn_digits($pendingLeaves) }}</strong></span>
                    <a href="{{ route('portal.teacher.leaves.create') }}" class="btn btn-sm btn-outline-primary ms-auto">{{ __('Apply for leave') }}</a>
                    <a href="{{ route('portal.teacher.leaves.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('My applications') }}</a>
                </div>
            </div>
        @elseif($portal === 'student')
            @include('portal.partials.student-home')
        @else
            @include('portal.partials.parent-home')
        @endif
    </div>
</div>
@endsection
