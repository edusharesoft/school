@extends($siteLayout ?? 'layouts.website')
@section('title', $post->displayTitle())

@section('content')
@php($src = \App\Support\Website::img($post->image_path))
<section class="site-section site-page">
    <div class="site-wrap site-narrow">
        <p class="site-crumb"><a href="{{ $post->listRoute() }}">{{ $post->type === 'notice' ? __('Notices') : __('News & Blog') }}</a></p>
        <h1 class="site-title site-title-left">{{ $post->displayTitle() }}</h1>
        <p class="site-byline"><i class="fa-regular fa-calendar"></i> {{ bn_digits(($post->published_at ?? $post->created_at)->translatedFormat('d M Y')) }}
            @if($post->author_name) · <i class="fa-regular fa-user"></i> {{ $post->author_name }}@endif</p>
        @if($src)<img class="site-post-img" src="{{ $src }}" alt="">@endif
        <div class="site-prose">{!! $post->displayBody() !!}</div>
        @if($att = \App\Support\Website::img($post->attachment_path))
            <p><a class="site-btn site-btn-teal" href="{{ $att }}" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip"></i> {{ __('Download attachment') }}</a></p>
        @endif
    </div>
</section>
@endsection
