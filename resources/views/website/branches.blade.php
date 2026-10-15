@extends('layouts.website')
@section('title', __('Branches'))

@section('content')
<section class="site-band-navy site-band-page" id="branches">
    <div class="site-wrap">
        <h2 class="site-title site-title-orange">{{ __('Our Branches') }}</h2>
        @include('website.partials.branch-grid', ['branches' => $branches])
    </div>
</section>
<section class="site-section site-section-flush">
    <div class="site-wrap"><h2 class="site-title site-title-orange">{{ __('All Branch Locations') }}</h2></div>
    @include('website.partials.branch-map', ['branches' => $branches])
</section>
@endsection
