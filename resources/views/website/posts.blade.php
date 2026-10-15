@extends($siteLayout ?? 'layouts.website')
@section('title', $type === 'notice' ? __('Notices') : __('News & Blog'))

@section('content')
<section class="site-section site-page">
    <div class="site-wrap">
        <h1 class="site-title">{{ $type === 'notice' ? __('Notices') : __('Latest News & Blog') }}</h1>
        @if($posts->isEmpty())
            <div class="site-empty"><i class="fa-regular fa-newspaper"></i> {{ __('Nothing has been published yet.') }}</div>
        @elseif($type === 'notice')
            <ul class="notice-list">
                @foreach($posts as $p)
                    <li>
                        <span class="notice-date">{{ bn_digits(($p->published_at ?? $p->created_at)->translatedFormat('d M Y')) }}</span>
                        <a href="{{ $p->route() }}">{{ $p->displayTitle() }}</a>
                        @if($p->attachment_path)<i class="fa-solid fa-paperclip" title="{{ __('Attachment') }}"></i>@endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="news-grid">@foreach($posts as $p)@include('website.partials.news-card', ['n' => $p])@endforeach</div>
        @endif
        <div class="site-pager">{{ $posts->links() }}</div>
    </div>
</section>
@endsection
