<ul class="nav nav-pills mb-3 small no-print">
    <li class="nav-item"><a @class(['nav-link', 'active' => request()->routeIs('branch.finance.reports.collection')]) href="{{ route('branch.finance.reports.collection') }}">{{ __('Collection') }}</a></li>
    <li class="nav-item"><a @class(['nav-link', 'active' => request()->routeIs('branch.finance.reports.dues')]) href="{{ route('branch.finance.reports.dues') }}">{{ __('Outstanding dues') }}</a></li>
    <li class="nav-item"><a @class(['nav-link', 'active' => request()->routeIs('branch.finance.reports.discounts')]) href="{{ route('branch.finance.reports.discounts') }}">{{ __('Discounts & waivers') }}</a></li>
</ul>
