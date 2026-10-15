@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'col' => 'col-md-6', 'placeholder' => null, 'help' => null])
@php($id = str_replace(['[', ']', '.'], ['_', '', '_'], $name))
@php($dot = str_replace(['[', ']'], ['.', ''], $name))
@php($selected = (string) old($dot, $value instanceof \BackedEnum ? $value->value : $value))
<div class="{{ $col }}">
    <label class="form-label" for="{{ $id }}">{{ $label }}@if($required) <span class="text-danger">*</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}" @required($required) {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($dot)]) }}>
        @if($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($selected === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @if($help)<div class="form-text">{{ $help }}</div>@endif
    @error($dot)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
