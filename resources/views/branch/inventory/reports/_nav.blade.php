<ul class="nav nav-pills mb-3 small">
    <li class="nav-item"><a class="nav-link @if(request()->routeIs('branch.inventory.reports.stock')) active @endif" href="{{ route('branch.inventory.reports.stock') }}">{{ __('Stock & valuation') }}</a></li>
    <li class="nav-item"><a class="nav-link @if(request()->routeIs('branch.inventory.reports.consumption')) active @endif" href="{{ route('branch.inventory.reports.consumption') }}">{{ __('Consumption') }}</a></li>
    <li class="nav-item"><a class="nav-link @if(request()->routeIs('branch.inventory.reports.purchases')) active @endif" href="{{ route('branch.inventory.reports.purchases') }}">{{ __('Purchases & dues') }}</a></li>
</ul>
