/* Shared app utilities — included on every protected page */

// ─── Current user ─────────────────────────────────────────────────────────────
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
    const name  = document.getElementById('user-name');
    const role  = document.getElementById('user-role');
    const av    = document.getElementById('user-avatar');
    const avSb  = document.getElementById('sidebar-avatar');
    const nameSb = document.getElementById('sidebar-user-name');
    const roleSb = document.getElementById('sidebar-user-role');
    const initial = (user.name || 'U').charAt(0).toUpperCase();
    if (name)  name.textContent  = user.name;
    if (role)  role.textContent  = roleLabel(user.role);
    if (av)    av.textContent    = initial;
    if (avSb)  avSb.textContent  = initial;
    if (nameSb) nameSb.textContent = user.name;
    if (roleSb) roleSb.textContent = roleLabel(user.role);

    // Hide admin-only nav items for non-admins
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

// ─── Logout ───────────────────────────────────────────────────────────────────
async function logout() {
    try { await API.post('api/logout.php'); } catch {}
    window.location.href = 'index.html';
}

// ─── Toast notifications ──────────────────────────────────────────────────────
function toast(msg, type = 'info', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.textContent = msg;
    el.onclick = () => el.remove();
    container.appendChild(el);
    setTimeout(() => el.remove(), duration);
}

// ─── Date formatting ──────────────────────────────────────────────────────────
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
    if (diff < 60)   return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

// ─── Badge HTML ───────────────────────────────────────────────────────────────
function badge(status, label) {
    const text = label || status || '—';
    return `<span class="badge badge-${(status || '').toLowerCase().replace(/\s+/g, '_')}">${text}</span>`;
}

// ─── Sidebar toggle ───────────────────────────────────────────────────────────
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

    // Mark active link
    const page = window.location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.nav-link').forEach(link => {
        if (link.getAttribute('href') === page) link.classList.add('active');
    });
}

// ─── Pagination ───────────────────────────────────────────────────────────────
function renderPagination(containerId, page, pages, total, perPage, onPage) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const infoId = containerId + '-info';
    let infoEl = document.getElementById(infoId);
    if (!infoEl) {
        infoEl = document.createElement('p');
        infoEl.id = infoId;
        infoEl.className = 'pagination-info';
        container.parentNode.insertBefore(infoEl, container);
    }
    const start = (page - 1) * perPage + 1;
    const end   = Math.min(page * perPage, total);
    infoEl.textContent = total > 0 ? `Showing ${start}–${end} of ${total}` : '0 results';

    container.innerHTML = '';
    if (pages <= 1) return;

    const btn = (label, p, disabled = false) => {
        const b = document.createElement('button');
        b.className = 'page-btn' + (p === page ? ' active' : '');
        b.textContent = label;
        b.disabled = disabled;
        if (!disabled) b.onclick = () => onPage(p);
        container.appendChild(b);
    };

    btn('‹', page - 1, page === 1);
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= page - 2 && i <= page + 2)) {
            btn(i, i);
        } else if (i === page - 3 || i === page + 3) {
            const dots = document.createElement('span');
            dots.textContent = '…';
            dots.style.cssText = 'padding:0 6px;color:var(--text-m)';
            container.appendChild(dots);
        }
    }
    btn('›', page + 1, page === pages);
}

// ─── Loading state helper ─────────────────────────────────────────────────────
function setLoading(btnEl, loading) {
    if (loading) {
        btnEl._origHTML = btnEl.innerHTML;
        btnEl.innerHTML = '<span class="spinner"></span>';
        btnEl.disabled = true;
    } else {
        btnEl.innerHTML = btnEl._origHTML || btnEl.innerHTML;
        btnEl.disabled = false;
    }
}

// ─── Modal helpers ────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

// Close modal on overlay click
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// ─── Escape HTML ─────────────────────────────────────────────────────────────
function esc(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── Init on DOM ready ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', initSidebar);
