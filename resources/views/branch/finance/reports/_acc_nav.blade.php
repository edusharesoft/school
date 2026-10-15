<ul class="nav nav-pills mb-3 small no-print flex-wrap">
    @foreach([
        'branch.finance.journal.index' => __('Day book'),
        'branch.finance.reports.ledger' => __('Ledger'),
        'branch.finance.reports.trial-balance' => __('Trial balance'),
        'branch.finance.reports.income-expenditure' => __('Income & expenditure'),
        'branch.finance.reports.balance-sheet' => __('Balance sheet'),
        'branch.finance.reports.cash-flow' => __('Cash flow'),
        'branch.finance.reports.budget' => __('Budget'),
        'branch.finance.reports.reconcile' => __('Bank reconciliation'),
        'branch.finance.years.index' => __('Financial years'),
    ] as $route => $label)
        <li class="nav-item"><a @class(['nav-link py-1', 'active' => request()->routeIs($route)]) href="{{ route($route) }}">{{ $label }}</a></li>
    @endforeach
</ul>
