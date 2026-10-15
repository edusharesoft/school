// Part 5-1 — in-app notification bell, desktop alerts, SMS length counter, audience pickers.

const SMS_GSM = /^[\n\r\x20-\x7E£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¡ÄÖÑÜ§¿äöñüà€]*$/;

export function smsParts(text) {
    if (!text) return { length: 0, parts: 0, unicode: false };
    const unicode = !SMS_GSM.test(text);
    const length = unicode ? [...text].length : [...text].length + (text.match(/[\^{}\\[~\]|€]/g) || []).length;
    const [single, multi] = unicode ? [70, 67] : [160, 153];
    return { length, parts: length <= single ? 1 : Math.ceil(length / multi), unicode };
}

function initBell() {
    const bell = document.querySelector('[data-inbox-bell]');
    if (!bell) return;
    const badge = bell.querySelector('[data-inbox-count]');
    const url = bell.dataset.url;
    let last = Number(bell.dataset.count || 0);
    let first = true;

    const poll = async () => {
        if (document.hidden) return;
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            badge.textContent = data.count > 99 ? '99+' : String(data.count);
            badge.classList.toggle('d-none', data.count === 0);
            // Desktop alert for new messages (only when the user allowed it on the inbox page).
            if (!first && data.count > last && 'Notification' in window && Notification.permission === 'granted' && data.latest?.length) {
                const m = data.latest[0];
                const n = new Notification(m.title, { body: m.body, tag: 'inbox-' + m.id });
                n.onclick = () => { window.focus(); window.location.href = m.url; };
            }
            last = data.count;
            first = false;
        } catch (_) { /* offline — try again later */ }
    };
    poll();
    setInterval(poll, 60000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

    document.querySelectorAll('[data-desktop-alerts]').forEach((btn) => {
        if (!('Notification' in window)) { btn.remove(); return; }
        const sync = () => {
            btn.disabled = Notification.permission !== 'default';
            btn.querySelector('[data-state]')?.replaceChildren(document.createTextNode(btn.dataset[Notification.permission] || ''));
        };
        sync();
        btn.addEventListener('click', () => Notification.requestPermission().then(sync));
    });
}

function initSmsCounters() {
    document.querySelectorAll('textarea[data-sms-counter]').forEach((ta) => {
        const out = document.getElementById(ta.dataset.smsCounter);
        if (!out) return;
        const render = () => {
            const c = smsParts(ta.value);
            out.textContent = `${c.length} ${out.dataset.chars || 'chars'} · ${c.parts} ${out.dataset.parts || 'SMS'}${c.unicode ? ' · Unicode' : ''}`;
        };
        ta.addEventListener('input', render);
        render();
    });
}

// <select data-audience> shows the matching [data-audience-for="classes students_specific"] blocks.
function initAudience() {
    document.querySelectorAll('select[data-audience]').forEach((sel) => {
        const form = sel.closest('form');
        const sync = () => form.querySelectorAll('[data-audience-for]').forEach((el) => {
            el.classList.toggle('d-none', !el.dataset.audienceFor.split(' ').includes(sel.value));
        });
        sel.addEventListener('change', sync);
        sync();
    });
}

// Student picker: <div data-student-picker data-url="…" data-name="student_ids[]"> with an input and a list of chosen chips.
function initStudentPicker() {
    document.querySelectorAll('[data-student-picker]').forEach((box) => {
        const input = box.querySelector('input[type=search]');
        const results = box.querySelector('[data-results]');
        const chosen = box.querySelector('[data-chosen]');
        let timer = null;
        const add = (id, label) => {
            if (chosen.querySelector(`input[value="${id}"]`)) return;
            const chip = document.createElement('span');
            chip.className = 'badge text-bg-light border me-1 mb-1';
            chip.textContent = label + ' ';
            const hidden = document.createElement('input');
            hidden.type = 'hidden'; hidden.name = box.dataset.name; hidden.value = id;
            const x = document.createElement('button');
            x.type = 'button'; x.className = 'btn-close btn-close-sm ms-1'; x.style.fontSize = '.6rem';
            x.setAttribute('aria-label', 'Remove');
            x.addEventListener('click', () => chip.remove());
            chip.append(hidden, x);
            chosen.append(chip);
        };
        chosen.querySelectorAll('[data-remove]').forEach((b) => b.addEventListener('click', () => b.closest('span').remove()));
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                results.replaceChildren();
                if (input.value.trim().length < 2) return;
                const res = await fetch(box.dataset.url + '?q=' + encodeURIComponent(input.value.trim()), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!res.ok) return;
                (await res.json()).forEach((s) => {
                    const b = document.createElement('button');
                    b.type = 'button'; b.className = 'list-group-item list-group-item-action small py-1';
                    b.textContent = s.label;
                    b.addEventListener('click', () => { add(s.id, s.label); results.replaceChildren(); input.value = ''; });
                    results.append(b);
                });
            }, 250);
        });
    });
}

export function initNotifications() {
    initBell();
    initSmsCounters();
    initAudience();
    initStudentPicker();
}
