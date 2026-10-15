@extends('layouts.website')
@section('title', __('Home'))
@section('header-overlay', '1')

@section('content')
@use('App\Support\Website')
@php
    $hero = $site['hero'] ?? [];
@endphp

{{-- Hero banner slider + three feature boxes --}}
@include('website.partials.hero', ['hero' => $hero, 'title' => $institution?->displayName() ?? config('app.name'), 'subtitle' => __(':count campuses, one institution.', ['count' => bn_digits($branches->count())])])
@if(! empty($hero['features']))
<div class="site-wrap">
    <div class="site-features">
        @foreach($hero['features'] as $f)
            <div class="site-feature tone-{{ $f['tone'] ?? 'teal' }}">
                <i class="fa-solid {{ $f['icon'] ?? 'fa-star' }}"></i>
                <div><h3>{{ $f['title'] }}</h3><p>{{ $f['text'] }}</p></div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- Our Branches --}}
<section class="site-band-navy" id="branches">
    <div class="site-wrap">
        <h2 class="site-title site-title-orange">{{ __('Our Branches') }}</h2>
        @include('website.partials.branch-grid', ['branches' => $branches->take(9)])
        @if($branches->count() > 9)
            <div class="site-center"><a href="{{ route('website.branches') }}" class="site-btn site-btn-orange">{{ __('View all branches') }} <i class="fa-solid fa-arrow-right"></i></a></div>
        @endif
    </div>
</section>

{{-- All Branch Locations --}}
<section class="site-section site-section-flush" id="locations">
    <div class="site-wrap"><h2 class="site-title site-title-orange">{{ __('All Branch Locations') }}</h2></div>
    @include('website.partials.branch-map', ['branches' => $branches])
</section>

{{-- Latest News & Blog --}}
@if($news->isNotEmpty())
<section class="site-section" id="news">
    <div class="site-wrap">
        <h2 class="site-title">{{ __('Latest News &') }} <span>{{ __('Blog') }}</span></h2>
        <div class="news-grid">
            @foreach($news as $n)
                @include('website.partials.news-card', ['n' => $n])
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
