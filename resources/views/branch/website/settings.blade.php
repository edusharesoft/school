@extends('layouts.branch')
@section('title', __('Website settings'))

@section('content')
@use('App\Support\Website')
@php
    $hero = $settings['hero'] ?? [];
    $own = $own ?? [];
    $features = array_pad($hero['features'] ?? [], 3, ['icon' => 'fa-star', 'title' => '', 'text' => '', 'tone' => 'teal']);
    $about = $settings['about'] ?? [];
    $aboutMain = Website::img($about['image_main'] ?? null);
    $aboutSecond = Website::img($about['image_second'] ?? null);
    $points = array_pad($about['points'] ?? [], 3, '');
    $social = $settings['social'] ?? [];
@endphp
<x-page-header :title="__('Website settings')" :breadcrumbs="[__('Website') => null, __('Site settings') => null]">
    <x-slot:actions><a href="{{ route('branch.home') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>{{ __('View website') }}</a></x-slot:actions>
</x-page-header>

<div class="alert alert-light border small"><i class="fa-solid fa-circle-info text-primary me-1"></i>
    {{ __('This is the public website of your branch. The menu and footer links come from Website → Pages, the “Latest News & Blog” block from Website → News & notices. Leave a block empty to hide it.') }}</div>

<form method="POST" enctype="multipart/form-data" action="{{ route('branch.website.settings.update') }}" class="card">
    @csrf @method('PUT')
    <div class="card-body row g-3">

        @include('website.partials.hero-slides-editor', ['slides' => \App\Services\Website\HeroSlides::effective($own['hero'] ?? [])])
        <div class="col-12 form-text mt-0">{{ __('Without slides of your own, the institution’s banner slides are shown.') }}</div>

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

        <div class="col-12 mt-4"><h2 class="h6 mb-0">{{ __('Discover our School') }}</h2></div>
        <x-form.input name="about[title]" :label="__('Heading')" :value="$about['title'] ?? ''" col="col-md-6" maxlength="120" :placeholder="__('Discover our School')" />
        <x-form.input name="about[subtitle]" :label="__('Sub-heading')" :value="$about['subtitle'] ?? ''" col="col-md-6" maxlength="200" :placeholder="__('Igniting the spark of genius in every child.')" />
        <div class="col-12"><label class="form-label" for="about_text">{{ __('Text') }}</label>
            <textarea id="about_text" name="about[text]" rows="4" maxlength="2000" class="form-control">{{ old('about.text', $about['text'] ?? '') }}</textarea></div>
        @foreach($points as $i => $pt)
            <x-form.input :name="'about[points]['.$i.']'" :label="__('Tick point :n', ['n' => $i + 1])" :value="$pt" col="col-md-4" maxlength="120" />
        @endforeach
        <x-form.input name="about[years]" type="number" :label="__('Years of experience (badge)')" :value="$about['years'] ?? ''" col="col-md-3" min="0" max="500" />
        <x-form.input name="about[years_label]" :label="__('Badge text')" :value="$about['years_label'] ?? ''" col="col-md-3" maxlength="60" :placeholder="__('Years Experience Working')" />
        <x-form.input name="about[button_text]" :label="__('Button text')" :value="$about['button_text'] ?? ''" col="col-md-3" maxlength="40" :placeholder="__('Read More')" />
        <x-form.input name="about[button_url]" :label="__('Button link')" :value="$about['button_url'] ?? ''" col="col-md-3" maxlength="255" placeholder="/page/about-us" :help="__('Starts with https:// or /')" />
        <div class="col-md-6"><label class="form-label" for="about_image_main">{{ __('Large picture') }}</label>
            <input id="about_image_main" type="file" name="about_image_main" accept="image/png,image/jpeg,image/webp" class="form-control @error('about_image_main') is-invalid @enderror">
            @error('about_image_main')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($aboutMain)<img src="{{ $aboutMain }}" alt="" class="img-thumbnail mt-2" style="height:70px"><label class="small ms-2"><input type="checkbox" class="form-check-input" name="remove_about_image_main" value="1"> {{ __('Remove') }}</label>@endif</div>
        <div class="col-md-6"><label class="form-label" for="about_image_second">{{ __('Small picture') }}</label>
            <input id="about_image_second" type="file" name="about_image_second" accept="image/png,image/jpeg,image/webp" class="form-control @error('about_image_second') is-invalid @enderror">
            @error('about_image_second')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($aboutSecond)<img src="{{ $aboutSecond }}" alt="" class="img-thumbnail mt-2" style="height:70px"><label class="small ms-2"><input type="checkbox" class="form-check-input" name="remove_about_image_second" value="1"> {{ __('Remove') }}</label>@endif</div>

        <div class="col-12 mt-4"><h2 class="h6 mb-0">{{ __('Message') }} <span class="text-body-secondary fw-normal small">— {{ __('up to :n, e.g. Chairman and Principal', ['n' => $limits['messages']]) }}</span></h2></div>
        <div class="col-12">@include('branch.website.partials.people', ['key' => 'messages', 'photoKey' => 'message_photos', 'slots' => $limits['messages'], 'items' => $settings['messages']['items'] ?? [], 'withText' => true])</div>

        <div class="col-12 mt-4"><h2 class="h6 mb-0">{{ __('Our Team') }} <span class="text-body-secondary fw-normal small">— {{ __('up to :n, e.g. Principal, Vice-Principal, Adjutant', ['n' => $limits['team']]) }}</span></h2></div>
        <div class="col-12">@include('branch.website.partials.people', ['key' => 'team', 'photoKey' => 'team_photos', 'slots' => $limits['team'], 'items' => $settings['team']['items'] ?? [], 'withText' => false])</div>

        <div class="col-12 mt-4"><h2 class="h6 mb-0">{{ __('Our Teacher') }} <span class="text-body-secondary fw-normal small">— {{ __('up to :n', ['n' => $limits['teachers']]) }}</span></h2></div>
        <div class="col-12">@include('branch.website.partials.people', ['key' => 'teachers', 'photoKey' => 'teacher_photos', 'slots' => $limits['teachers'], 'items' => $settings['teachers']['items'] ?? [], 'withText' => false])</div>

        <div class="col-12 mt-4"><h2 class="h6 mb-0">{{ __('Partner / affiliation logos') }}</h2></div>
        <div class="col-12">
            @if(! empty($own['partners']))
                <div class="d-flex flex-wrap gap-3 mb-2">
                    @foreach($own['partners'] as $path)
                        @if($src = Website::img($path))
                            <label class="border rounded p-2 text-center small"><img src="{{ $src }}" alt="" style="height:48px;width:auto"><br>
                                <input type="checkbox" name="remove_partners[]" value="{{ $path }}" class="form-check-input"> {{ __('Remove') }}</label>
                        @endif
                    @endforeach
                </div>
            @endif
            <input type="file" name="partners[]" multiple accept="image/png,image/jpeg,image/webp" class="form-control @error('partners.*') is-invalid @enderror" aria-label="{{ __('Add logos') }}">
            @error('partners.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('Up to 12 logos, 1 MB each. Without your own logos the institution’s are shown.') }}</div>
        </div>

        <div class="col-12 mt-4"><h2 class="h6 mb-0">{{ __('Footer') }}</h2></div>
        <div class="col-md-6"><label class="form-label" for="footer_text">{{ __('Text under the logo') }}</label>
            <textarea id="footer_text" name="footer_text" rows="3" maxlength="400" class="form-control">{{ old('footer_text', $own['footer']['text'] ?? '') }}</textarea></div>
        <div class="col-md-6"><label class="form-label" for="footer_text_bn">{{ __('Text under the logo (Bangla)') }}</label>
            <textarea id="footer_text_bn" name="footer_text_bn" rows="3" maxlength="400" class="form-control bn">{{ old('footer_text_bn', $own['footer']['text_bn'] ?? '') }}</textarea></div>
        @foreach(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'] as $k => $label)
            <x-form.input :name="'social['.$k.']'" :label="$label" type="url" :value="$own['social'][$k] ?? ''" col="col-md-3" maxlength="255" placeholder="https://" />
        @endforeach
    </div>
    @can('website.update')<x-form.actions :cancel="route('branch.dashboard')" />@endcan
</form>

<script>
    document.querySelectorAll('.js-employee-pick').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var o = sel.options[sel.selectedIndex], t = sel.dataset.target;
            if (!o || !o.value) { return; }
            document.getElementById(t + '_name').value = o.dataset.name || '';
            document.getElementById(t + '_role').value = o.dataset.role || '';
        });
    });
</script>
@endsection
