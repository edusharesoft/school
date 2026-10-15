<header class="app-topbar">
    <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-toggle-sidebar aria-label="{{ __('Open menu') }}">
        <i class="bi bi-list"></i>
    </button>

    @if(tenant()->isBranch())
        <span class="badge rounded-pill text-bg-light border d-none d-md-inline-flex align-items-center gap-1">
            <i class="bi bi-geo-alt text-brand"></i>{{ $currentBranch?->code }} · {{ $currentBranch?->host() }}
        </span>
    @else
        <span class="badge rounded-pill text-bg-light border d-none d-md-inline-flex align-items-center gap-1">
            <i class="bi bi-globe2 text-brand"></i>{{ __('Central') }}
        </span>
    @endif

    <div class="ms-auto d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-theme-toggle aria-label="{{ __('Toggle dark mode') }}"><i class="bi bi-moon-stars"></i></button>
        <a href="{{ route('locale.switch', app()->getLocale() === 'bn' ? 'en' : 'bn') }}" class="btn btn-sm btn-outline-secondary {{ app()->getLocale() === 'bn' ? '' : 'bn' }}">
            {{ app()->getLocale() === 'bn' ? 'EN' : 'বাংলা' }}
        </a>
        @include('partials.user-menu')
    </div>
</header>
