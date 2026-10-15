{{-- Employee profile fields (create + edit). $canSeeConfidential controls NID and salary. --}}
@php($confidential = $canSeeConfidential ?? true)
<div class="card mb-3">
    <div class="card-header bg-transparent"><h2 class="h6 mb-0"><i class="fa-solid fa-briefcase me-1 text-primary"></i> {{ __('Employment') }}</h2></div>
    <div class="card-body row g-3">
        <x-form.select name="employee_type" :label="__('Type')" :value="$employee->employee_type" required col="col-md-3"
            :options="['teacher' => __('Teacher'), 'staff' => __('Other staff')]" />
        <x-form.select name="employment_type" :label="__('Employment type')" :value="$employee->employment_type" required col="col-md-3"
            :options="collect(App\Models\HR\Employee::EMPLOYMENT_TYPES)->mapWithKeys(fn ($t) => [$t => __(ucfirst(str_replace('_', ' ', $t)))])->all()" />
        <div class="col-md-3">
            <label class="form-label" for="designation_id">{{ __('Designation') }}</label>
            <select id="designation_id" name="designation_id" class="form-select @error('designation_id') is-invalid @enderror">
                <option value="">—</option>
                @foreach($designations->groupBy('category') as $category => $items)
                    <optgroup label="{{ $category === 'teaching' ? __('Teaching') : __('Non-teaching') }}">
                        @foreach($items as $d)<option value="{{ $d->id }}" @selected((string) old('designation_id', $employee->designation_id) === (string) $d->id)>{{ $d->name }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
            @error('designation_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <x-form.select name="department_id" :label="__('Department')" :value="$employee->department_id" col="col-md-3" placeholder="—" :options="$departments->all()" />
        <x-form.input name="joining_date" type="date" :label="__('Joining date')" :value="$employee->joining_date?->toDateString()" required col="col-md-3" />
        <x-form.input name="experience_years" type="number" step="0.5" min="0" max="60" :label="__('Experience (years)')" :value="$employee->experience_years" col="col-md-3" />
        @if($confidential)
            <x-form.input name="basic_salary" type="number" step="0.01" min="0" :label="__('Basic salary (BDT)')" :value="$employee->basic_salary" col="col-md-3"
                :help="__('Confidential — visible only with the salary permission.')" />
        @endif
        <div class="col-12"><label class="form-label" for="qualification">{{ __('Qualification') }}</label>
            <textarea id="qualification" name="qualification" rows="2" class="form-control @error('qualification') is-invalid @enderror" maxlength="2000"
                placeholder="{{ __('e.g. M.Sc. in Mathematics (DU, 2012); B.Ed. (2014)') }}">{{ old('qualification', $employee->qualification) }}</textarea>
            @error('qualification')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-transparent"><h2 class="h6 mb-0"><i class="fa-solid fa-id-card me-1 text-primary"></i> {{ __('Personal information') }}</h2></div>
    <div class="card-body row g-3">
        <x-form.input name="name" :label="__('Full name (English)')" :value="$employee->name" required col="col-md-6" maxlength="191" />
        <x-form.input name="name_bn" :label="__('Full name (Bangla)')" :value="$employee->name_bn" col="col-md-6" class="bn" maxlength="191" />
        <x-form.select name="gender" :label="__('Gender')" :value="$employee->gender" required col="col-md-3"
            :options="['male' => __('Male'), 'female' => __('Female'), 'other' => __('Other')]" placeholder="—" />
        <x-form.input name="date_of_birth" type="date" :label="__('Date of birth')" :value="$employee->date_of_birth?->toDateString()" col="col-md-3" />
        <x-form.select name="blood_group" :label="__('Blood group')" :value="$employee->blood_group" col="col-md-3" placeholder="—"
            :options="array_combine(App\Models\Students\Student::BLOOD_GROUPS, App\Models\Students\Student::BLOOD_GROUPS)" />
        <x-form.input name="religion" :label="__('Religion')" :value="$employee->religion" col="col-md-3" maxlength="30" />
        <x-form.input name="phone" :label="__('Mobile')" :value="$employee->phone" required col="col-md-4" placeholder="01XXXXXXXXX" inputmode="tel" />
        <x-form.input name="email" type="email" :label="__('E-mail')" :value="$employee->email" col="col-md-4" />
        @if($confidential)
            <x-form.input name="nid_number" :label="__('NID number')" :value="$employee->nid_number" col="col-md-4" inputmode="numeric"
                :help="__('10, 13 or 17 digits. Confidential.')" />
        @endif
        <div class="col-md-6"><label class="form-label" for="present_address">{{ __('Present address') }}</label>
            <textarea id="present_address" name="present_address" rows="2" class="form-control" maxlength="1000">{{ old('present_address', $employee->present_address) }}</textarea></div>
        <div class="col-md-6"><label class="form-label" for="permanent_address">{{ __('Permanent address') }}</label>
            <textarea id="permanent_address" name="permanent_address" rows="2" class="form-control" maxlength="1000">{{ old('permanent_address', $employee->permanent_address) }}</textarea></div>
        <x-form.input name="emergency_contact_name" :label="__('Emergency contact name')" :value="$employee->emergency_contact_name" col="col-md-4" />
        <x-form.input name="emergency_contact_phone" :label="__('Emergency contact mobile')" :value="$employee->emergency_contact_phone" col="col-md-4" inputmode="tel" />
        <div class="col-md-4"><label class="form-label" for="photo">{{ __('Photo') }}</label>
            <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control @error('photo') is-invalid @enderror">
            <div class="form-text">{{ __('JPG / PNG / WebP, max 1 MB.') }}</div>
            @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </div>
</div>
