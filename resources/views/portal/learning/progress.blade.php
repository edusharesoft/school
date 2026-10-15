@extends('layouts.portal')
@section('title', __('Homework & progress'))

@section('content')
<x-page-header :title="__('Homework & progress')" :breadcrumbs="$mode === 'parent' ? [__('My children') => route('portal.parent'), $student->name => null] : [__('Learning') => null]">
    <x-slot:actions>
        <a href="{{ route($routePrefix.'materials', $routeParams) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-folder2-open me-1"></i>{{ __('Learning materials') }}</a>
    </x-slot:actions>
</x-page-header>
@if($mode === 'parent')<p class="text-body-secondary">{{ $student->name }} · {{ $student->student_code }}</p>@endif
@include('portal.learning.partials.progress-body', ['progress' => $progress, 'assignmentUrl' => fn ($a) => route($routePrefix.'assignments.show', [...$routeParams, $a])])
@endsection
