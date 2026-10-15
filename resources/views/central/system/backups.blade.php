@extends('layouts.central')
@section('title', __('Backups'))

@section('content')
<x-page-header :title="__('Backups')" :breadcrumbs="[__('System') => null, __('Backups') => null]">
    <x-slot:actions>
        @can('backups.run')<form method="POST" action="{{ route('central.backups.run') }}">@csrf<button class="btn btn-brand btn-sm"><i class="bi bi-cloud-arrow-down me-1"></i>{{ __('Back up now') }}</button></form>@endcan
    </x-slot:actions>
</x-page-header>
<div class="alert alert-info small">
    {{ __('A backup holds every table of every institution plus uploaded files. It runs every night. A backup is only proven when it has been restored: "Test restore" builds an empty scratch database, loads the backup and compares every table — it also runs weekly.') }}
    @unless($passphrase) <strong>{{ __('Set BACKUP_PASSPHRASE to encrypt the files.') }}</strong>@endunless
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>#</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th>{{ __('Size') }}</th><th>{{ __('Contents') }}</th><th>{{ __('Checked') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($runs as $r)
        <tr>
            <td>{{ $r->id }}</td>
            <td>{{ $r->started_at?->format('d M Y, h:i A') }}<div class="small text-body-secondary">{{ $r->trigger === 'schedule' ? __('Scheduled') : ($r->creator?->name ?? __('Manual')) }}</div></td>
            <td><span class="badge text-bg-{{ ['done' => 'success', 'failed' => 'danger', 'running' => 'info'][$r->status] ?? 'secondary' }}">{{ ['done' => __('Done'), 'failed' => __('Failed'), 'running' => __('Running')][$r->status] ?? $r->status }}</span>
                @if($r->encrypted) <i class="bi bi-lock" title="{{ __('Encrypted') }}"></i>@endif
                @if($r->error)<div class="small text-danger">{{ $r->error }}</div>@endif</td>
            <td>{{ $r->bytes ? number_format($r->bytes / 1048576, 2).' MB' : '—' }}</td>
            <td class="small">{{ $r->tables_count ? $r->tables_count.' '.__('tables').', '.number_format($r->rows_count).' '.__('rows').', '.$r->files_count.' '.__('files') : '—' }}</td>
            <td class="small">@if($r->verify_status)<span class="badge text-bg-{{ $r->verify_status === 'ok' ? 'success' : 'danger' }}">{{ $r->verify_status === 'ok' ? ($r->drilled ? __('Restore tested') : __('File checked')) : __('Check failed') }}</span>
                <div class="text-body-secondary">{{ $r->verified_at?->diffForHumans() }}</div>@if($r->verify_status !== 'ok')<div class="text-danger">{{ \Illuminate\Support\Str::limit($r->verify_note, 160) }}</div>@endif @else — @endif</td>
            <td class="text-end text-nowrap">
                @can('backups.run')
                @if($r->status === 'done')
                    <form method="POST" action="{{ route('central.backups.verify', $r) }}" class="d-inline">@csrf<input type="hidden" name="drill" value="1"><button class="btn btn-sm btn-outline-secondary">{{ __('Test restore') }}</button></form>
                    <a href="{{ route('central.backups.download', $r) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Download') }}"><i class="bi bi-download"></i></a>
                @endif
                <form method="POST" action="{{ route('central.backups.destroy', $r) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this backup?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button></form>
                @endcan
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('No backups yet.') }}</td></tr>
    @endforelse
    </tbody></table></div>
    @if($runs->hasPages())<div class="card-footer">{{ $runs->links() }}</div>@endif
</div>
@endsection
