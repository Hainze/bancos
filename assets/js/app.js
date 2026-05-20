// ── SIDEBAR TOGGLE ─────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebar-overlay');
    const hamburger = document.getElementById('hamburger');

    function openSidebar() {
        sidebar?.classList.add('open');
        overlay?.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('open');
        document.body.style.overflow = '';
    }

    hamburger?.addEventListener('click', () => {
        sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
    });

    overlay?.addEventListener('click', closeSidebar);

    // Close on nav item click (mobile)
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', () => {
            if (window.innerWidth <= 900) closeSidebar();
        });
    });
});

// ── TOAST ──────────────────────────────────────
function toast(msg, type = 'info', duration = 3500) {
    const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span>${icons[type] || 'ℹ'}</span><span>${msg}</span>`;
    document.getElementById('toast-container').appendChild(el);
    setTimeout(() => {
        el.style.animation = 'slideIn 0.2s ease reverse';
        setTimeout(() => el.remove(), 200);
    }, duration);
}

// ── MODAL ──────────────────────────────────────
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
    if (e.target.classList.contains('modal-close')) {
        e.target.closest('.modal-overlay')?.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// ── DRAG & DROP ────────────────────────────────
function initDropZone(zoneId, callback) {
    const zone = document.getElementById(zoneId);
    if (!zone) return;

    zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.classList.add('drag-over');
    });
    zone.addEventListener('dragleave', e => {
        if (!zone.contains(e.relatedTarget)) zone.classList.remove('drag-over');
    });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) callback(file);
    });

    const input = zone.querySelector('input[type=file]');
    if (input) {
        input.addEventListener('change', e => {
            if (e.target.files[0]) callback(e.target.files[0]);
        });
        // Make entire zone clickable (not just the input)
        zone.addEventListener('click', e => {
            if (e.target !== input) input.click();
        });
    }
}

// ── FORMAT ─────────────────────────────────────
function formatMoney(n) {
    if (n === null || n === undefined || isNaN(n)) return '$ —';
    const abs = Math.abs(n);
    const formatted = new Intl.NumberFormat('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(abs);
    return (n < 0 ? '-' : '') + '$ ' + formatted;
}

function formatDate(str) {
    if (!str) return '—';
    const d = new Date(str + 'T00:00:00');
    return d.toLocaleDateString('es-AR');
}

// ── DATE RANGE HELPERS ─────────────────────────
function getDateRange(range) {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const d = now.getDate();

    const fmt = dt => dt.toISOString().slice(0,10);

    switch (range) {
        case 'hoy':
            return { desde: fmt(now), hasta: fmt(now) };
        case 'semana': {
            const day = now.getDay() || 7;
            const mon = new Date(now); mon.setDate(d - day + 1);
            return { desde: fmt(mon), hasta: fmt(now) };
        }
        case 'mes':
            return { desde: `${y}-${String(m+1).padStart(2,'0')}-01`, hasta: fmt(now) };
        case 'mes_ant': {
            const first = new Date(y, m - 1, 1);
            const last  = new Date(y, m, 0);
            return { desde: fmt(first), hasta: fmt(last) };
        }
        case 'anio':
            return { desde: `${y}-01-01`, hasta: fmt(now) };
        case 'todo':
            return { desde: '2000-01-01', hasta: fmt(now) };
        default:
            return { desde: `${y}-${String(m+1).padStart(2,'0')}-01`, hasta: fmt(now) };
    }
}

// ── CONFIRM ────────────────────────────────────
function confirmAction(msg, onConfirm) {
    if (confirm(msg)) onConfirm();
}

// ── FETCH HELPER ───────────────────────────────
async function apiFetch(url, options = {}) {
    try {
        const res = await fetch(url, {
            headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
            ...options
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return await res.json();
    } catch (e) {
        toast('Error de conexión', 'error');
        throw e;
    }
}
