/* Shared app utilities */
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
        document.querySelectorAll('.admin-only').forEach(el => el.classList.add('d-none'));
    }
    if (!['super_admin', 'regional_admin', 'hospital_admin'].includes(user.role)) {
        document.querySelectorAll('.manager-only').forEach(el => el.classList.add('d-none'));
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

// ── Toast (Bootstrap) ─────────────────────────────────────────
function toast(msg, type = 'info', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = 9999;
        document.body.appendChild(container);
    }
    const bgMap = { success: 'bg-success', error: 'bg-danger', info: 'bg-primary', warning: 'bg-warning text-dark' };
    const bg    = bgMap[type] || 'bg-secondary';
    const id    = 'toast-' + Date.now();
    container.insertAdjacentHTML('beforeend', `
      <div id="${id}" class="toast align-items-center text-white ${bg} border-0" role="alert" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">${esc(msg)}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>`);
    const el = document.getElementById(id);
    const bsToast = new bootstrap.Toast(el, { delay: duration });
    bsToast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

// ── Date helpers ──────────────────────────────────────────────
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function fmtDateTime(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function timeAgo(d) {
    if (!d) return '—';
    const diff = (Date.now() - new Date(d)) / 1000;
    if (diff < 60)    return 'just now';
    if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

// ── Badge helper ──────────────────────────────────────────────
function badge(status, label) {
    const text = label || status || '—';
    const cls  = 'badge-status-' + (status || '').toLowerCase().replace(/\s+/g, '_');
    return `<span class="badge ${cls}">${esc(text)}</span>`;
}

// ── Modal helpers (Bootstrap) ─────────────────────────────────
function openModal(id) {
    const el = document.getElementById(id);
    if (el) bootstrap.Modal.getOrCreateInstance(el).show();
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) bootstrap.Modal.getOrCreateInstance(el).hide();
}

// ── Pagination (Bootstrap) ────────────────────────────────────
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
        infoEl.className = 'text-muted small mb-1 mt-2 px-3';
        container.parentNode.insertBefore(infoEl, container);
    }
    infoEl.textContent = total > 0 ? `Showing ${start}–${end} of ${total}` : '0 results';

    container.innerHTML = '';
    if (pages <= 1) return;

    const ul = document.createElement('ul');
    ul.className = 'pagination pagination-sm mb-0';

    const addItem = (label, p, disabled = false, active = false) => {
        const li = document.createElement('li');
        li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.innerHTML = label;
        if (!disabled && !active) a.onclick = e => { e.preventDefault(); onPage(p); };
        li.appendChild(a);
        ul.appendChild(li);
    };

    addItem('&laquo;', page - 1, page === 1);
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= page - 2 && i <= page + 2)) {
            addItem(i, i, false, i === page);
        } else if (i === page - 3 || i === page + 3) {
            addItem('…', i, true);
        }
    }
    addItem('&raquo;', page + 1, page === pages);
    container.appendChild(ul);
}

// ── Loading state ─────────────────────────────────────────────
function setLoading(btnEl, loading) {
    if (loading) {
        btnEl._origHTML = btnEl.innerHTML;
        btnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        btnEl.disabled  = true;
    } else {
        btnEl.innerHTML = btnEl._origHTML || btnEl.innerHTML;
        btnEl.disabled  = false;
    }
}

// ── Sidebar toggle ────────────────────────────────────────────
function initSidebar() {
    const toggle  = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    toggle?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('open');
    });
    overlay?.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
    });

    const page = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.sidebar-link').forEach(link => {
        if (link.getAttribute('href') === page) link.classList.add('active');
    });
}

// ── Escape HTML ───────────────────────────────────────────────
function esc(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', initSidebar);
