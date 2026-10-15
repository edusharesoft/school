@auth
<div class="dropdown">
    <button class="btn btn-sm d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="avatar">{{ auth()->user()->initials() }}</span>
        <span class="d-none d-md-block text-start lh-sm">
            <span class="d-block fw-semibold small">{{ auth()->user()->name }}</span>
            <span class="d-block text-body-secondary" style="font-size:.72rem">{{ auth()->user()->roleLabels() }}</span>
        </span>
        <i class="bi bi-chevron-down small"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><span class="dropdown-item-text small text-body-secondary">{{ auth()->user()->email }}</span></li>
        <li><hr class="dropdown-divider"></li>
        @if(tenant()->isBranch())
            <li><a class="dropdown-item" href="{{ route('branch.switch-branch') }}"><i class="bi bi-arrow-left-right me-2"></i>{{ __('Switch branch') }}</a></li>
            @if(auth()->user()->canUseCentral())
                <li>
                    <form method="POST" action="{{ route('branch.switch-central') }}">@csrf
                        <button class="dropdown-item"><i class="bi bi-globe2 me-2"></i>{{ __('Central administration') }}</button>
                    </form>
                </li>
            @endif
        @else
            <li><a class="dropdown-item" href="{{ route('central.switch-branch') }}"><i class="bi bi-arrow-left-right me-2"></i>{{ __('Open a branch') }}</a></li>
        @endif
        <li><hr class="dropdown-divider"></li>
        <li>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>{{ __('Sign out') }}</button>
            </form>
        </li>
    </ul>
</div>
@endauth
