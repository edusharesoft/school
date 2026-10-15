<ul class="nav nav-pills mb-3 small">
    <li class="nav-item"><a class="nav-link @if(request()->routeIs('branch.assets.reports.summary')) active @endif" href="{{ route('branch.assets.reports.summary') }}">{{ __('Value by category') }}</a></li>
    <li class="nav-item"><a class="nav-link @if(request()->routeIs('branch.assets.reports.depreciation')) active @endif" href="{{ route('branch.assets.reports.depreciation') }}">{{ __('Depreciation') }}</a></li>
    <li class="nav-item"><a class="nav-link @if(request()->routeIs('branch.assets.reports.maintenance')) active @endif" href="{{ route('branch.assets.reports.maintenance') }}">{{ __('Maintenance') }}</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('branch.assets.disposals.index') }}">{{ __('Disposals') }}</a></li>
    <li class="nav-item"><a class="nav-link" href="{{ route('branch.assets.register.export') }}"><i class="bi bi-filetype-csv"></i> {{ __('Register (CSV)') }}</a></li>
</ul>
