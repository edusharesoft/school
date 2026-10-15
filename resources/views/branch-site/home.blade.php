{{-- Home page of a branch website (design: hero + 3 boxes, Discover our School, Message, Our Team, Our Teacher, Our Branches, map, news). --}}
@extends('layouts.branch-site')
@section('title', __('Home'))
@section('header-overlay', '1')

@section('content')
@use('App\Support\Website')
@php
    $hero = $site['hero'] ?? [];
    $about = $site['about'] ?? [];
    $aboutMain = Website::img($about['image_main'] ?? null);
    $aboutSecond = Website::img($about['image_second'] ?? null);
    $hasAbout = ! empty($about['text']) || $aboutMain || $aboutSecond || ! empty($about['points']);
    $messages = collect($site['messages']['items'] ?? []);
    $team = collect($site['team']['items'] ?? []);
    $teachers = collect($site['teachers']['items'] ?? []);
@endphp

{{-- Hero banner slider + three feature boxes --}}
@include('website.partials.hero', ['hero' => $hero, 'title' => $branch->displayName(), 'subtitle' => $branch->institution?->displayName()])
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

{{-- Discover our School --}}
@if($hasAbout)
<section class="site-section" id="about">
    <div class="site-wrap">
        <div class="site-about">
            <div class="site-about-pics">
                <div class="site-about-main">@if($aboutMain)<img src="{{ $aboutMain }}" alt="" loading="lazy">@else<span class="ph"><i class="fa-solid fa-school"></i></span>@endif</div>
                <div class="site-about-row">
                    <div class="site-about-second">@if($aboutSecond)<img src="{{ $aboutSecond }}" alt="" loading="lazy">@endif</div>
                    @if(! empty($about['years']))
                        <div class="site-about-years"><b>{{ bn_digits($about['years']) }}+</b><span>{{ $about['years_label'] ?: __('Years Experience Working') }}</span></div>
                    @endif
                </div>
            </div>
            <div class="site-about-text">
                <h2>{{ $about['title'] ?: __('Discover our School') }}</h2>
                <p class="site-about-sub">{{ $about['subtitle'] ?: __('Igniting the spark of genius in every child.') }}</p>
                @if(! empty($about['text']))<p class="site-about-body">{!! nl2br(e($about['text'])) !!}</p>@endif
                @if(! empty($about['points']))
                    <ul class="site-ticks">@foreach($about['points'] as $pt)<li><i class="fa-solid fa-check"></i>{{ $pt }}</li>@endforeach</ul>
                @endif
                @php($more = $about['button_url'] ?? null)
                @if($more || ! empty($about['button_text']))
                    <a href="{{ $more ?: route('admission.index') }}" class="site-btn site-btn-teal">{{ $about['button_text'] ?: __('Read More') }}</a>
                @endif
            </div>
        </div>
    </div>
</section>
@endif

{{-- Message --}}
@if($messages->isNotEmpty())
<section class="site-band-light" id="message">
    <div class="site-wrap">
        <h2 class="site-title site-title-orange">{{ __('Message') }}</h2>
        <div class="site-messages cols-{{ $messages->count() }}">
            @foreach($messages as $i => $m)
                @php($pic = Website::img($m['photo'] ?? null))
                <a class="site-message" href="{{ route('site.message', $i + 1) }}">
                    @if($pic)<img src="{{ $pic }}" alt="{{ $m['name'] }}" loading="lazy">@else<span class="ph"><i class="fa-solid fa-user-tie"></i></span>@endif
                    <span class="site-message-cap"><b>{{ $m['name'] }}</b>@if(! empty($m['designation']))<small>{{ $m['designation'] }}</small>@endif
                        @if(! empty($m['text']))<em>{{ __('Read message') }} <i class="fa-solid fa-arrow-right"></i></em>@endif</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Our Team --}}
@if($team->isNotEmpty())
<section class="site-section site-team-sec" id="team">
    <div class="site-wrap">
        <h2 class="site-title site-title-orange">{{ __('Our Team') }}</h2>
        <div class="site-team">
            @foreach($team as $i => $p)
                @php($pic = Website::img($p['photo'] ?? null))
                <article class="site-team-card tone-{{ $i % 3 }}">
                    <div class="site-team-pic">@if($pic)<img src="{{ $pic }}" alt="{{ $p['name'] }}" loading="lazy">@else<span class="ph"><i class="fa-solid fa-user"></i></span>@endif</div>
                    <div class="site-team-info"><h3>{{ $p['designation'] ?: $p['name'] }}</h3>
                        <p>@if(! empty($p['designation'])){{ $p['name'] }}@endif @if(! empty($p['note']))<br><span>{{ $p['note'] }}</span>@endif</p></div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Our Teacher --}}
@if($teachers->isNotEmpty())
<section class="site-section site-teachers-sec" id="teachers">
    <div class="site-wrap">
        <h2 class="site-title site-title-orange">{{ __('Our Teacher') }}</h2>
        <div class="site-teachers">
            @foreach($teachers as $p)
                @php($pic = Website::img($p['photo'] ?? null))
                <article class="site-teacher">
                    <div class="site-teacher-pic">@if($pic)<img src="{{ $pic }}" alt="{{ $p['name'] }}" loading="lazy">@else<span class="ph"><i class="fa-solid fa-user"></i></span>@endif</div>
                    <h3>{{ $p['name'] }}</h3>
                    @if(! empty($p['designation']))<span class="site-teacher-role">{{ $p['designation'] }}</span>@endif
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Our Branches --}}
@if($branches->isNotEmpty())
<section class="site-band-navy" id="branches">
    <div class="site-wrap">
        <h2 class="site-title site-title-orange">{{ __('Our Branches') }}</h2>
        @include('website.partials.branch-grid', ['branches' => $branches->take(9)])
    </div>
</section>

{{-- All Branch Locations --}}
<section class="site-section site-section-flush" id="locations">
    <div class="site-wrap"><h2 class="site-title site-title-orange">{{ __('All Branch Locations') }}</h2></div>
    @include('website.partials.branch-map', ['branches' => $branches])
</section>
@endif

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
