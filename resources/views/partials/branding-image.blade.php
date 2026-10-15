{{-- One image setting: current preview + remove checkbox + file input. Expects $key, $label, $hint, $path, $fit --}}
<div class="{{ $col ?? 'col-md-4' }}">
    <label class="form-label small fw-semibold" for="f_{{ $key }}">{{ $label }}</label>
    @if ($path)
        <div class="d-flex align-items-center gap-2 mb-2">
            <img src="{{ Storage::disk('public')->url($path) }}" alt="" class="rounded border" style="height:54px;max-width:140px;object-fit:{{ $fit ?? 'cover' }};background:#f3f4f6">
            <div class="form-check small">
                <input class="form-check-input" type="checkbox" name="remove_{{ $key }}" value="1" id="rm_{{ $key }}">
                <label class="form-check-label text-danger" for="rm_{{ $key }}">{{ __('Remove') }}</label>
            </div>
        </div>
    @endif
    <input type="file" id="f_{{ $key }}" name="{{ $key }}" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm @error($key) is-invalid @enderror">
    @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">{{ $hint }}</div>
</div>
