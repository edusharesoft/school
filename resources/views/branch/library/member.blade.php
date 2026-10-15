@extends('layouts.branch')
@section('title', __('Library account'))

@section('content')
<x-page-header :title="__('Library account')" :breadcrumbs="[__('Library') => route('branch.library.index'), __('Library account') => null]">
    @can('library.issue')
        <x-slot:actions><a href="{{ route('branch.library.desk', ['member' => $type.':'.$member->id]) }}" class="btn btn-brand btn-sm">{{ __('Open at the desk') }}</a></x-slot:actions>
    @endcan
</x-page-header>
<div style="max-width: 760px">@include('branch.library.partials.account', ['returnable' => auth()->user()->can('library.issue')])</div>
@endsection
