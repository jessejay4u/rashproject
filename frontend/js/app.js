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
    const name   = document.getElementById('user-name');
    const role   = document.getElementById('user-role');
    const av     = document.getElementById('user-avatar');
    const avSb   = document.getElementById('sidebar-avatar');
    const nameSb = document.getElementById('sidebar-user-name');
    const roleSb = document.getElementById('sidebar-user-role');
    const initial = (user.name || 'U').charAt(0).toUpperCase();
    if (name)   name.textContent  = user.name;
    if (role)   role.textContent  = roleLabel(user.role);
    if (av)     av.textContent    = initial;
    if (avSb)   avSb.textContent  = initial;
    if (nameSb) nameSb.textContent = user.name;
    if (roleSb) roleSb.textContent = roleLabel(user.role);

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

// ─── Toast notifications (Bootstrap 5) ───────────────────────────────────────
function toast(msg, type = 'info', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    const colorMap = {
        success: 'text-bg-success',
        error:   'text-bg-danger',
        warning: 'text-bg-warning',
        info:    'text-bg-primary',
    };
    const colorCls = colorMap[type] || 'text-bg-primary';
    const el = document.createElement('div');
    el.className = `toast align-items-center ${colorCls} border-0`;
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-live', 'assertive');
    el.innerHTML = `
      <div class="d-flex">
        <div class="toast-body fw-semibold">${esc(msg)}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
    container.appendChild(el);
    const bsToast = new bootstrap.Toast(el, { delay: duration });
    bsToast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
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
    if (diff < 60)    return 'just now';
    if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

// ─── Badge HTML (Bootstrap 5) ─────────────────────────────────────────────────
function badge(status, label) {
    const text = label || status || '—';
    const map = {
        draft:     'bg-secondary',
        submitted: 'bg-primary',
        approved:  'bg-success',
        rejected:  'bg-danger',
        published: 'bg-success',
        archived:  'bg-secondary',
        active:    'bg-success',
        inactive:  'bg-danger',
    };
    const s = (status || '').toLowerCase().replace(/\s+/g, '_');
    const cls = map[s] || 'bg-secondary';
    return `<span class="badge ${cls}">${esc(text)}</span>`;
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
    document.querySelectorAll('.sb-link').forEach(link => {
        if (link.getAttribute('href') === page) link.classList.add('active');
    });
}

// ─── Pagination (Bootstrap 5) ─────────────────────────────────────────────────
function renderPagination(containerId, page, pages, total, perPage, onPage) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const start = total > 0 ? (page - 1) * perPage + 1 : 0;
    const end   = Math.min(page * perPage, total);
    const info  = total > 0 ? `Showing ${start}–${end} of ${total}` : 'No results';

    if (pages <= 1) {
        container.innerHTML = `<p class="text-muted small text-center mb-0 py-2">${info}</p>`;
        return;
    }

    let items = '';
    items += `<li class="page-item${page === 1 ? ' disabled' : ''}"><a class="page-link" href="#" data-page="${page - 1}"><i class="bi bi-chevron-left"></i></a></li>`;
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= page - 2 && i <= page + 2)) {
            items += `<li class="page-item${i === page ? ' active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        } else if (i === page - 3 || i === page + 3) {
            items += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
        }
    }
    items += `<li class="page-item${page === pages ? ' disabled' : ''}"><a class="page-link" href="#" data-page="${page + 1}"><i class="bi bi-chevron-right"></i></a></li>`;

    container.innerHTML = `
      <div class="d-flex flex-column align-items-center gap-2 py-3">
        <p class="text-muted small mb-0">${info}</p>
        <nav><ul class="pagination pagination-sm mb-0">${items}</ul></nav>
      </div>`;

    container.querySelectorAll('.page-link[data-page]').forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            const p = parseInt(a.dataset.page);
            if (!isNaN(p) && p >= 1 && p <= pages && p !== page) onPage(p);
        });
    });
}

// ─── Loading state helper (Bootstrap spinner) ─────────────────────────────────
function setLoading(btnEl, loading) {
    if (loading) {
        btnEl._origHTML = btnEl.innerHTML;
        btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Loading…';
        btnEl.disabled = true;
    } else {
        btnEl.innerHTML = btnEl._origHTML || btnEl.innerHTML;
        btnEl.disabled = false;
    }
}

// ─── Modal helpers (Bootstrap 5) ─────────────────────────────────────────────
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    bootstrap.Modal.getOrCreateInstance(el).show();
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const m = bootstrap.Modal.getInstance(el);
    if (m) m.hide();
}

// ─── Escape HTML ─────────────────────────────────────────────────────────────
function esc(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ─── Init on DOM ready ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', initSidebar);
