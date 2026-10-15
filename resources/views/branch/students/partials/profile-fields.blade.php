{{-- Shared student profile fields (admission + edit). Expects $student. --}}
<div class="card mb-3">
    <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Student information') }}</h2></div>
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Full name (English)')" :value="$student->name" required col="col-md-6" maxlength="191" />
        <x-form.input name="name_bn" :label="__('Full name (Bangla)')" :value="$student->name_bn" col="col-md-6" maxlength="191" class="bn" />
        <x-form.select name="gender" :label="__('Gender')" :value="$student->gender" required col="col-md-3" :placeholder="__('Choose…')"
            :options="['male' => __('Male'), 'female' => __('Female'), 'other' => __('Other')]" />
        <x-form.input name="date_of_birth" type="date" :label="__('Date of birth')" :value="$student->date_of_birth?->toDateString()" required col="col-md-3" />
        <x-form.input name="birth_registration_no" :label="__('Birth registration no.')" :value="$student->birth_registration_no" col="col-md-3"
            maxlength="17" inputmode="numeric" :help="__('17 digits')" />
        <x-form.select name="blood_group" :label="__('Blood group')" :value="$student->blood_group" col="col-md-3" :placeholder="__('Unknown')"
            :options="array_combine(App\Models\Students\Student::BLOOD_GROUPS, App\Models\Students\Student::BLOOD_GROUPS)" />
        <x-form.input name="religion" :label="__('Religion')" :value="$student->religion" col="col-md-3" maxlength="30" />
        <x-form.input name="nationality" :label="__('Nationality')" :value="$student->nationality" col="col-md-3" maxlength="50" />
        <x-form.input name="phone" :label="__('Student mobile')" :value="$student->phone" col="col-md-3" placeholder="+8801XXXXXXXXX" />
        <x-form.input name="email" type="email" :label="__('E-mail')" :value="$student->email" col="col-md-3" />
        <x-form.input name="present_address" :label="__('Present address')" :value="$student->present_address" col="col-md-6" />
        <x-form.input name="permanent_address" :label="__('Permanent address')" :value="$student->permanent_address" col="col-md-6" />
        <x-form.input name="previous_school" :label="__('Previous school')" :value="$student->previous_school" col="col-md-6" />
        <x-form.input name="admission_date" type="date" :label="__('Admission date')" :value="$student->admission_date?->toDateString()" col="col-md-3" :required="$student->exists" />
        <div class="col-md-3">
            <label class="form-label" for="photo">{{ __('Photo') }}</label>
            <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control @error('photo') is-invalid @enderror">
            <div class="form-text">{{ __('JPG/PNG/WebP, ≤1 MB') }}</div>
            @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <x-form.input name="emergency_contact_name" :label="__('Emergency contact name')" :value="$student->emergency_contact_name" col="col-md-6" />
        <x-form.input name="emergency_contact_phone" :label="__('Emergency contact phone')" :value="$student->emergency_contact_phone" col="col-md-6" />
        @if(! $student->exists || auth()->user()->can('viewHealth', $student))
            <div class="col-12">
                <label class="form-label" for="medical_notes">{{ __('Medical notes') }} <span class="badge text-bg-warning">{{ __('Restricted') }}</span></label>
                <textarea id="medical_notes" name="medical_notes" rows="2" maxlength="2000" class="form-control">{{ old('medical_notes', $student->exists ? $student->medical_notes : '') }}</textarea>
                <div class="form-text">{{ __('Stored encrypted; visible only to staff with the health-information permission.') }}</div>
            </div>
        @endif
    </div>
</div>
