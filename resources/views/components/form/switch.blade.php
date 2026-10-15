@props(['name', 'label', 'checked' => false, 'col' => 'col-12'])
@php($id = 'sw_'.str_replace(['[', ']', '.'], ['_', '', '_'], $name))
<div class="{{ $col }}">
    <div class="form-check form-switch">
        <input type="hidden" name="{{ $name }}" value="0">
        <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" value="1" id="{{ $id }}" @checked(old($name, $checked))>
        <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
    </div>
</div>
