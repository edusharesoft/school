@extends($layout)
@section('title', __('Mark correction requests'))

@section('content')
<x-page-header :title="__('Mark correction requests')" :breadcrumbs="[__('Marks entry') => route($routePrefix.'exams.marks.index'), __('Corrections') => null]" />

<div class="card">
    <div class="card-body border-bottom">
        <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Status') }}">
            @foreach(['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), '' => __('All')] as $value => $label)
                <a href="{{ request()->fullUrlWithQuery(['status' => $value, 'page' => null]) }}" @class(['btn', 'btn-brand' => (string) request('status', $reviewer ? 'pending' : '') === (string) $value, 'btn-outline-secondary' => (string) request('status', $reviewer ? 'pending' : '') !== (string) $value])>{{ $label }}</a>
            @endforeach
        </div>
    </div>
    @if($items->isEmpty())
        <x-empty-state icon="check2-circle" :title="__('Nothing here yet')" />
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Exam / paper') }}</th><th>{{ __('Change') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($items as $c)
                    <tr>
                        <td><div class="fw-semibold">{{ $c->mark?->student?->name }}</div><div class="small text-body-secondary">{{ $c->mark?->student?->student_code }}</div></td>
                        <td class="small">{{ $c->mark?->examination?->name }}<br>{{ $c->mark?->paper?->schoolClass?->name }} · {{ $c->mark?->paper?->subject?->name }}</td>
                        <td class="small text-nowrap"><span class="text-body-secondary">{{ App\Models\Exams\MarkCorrection::describe($c->old_marks, $c->old_absent) }}</span><br><i class="bi bi-arrow-right"></i> <strong>{{ App\Models\Exams\MarkCorrection::describe($c->new_marks, $c->new_absent) }}</strong></td>
                        <td class="small">{{ $c->reason }}<div class="text-body-secondary">{{ $c->requester?->name }} · {{ $c->created_at->format('d M Y H:i') }}</div></td>
                        <td><span class="badge text-bg-{{ ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$c->status] ?? 'secondary' }}">{{ __(ucfirst($c->status)) }}</span>
                            @if($c->reviewer)<div class="small text-body-secondary">{{ $c->reviewer->name }}@if($c->review_note): {{ $c->review_note }}@endif</div>@endif</td>
                        <td class="text-end text-nowrap">
                            @if($reviewer && $c->isPending() && $c->requested_by !== auth()->id())
                                <form method="POST" action="{{ route('branch.exams.corrections.approve', $c) }}" class="d-inline" data-confirm="{{ __('Approve this correction? Results of the exam will be processed again.') }}">
                                    @csrf <button class="btn btn-sm btn-success">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('branch.exams.corrections.reject', $c) }}" class="d-inline-flex gap-1 mt-1">
                                    @csrf
                                    <input name="note" class="form-control form-control-sm" placeholder="{{ __('Reason') }}" required maxlength="500" aria-label="{{ __('Reason') }}" style="width:140px">
                                    <button class="btn btn-sm btn-outline-danger">{{ __('Reject') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $items->links() }}</div>
    @endif
</div>
@endsection
