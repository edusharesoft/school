@props(['icon' => 'inbox', 'title' => __('Nothing here yet'), 'message' => null])
<div class="empty-state">
    <i class="bi bi-{{ $icon }}"></i>
    <div class="fw-semibold text-body">{{ $title }}</div>
    @if($message)<div class="small">{{ $message }}</div>@endif
    {{ $slot }}
</div>
