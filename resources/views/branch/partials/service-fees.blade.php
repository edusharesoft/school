{{-- Monthly service-fee run (transport / hostel) and what has been collected. --}}
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
    <form method="GET" class="d-flex gap-2 align-items-end"><div><label class="form-label small mb-0" for="period">{{ __('Month') }}</label><input id="period" type="month" name="period" value="{{ $period }}" class="form-control form-control-sm"></div><button class="btn btn-sm btn-outline-secondary">{{ __('Show') }}</button></form>
    @can($can)<form method="POST" action="{{ route($generateRoute) }}" data-confirm="{{ __('Create the invoices for :m?', ['m' => $period]) }}">@csrf<input type="hidden" name="period" value="{{ $period }}"><button class="btn btn-sm btn-brand"><i class="bi bi-receipt me-1"></i>{{ __('Generate invoices') }}</button></form>@endcan
    <div class="small text-body-secondary ms-auto" style="max-width: 520px">{{ $note }}</div>
</div></div>
<div class="row g-3 mb-3">
    <div class="col-4"><x-stat-card :label="__('Billed')" :value="'৳'.number_format($totals['billed'], 2)" icon="fa-solid fa-file-invoice" /></div>
    <div class="col-4"><x-stat-card :label="__('Collected')" :value="'৳'.number_format($totals['paid'], 2)" icon="fa-solid fa-check" bg="#bbf7d0" color="#15803d" /></div>
    <div class="col-4"><x-stat-card :label="__('Still due')" :value="'৳'.number_format($totals['due'], 2)" icon="fa-solid fa-hourglass-half" bg="#fecaca" color="#b91c1c" /></div>
</div>
<div class="card">
    @if($invoices->isEmpty())<x-empty-state icon="receipt" :title="__('No invoices for this month')" />
    @else<div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Student') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Balance') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>@foreach($invoices as $i)
            <tr><td>{{ $i->invoice_no }}</td><td>{{ $i->student?->name }} <span class="text-body-secondary">{{ $i->student?->student_code }}</span></td>
                <td class="text-end">{{ number_format((float) $i->total, 2) }}</td><td class="text-end">{{ number_format((float) $i->paid, 2) }}</td><td class="text-end">{{ number_format((float) $i->balance, 2) }}</td>
                <td><span class="badge text-bg-{{ ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger'][$i->status] ?? 'secondary' }}">{{ __(ucfirst($i->status)) }}</span></td></tr>
        @endforeach</tbody></table></div>@endif
</div>
