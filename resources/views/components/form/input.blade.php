@props(['name', 'label', 'value' => null, 'type' => 'text', 'required' => false, 'col' => 'col-md-6', 'help' => null])
@php($id = str_replace(['[', ']', '.'], ['_', '', '_'], $name))
@php($dot = str_replace(['[', ']'], ['.', ''], $name))
<div class="{{ $col }}">
    <label class="form-label" for="{{ $id }}">{{ $label }}@if($required) <span class="text-danger">*</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($dot, $value) }}" @required($required)
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($dot)]) }}>
    @if($help)<div class="form-text">{{ $help }}</div>@endif
    @error($dot)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
