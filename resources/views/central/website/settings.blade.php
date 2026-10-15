@extends('layouts.central')
@section('title', __('Home page settings'))

@section('content')
@use('App\Support\Website')
@php
    $hero = $settings['hero'] ?? [];
    $features = array_pad($hero['features'] ?? [], 3, ['icon' => 'fa-star', 'title' => '', 'text' => '', 'tone' => 'teal']);
    $social = $settings['social'] ?? [];
@endphp
<x-page-header :title="__('Home page settings')" :breadcrumbs="[__('Website') => null, __('Home page settings') => null]">
    <x-slot:actions><a href="{{ route('website.home') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>{{ __('View website') }}</a></x-slot:actions>
</x-page-header>

<div class="alert alert-light border small"><i class="fa-solid fa-circle-info text-primary me-1"></i>
    {{ __('Branch pictures and map positions are edited on each branch (Branches → Edit → Public website). The menu, footer links and news come from Pages and News & notices.') }}</div>

<form method="POST" enctype="multipart/form-data" action="{{ route('central.website.settings.update') }}" class="card">
    @csrf @method('PUT')
    <div class="card-body row g-3">
        @include('website.partials.hero-slides-editor', ['slides' => $hero['slides'] ?? []])

        <div class="col-12 mt-4"><h2 class="h6 mb-0">{{ __('Three feature boxes') }}</h2></div>
        @foreach($features as $i => $f)
            <div class="col-md-4"><div class="border rounded p-3 h-100 row g-2">
                <div class="col-7"><label class="form-label small" for="f{{ $i }}_icon">{{ __('Icon') }}</label>
                    <select id="f{{ $i }}_icon" name="features[{{ $i }}][icon]" class="form-select form-select-sm">
                        @foreach($icons as $cls => $lbl)<option value="{{ $cls }}" @selected(old("features.$i.icon", $f['icon'] ?? '') === $cls)>{{ __($lbl) }}</option>@endforeach
                    </select></div>
                <div class="col-5"><label class="form-label small" for="f{{ $i }}_tone">{{ __('Colour') }}</label>
                    <select id="f{{ $i }}_tone" name="features[{{ $i }}][tone]" class="form-select form-select-sm">
                        <option value="teal" @selected(old("features.$i.tone", $f['tone'] ?? 'teal') === 'teal')>{{ __('Teal') }}</option>
                        <option value="orange" @selected(old("features.$i.tone", $f['tone'] ?? '') === 'orange')>{{ __('Orange') }}</option></select></div>
                <div class="col-12"><label class="form-label small" for="f{{ $i }}_title">{{ __('Title') }}</label>
                    <input id="f{{ $i }}_title" name="features[{{ $i }}][title]" maxlength="60" class="form-control form-control-sm" value="{{ old("features.$i.title", $f['title'] ?? '') }}"></div>
                <div class="col-12"><label class="form-label small" for="f{{ $i }}_text">{{ __('Text') }}</label>
                    <textarea id="f{{ $i }}_text" name="features[{{ $i }}][text]" maxlength="200" rows="3" class="form-control form-control-sm">{{ old("features.$i.text", $f['text'] ?? '') }}</textarea></div>
            </div></div>
        @endforeach

        <div class="col-12 mt-4"><h2 class="h6 mb-0">{{ __('Partner / affiliation logos') }}</h2></div>
        <div class="col-12">
            @if(! empty($settings['partners']))
                <div class="d-flex flex-wrap gap-3 mb-2">
                    @foreach($settings['partners'] as $path)
                        @if($src = Website::img($path))
                            <label class="border rounded p-2 text-center small"><img src="{{ $src }}" alt="" style="height:48px;width:auto"><br>
                                <input type="checkbox" name="remove_partners[]" value="{{ $path }}" class="form-check-input"> {{ __('Remove') }}</label>
                        @endif
                    @endforeach
                </div>
            @endif
            <input type="file" name="partners[]" multiple accept="image/png,image/jpeg,image/webp" class="form-control @error('partners.*') is-invalid @enderror" aria-label="{{ __('Add logos') }}">
            @error('partners.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('Up to 12 logos, 1 MB each. The strip is hidden when there are none.') }}</div>
        </div>

        <div class="col-12 mt-4"><h2 class="h6 mb-0">{{ __('Footer') }}</h2></div>
        <div class="col-md-6"><label class="form-label" for="footer_text">{{ __('Text under the logo') }}</label>
            <textarea id="footer_text" name="footer_text" rows="3" maxlength="400" class="form-control">{{ old('footer_text', $settings['footer']['text'] ?? '') }}</textarea></div>
        <div class="col-md-6"><label class="form-label" for="footer_text_bn">{{ __('Text under the logo (Bangla)') }}</label>
            <textarea id="footer_text_bn" name="footer_text_bn" rows="3" maxlength="400" class="form-control bn">{{ old('footer_text_bn', $settings['footer']['text_bn'] ?? '') }}</textarea></div>
        @foreach(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'] as $k => $label)
            <x-form.input :name="'social['.$k.']'" :label="$label" type="url" :value="$social[$k] ?? ''" col="col-md-3" maxlength="255" placeholder="https://" />
        @endforeach
    </div>
    @can('website.update')<x-form.actions :cancel="route('central.dashboard')" />@endcan
</form>
@endsection
