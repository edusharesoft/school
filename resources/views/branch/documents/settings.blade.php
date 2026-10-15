@extends('layouts.branch')
@section('title', __('Document settings'))

@section('content')
<x-page-header :title="__('Document settings')" :breadcrumbs="[__('Documents') => null, __('Settings') => null]" />
<form method="POST" action="{{ route('branch.documents.settings.update') }}" enctype="multipart/form-data" class="card" style="max-width: 980px">
    @csrf @method('PUT')
    <div class="card-body row g-3">
        <div class="col-12 fw-semibold">{{ __('Who signs') }}</div>
        <x-form.input name="signatory_name" :label="__('Name of the signatory')" :value="$values['signatory_name']" :help="__('Blank = the principal’s name of this branch (:name).', ['name' => $branch->principal_name ?: '—'])" maxlength="120" />
        <x-form.input name="signatory_title" :label="__('Title')" :value="$values['signatory_title']" maxlength="120" />
        <div class="col-md-6">
            <label class="form-label" for="signature">{{ __('Signature image (PNG / JPG, transparent background is best)') }}</label>
            <input id="signature" type="file" name="signature" accept="image/png,image/jpeg" class="form-control @error('signature') is-invalid @enderror">
            @error('signature')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($signatureUrl)<div class="mt-2 d-flex align-items-center gap-3"><img src="{{ $signatureUrl }}" alt="" style="height:48px;background:#fff;border:1px solid #e2e8f0;padding:2px">
                <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_signature" value="1" id="rm_sig"><label class="form-check-label" for="rm_sig">{{ __('Remove') }}</label></div></div>@endif
        </div>
        <div class="col-md-6">
            <label class="form-label" for="seal">{{ __('Seal / stamp image') }}</label>
            <input id="seal" type="file" name="seal" accept="image/png,image/jpeg" class="form-control @error('seal') is-invalid @enderror">
            @error('seal')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($sealUrl)<div class="mt-2 d-flex align-items-center gap-3"><img src="{{ $sealUrl }}" alt="" style="height:48px;background:#fff;border:1px solid #e2e8f0;padding:2px">
                <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_seal" value="1" id="rm_seal"><label class="form-check-label" for="rm_seal">{{ __('Remove') }}</label></div></div>@endif
        </div>

        <div class="col-12 fw-semibold mt-3">{{ __('Certificates & mark sheets') }}</div>
        <x-form.select name="certificate_style" :label="__('Certificate border')" :value="$values['certificate_style']" :options="['classic' => __('Classic (double line)'), 'modern' => __('Modern (side band)'), 'plain' => __('Plain (thin line)')]" required col="col-md-4" />
        <div class="col-md-4">
            <label class="form-label" for="accent_color">{{ __('Accent colour') }}</label>
            <input id="accent_color" type="color" name="accent_color" value="{{ old('accent_color', $values['accent_color'] ?: $branch->brandColor()) }}" class="form-control form-control-color">
            <div class="form-text">{{ __('Defaults to the branch colour.') }}</div>
        </div>
        <x-form.input name="certificate_footer" :label="__('Line at the bottom of certificates')" :value="$values['certificate_footer']" maxlength="200" col="col-md-12" />

        <div class="col-12 fw-semibold mt-3">{{ __('ID cards') }}</div>
        <x-form.select name="card_theme" :label="__('Card colour')" :value="$values['card_theme']" :options="['brand' => __('Branch colour'), 'blue' => __('Blue'), 'green' => __('Green'), 'maroon' => __('Maroon'), 'slate' => __('Slate')]" required col="col-md-4" />
        <x-form.input name="card_validity_months" type="number" min="1" max="120" :label="__('Cards are valid for (months)')" :value="$values['card_validity_months']" required col="col-md-4" />
        <x-form.input name="card_emergency_phone" :label="__('Emergency phone on the back')" :value="$values['card_emergency_phone']" :help="__('Blank = the branch phone.')" maxlength="40" col="col-md-4" />
        <x-form.switch name="card_show_blood_group" :label="__('Show the blood group')" :checked="$values['card_show_blood_group']" col="col-md-6" />
        <x-form.switch name="card_show_guardian_phone" :label="__('Show the guardian’s phone (staff: own phone)')" :checked="$values['card_show_guardian_phone']" col="col-md-6" />
        <div class="col-12">
            <label class="form-label" for="card_back_text">{{ __('Text on the back of the card') }}</label>
            <textarea id="card_back_text" name="card_back_text" rows="3" maxlength="400" class="form-control">{{ old('card_back_text', $values['card_back_text']) }}</textarea>
        </div>
    </div>
    <x-form.actions :cancel="route('branch.idcards.index')" />
</form>
@endsection
