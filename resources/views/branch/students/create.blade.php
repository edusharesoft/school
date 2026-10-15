@extends('layouts.branch')
@section('title', __('New admission'))

@php
    $current = $sessions->firstWhere('is_current', true) ?? $sessions->first();
    $guardians = old('guardians', [['relation' => 'father', 'is_primary' => 1], ['relation' => 'mother']]);
@endphp

@section('content')
<x-page-header :title="__('New admission')" :breadcrumbs="[__('Students') => route('branch.students.index'), __('New admission') => null]" />

@if($sessions->isEmpty() || $classes->isEmpty())
    <div class="alert alert-warning">{{ __('Set up an open academic session and at least one class with a section before admitting students.') }}</div>
@endif

<form method="POST" action="{{ route('branch.students.store') }}" enctype="multipart/form-data"
      x-data="admission(@js($classes->map(fn ($c) => ['id' => $c->id, 'has_groups' => $c->has_groups, 'sections' => $c->sections->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'capacity' => $s->capacity])])), @js((int) old('school_class_id')), @js((int) old('section_id')))">
    @csrf

    <div class="card mb-3">
        <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Enrollment') }}</h2></div>
        <div class="card-body row g-3">
            <x-form.select name="academic_session_id" :label="__('Session')" required col="col-md-3" :value="$current?->id"
                :options="$sessions->pluck('name', 'id')" />
            <div class="col-md-3">
                <label class="form-label" for="school_class_id">{{ __('Class') }} <span class="text-danger">*</span></label>
                <select id="school_class_id" name="school_class_id" class="form-select @error('school_class_id') is-invalid @enderror" x-model.number="classId" required>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->displayName() }}</option>@endforeach
                </select>
                @error('school_class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label" for="section_id">{{ __('Section') }} <span class="text-danger">*</span></label>
                <select id="section_id" name="section_id" class="form-select @error('section_id') is-invalid @enderror" x-model.number="sectionId" required>
                    <option value="">{{ __('Choose…') }}</option>
                    <template x-for="s in sections" :key="s.id"><option :value="s.id" x-text="s.name" :selected="s.id === sectionId"></option></template>
                </select>
                @error('section_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2" x-show="hasGroups" x-cloak>
                <label class="form-label" for="study_group_id">{{ __('Group') }}</label>
                <select id="study_group_id" name="study_group_id" class="form-select @error('study_group_id') is-invalid @enderror">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach($groups as $g)<option value="{{ $g->id }}" @selected((int) old('study_group_id') === $g->id)>{{ $g->name }}</option>@endforeach
                </select>
                @error('study_group_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <x-form.input name="roll_no" type="number" min="1" :label="__('Roll no.')" col="col-md-2" :help="__('Blank = next available')" />
            @error('academic_session_id')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
        </div>
    </div>

    @include('branch.students.partials.profile-fields')

    <div class="card mb-3">
        <div class="card-header bg-transparent d-flex align-items-center">
            <h2 class="h6 mb-0">{{ __('Parents / guardians') }}</h2>
            <span class="ms-auto small text-body-secondary">{{ __('An existing guardian with the same mobile number is reused (siblings).') }}</span>
        </div>
        <div class="card-body">
            @error('guardians')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
            @foreach([0, 1, 2] as $i)
                <div class="row g-2 {{ $i ? 'mt-2 pt-2 border-top' : '' }}">
                    <x-form.input :name="'guardians['.$i.'][name]'" :label="__('Name')" :value="$guardians[$i]['name'] ?? null" col="col-md-3" :required="$i === 0" />
                    <x-form.input :name="'guardians['.$i.'][phone]'" :label="__('Mobile')" :value="$guardians[$i]['phone'] ?? null" col="col-md-2" placeholder="+8801…" :required="$i === 0" />
                    <x-form.select :name="'guardians['.$i.'][relation]'" :label="__('Relation')" :value="$guardians[$i]['relation'] ?? 'other'" col="col-md-2"
                        :options="collect($relations)->mapWithKeys(fn ($r) => [$r => __(ucfirst($r))])" />
                    <x-form.input :name="'guardians['.$i.'][occupation]'" :label="__('Occupation')" :value="$guardians[$i]['occupation'] ?? null" col="col-md-2" />
                    <x-form.input :name="'guardians['.$i.'][email]'" type="email" :label="__('E-mail')" :value="$guardians[$i]['email'] ?? null" col="col-md-2" />
                    <div class="col-md-1 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="primary_guardian" value="{{ $i }}" id="pg{{ $i }}"
                                @checked(! empty($guardians[$i]['is_primary'])) x-on:change="setPrimary({{ $i }})">
                            <label class="form-check-label small" for="pg{{ $i }}">{{ __('Primary') }}</label>
                            <input type="hidden" name="guardians[{{ $i }}][is_primary]" value="{{ ! empty($guardians[$i]['is_primary']) ? 1 : 0 }}" data-primary="{{ $i }}">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-brand" type="submit"><i class="bi bi-person-check me-1"></i>{{ __('Admit student') }}</button>
        <a href="{{ route('branch.students.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('admission', (classes, classId, sectionId) => ({
            classes, classId: classId || '', sectionId: sectionId || '',
            get current() { return this.classes.find(c => c.id === Number(this.classId)); },
            get sections() { return this.current ? this.current.sections : []; },
            get hasGroups() { return this.current ? this.current.has_groups : false; },
            setPrimary(i) { document.querySelectorAll('[data-primary]').forEach(el => { el.value = Number(el.dataset.primary) === i ? 1 : 0; }); },
        }));
    });
</script>
@endpush
@endsection
