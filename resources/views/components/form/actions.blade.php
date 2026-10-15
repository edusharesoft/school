@props(['cancel'])
<div class="card-footer bg-transparent d-flex gap-2">
    <button class="btn btn-brand" type="submit">{{ $slot->isEmpty() ? __('Save') : $slot }}</button>
    <a href="{{ $cancel }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
</div>
