@extends('layouts.branch')
@section('title', __('Scan station'))

@section('content')
<x-page-header :title="__('Attendance scan station')" :breadcrumbs="[__('Attendance') => route('branch.attendance.students.index'), __('Scan station') => null]" />

<div class="row g-4">
    <div class="col-lg-6">
        <div class="dp green">
            <div class="dp-head"><h2><i class="fa-solid fa-qrcode me-1"></i> {{ __('Scan an ID card or QR code') }}</h2></div>
            <div class="dp-body">
                <form id="scanForm" autocomplete="off" class="mb-3">
                    <label class="form-label" for="scanCode">{{ __('Student ID / Employee ID') }}</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                        <input id="scanCode" class="form-control text-uppercase" placeholder="BR01-260001" maxlength="40" autofocus inputmode="text" autocapitalize="characters" spellcheck="false">
                        <button class="btn btn-brand" type="submit">{{ __('Mark') }}</button>
                    </div>
                    <div class="form-text">{{ __('USB / Bluetooth scanners type the ID and press Enter automatically. Keep this box focused.') }}</div>
                </form>
                <div class="d-flex gap-2 align-items-center mb-2">
                    <button type="button" id="camBtn" class="btn btn-outline-secondary btn-sm d-none"><i class="fa-solid fa-camera me-1"></i>{{ __('Use camera') }}</button>
                    <span class="small text-body-secondary">
                        @if($canStudents)<i class="fa-solid fa-user-graduate"></i> {{ __('students: today’s attendance') }}@endif
                        @if($canStaff) · <i class="fa-solid fa-user-tie"></i> {{ __('staff: check-in, then check-out') }}@endif
                    </span>
                </div>
                <video id="camView" class="w-100 rounded d-none" muted playsinline style="max-height:260px;background:#000"></video>
                <div id="scanResult" class="mt-3" aria-live="polite"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="dp blue">
            <div class="dp-head"><h2><i class="fa-solid fa-list me-1"></i> {{ __('This session') }}</h2><span class="sub" id="scanCount">0</span></div>
            <div class="dp-body p-0"><ul class="list-group list-group-flush" id="scanLog"></ul></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('scanForm'), input = document.getElementById('scanCode');
    const result = document.getElementById('scanResult'), log = document.getElementById('scanLog'), count = document.getElementById('scanCount');
    const url = @json(route('branch.attendance.scan.store')), token = document.querySelector('meta[name=csrf-token]')?.content;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let n = 0, busy = false, last = '', lastAt = 0;

    async function mark(code, method) {
        code = code.trim().toUpperCase();
        if (!code || busy) return;
        if (code === last && Date.now() - lastAt < 3000) return; // camera repeats the same frame
        last = code; lastAt = Date.now(); busy = true;
        try {
            const res = await fetch(url, { method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ code, method }) });
            const j = await res.json().catch(() => ({}));
            const ok = res.ok && j.ok;
            const tone = ok ? (j.state === 'duplicate' ? 'warning' : 'success') : 'danger';
            const msg = j.message || (j.errors ? Object.values(j.errors).flat()[0] : @json(__('Could not mark attendance.')));
            result.innerHTML = `<div class="alert alert-${tone} mb-0 fs-5"><strong>${esc(j.person?.name || code)}</strong><br><span class="fs-6">${esc(msg)}</span></div>`;
            log.insertAdjacentHTML('afterbegin', `<li class="list-group-item d-flex gap-2"><span class="badge text-bg-${tone}">${esc(j.time || '')}</span><span class="me-auto">${esc(j.person?.name || code)}</span><span class="small text-body-secondary">${esc(j.status || j.state || '')}</span></li>`);
            count.textContent = ++n;
            if (ok && navigator.vibrate) navigator.vibrate(60);
        } catch (e) {
            result.innerHTML = `<div class="alert alert-danger mb-0">${esc(@json(__('Network error — please scan again.')))}</div>`;
        } finally {
            busy = false; input.value = ''; input.focus();
        }
    }

    form.addEventListener('submit', (e) => { e.preventDefault(); mark(input.value, 'id_card'); });

    // Camera scanning where the browser has the native BarcodeDetector (Chrome / Edge on Android, ChromeOS, macOS).
    if ('BarcodeDetector' in window && navigator.mediaDevices?.getUserMedia) {
        const btn = document.getElementById('camBtn'), video = document.getElementById('camView');
        btn.classList.remove('d-none');
        btn.addEventListener('click', async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                video.srcObject = stream; video.classList.remove('d-none'); await video.play(); btn.disabled = true;
                const detector = new BarcodeDetector({ formats: ['qr_code', 'code_128', 'code_39'] });
                const tick = async () => {
                    try { const codes = await detector.detect(video); if (codes[0]) await mark(codes[0].rawValue, 'qr'); } catch (e) {}
                    requestAnimationFrame(tick);
                };
                tick();
            } catch (e) {
                result.innerHTML = `<div class="alert alert-warning mb-0">${esc(@json(__('Camera permission was denied or no camera is available.')))}</div>`;
            }
        });
    }
});
</script>
@endpush
