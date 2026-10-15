@extends($layout)
@section('title', __('Attendance corrections'))

@section('content')
<x-page-header :title="__('Attendance correction requests')" :breadcrumbs="[__('Attendance') => route($routePrefix.'attendance.students.index'), __('Corrections') => null]" />

<div class="card">
    <div class="card-body border-bottom d-flex flex-wrap gap-2">
        @foreach(['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), 'all' => __('All')] as $key => $label)
            <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}" @class(['btn btn-sm', 'btn-brand' => $status === $key, 'btn-outline-secondary' => $status !== $key])>{{ $label }}</a>
        @endforeach
    </div>

    @if($corrections->isEmpty())
        <x-empty-state icon="check2-circle" :title="__('No correction requests')" />
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>{{ __('Person') }}</th><th>{{ __('Date') }}</th><th>{{ __('Change') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Requested by') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @foreach($corrections as $c)
                    @php
                        $rec = $c->attendable;
                        $isStaff = $c->isForStaff();
                        $canReview = $routePrefix === 'branch.' && $c->isPending() && ($isStaff ? $approveStaff : $approveStudents) && $c->requested_by !== auth()->id();
                    @endphp
                    <tr>
                        <td>
                            @if($isStaff)
                                <div class="fw-medium">{{ $rec?->employee?->name ?? '—' }}</div><div class="small"><i class="fa-solid fa-user-tie"></i> <code>{{ $rec?->employee?->employee_code }}</code></div>
                            @else
                                <div class="fw-medium">{{ $rec?->student?->name ?? '—' }}</div><div class="small"><i class="fa-solid fa-user-graduate"></i> {{ $rec?->section?->schoolClass?->name }} {{ $rec?->section?->name }}</div>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ $rec?->date?->format('d M Y') }}</td>
                        <td class="text-nowrap"><span class="badge text-bg-{{ $c->current_status->tone() }}">{{ $c->current_status->label() }}</span> → <span class="badge text-bg-{{ $c->requested_status->tone() }}">{{ $c->requested_status->label() }}</span>
                            @if($c->requested_check_in || $c->requested_check_out)<div class="small">{{ $c->requested_check_in ?? '—' }} – {{ $c->requested_check_out ?? '—' }}</div>@endif</td>
                        <td class="small" style="max-width:280px">{{ $c->reason }}
                            @if($c->review_note)<div class="text-body-secondary fst-italic">{{ $c->reviewer?->name }}: {{ $c->review_note }}</div>@endif</td>
                        <td class="small">{{ $c->requester?->name }}<div class="text-body-secondary">{{ $c->created_at->diffForHumans() }}</div></td>
                        <td><span class="badge text-bg-{{ ['approved' => 'success', 'rejected' => 'danger'][$c->status] ?? 'warning' }}">{{ __(ucfirst($c->status)) }}</span></td>
                        <td class="text-end text-nowrap">
                            @if($canReview)
                                <form method="POST" action="{{ route('branch.attendance.corrections.approve', $c) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-success" title="{{ __('Approve') }}"><i class="bi bi-check2"></i></button></form>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cr{{ $c->id }}" title="{{ __('Reject') }}"><i class="bi bi-x-lg"></i></button>
                            @endif
                        </td>
                    </tr>
                    @if($canReview)
                        <tr class="collapse" id="cr{{ $c->id }}"><td colspan="7">
                            <form method="POST" action="{{ route('branch.attendance.corrections.reject', $c) }}" class="d-flex gap-2">@csrf
                                <input name="note" class="form-control form-control-sm" placeholder="{{ __('Reason for rejection') }}" required minlength="3" maxlength="500" aria-label="{{ __('Reason for rejection') }}">
                                <button class="btn btn-sm btn-danger">{{ __('Reject') }}</button></form>
                        </td></tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $corrections->links() }}</div>
    @endif
</div>
@endsection
