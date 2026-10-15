@extends('layouts.portal')
@section('title', __('Learning materials'))

@section('content')
<x-page-header :title="__('Learning materials')" :breadcrumbs="$mode === 'parent' ? [__('My children') => route('portal.parent'), $student->name => null] : [__('Learning') => null]" />

@forelse($materials as $subject => $items)
    <div class="dp blue mb-3">
        <div class="dp-head"><h2><i class="fa-solid fa-book-open me-1"></i> {{ $subject }}</h2><span class="sub">{{ bn_digits($items->count()) }}</span></div>
        <div class="dp-body">
            @foreach($items as $m)
                <div class="d-flex align-items-start gap-3 border-bottom py-2">
                    <i class="fa-solid {{ ['document' => 'fa-file-lines text-primary', 'video' => 'fa-circle-play text-danger', 'recorded_lesson' => 'fa-video text-danger', 'link' => 'fa-link text-success'][$m->type] }} fs-4 mt-1"></i>
                    <div class="me-auto">
                        <div class="fw-semibold">{{ $m->title }}</div>
                        @if($m->description)<div class="small text-body-secondary">{{ $m->description }}</div>@endif
                        <div class="small text-body-secondary">{{ __(Illuminate\Support\Str::headline($m->type)) }} · {{ $m->published_at?->format('d M Y') }}</div>
                    </div>
                    @if($m->hasFile())
                        <a href="{{ route($routePrefix.'materials.file', [...$routeParams, $m]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>{{ __('Download') }}</a>
                    @elseif($m->url)
                        <a href="{{ $m->url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Open') }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@empty
    <div class="panel panel-body text-center text-body-secondary">{{ __('No learning materials have been shared yet.') }}</div>
@endforelse
@endsection
