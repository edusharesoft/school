@extends($layout)
@section('title', $material->exists ? __('Edit material') : __('Add material'))

@section('content')
<x-page-header :title="$material->exists ? __('Edit material') : __('Add material')" :breadcrumbs="[__('Class materials') => route($routePrefix.'learning.materials.index'), ($material->exists ? __('Edit') : __('New')) => null]" />

<form method="POST" enctype="multipart/form-data" class="card" style="max-width: 900px"
      action="{{ $material->exists ? route($routePrefix.'learning.materials.update', $material) : route($routePrefix.'learning.materials.store') }}"
      x-data="{ type: @js(old('type', $material->type)), cls: @js((string) old('school_class_id', $material->school_class_id)), sections: @js($sections), subjects: @js($subjects) }">
    @csrf @if($material->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label" for="type">{{ __('Type') }}</label>
            @if($material->exists)
                <div class="form-control-plaintext fw-semibold">{{ __(Illuminate\Support\Str::headline($material->type)) }}</div>
            @else
                <select id="type" name="type" class="form-select" x-model="type">
                    @foreach(App\Models\Learning\LearningMaterial::TYPES as $t)<option value="{{ $t }}">{{ __(Illuminate\Support\Str::headline($t)) }}</option>@endforeach
                </select>
            @endif
        </div>
        <x-form.input name="title" :label="__('Title')" :value="$material->title" required col="col-md-8" maxlength="191" />

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
                    <option :value="s.id" x-text="s.name" :selected="String(s.id) === @js((string) old('section_id', $material->section_id))"></option>
                </template>
            </select>
            @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="subject_id">{{ __('Subject') }}</label>
            <select id="subject_id" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror">
                <option value="">{{ __('General / none') }}</option>
                <template x-for="s in subjects.filter(s => String(s.class) === String(cls))" :key="s.id">
                    <option :value="s.id" x-text="s.name" :selected="String(s.id) === @js((string) old('subject_id', $material->subject_id))"></option>
                </template>
            </select>
            @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label" for="description">{{ __('Description') }}</label>
            <textarea id="description" name="description" rows="3" maxlength="2000" class="form-control">{{ old('description', $material->description) }}</textarea>
        </div>

        <div class="col-12" x-show="type === 'document'">
            <label class="form-label" for="file">{{ __('File (PDF, Word, PowerPoint, Excel, image — max 20 MB)') }}</label>
            <input id="file" name="file" type="file" class="form-control @error('file') is-invalid @enderror" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.png,.jpg,.jpeg,.webp">
            @if($material->file_name)<div class="form-text">{{ __('Current file') }}: {{ $material->file_name }} — {{ __('upload a new one to replace it.') }}</div>@endif
            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12" x-show="type !== 'document'">
            <label class="form-label" for="url">{{ __('Link (YouTube, Vimeo, Google Drive, Zoom recording …)') }}</label>
            <input id="url" name="url" type="url" maxlength="500" placeholder="https://" value="{{ old('url', $material->url) }}" class="form-control @error('url') is-invalid @enderror">
            @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <x-form.switch name="is_published" :label="__('Share with students now (hidden materials are only visible to teachers)')" :checked="$material->exists ? $material->is_published : true" />
    </div>
    <x-form.actions :cancel="route($routePrefix.'learning.materials.index')" />
</form>
@endsection
