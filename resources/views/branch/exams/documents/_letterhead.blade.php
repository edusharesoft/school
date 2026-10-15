{{-- Institution / branch letterhead for printed exam documents. Needs $branch. --}}
@php($logo = \App\Support\AuthTheme::logo($branch))
<div class="lh">
    @if($logo)<img src="{{ $logo }}" alt="" class="lh-logo">@endif
    <div>
        <div class="lh-inst">{{ $branch->institution?->name }}</div>
        <div class="lh-branch">{{ $branch->name }}</div>
        @if($branch->address)<div class="lh-meta">{{ $branch->address }}@if($branch->phone) · {{ $branch->phone }}@endif</div>@endif
    </div>
</div>
