@php($src = \App\Support\Website::img($n->image_path))
<article class="news-card">
    <a href="{{ $n->route() }}" class="news-img" tabindex="-1" aria-hidden="true">
        @if($src)<img src="{{ $src }}" alt="" loading="lazy">@else<span class="ph"><i class="fa-regular fa-newspaper"></i></span>@endif
        <span class="news-date"><i class="fa-regular fa-calendar"></i> {{ bn_digits(($n->published_at ?? $n->created_at)->translatedFormat('M d, Y')) }}</span>
    </a>
    <div class="news-meta"><span><i class="fa-regular fa-eye"></i> {{ __('By') }} {{ $n->author_name ?: __('Admin') }}</span></div>
    <h3><a href="{{ $n->route() }}">{{ $n->displayTitle() }}</a></h3>
    <a href="{{ $n->route() }}" class="site-btn site-btn-orange site-btn-sm">{{ __('Read More') }} <i class="fa-solid fa-arrow-right"></i></a>
</article>
