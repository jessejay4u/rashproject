/* Shared app utilities — custom UI (no Bootstrap JS) */
let currentUser = null;

async function requireAuth() {
    try {
        const res = await API.get('api/me.php');
        currentUser = res.data;
        fillUserUI(currentUser);
        return currentUser;
    } catch {
        window.location.href = 'index.html';
        throw new Error('Not authenticated');
    }
}

function fillUserUI(user) {
    const initial = (user.name || 'U').charAt(0).toUpperCase();
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    set('user-name', user.name);
    set('user-role', roleLabel(user.role));
    set('sidebar-user-name', user.name);
    set('sidebar-user-role', roleLabel(user.role));
    const av   = document.getElementById('user-avatar');
    const avSb = document.getElementById('sidebar-avatar');
    if (av)   av.textContent   = initial;
    if (avSb) avSb.textContent = initial;

    if (!['super_admin'].includes(user.role)) {
        document.querySelectorAll('.admin-only').forEach(el => el.style.display = 'none');
    }
    if (!['super_admin', 'regional_admin', 'hospital_admin'].includes(user.role)) {
        document.querySelectorAll('.manager-only').forEach(el => el.style.display = 'none');
    }
}

function roleLabel(role) {
    const labels = {
        super_admin: 'Super Admin', regional_admin: 'Regional Admin',
        hospital_admin: 'Hospital Admin', data_entry: 'Data Entry', viewer: 'Viewer',
    };
    return labels[role] || role;
}

async function logout() {
    try { await API.post('api/logout.php'); } catch {}
    window.location.href = 'index.html';
}

// ── Toast ──────────────────────────────────────────────────────
function toast(msg, type = 'info', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        Object.assign(container.style, { position:'fixed', bottom:'24px', right:'24px', zIndex:9999, display:'flex', flexDirection:'column', gap:'8px' });
        document.body.appendChild(container);
    }
    const clsMap = { success:'success', error:'error', warning:'warning', info:'info' };
    const cls    = clsMap[type] || 'info';
    const id     = 'toast-' + Date.now();
    const icons  = { success:'bi-check-circle-fill', error:'bi-x-circle-fill', warning:'bi-exclamation-triangle-fill', info:'bi-info-circle-fill' };
    container.insertAdjacentHTML('beforeend', `
      <div id="${id}" class="toast-item ${cls}">
        <i class="bi ${icons[cls]||icons.info}"></i>
        <span>${esc(msg)}</span>
        <button onclick="this.closest('.toast-item').remove()" style="background:none;border:none;cursor:pointer;margin-left:auto;opacity:.6;font-size:1rem;">&times;</button>
      </div>`);
    setTimeout(() => document.getElementById(id)?.remove(), duration);
}

// ── Date helpers ───────────────────────────────────────────────
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
}

function fmtDateTime(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('en-US', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
}

function timeAgo(d) {
    if (!d) return '—';
    const diff = (Date.now() - new Date(d)) / 1000;
    if (diff < 60)    return 'just now';
    if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

// ── Badge helper ───────────────────────────────────────────────
function badge(status, label) {
    const text    = label !== undefined ? label : (status || '—');
    const clsMap  = {
        approved:  'badge-success',
        active:    'badge-success',
        published: 'badge-success',
        rejected:  'badge-danger',
        inactive:  'badge-danger',
        archived:  'badge-secondary',
        submitted: 'badge-info',
        draft:     'badge-secondary',
        pending:   'badge-warning',
    };
    const cls = clsMap[(status||'').toLowerCase()] || 'badge-secondary';
    return `<span class="badge ${cls}">${esc(String(text))}</span>`;
}

// ── Modal helpers ──────────────────────────────────────────────
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
    document.body.style.overflow = '';
}

// Close modal on overlay click
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// ── Pagination ─────────────────────────────────────────────────
function renderPagination(containerId, page, pages, total, perPage, onPage) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const start = total > 0 ? (page - 1) * perPage + 1 : 0;
    const end   = Math.min(page * perPage, total);

    const infoId = containerId + '-info';
    let infoEl = document.getElementById(infoId);
    if (!infoEl) {
        infoEl = document.createElement('p');
        infoEl.id = infoId;
        infoEl.className = 'text-muted small mb-1 mt-2';
        container.parentNode.insertBefore(infoEl, container);
    }
    infoEl.textContent = total > 0 ? `Showing ${start}–${end} of ${total}` : '0 results';

    container.innerHTML = '';
    if (pages <= 1) return;

    const wrap = document.createElement('div');
    wrap.className = 'pagination';

    const addBtn = (label, p, disabled = false, active = false) => {
        const btn = document.createElement('button');
        btn.className = 'page-btn' + (active ? ' active' : '');
        btn.innerHTML = label;
        btn.disabled  = disabled;
        if (!disabled && !active) btn.onclick = () => onPage(p);
        wrap.appendChild(btn);
    };

    addBtn('&laquo;', page - 1, page === 1);
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= page - 2 && i <= page + 2)) {
            addBtn(i, i, false, i === page);
        } else if (i === page - 3 || i === page + 3) {
            addBtn('…', i, true);
        }
    }
    addBtn('&raquo;', page + 1, page === pages);
    container.appendChild(wrap);
}

// ── Loading state ──────────────────────────────────────────────
function setLoading(btnEl, loading) {
    if (loading) {
        btnEl._origHTML = btnEl.innerHTML;
        btnEl.innerHTML = '<span class="spinner"></span>';
        btnEl.disabled  = true;
    } else {
        btnEl.innerHTML = btnEl._origHTML || btnEl.innerHTML;
        btnEl.disabled  = false;
    }
}

// ── Sidebar toggle ─────────────────────────────────────────────
function initSidebar() {
    const toggle  = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    toggle?.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
        overlay?.classList.toggle('open');
    });
    overlay?.addEventListener('click', () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('open');
    });

    const page = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.sidebar-link').forEach(link => {
        if (link.getAttribute('href') === page) link.classList.add('active');
    });
}

// ── Escape HTML ────────────────────────────────────────────────
function esc(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', initSidebar);
