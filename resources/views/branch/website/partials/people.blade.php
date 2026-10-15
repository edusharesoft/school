{{-- Fixed slots for a list of people / messages. A slot with an empty name is dropped on save.
     $key: messages|team|teachers  $photoKey: message_photos|team_photos|teacher_photos  $slots: int  $items: saved list  $withText: bool --}}
@use('App\Support\Website')
@php($rows = array_pad(array_values($items), $slots, []))
<div class="row g-3">
@foreach($rows as $i => $it)
    @php($pic = Website::img($it['photo'] ?? null))
    <div class="col-lg-6"><div class="border rounded p-3 h-100">
        <div class="d-flex gap-3">
            <div class="flex-shrink-0 text-center" style="width:84px">
                <div class="border rounded bg-body-tertiary overflow-hidden" style="width:84px;height:84px">@if($pic)<img src="{{ $pic }}" alt="" style="width:100%;height:100%;object-fit:cover">@else<span class="d-flex h-100 align-items-center justify-content-center text-body-secondary"><i class="fa-solid fa-user"></i></span>@endif</div>
                @if($pic)<input type="hidden" name="{{ $key }}[{{ $i }}][photo]" value="{{ $it['photo'] }}">
                    <label class="small d-block mt-1"><input type="checkbox" class="form-check-input" name="{{ $key }}[{{ $i }}][remove_photo]" value="1"> {{ __('Remove') }}</label>@endif
            </div>
            <div class="flex-grow-1 row g-2">
                <div class="col-12">
                    <label class="form-label small mb-0" for="{{ $key }}_{{ $i }}_emp">{{ __('Fill from an employee (optional)') }}</label>
                    <select id="{{ $key }}_{{ $i }}_emp" name="{{ $key }}[{{ $i }}][employee_id]" class="form-select form-select-sm js-employee-pick" data-target="{{ $key }}_{{ $i }}">
                        <option value="">—</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}" data-name="{{ $e->name }}" data-role="{{ $e->designation?->displayName() ?? '' }}" @selected((string) old("$key.$i.employee_id") === (string) $e->id)>{{ $e->name }}@if($e->designation) — {{ $e->designation->displayName() }}@endif</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label small mb-0" for="{{ $key }}_{{ $i }}_name">{{ __('Name') }}</label>
                    <input id="{{ $key }}_{{ $i }}_name" name="{{ $key }}[{{ $i }}][name]" maxlength="120" class="form-control form-control-sm @error("$key.$i.name") is-invalid @enderror" value="{{ old("$key.$i.name", $it['name'] ?? '') }}">
                    @error("$key.$i.name")<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label small mb-0" for="{{ $key }}_{{ $i }}_role">{{ __('Designation') }}</label>
                    <input id="{{ $key }}_{{ $i }}_role" name="{{ $key }}[{{ $i }}][designation]" maxlength="120" class="form-control form-control-sm" value="{{ old("$key.$i.designation", $it['designation'] ?? '') }}"></div>
                @if($withText)
                    <div class="col-12"><label class="form-label small mb-0" for="{{ $key }}_{{ $i }}_text">{{ __('Message') }}</label>
                        <textarea id="{{ $key }}_{{ $i }}_text" name="{{ $key }}[{{ $i }}][text]" rows="4" maxlength="6000" class="form-control form-control-sm">{{ old("$key.$i.text", $it['text'] ?? '') }}</textarea></div>
                @else
                    <div class="col-12"><label class="form-label small mb-0" for="{{ $key }}_{{ $i }}_note">{{ __('Short note (optional)') }}</label>
                        <input id="{{ $key }}_{{ $i }}_note" name="{{ $key }}[{{ $i }}][note]" maxlength="160" class="form-control form-control-sm" value="{{ old("$key.$i.note", $it['note'] ?? '') }}"></div>
                @endif
                <div class="col-12"><label class="form-label small mb-0" for="{{ $key }}_{{ $i }}_photo">{{ __('Photo') }}</label>
                    <input id="{{ $key }}_{{ $i }}_photo" type="file" name="{{ $photoKey }}[{{ $i }}]" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm @error("$photoKey.$i") is-invalid @enderror">
                    @error("$photoKey.$i")<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
        </div>
    </div></div>
@endforeach
</div>
