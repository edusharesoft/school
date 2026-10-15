import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import { initDashboard } from './dashboard';
import { initNotifications } from './notifications';
import './slugify-bn';

window.bootstrap = bootstrap;
window.Alpine = Alpine;
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initNotifications();
    // ☰ button — desktop: icons-only sidebar (remembered); mobile: slide-in menu
    const sidebar = document.getElementById('sidebar');
    try { if (localStorage.getItem('sb_mini') === '1' && innerWidth >= 992) document.body.classList.add('sb-mini'); } catch (_) { /* storage unavailable */ }
    document.getElementById('sbToggle')?.addEventListener('click', () => {
        if (innerWidth >= 992) {
            document.body.classList.toggle('sb-mini');
            try { localStorage.setItem('sb_mini', document.body.classList.contains('sb-mini') ? '1' : '0'); } catch (_) { /* ignore */ }
        } else {
            sidebar?.classList.toggle('show');
        }
    });
    document.querySelector('.sb-backdrop')?.addEventListener('click', () => sidebar?.classList.remove('show'));

    // show / hide password on auth pages
    document.querySelectorAll('.au-field .eye').forEach((b) => b.addEventListener('click', () => {
        const input = b.parentElement.querySelector('input');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        b.innerHTML = show ? '<i class="fa-regular fa-eye"></i>' : '<i class="fa-regular fa-eye-slash"></i>';
    }));

    // YouTube on auth pages: the player loads only when clicked (fast page, no tracking before that)
    document.querySelectorAll('.au-media[data-yt]').forEach((b) => b.addEventListener('click', () => {
        const id = b.dataset.yt;
        if (!/^[A-Za-z0-9_-]{11}$/.test(id)) return;
        b.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        b.style.cursor = 'default';
    }, { once: true }));

    // Toast notifications (flash messages rendered server-side)
    document.querySelectorAll('.toast[data-autoshow]').forEach((el) => bootstrap.Toast.getOrCreateInstance(el, { delay: 5000 }).show());

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

    // Confirmation dialog for destructive/irreversible forms: <form data-confirm="Are you sure?">
    const modalEl = document.getElementById('confirmModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        let pending = null;
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) return;
            e.preventDefault();
            pending = form;
            modalEl.querySelector('[data-confirm-text]').textContent = form.dataset.confirm;
            modal.show();
        });
        modalEl.querySelector('[data-confirm-ok]').addEventListener('click', () => {
            if (!pending) return;
            pending.dataset.confirmed = '1';
            modal.hide();
            pending.requestSubmit ? pending.requestSubmit() : pending.submit();
        });
    }

    // Disable submit buttons after first submit (prevents duplicate records)
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.dataset.confirm && !form.dataset.confirmed) return;
        form.querySelectorAll('button[type="submit"]').forEach((b) => {
            b.disabled = true;
            b.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>');
        });
    });

    // Theme toggle (remembered per browser)
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => btn.addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('theme', next); } catch (_) { /* storage unavailable */ }
    }));

    document.querySelectorAll('[data-dashboard]').forEach((el) => initDashboard(el));
});
