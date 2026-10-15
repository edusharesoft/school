{{-- Photo grid of branches (master design): picture tiles with a white frame; name appears on hover/focus. --}}
@if($branches->isEmpty())
    <div class="site-empty site-empty-dark"><i class="fa-solid fa-school"></i> {{ __('Branch information will be published soon.') }}</div>
@else
    <div class="branch-grid">
        @foreach($branches as $b)
            @php($photo = \App\Support\Website::img($b->cover_path))
            <a class="branch-tile" href="{{ $b->url('/') }}" style="--tone: {{ $b->brandColor() }}" title="{{ $b->displayName() }}">
                @if($photo)
                    <img src="{{ $photo }}" alt="{{ $b->displayName() }}" loading="lazy">
                @else
                    <span class="branch-ph"><i class="fa-solid fa-school"></i><b>{{ $b->displayName() }}</b></span>
                @endif
                <span class="branch-cap"><b>{{ $b->displayName() }}</b>@if($b->address)<small>{{ \Illuminate\Support\Str::limit($b->address, 60) }}</small>@endif</span>
            </a>
        @endforeach
    </div>
@endif
