@extends('layouts.auth')
@section('title', __('Register your institution'))
@section('heading', __('Register your institution'))
@section('subheading', __('Fill in the information below to open your school / college portal.'))
@section('auth_kind', 'register')
@section('auth_size', 'wide')

@section('content')
@php($bad = fn ($name) => $errors->has($name))
<form method="POST" action="{{ route('register.store') }}" novalidate>
    @csrf
    <div class="row g-2">
        <div class="col-sm-6">
            <div @class(['au-field', 'is-invalid' => $bad('name')])><span class="ic"><i class="fa-regular fa-user"></i></span>
                <input name="name" value="{{ old('name') }}" placeholder="{{ __('Your name') }} *" aria-label="{{ __('Your name') }}" autocomplete="name" required></div>
            @error('name')<div class="au-err">{{ $message }}</div>@enderror
        </div>
        <div class="col-sm-6">
            <div @class(['au-field', 'is-invalid' => $bad('mobile')])><span class="ic"><i class="fa-solid fa-mobile-screen"></i></span>
                <input name="mobile" value="{{ old('mobile') }}" placeholder="{{ __('Mobile') }} (01XXXXXXXXX) *" aria-label="{{ __('Mobile') }}" inputmode="tel" autocomplete="tel" required></div>
            @error('mobile')<div class="au-err">{{ $message }}</div>@enderror
        </div>
    </div>
    <div @class(['au-field', 'is-invalid' => $bad('email')])><span class="ic"><i class="fa-regular fa-envelope"></i></span>
        <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('E-mail address') }} *" aria-label="{{ __('E-mail') }}" autocomplete="email" required></div>
    @error('email')<div class="au-err">{{ $message }}</div>@enderror

    <div @class(['au-field', 'is-invalid' => $bad('institution_name')])><span class="ic"><i class="fa-solid fa-school"></i></span>
        <input name="institution_name" id="institution_name" value="{{ old('institution_name') }}" placeholder="{{ __('Institution name') }} *" aria-label="{{ __('Institution name') }}" required></div>
    @error('institution_name')<div class="au-err">{{ $message }}</div>@enderror

    <div @class(['au-field', 'is-invalid' => $bad('subdomain')])><span class="ic"><i class="fa-solid fa-globe"></i></span>
        <input name="subdomain" id="subdomain" value="{{ old('subdomain') }}" placeholder="your-school *" aria-label="{{ __('Subdomain') }}"
               autocomplete="off" autocapitalize="off" spellcheck="false" inputmode="url" required>
        <span class="post">.{{ $baseDomain }}</span></div>
    @error('subdomain')<div class="au-err">{{ $message }}</div>@enderror
    <div class="au-help" id="subHelp">{{ __('English letters (a-z), digits and hyphens. Typing in Bangla is converted automatically, e.g. "ঢাকা স্কুল" → dhaka-skul.') }}</div>

    <div @class(['au-field', 'is-invalid' => $bad('address')])><span class="ic"><i class="fa-solid fa-location-dot"></i></span>
        <textarea name="address" rows="2" placeholder="{{ __('Full address') }} *" aria-label="{{ __('Address') }}" required>{{ old('address') }}</textarea></div>
    @error('address')<div class="au-err">{{ $message }}</div>@enderror

    <div class="row g-2">
        <div class="col-sm-6">
            <div @class(['au-field', 'is-invalid' => $bad('password')])><span class="ic"><i class="fa-solid fa-lock"></i></span>
                <input type="password" name="password" placeholder="{{ __('Password') }} *" aria-label="{{ __('Password') }}" autocomplete="new-password" required>
                <button type="button" class="eye" tabindex="-1" aria-label="{{ __('Show password') }}"><i class="fa-regular fa-eye-slash"></i></button></div>
            @error('password')<div class="au-err">{{ $message }}</div>@else<div class="au-help">{{ __('At least 8 characters with letters and numbers.') }}</div>@enderror
        </div>
        <div class="col-sm-6">
            <div class="au-field"><span class="ic"><i class="fa-solid fa-lock"></i></span>
                <input type="password" name="password_confirmation" placeholder="{{ __('Confirm password') }} *" aria-label="{{ __('Confirm password') }}" autocomplete="new-password" required>
                <button type="button" class="eye" tabindex="-1" aria-label="{{ __('Show password') }}"><i class="fa-regular fa-eye-slash"></i></button></div>
        </div>
    </div>

    <div class="form-check mb-3">
        <input @class(['form-check-input', 'is-invalid' => $bad('terms')]) type="checkbox" name="terms" value="1" id="terms" @checked(old('terms'))>
        <label class="form-check-label" for="terms">{{ __('I agree to the Terms & Conditions and Privacy Policy.') }}</label>
        @error('terms')<div class="au-err mt-1">{{ $message }}</div>@enderror
    </div>
    <button class="au-btn" type="submit"><i class="fa-solid fa-school"></i> {{ __('Create my institution') }}</button>
    <a href="{{ route('login') }}" class="au-btn-2">{{ __('Already have an account? Sign in') }}</a>
    <div class="au-note"><i class="fa-solid fa-circle-info text-primary"></i> {{ __('Your portal address will be') }}:
        <code id="previewUrl">{{ old('subdomain') ?: 'your-school' }}.{{ $baseDomain }}</code></div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('subdomain'), help = document.getElementById('subHelp'), preview = document.getElementById('previewUrl');
    const nameInput = document.getElementById('institution_name');
    const base = @json($baseDomain), checkUrl = @json(route('register.check-subdomain'));
    const hint = help.textContent;
    const msg = {
        min: @json(__('At least 3 characters.')),
        ok: @json(__('is available')),
        taken: @json(__('This address is already taken. Please choose another.')),
        invalid: @json(__('Only English letters, digits and hyphens.')),
    };
    let t, composing = false, touched = input.value !== '';
    const slug = (v, keep) => window.slugifyBn ? window.slugifyBn(v, keep) : v.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
    const run = () => {
        const raw = input.value, clean = slug(raw, true);
        if (clean !== raw) { input.value = clean; try { input.setSelectionRange(clean.length, clean.length); } catch (e) {} }
        const s = clean.replace(/^-+|-+$/g, '');
        preview.textContent = (s || 'your-school') + '.' + base;
        clearTimeout(t);
        if (s.length < 3) { help.className = 'au-help'; help.textContent = s.length ? msg.min : hint; return; }
        t = setTimeout(async () => {
            try {
                const r = await fetch(checkUrl + '?subdomain=' + encodeURIComponent(s), { headers: { Accept: 'application/json' } });
                const j = await r.json();
                help.className = 'au-help ' + (j.available ? 'text-success' : 'text-danger');
                help.textContent = j.available ? '✓ ' + j.host + ' ' + msg.ok : '✗ ' + (j.valid ? msg.taken : msg.invalid);
            } catch (e) { /* offline: server-side validation still applies */ }
        }, 350);
    };
    // Suggest the address from the institution name until the user edits it
    nameInput.addEventListener('input', () => { if (!touched) { input.value = slug(nameInput.value, false); run(); } });
    // Bangla / phonetic keyboards compose several keystrokes into one letter — wait until it is finished
    input.addEventListener('compositionstart', () => { composing = true; });
    input.addEventListener('compositionend', () => { composing = false; run(); });
    input.addEventListener('input', () => { touched = true; if (!composing) run(); });
    input.addEventListener('blur', () => { input.value = input.value.replace(/^-+|-+$/g, ''); run(); });
    if (input.value) run();
});
</script>
@endpush
