@props(['edit' => null, 'destroy' => null, 'confirm' => null, 'canEdit' => true, 'canDelete' => true])
<div class="text-end text-nowrap">
    {{ $slot }}
    @if($edit && $canEdit)
        <a href="{{ $edit }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
    @endif
    @if($destroy && $canDelete)
        <form method="POST" action="{{ $destroy }}" class="d-inline" data-confirm="{{ $confirm ?? __('Delete this record?') }}">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
        </form>
    @endif
</div>
