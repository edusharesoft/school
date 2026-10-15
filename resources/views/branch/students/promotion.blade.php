@extends('layouts.branch')
@section('title', __('Promotion'))

@section('content')
<x-page-header :title="__('Promote students')" :breadcrumbs="[__('Students') => route('branch.students.index'), __('Promotion') => null]" />

<form method="GET" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <x-form.select name="session_id" :label="__('From session')" col="col-md-3" :value="request('session_id')" :placeholder="__('Choose…')"
            :options="$sessions->pluck('name', 'id')" />
        <x-form.select name="class_id" :label="__('From class')" col="col-md-3" :value="request('class_id')" :placeholder="__('Choose…')"
            :options="$classes->pluck('name', 'id')" />
        <x-form.select name="section_id" :label="__('Section (optional)')" col="col-md-3" :value="request('section_id')" :placeholder="__('All sections')"
            :options="optional($classes->firstWhere('id', (int) request('class_id')))->sections?->pluck('name', 'id') ?? []" />
        <div class="col-md-3"><button class="btn btn-outline-secondary w-100"><i class="bi bi-list-check me-1"></i>{{ __('Load students') }}</button></div>
    </div>
</form>

@if(session('promotion_skipped'))
    <div class="alert alert-warning small">
        <strong>{{ __('Skipped') }}:</strong>
        <ul class="mb-0">@foreach(session('promotion_skipped') as $id => $reason)<li>#{{ $id }} — {{ $reason }}</li>@endforeach</ul>
    </div>
@endif

@if(request('class_id'))
    <form method="POST" action="{{ route('branch.students.promotion.store') }}" x-data="{ all: true }"
          data-confirm="{{ __('Move the selected students to the target session and class?') }}">
        @csrf
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    @if($enrollments->isEmpty())
                        <x-empty-state icon="people" :title="__('No enrolled students in this class and session')" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr>
                                    <th><input class="form-check-input" type="checkbox" x-model="all" x-on:change="$root.querySelectorAll('[name=\'enrollment_ids[]\']').forEach(c => c.checked = all)" aria-label="{{ __('Select all') }}"></th>
                                    <th>{{ __('Roll') }}</th><th>{{ __('Student') }}</th><th>{{ __('ID') }}</th><th>{{ __('Section') }}</th>
                                </tr></thead>
                                <tbody>
                                @foreach($enrollments as $e)
                                    <tr>
                                        <td><input class="form-check-input" type="checkbox" name="enrollment_ids[]" value="{{ $e->id }}" checked aria-label="{{ $e->student?->name }}"></td>
                                        <td>{{ $e->roll_no }}</td><td>{{ $e->student?->name }}</td><td><code>{{ $e->student?->student_code }}</code></td><td>{{ $e->section?->name }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Move selected to') }}</h2></div>
                    <div class="card-body row g-2">
                        <x-form.select name="target_session_id" :label="__('Target session')" required col="col-12" :placeholder="__('Choose…')"
                            :options="$sessions->where('status', 'open')->pluck('name', 'id')" />
                        <x-form.select name="outcome" :label="__('Outcome')" col="col-12" value="promoted"
                            :options="['promoted' => __('Promoted to next class'), 'repeated' => __('Retained (repeat class)')]" />
                        <x-form.select name="target_class_id" :label="__('Target class')" required col="col-12" :value="$suggested?->id" :placeholder="__('Choose…')"
                            :options="$classes->pluck('name', 'id')" :help="__('For retained students choose the same class.')" />
                        <x-form.select name="target_section_id" :label="__('Target section')" col="col-12" :placeholder="__('Same section name')"
                            :options="$classes->flatMap(fn ($c) => $c->sections->mapWithKeys(fn ($s) => [$s->id => $c->name.' — '.$s->name]))" />
                        <div class="col-12 small text-body-secondary">{{ __('Rolls in the target section are assigned automatically. Students already enrolled in the target session are skipped.') }}</div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <button class="btn btn-brand w-100" @disabled($enrollments->isEmpty())>{{ __('Move students') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endif
@endsection
