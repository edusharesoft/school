@extends($layout)
@section('title', $class->exists ? __('Edit class') : __('Schedule a class'))

@section('content')
<x-page-header :title="$class->exists ? __('Edit class') : __('Schedule a class')" :breadcrumbs="[__('Online & offline classes') => route($routePrefix.'learning.online-classes.index'), ($class->exists ? __('Edit') : __('New')) => null]" />

<form method="POST" class="card" style="max-width: 900px"
      action="{{ $class->exists ? route($routePrefix.'learning.online-classes.update', $class) : route($routePrefix.'learning.online-classes.store') }}"
      x-data="{ mode: @js(old('mode', $class->mode)), provider: @js(old('provider', $class->provider ?? 'manual')), cls: @js((string) old('school_class_id', $class->school_class_id)), sections: @js($sections), subjects: @js($subjects) }">
    @csrf @if($class->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label" for="mode">{{ __('Mode') }}</label>
            <select id="mode" name="mode" class="form-select" x-model="mode">
                <option value="online">{{ __('Online (live link)') }}</option>
                <option value="offline">{{ __('Offline (in school)') }}</option>
            </select>
        </div>
        <x-form.input name="title" :label="__('Title')" :value="$class->title" required col="col-md-8" maxlength="191" />

        <div class="col-md-4">
            <label class="form-label" for="school_class_id">{{ __('Class') }} <span class="text-danger">*</span></label>
            <select id="school_class_id" name="school_class_id" class="form-select @error('school_class_id') is-invalid @enderror" required x-model="cls">
                <option value="">{{ __('Choose…') }}</option>
                @foreach($classes as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            @error('school_class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="section_id">{{ __('Section') }}@if($sectionRequired) <span class="text-danger">*</span>@endif</label>
            <select id="section_id" name="section_id" class="form-select @error('section_id') is-invalid @enderror" @required($sectionRequired)>
                @unless($sectionRequired)<option value="">{{ __('Whole class') }}</option>@endunless
                <template x-for="s in sections.filter(s => String(s.class) === String(cls))" :key="s.id">
                    <option :value="s.id" x-text="s.name" :selected="String(s.id) === @js((string) old('section_id', $class->section_id))"></option>
                </template>
            </select>
            @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="subject_id">{{ __('Subject') }}</label>
            <select id="subject_id" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror">
                <option value="">{{ __('General / none') }}</option>
                <template x-for="s in subjects.filter(s => String(s.class) === String(cls))" :key="s.id">
                    <option :value="s.id" x-text="s.name" :selected="String(s.id) === @js((string) old('subject_id', $class->subject_id))"></option>
                </template>
            </select>
            @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <x-form.input name="starts_at" type="datetime-local" :label="__('Starts')" :value="$class->starts_at?->format('Y-m-d\TH:i')" required col="col-md-4" />
        <x-form.input name="ends_at" type="datetime-local" :label="__('Ends')" :value="$class->ends_at?->format('Y-m-d\TH:i')" required col="col-md-4" />
        @if($teachers->isNotEmpty())
            <div class="col-md-4">
                <label class="form-label" for="teacher_id">{{ __('Teacher') }}</label>
                <select id="teacher_id" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror">
                    <option value="">{{ __('Me') }}</option>
                    @foreach($teachers as $id => $name)<option value="{{ $id }}" @selected((int) old('teacher_id', $class->teacher_id) === $id)>{{ $name }}</option>@endforeach
                </select>
                @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endif

        <div class="col-12" x-show="mode === 'online'">
            <label class="form-label" for="provider">{{ __('Meeting platform') }}</label>
            <select id="provider" name="provider" class="form-select @error('provider') is-invalid @enderror" x-model="provider" :disabled="mode !== 'online'">
                @foreach($providers as $p)<option value="{{ $p }}">{{ ['zoom' => 'Zoom', 'google_meet' => 'Google Meet', 'manual' => __('Paste a link myself')][$p] }}</option>@endforeach
                @if($class->provider && ! in_array($class->provider, $providers, true))<option value="{{ $class->provider }}">{{ $class->providerLabel() }} ({{ __('not connected') }})</option>@endif
            </select>
            @error('provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text" x-show="provider !== 'manual'">{{ __('The meeting is created automatically and the link is shared with students and guardians.') }}</div>
            @cannot('integrations.view')@else
                @if(count($providers) < 3)<div class="form-text">{{ __('Zoom and Google Meet appear here once connected under') }} <a href="{{ route('branch.learning.integrations.index') }}">{{ __('Zoom & Google Meet') }}</a>.</div>@endif
            @endcannot
        </div>
        <template x-if="mode === 'online' && provider === 'manual'">
            <div class="row g-3 m-0 p-0">
                <x-form.input name="join_url" type="url" :label="__('Meeting link')" :value="$class->join_url" required col="col-md-6" placeholder="https://" />
                <x-form.input name="meeting_id" :label="__('Meeting ID')" :value="$class->meeting_id" col="col-md-3" />
                <x-form.input name="passcode" :label="__('Passcode')" :value="$class->passcode" col="col-md-3" />
            </div>
        </template>
        <div class="col-md-6" x-show="mode === 'offline'">
            <label class="form-label" for="room">{{ __('Room') }}</label>
            <input id="room" name="room" maxlength="120" value="{{ old('room', $class->room) }}" class="form-control @error('room') is-invalid @enderror" :disabled="mode !== 'offline'">
            @error('room')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="description">{{ __('Description') }}</label>
            <textarea id="description" name="description" rows="3" maxlength="2000" class="form-control">{{ old('description', $class->description) }}</textarea>
        </div>
    </div>
    <x-form.actions :cancel="route($routePrefix.'learning.online-classes.index')" />
</form>
@endsection
