@extends('layouts.branch')
@section('title', $asset->code)

@section('content')
@php
    $canUpdate = auth()->user()->can('assets.update') && $asset->isLive();
    $canApprove = auth()->user()->can('assets.approve');
    $current = $asset->currentAllocation;
    $fmtMethod = fn ($m) => __(ucwords(str_replace('_', ' ', $m)));
@endphp
<x-page-header :title="$asset->displayName()" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Asset register') => route('branch.assets.register.index'), $asset->code => null]">
    <x-slot:actions>
        <a href="{{ route('branch.assets.register.labels', ['ids' => $asset->id]) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upc-scan me-1"></i>{{ __('Print tag') }}</a>
        @if($canUpdate)<a href="{{ route('branch.assets.register.edit', $asset) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>@endif
        @if($canApprove && $asset->isLive())<a href="{{ route('branch.assets.disposals.create', $asset) }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Dispose') }}</a>@endif
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
<div class="col-xl-8">
    <div class="card mb-3"><div class="card-body small">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3"><x-asset-status :asset="$asset" /> <span class="badge text-bg-light border">{{ __(ucfirst($asset->condition)) }}</span><span class="text-body-secondary">{{ $asset->code }}</span></div>
        <div class="row g-3">
            <div class="col-md-6"><dl class="row mb-0">
                <dt class="col-5">{{ __('Category') }}</dt><dd class="col-7">{{ $asset->category?->name }}</dd>
                <dt class="col-5">{{ __('Serial no.') }}</dt><dd class="col-7">{{ $asset->serial_no ?: '—' }}</dd>
                <dt class="col-5">{{ __('Brand / model') }}</dt><dd class="col-7">{{ collect([$asset->brand, $asset->model])->filter()->implode(' / ') ?: '—' }}</dd>
                <dt class="col-5">{{ __('Place') }}</dt><dd class="col-7">{{ collect([$asset->room?->name, $asset->location])->filter()->implode(' · ') ?: '—' }}</dd>
                <dt class="col-5">{{ __('Supplier') }}</dt><dd class="col-7">{{ $asset->vendor?->name ?: '—' }}@if($asset->bill_no) <span class="text-body-secondary">({{ $asset->bill_no }})</span>@endif</dd>
                <dt class="col-5">{{ __('Warranty') }}</dt><dd class="col-7">@if($asset->warranty_expires_on){{ $asset->warranty_expires_on->format('d M Y') }} <span class="@if($asset->warrantyDaysLeft() < 0) text-danger @elseif($asset->warrantyDaysLeft() <= 60) text-warning @endif">({{ $asset->warrantyDaysLeft() < 0 ? __('expired') : __(':n days left', ['n' => $asset->warrantyDaysLeft()]) }})</span>@else — @endif</dd>
                @if($asset->safety_notes)<dt class="col-5">{{ __('Safety') }}</dt><dd class="col-7 text-danger">{{ $asset->safety_notes }}</dd>@endif
                @if($asset->description)<dt class="col-5">{{ __('Description') }}</dt><dd class="col-7">{{ $asset->description }}</dd>@endif
            </dl></div>
            <div class="col-md-6"><dl class="row mb-0">
                <dt class="col-6">{{ __('Acquired') }}</dt><dd class="col-6">{{ $asset->acquired_on->format('d M Y') }} <span class="text-body-secondary">({{ __(ucfirst($asset->acquisition_type)) }})</span></dd>
                @if($asset->stockItem)<dt class="col-6">{{ __('From stock item') }}</dt><dd class="col-6"><a href="{{ route('branch.inventory.items.show', $asset->stockItem) }}">{{ $asset->stockItem->code }}</a></dd>@endif
                <dt class="col-6">{{ __('Cost') }}</dt><dd class="col-6">৳{{ number_format($asset->cost, 2) }}</dd>
                <dt class="col-6">{{ __('Depreciation so far') }}</dt><dd class="col-6">৳{{ number_format($asset->accumulated_depreciation, 2) }}</dd>
                <dt class="col-6 fw-semibold">{{ __('Book value') }}</dt><dd class="col-6 fw-semibold">৳{{ number_format($asset->bookValue(), 2) }}</dd>
                <dt class="col-6">{{ __('Method') }}</dt><dd class="col-6">{{ $fmtMethod($asset->depreciation_method) }}@if($asset->depreciation_method === 'straight_line') · {{ __(':n months', ['n' => $asset->useful_life_months]) }}@elseif($asset->depreciation_method === 'declining_balance') · {{ $asset->declining_rate + 0 }}%@endif</dd>
                <dt class="col-6">{{ __('Salvage value') }}</dt><dd class="col-6">৳{{ number_format($asset->salvage_value, 2) }}</dd>
                <dt class="col-6">{{ __('Charged through') }}</dt><dd class="col-6">{{ $asset->depreciated_through ?: '—' }}</dd>
                @if($asset->last_verified_on)<dt class="col-6">{{ __('Last verified') }}</dt><dd class="col-6">{{ $asset->last_verified_on->format('d M Y') }}</dd>@endif
                @if($asset->status === 'disposed' && $asset->disposal)<dt class="col-6">{{ __('Disposed') }}</dt><dd class="col-6"><a href="{{ route('branch.assets.disposals.show', $asset->disposal) }}">{{ $asset->disposal->disposal_no }}</a></dd>@endif
            </dl></div>
        </div>
    </div></div>

    <div class="card mb-3">
        <div class="card-header bg-transparent fw-semibold d-flex justify-content-between"><span>{{ __('Maintenance & calibration') }}</span></div>
        @if($asset->plans->isEmpty())<div class="card-body small text-body-secondary">{{ __('No recurring tasks. Add a service or calibration schedule below.') }}</div>
        @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
            <thead><tr><th>{{ __('Task') }}</th><th>{{ __('Every') }}</th><th>{{ __('Last done') }}</th><th>{{ __('Next due') }}</th><th>{{ __('Responsible') }}</th><th></th></tr></thead>
            <tbody>@foreach($asset->plans as $p)<tr class="@unless($p->is_active) text-body-secondary @endunless">
                <td>{{ $p->title }}<div class="text-body-secondary">{{ __(ucfirst($p->kind)) }}@unless($p->is_active) · {{ __('inactive') }}@endunless</div></td><td>{{ __(':n month(s)', ['n' => $p->interval_months]) }}</td>
                <td>{{ $p->last_done_on?->format('d M Y') ?? '—' }}</td>
                <td class="text-nowrap @if($p->isOverdue()) text-danger fw-semibold @endif">{{ $p->next_due_on->format('d M Y') }}</td><td>{{ $p->employee?->name ?? '—' }}</td>
                <td class="text-end">@if($canUpdate && $p->is_active)
                    <form method="POST" action="{{ route('branch.assets.maintenance.plans.done', $p) }}" class="d-inline-flex gap-1">@csrf <input type="hidden" name="completed_on" value="{{ now()->toDateString() }}">
                        <input type="number" step="0.01" min="0" name="cost" class="form-control form-control-sm" style="width:90px" placeholder="{{ __('Cost') }}" aria-label="{{ __('Cost') }}">
                        <button class="btn btn-sm btn-outline-success" title="{{ __('Mark as done today') }}"><i class="bi bi-check2-circle"></i> {{ __('Done') }}</button></form>@endif</td></tr>@endforeach</tbody></table></div>@endif
        @if($canUpdate)
        <form method="POST" action="{{ route('branch.assets.maintenance.plans.store', $asset) }}" class="card-body border-top row g-2 align-items-end">@csrf
            <div class="col-md-3"><label class="form-label small mb-0" for="pt">{{ __('Task') }}</label><input id="pt" name="title" class="form-control form-control-sm" required maxlength="150" placeholder="{{ $asset->category?->kind === 'laboratory' ? __('Calibration') : __('Service') }}"></div>
            <div class="col-md-2"><label class="form-label small mb-0" for="pk">{{ __('Kind') }}</label><select id="pk" name="kind" class="form-select form-select-sm">@foreach(App\Models\Assets\MaintenancePlan::KINDS as $k)<option value="{{ $k }}" @selected($k === ($asset->category?->kind === 'laboratory' ? 'calibration' : 'preventive'))>{{ __(ucfirst($k)) }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small mb-0" for="pi">{{ __('Every (months)') }}</label><input id="pi" type="number" name="interval_months" min="1" max="120" value="{{ $asset->category?->kind === 'laboratory' ? 12 : 6 }}" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><label class="form-label small mb-0" for="ps">{{ __('First due') }}</label><input id="ps" type="date" name="start_on" value="{{ now()->addMonths(6)->toDateString() }}" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><label class="form-label small mb-0" for="pe">{{ __('Responsible') }}</label><select id="pe" name="employee_id" class="form-select form-select-sm"><option value="">—</option>@foreach($employees ?? [] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
            <div class="col-md-1"><button class="btn btn-sm btn-brand w-100">{{ __('Add') }}</button></div>
        </form>@endif
    </div>

    <div class="card mb-3">
        <div class="card-header bg-transparent fw-semibold">{{ __('Work orders') }}</div>
        @if($asset->logs->isEmpty())<div class="card-body small text-body-secondary">{{ __('No work orders yet.') }}</div>
        @else<div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
            <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Work') }}</th><th>{{ __('Reported') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Cost (৳)') }}</th></tr></thead>
            <tbody>@foreach($asset->logs->take(12) as $l)<tr><td><a href="{{ route('branch.assets.maintenance.orders.show', $l) }}" class="text-decoration-none">{{ $l->log_no }}</a></td><td>{{ $l->title }}<div class="text-body-secondary">{{ __(ucfirst($l->kind)) }}</div></td>
                <td>{{ $l->reported_on->format('d M Y') }}</td><td><x-inv-status :status="$l->status === 'in_progress' ? 'open' : $l->status" /></td><td class="text-end">{{ $l->cost > 0 ? number_format($l->cost, 2) : '—' }}</td></tr>@endforeach</tbody></table></div>@endif
        @if($canUpdate)
        <form method="POST" action="{{ route('branch.assets.maintenance.orders.store', $asset) }}" class="card-body border-top row g-2 align-items-end">@csrf
            <div class="col-md-2"><label class="form-label small mb-0" for="wk">{{ __('Kind') }}</label><select id="wk" name="kind" class="form-select form-select-sm">@foreach(App\Models\Assets\MaintenanceLog::KINDS as $k)<option value="{{ $k }}" @selected($k === 'repair')>{{ __(ucfirst($k)) }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label small mb-0" for="wt">{{ __('What is wrong / needed') }}</label><input id="wt" name="title" class="form-control form-control-sm" required maxlength="150"></div>
            <div class="col-md-2"><label class="form-label small mb-0" for="wr">{{ __('Reported') }}</label><input id="wr" type="date" name="reported_on" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><div class="form-check"><input type="checkbox" class="form-check-input" id="wo" name="takes_offline" value="1" checked><label class="form-check-label small" for="wo">{{ __('Out of service meanwhile') }}</label></div></div>
            <div class="col-md-2"><button class="btn btn-sm btn-brand w-100">{{ __('Open work order') }}</button></div>
        </form>@endif
    </div>

    <div class="row g-3">
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header bg-transparent fw-semibold">{{ __('Depreciation posted') }}</div>
            @if($depreciations->isEmpty())<div class="card-body small text-body-secondary">{{ $asset->isDepreciable() ? __('Nothing posted yet.') : __('This asset is not depreciated.') }}</div>
            @else<div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Charge (৳)') }}</th><th class="text-end">{{ __('Book value (৳)') }}</th></tr></thead>
                <tbody>@foreach($depreciations as $d)<tr class="@if($d->is_reversed) text-decoration-line-through text-body-secondary @endif"><td><a href="{{ route('branch.assets.depreciation.show', $d->asset_depreciation_run_id) }}" class="text-decoration-none">{{ $d->period }}</a></td><td class="text-end">{{ number_format($d->amount, 2) }}</td><td class="text-end">{{ number_format($d->book_value_after, 2) }}</td></tr>@endforeach</tbody></table></div>@endif
        </div></div>
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header bg-transparent fw-semibold">{{ __('Next 12 months (forecast)') }}</div>
            @if(empty($forecast))<div class="card-body small text-body-secondary">—</div>
            @else<div class="table-responsive"><table class="table table-sm mb-0 small"><thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Charge (৳)') }}</th><th class="text-end">{{ __('Book value (৳)') }}</th></tr></thead>
                <tbody>@foreach($forecast as $f)<tr><td>{{ $f['period'] }}</td><td class="text-end">{{ number_format($f['amount'], 2) }}</td><td class="text-end">{{ number_format($f['book_value'], 2) }}</td></tr>@endforeach</tbody></table></div>@endif
        </div></div>
    </div>
</div>

<div class="col-xl-4">
    <div class="card mb-3">
        <div class="card-header bg-transparent fw-semibold">{{ __('Who has it') }}</div>
        <div class="card-body small">
            @if($current)
                <div class="fw-semibold">{{ $current->holderLabel() }}</div>
                <div class="text-body-secondary">{{ __('since :d', ['d' => $current->allocated_on->format('d M Y')]) }}@if($current->expected_return_on) · {{ __('back by :d', ['d' => $current->expected_return_on->format('d M Y')]) }}@endif</div>
                @if($current->isOverdue())<div class="text-danger"><i class="bi bi-exclamation-circle"></i> {{ __('Overdue') }}</div>@endif
                @if($canUpdate)<form method="POST" action="{{ route('branch.assets.allocations.return', $current) }}" class="row g-2 mt-1">@csrf
                    <div class="col-5"><input type="date" name="returned_on" value="{{ now()->toDateString() }}" class="form-control form-control-sm" aria-label="{{ __('Returned on') }}"></div>
                    <div class="col-4"><select name="return_condition" class="form-select form-select-sm" aria-label="{{ __('Condition') }}"><option value="">{{ __('Condition') }}</option>@foreach(App\Models\Assets\Asset::CONDITIONS as $c)<option value="{{ $c }}">{{ __(ucfirst($c)) }}</option>@endforeach</select></div>
                    <div class="col-3"><button class="btn btn-sm btn-outline-secondary w-100">{{ __('Take back') }}</button></div></form>@endif
            @elseif($asset->isLive())
                <div class="text-body-secondary mb-2">{{ $asset->is_under_repair ? __('Under repair — it cannot be handed out.') : __('Available in store.') }}</div>
                @if($canUpdate && ! $asset->is_under_repair)
                <form method="POST" action="{{ route('branch.assets.allocations.store', $asset) }}" class="row g-2">@csrf
                    <div class="col-12"><select name="employee_id" class="form-select form-select-sm" aria-label="{{ __('Employee') }}"><option value="">{{ __('— employee —') }}</option>@foreach($employees ?? [] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                    <div class="col-12"><select name="department_id" class="form-select form-select-sm" aria-label="{{ __('Department') }}"><option value="">{{ __('— department —') }}</option>@foreach($departments ?? [] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                    <div class="col-12"><select name="room_id" class="form-select form-select-sm" aria-label="{{ __('Room') }}"><option value="">{{ __('— room —') }}</option>@foreach($rooms as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                    <div class="col-12"><input name="assignee" class="form-control form-control-sm" maxlength="150" placeholder="{{ __('Or a name, e.g. Science club') }}" aria-label="{{ __('Name') }}"></div>
                    <div class="col-6"><input type="date" name="allocated_on" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required aria-label="{{ __('From') }}"></div>
                    <div class="col-6"><input type="date" name="expected_return_on" class="form-control form-control-sm" aria-label="{{ __('Expected back') }}" title="{{ __('Expected back') }}"></div>
                    <div class="col-12"><button class="btn btn-sm btn-brand">{{ __('Hand over') }}</button></div></form>@endif
            @endif
        </div>
        @if($asset->allocations->where('returned_on', '!=', null)->isNotEmpty())
        <ul class="list-group list-group-flush small">@foreach($asset->allocations->where('returned_on', '!=', null)->take(6) as $h)
            <li class="list-group-item">{{ $h->holderLabel() }}<div class="text-body-secondary">{{ $h->allocated_on->format('d M Y') }} → {{ $h->returned_on->format('d M Y') }}@if($h->return_condition) · {{ __(ucfirst($h->return_condition)) }}@endif</div></li>@endforeach</ul>@endif
    </div>

    @if($journal->isNotEmpty())
    <div class="card mb-3"><div class="card-header bg-transparent fw-semibold">{{ __('Entries in the books') }}</div>
        <ul class="list-group list-group-flush small">@foreach($journal as $e)<li class="list-group-item d-flex justify-content-between"><span>{{ $e->entry_no }}<div class="text-body-secondary">{{ $e->narration }}</div></span><span class="text-end">{{ number_format($e->amount, 2) }}@if($e->status === 'reversed')<div class="text-body-secondary">{{ __('reversed') }}</div>@endif</span></li>@endforeach</ul></div>@endif

    @if($canApprove && $asset->isLive())
    <form method="POST" action="{{ route('branch.assets.register.void', $asset) }}" class="card border-danger-subtle" data-confirm="{{ __('Cancel this registration?') }}">@csrf
        <div class="card-body small"><label class="form-label" for="vr">{{ __('Registered by mistake?') }}</label>
            <input id="vr" name="reason" class="form-control form-control-sm mb-2" placeholder="{{ __('Reason') }}" required maxlength="200">
            <button class="btn btn-outline-danger btn-sm">{{ __('Cancel registration') }}</button>
            <div class="form-text">{{ __('Only possible while the asset has not been allocated, depreciated or serviced; the books are reversed.') }}</div></div></form>@endif
</div>
</div>
@endsection
