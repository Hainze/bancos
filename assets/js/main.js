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

// ── TABS ───────────────────────────────────────
function initTabs(containerSelector) {
    const containers = document.querySelectorAll(containerSelector || '.tab-container');
    containers.forEach(container => {
        const btns = container.querySelectorAll('.tab-btn');
        const contents = container.querySelectorAll('.tab-content');
        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                btns.forEach(b => b.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                const target = container.querySelector('#tab-' + btn.dataset.tab);
                if (target) target.classList.add('active');
            });
        });
    });
}

// ── MODAL ──────────────────────────────────────
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}
function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
    if (e.target.classList.contains('modal-close')) {
        e.target.closest('.modal-overlay')?.classList.remove('open');
    }
});

// ── DRAG & DROP ────────────────────────────────
function initDropZone(zoneId, callback) {
    const zone = document.getElementById(zoneId);
    if (!zone) return;
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
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
    }
}

// ── FORMAT ─────────────────────────────────────
function formatMoney(n) {
    const abs = Math.abs(n);
    const formatted = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(abs);
    return (n < 0 ? '-' : '') + '$ ' + formatted;
}

function formatDate(str) {
    if (!str) return '—';
    const d = new Date(str + 'T00:00:00');
    return d.toLocaleDateString('es-AR');
}

// ── CONFIRM ────────────────────────────────────
function confirmAction(msg, onConfirm) {
    if (confirm(msg)) onConfirm();
}

// ── FETCH HELPER ───────────────────────────────
async function apiFetch(url, options = {}) {
    try {
        const res = await fetch(url, {
            headers: { 'Content-Type': 'application/json', ...options.headers },
            ...options
        });
        return await res.json();
    } catch (e) {
        toast('Error de conexión', 'error');
        throw e;
    }
}

// ── INIT ───────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initTabs();
});
