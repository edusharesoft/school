@extends('layouts.branch-site')
@section('title', $message['name'])

@section('content')
@use('App\Support\Website')
@php($pic = Website::img($message['photo'] ?? null))
<section class="site-section site-page">
    <div class="site-wrap site-narrow">
        <p class="site-crumb"><a href="{{ route('branch.home') }}#message">{{ __('Message') }}</a></p>
        <div class="site-msg-head">
            @if($pic)<img src="{{ $pic }}" alt="{{ $message['name'] }}">@endif
            <div><h1 class="site-title-left site-msg-name">{{ $message['name'] }}</h1>@if(! empty($message['designation']))<p class="site-byline site-msg-role">{{ $message['designation'] }}</p>@endif</div>
        </div>
        @if(! empty($message['text']))<div class="site-prose">{!! \App\Models\Website\Page::markdown($message['text']) !!}</div>@endif
    </div>
</section>
@endsection
