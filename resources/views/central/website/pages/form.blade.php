@extends($layout ?? 'layouts.central')
@section('title', $page->exists ? __('Edit page') : __('New page'))

@section('content')
@php($rp = $routePrefix ?? 'central.website.')
<x-page-header :title="$page->exists ? __('Edit page') : __('New page')"
    :breadcrumbs="[__('Pages') => route($rp.'pages.index'), ($page->exists ? $page->title : __('New')) => null]" />

<form method="POST" action="{{ $page->exists ? route($rp.'pages.update', $page) : route($rp.'pages.store') }}" class="card">
    @csrf
    @if($page->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="title" :label="__('Title')" :value="$page->title" required maxlength="191" />
        <x-form.input name="title_bn" :label="__('Title (Bangla)')" :value="$page->title_bn" maxlength="191" class="bn" />
        <x-form.input name="slug" :label="__('Address')" :value="$page->slug" maxlength="140" :help="__('Lower-case letters, digits and hyphens. Leave empty to create it from the title.')" />
        <x-form.select name="placement" :label="__('Shown in')" :value="$page->placement" required :options="['menu' => __('Main menu'), 'footer_quick' => __('Footer · Quick Links'), 'footer_campus' => __('Footer · Our Campus'), 'hidden' => __('Not in a menu')]" />
        <x-form.input name="sort_order" type="number" :label="__('Order')" :value="$page->sort_order ?? 0" col="col-md-3" min="0" max="9999" />
        <x-form.select name="status" :label="__('Status')" :value="$page->status" required col="col-md-3" :options="['published' => __('Published'), 'draft' => __('Draft')]" />
        <x-form.input name="meta_description" :label="__('Short description (search engines)')" :value="$page->meta_description" col="col-12" maxlength="300" />
        <div class="col-12"><label class="form-label" for="body">{{ __('Content') }}</label>
            <textarea id="body" name="body" rows="14" class="form-control font-monospace @error('body') is-invalid @enderror">{{ old('body', $page->body) }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('Markdown is supported (## heading, **bold**, - list, [link](https://…)). Raw HTML is removed for safety.') }}</div></div>
        <div class="col-12"><label class="form-label" for="body_bn">{{ __('Content (Bangla)') }}</label>
            <textarea id="body_bn" name="body_bn" rows="8" class="form-control bn font-monospace">{{ old('body_bn', $page->body_bn) }}</textarea></div>
    </div>
    <x-form.actions :cancel="route($rp.'pages.index')" />
</form>
@endsection
