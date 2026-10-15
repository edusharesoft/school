@extends($layout ?? 'layouts.central')
@section('title', $post->exists ? __('Edit post') : __('New post'))

@section('content')
@php($rp = $routePrefix ?? 'central.website.')
<x-page-header :title="$post->exists ? __('Edit post') : ($post->type === 'notice' ? __('New notice') : __('New article'))"
    :breadcrumbs="[__('News & notices') => route($rp.'posts.index'), ($post->exists ? $post->title : __('New')) => null]" />

<form method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route($rp.'posts.update', $post) : route($rp.'posts.store') }}" class="card">
    @csrf
    @if($post->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        @if($post->exists)
            <div class="col-md-3"><label class="form-label">{{ __('Type') }}</label><div class="form-control-plaintext">{{ $post->type === 'notice' ? __('Notice') : __('Article') }}</div></div>
        @else
            <x-form.select name="type" :label="__('Type')" :value="$post->type" required col="col-md-3" :options="['news' => __('Article (News & Blog)'), 'notice' => __('Notice')]" />
        @endif
        <x-form.input name="title" :label="__('Title')" :value="$post->title" required maxlength="191" col="col-md-9" />
        <x-form.input name="title_bn" :label="__('Title (Bangla)')" :value="$post->title_bn" maxlength="191" class="bn" />
        <x-form.input name="slug" :label="__('Address')" :value="$post->slug" maxlength="140" :help="__('Leave empty to create it from the title.')" />
        <x-form.input name="author_name" :label="__('Author')" :value="$post->author_name" col="col-md-4" maxlength="120" />
        <x-form.input name="published_at" type="datetime-local" :label="__('Publish date')" :value="$post->published_at?->format('Y-m-d\TH:i')" col="col-md-4" :help="__('A future date keeps the post hidden until then.')" />
        <x-form.select name="status" :label="__('Status')" :value="$post->status" required col="col-md-4" :options="['published' => __('Published'), 'draft' => __('Draft')]" />
        <x-form.input name="excerpt" :label="__('Summary')" :value="$post->excerpt" col="col-12" maxlength="400" />
        <div class="col-12"><label class="form-label" for="body">{{ __('Content') }}</label>
            <textarea id="body" name="body" rows="12" class="form-control font-monospace @error('body') is-invalid @enderror">{{ old('body', $post->body) }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('Markdown is supported. Raw HTML is removed for safety.') }}</div></div>
        <div class="col-12"><label class="form-label" for="body_bn">{{ __('Content (Bangla)') }}</label>
            <textarea id="body_bn" name="body_bn" rows="6" class="form-control bn font-monospace">{{ old('body_bn', $post->body_bn) }}</textarea></div>
        <div class="col-md-6"><label class="form-label" for="image">{{ __('Picture') }}</label>
            <input id="image" type="file" name="image" accept="image/png,image/jpeg,image/webp" class="form-control @error('image') is-invalid @enderror">
            @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($post->image_path)<div class="form-check mt-1"><input type="hidden" name="remove_image" value="0"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="rm_img"><label class="form-check-label" for="rm_img">{{ __('Remove the current picture') }}</label></div>@endif</div>
        <div class="col-md-6"><label class="form-label" for="attachment">{{ __('PDF attachment') }}</label>
            <input id="attachment" type="file" name="attachment" accept="application/pdf" class="form-control @error('attachment') is-invalid @enderror">
            @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($post->attachment_path)<div class="form-check mt-1"><input type="hidden" name="remove_attachment" value="0"><input class="form-check-input" type="checkbox" name="remove_attachment" value="1" id="rm_att"><label class="form-check-label" for="rm_att">{{ __('Remove the current attachment') }}</label></div>@endif</div>
    </div>
    <x-form.actions :cancel="route($rp.'posts.index')" />
</form>
@endsection
