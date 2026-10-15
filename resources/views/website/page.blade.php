@extends($siteLayout ?? 'layouts.website')
@section('title', $page->displayTitle())

@section('content')
<section class="site-section site-page">
    <div class="site-wrap site-narrow">
        <h1 class="site-title site-title-left">{{ $page->displayTitle() }}</h1>
        <div class="site-prose">{!! $page->displayBody() !!}</div>
    </div>
</section>
@endsection
