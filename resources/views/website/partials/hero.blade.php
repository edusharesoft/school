{{-- Hero banner: one picture, or a sliding banner when there are several. Expects $hero (settings block), $title, $subtitle. --}}
@use('App\Support\Website')
@php
    $slides = collect($hero['slides'] ?? [])->map(fn ($s) => $s + ['url' => Website::img($s['image'] ?? null)])->filter(fn ($s) => $s['url'])->values();
    $many = $slides->count() > 1;
@endphp
<section class="site-hero {{ $slides->isNotEmpty() ? 'has-slides' : '' }}" @if($many) data-hero-slider aria-roledescription="carousel" aria-label="{{ __('Banner') }}" @endif>
    @foreach($slides as $i => $s)
        <div class="hero-slide {{ $i === 0 ? 'is-active' : '' }}" style="background-image:url('{{ $s['url'] }}')" @if($many) role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} / {{ $slides->count() }}" @endif>
            @if(! empty($s['title']) || ! empty($s['text']) || ! empty($s['link']))
                <div class="site-wrap hero-caption">
                    @if(! empty($s['title']))<h2>{{ $s['title'] }}</h2>@endif
                    @if(! empty($s['text']))<p>{{ $s['text'] }}</p>@endif
                    @if(! empty($s['link']))<a href="{{ $s['link'] }}" class="site-btn site-btn-orange">{{ __('Learn more') }} <i class="fa-solid fa-arrow-right"></i></a>@endif
                </div>
            @endif
        </div>
    @endforeach
    <div class="site-hero-shade"></div>
    @if($slides->isEmpty())
        <div class="site-wrap site-hero-text">
            <h1>{{ $title }}</h1>
            @if(! empty($subtitle))<p>{{ $subtitle }}</p>@endif
        </div>
    @endif
    @if($many)
        <button type="button" class="hero-nav hero-prev" data-hero-prev aria-label="{{ __('Previous slide') }}"><i class="fa-solid fa-chevron-left"></i></button>
        <button type="button" class="hero-nav hero-next" data-hero-next aria-label="{{ __('Next slide') }}"><i class="fa-solid fa-chevron-right"></i></button>
        <div class="hero-dots" role="tablist">
            @foreach($slides as $i => $s)<button type="button" class="{{ $i === 0 ? 'is-active' : '' }}" data-hero-dot="{{ $i }}" aria-label="{{ __('Slide :n', ['n' => $i + 1]) }}"></button>@endforeach
        </div>
        @once
            @push('scripts')
            <script>
                (function () {
                    var root = document.querySelector('[data-hero-slider]');
                    if (!root) { return; }
                    var slides = root.querySelectorAll('.hero-slide'), dots = root.querySelectorAll('[data-hero-dot]');
                    var cur = 0, timer = null, still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    function show(n) {
                        cur = (n + slides.length) % slides.length;
                        slides.forEach(function (s, i) { s.classList.toggle('is-active', i === cur); s.setAttribute('aria-hidden', i === cur ? 'false' : 'true'); });
                        dots.forEach(function (d, i) { d.classList.toggle('is-active', i === cur); });
                    }
                    function play() { stop(); if (!still) { timer = setInterval(function () { show(cur + 1); }, 6000); } }
                    function stop() { if (timer) { clearInterval(timer); timer = null; } }
                    root.querySelector('[data-hero-prev]').addEventListener('click', function () { show(cur - 1); play(); });
                    root.querySelector('[data-hero-next]').addEventListener('click', function () { show(cur + 1); play(); });
                    dots.forEach(function (d) { d.addEventListener('click', function () { show(parseInt(d.dataset.heroDot, 10)); play(); }); });
                    root.addEventListener('mouseenter', stop); root.addEventListener('mouseleave', play);
                    root.addEventListener('focusin', stop); root.addEventListener('focusout', play);
                    var x0 = null;
                    root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
                    root.addEventListener('touchend', function (e) { if (x0 === null) { return; } var dx = e.changedTouches[0].clientX - x0; x0 = null; if (Math.abs(dx) > 40) { show(cur + (dx < 0 ? 1 : -1)); play(); } });
                    document.addEventListener('visibilitychange', function () { document.hidden ? stop() : play(); });
                    show(0); play();
                })();
            </script>
            @endpush
        @endonce
    @endif
</section>
