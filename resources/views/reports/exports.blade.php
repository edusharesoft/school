@extends($layout)
@section('title', __('My exports'))

@section('content')
<x-page-header :title="__('My exports')" :breadcrumbs="[__('Reports') => route($prefix.'.index'), __('My exports') => null]">
    <x-slot:actions><a href="{{ route($prefix.'.exports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate me-1"></i>{{ __('Refresh') }}</a></x-slot:actions>
</x-page-header>

<p class="small text-body-secondary">{{ __('Exports prepared in the background are kept for :n days and then deleted. Only you can see and download your exports.', ['n' => $keepDays]) }}</p>

@if($runs->isEmpty())
    <x-empty-state icon="file-earmark-arrow-down" :title="__('No exports yet')" :message="__('Use Export → Prepare in the background on any report.')" />
@else
<div class="card"><div class="table-responsive">
    <table class="table table-sm align-middle mb-0 small">
        <thead class="table-light"><tr><th>{{ __('Report') }}</th><th>{{ __('Format') }}</th><th>{{ __('Asked') }}</th><th class="text-end">{{ __('Rows') }}</th><th class="text-end">{{ __('Size') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
            @foreach($runs as $run)
                <tr>
                    <td class="fw-semibold">{{ $run->title }}@if($run->error)<div class="text-danger fw-normal">{{ $run->error }}</div>@endif</td>
                    <td class="text-uppercase">{{ $run->format }}</td>
                    <td>{{ $run->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-end">{{ $run->row_count !== null ? number_format($run->row_count) : '—' }}</td>
                    <td class="text-end">{{ $run->bytes ? number_format($run->bytes / 1024, 1).' KB' : '—' }}</td>
                    <td>
                        @switch($run->status)
                            @case('done')<span class="badge text-bg-success">{{ __('Ready') }}</span>@if($run->expires_at)<div class="text-body-secondary">{{ __('until :d', ['d' => $run->expires_at->format('d/m/Y')]) }}</div>@endif @break
                            @case('failed')<span class="badge text-bg-danger">{{ __('Failed') }}</span> @break
                            @case('expired')<span class="badge text-bg-secondary">{{ __('Expired') }}</span> @break
                            @case('running')<span class="badge text-bg-info">{{ __('Preparing') }}</span> @break
                            @default<span class="badge text-bg-warning">{{ __('Waiting') }}</span>
                        @endswitch
                    </td>
                    <td class="text-end text-nowrap">
                        @if($run->isDownloadable())<a href="{{ route($prefix.'.exports.download', $run) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-download"></i> {{ __('Download') }}</a>@endif
                        @unless($run->isPending())
                            <form method="POST" action="{{ route($prefix.'.exports.destroy', $run) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this export?') }}')">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="fa-solid fa-trash"></i></button></form>
                        @endunless
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div></div>
@endif
@endsection
