{{-- Applicant form fields (public online form + back-office offline entry). $campaign decides optional fields and documents. --}}
@php
    $show = fn ($f) => $campaign->shows($f);
    $req = fn ($f) => $campaign->requires($f);
@endphp
<div class="card mb-3">
    <div class="card-header bg-transparent"><h2 class="h6 mb-0"><i class="fa-solid fa-user-graduate me-1 text-primary"></i> {{ __('Applicant') }}</h2></div>
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Full name (English)')" required col="col-md-6" maxlength="191" />
        @if($show('name_bn'))<x-form.input name="name_bn" :label="__('Full name (Bangla)')" :required="$req('name_bn')" col="col-md-6" class="bn" maxlength="191" />@endif
        <x-form.select name="gender" :label="__('Gender')" required col="col-md-3" placeholder="—" :options="['male' => __('Male'), 'female' => __('Female'), 'other' => __('Other')]" />
        <x-form.input name="date_of_birth" type="date" :label="__('Date of birth')" required col="col-md-3" />
        @if($show('birth_registration_no'))<x-form.input name="birth_registration_no" :label="__('Birth registration no.')" :required="$req('birth_registration_no')" col="col-md-6" inputmode="numeric" maxlength="17" :help="__('17 digits')" />@endif
        @if($show('religion'))<x-form.input name="religion" :label="__('Religion')" :required="$req('religion')" col="col-md-3" maxlength="30" />@endif
        @if($show('blood_group'))<x-form.select name="blood_group" :label="__('Blood group')" :required="$req('blood_group')" col="col-md-3" placeholder="—"
            :options="array_combine(App\Models\Students\Student::BLOOD_GROUPS, App\Models\Students\Student::BLOOD_GROUPS)" />@endif
        <x-form.input name="phone" :label="__('Contact mobile')" required col="col-md-3" placeholder="01XXXXXXXXX" inputmode="tel" :help="__('Used to check the application status.')" />
        @if($show('email'))<x-form.input name="email" type="email" :label="__('E-mail')" :required="$req('email')" col="col-md-3" />@endif
        <div class="col-md-6"><label class="form-label" for="present_address">{{ __('Present address') }} <span class="text-danger">*</span></label>
            <textarea id="present_address" name="present_address" rows="2" class="form-control @error('present_address') is-invalid @enderror" required maxlength="1000">{{ old('present_address') }}</textarea>
            @error('present_address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        @if($show('permanent_address'))
            <div class="col-md-6"><label class="form-label" for="permanent_address">{{ __('Permanent address') }}@if($req('permanent_address')) <span class="text-danger">*</span>@endif</label>
                <textarea id="permanent_address" name="permanent_address" rows="2" class="form-control @error('permanent_address') is-invalid @enderror" maxlength="1000" @required($req('permanent_address'))>{{ old('permanent_address') }}</textarea>
                @error('permanent_address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        @endif
        @if($show('previous_school'))<x-form.input name="previous_school" :label="__('Previous school')" :required="$req('previous_school')" col="col-md-6" maxlength="191" />@endif
        @if($show('previous_class'))<x-form.input name="previous_class" :label="__('Previous class')" :required="$req('previous_class')" col="col-md-3" maxlength="60" />@endif
        @if($show('previous_result'))<x-form.input name="previous_result" :label="__('Previous result (GPA / position)')" :required="$req('previous_result')" col="col-md-3" maxlength="60" />@endif
        @if($show('photo'))
            <div class="col-md-6"><label class="form-label" for="photo">{{ __('Photo') }}@if($req('photo')) <span class="text-danger">*</span>@endif</label>
                <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control @error('photo') is-invalid @enderror" @required($req('photo'))>
                <div class="form-text">{{ __('JPG / PNG / WebP, max 1 MB.') }}</div>
                @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-transparent"><h2 class="h6 mb-0"><i class="fa-solid fa-people-roof me-1 text-primary"></i> {{ __('Parents / guardian') }}</h2></div>
    <div class="card-body row g-3">
        @if($show('father_name'))<x-form.input name="father_name" :label="__('Father’s name')" :required="$req('father_name')" col="col-md-6" maxlength="191" />@endif
        @if($show('mother_name'))<x-form.input name="mother_name" :label="__('Mother’s name')" :required="$req('mother_name')" col="col-md-6" maxlength="191" />@endif
        <x-form.input name="guardian_name" :label="__('Guardian name')" required col="col-md-4" maxlength="191" />
        <x-form.select name="guardian_relation" :label="__('Relation')" required col="col-md-2" placeholder="—"
            :options="collect($relations)->mapWithKeys(fn ($r) => [$r => __(ucfirst($r))])->all()" />
        <x-form.input name="guardian_phone" :label="__('Guardian mobile')" required col="col-md-3" placeholder="01XXXXXXXXX" inputmode="tel" />
        @if($show('guardian_occupation'))<x-form.input name="guardian_occupation" :label="__('Occupation')" :required="$req('guardian_occupation')" col="col-md-3" maxlength="100" />@endif
        @if($show('emergency_contact_phone'))<x-form.input name="emergency_contact_phone" :label="__('Emergency contact mobile')" :required="$req('emergency_contact_phone')" col="col-md-3" inputmode="tel" />@endif
    </div>
</div>

@if(! empty($campaign->required_documents))
<div class="card mb-3">
    <div class="card-header bg-transparent"><h2 class="h6 mb-0"><i class="fa-solid fa-paperclip me-1 text-primary"></i> {{ __('Required documents') }}</h2></div>
    <div class="card-body row g-3">
        @foreach($campaign->required_documents as $type)
            <div class="col-md-6"><label class="form-label" for="doc_{{ $type }}">{{ __(ucfirst(str_replace('_', ' ', $type))) }} <span class="text-danger">*</span></label>
                <input id="doc_{{ $type }}" type="file" name="documents[{{ $type }}]" accept="application/pdf,image/jpeg,image/png,image/webp" class="form-control @error('documents.'.$type) is-invalid @enderror" required>
                @error('documents.'.$type)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        @endforeach
        <div class="col-12 form-text">{{ __('PDF / JPG / PNG, up to 5 MB each.') }}</div>
    </div>
</div>
@endif
