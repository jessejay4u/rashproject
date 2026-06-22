/**
 * Core app shell — sidebar, topbar, routing, toast notifications.
 * Included on every authenticated page.
 */
const App = (() => {
    'use strict';

    // ── State ──────────────────────────────────────────────
    let currentUser = null;

    // ── Init ───────────────────────────────────────────────
    function init() {
        if (!API.isAuthenticated()) {
            window.location.href = '/?session_expired=1';
            return;
        }

        currentUser = JSON.parse(localStorage.getItem('user') || 'null');

        renderSidebar();
        renderTopbar();
        highlightActiveNav();
        initLogout();
    }

    // ── Sidebar ────────────────────────────────────────────
    function renderSidebar() {
        const role = currentUser?.role_name;

        const navItems = [
            { label: 'Dashboard',    icon: 'home',         href: '/pages/dashboard.html',     perm: 'dashboard.view' },
            { label: 'Submissions',  icon: 'clipboard',    href: '/pages/submissions.html',   perm: 'submissions.view' },
            { label: 'Forms',        icon: 'file-text',    href: '/pages/forms.html',         perm: 'forms.view' },
            { label: 'Hospitals',    icon: 'hospital',     href: '/pages/hospitals.html',     perm: 'hospitals.view', divider: true },
            { label: 'Reports',      icon: 'bar-chart',    href: '/pages/reports.html',       perm: 'reports.view' },
            { label: 'Users',        icon: 'users',        href: '/pages/users.html',         perm: 'users.view', divider: true, adminOnly: true },
            { label: 'Audit Logs',   icon: 'shield',       href: '/pages/audit.html',         perm: 'audit.view',  adminOnly: true },
            { label: 'Settings',     icon: 'settings',     href: '/pages/settings.html',      perm: 'settings.manage', adminOnly: true },
        ];

        const nav   = document.getElementById('sidebar-nav');
        if (!nav) return;

        nav.innerHTML = navItems.map(item => {
            if (item.adminOnly && !['super_admin', 'regional_admin'].includes(role)) return '';
            return `
                <a href="${item.href}" class="nav-item" data-href="${item.href}">
                    ${getNavIcon(item.icon)}
                    <span>${item.label}</span>
                </a>
            `;
        }).join('');

        // User info
        const userInfo = document.getElementById('sidebar-user');
        if (userInfo && currentUser) {
            const initials = currentUser.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
            userInfo.innerHTML = `
                <div class="user-info">
                    <div class="user-avatar">${initials}</div>
                    <div class="user-details">
                        <div class="user-name truncate">${escHtml(currentUser.name)}</div>
                        <div class="user-role truncate">${escHtml(currentUser.role_display || currentUser.role_name || '')}</div>
                    </div>
                    <button class="logout-btn" id="logout-btn" title="Sign out">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </button>
                </div>
            `;
        }
    }

    function renderTopbar() {
        const title = document.getElementById('page-title');
        const page  = document.querySelector('meta[name="page-title"]')?.content;
        if (title && page) title.textContent = page;
    }

    function highlightActiveNav() {
        const current = window.location.pathname;
        document.querySelectorAll('.nav-item[data-href]').forEach(el => {
            el.classList.toggle('active', el.dataset.href === current);
        });
    }

    function initLogout() {
        document.addEventListener('click', async (e) => {
            if (e.target.closest('#logout-btn')) {
                try {
                    await API.logout(API.getRefreshToken());
                } catch (_) {}
                API.clearTokens();
                window.location.href = '/';
            }
        });
    }

    // ── Icons ──────────────────────────────────────────────
    const icons = {
        home:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>',
        clipboard:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>',
        'file-text':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>',
        hospital:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><line x1="12" y1="7" x2="12" y2="13"/><line x1="9" y1="10" x2="15" y2="10"/></svg>',
        'bar-chart':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>',
        users:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        shield:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        settings:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    };

    function getNavIcon(name) {
        return icons[name] || '<svg viewBox="0 0 24 24"/>';
    }

    // ── Toast ──────────────────────────────────────────────
    function toast(message, type = 'info', duration = 4000) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id        = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const el        = document.createElement('div');
        el.className    = `toast toast-${type}`;
        el.innerHTML    = `
            <span class="toast-message">${escHtml(message)}</span>
            <button class="toast-close" aria-label="Close">&times;</button>
        `;

        el.querySelector('.toast-close').addEventListener('click', () => el.remove());
        container.appendChild(el);

        if (duration > 0) setTimeout(() => el.remove(), duration);
    }

    // ── Formatting ─────────────────────────────────────────
    function formatDate(iso) {
        if (!iso) return '—';
        return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function formatDateTime(iso) {
        if (!iso) return '—';
        return new Date(iso).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function formatNumber(n) {
        return Number(n).toLocaleString('en-US');
    }

    function statusBadge(status) {
        const map = {
            submitted: 'info',    approved: 'success', rejected: 'danger',
            draft:     'neutral', pending:  'warning', published: 'success',
            archived:  'neutral',
        };
        return `<span class="badge badge-${map[status] || 'neutral'}">${status}</span>`;
    }

    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Pagination helper ──────────────────────────────────
    function renderPagination(container, meta, onPageChange) {
        const { total, per_page, current_page, last_page, from, to } = meta;
        container.innerHTML = `
            <div class="pagination">
                <span class="pagination-info">Showing ${from}–${to} of ${formatNumber(total)}</span>
                <div class="pagination-controls">
                    <button class="page-btn" ${current_page <= 1 ? 'disabled' : ''} data-page="${current_page - 1}">&laquo; Prev</button>
                    ${buildPageNumbers(current_page, last_page)}
                    <button class="page-btn" ${current_page >= last_page ? 'disabled' : ''} data-page="${current_page + 1}">Next &raquo;</button>
                </div>
            </div>
        `;

        container.querySelectorAll('.page-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!btn.disabled) onPageChange(parseInt(btn.dataset.page));
            });
        });
    }

    function buildPageNumbers(current, last) {
        if (last <= 7) {
            return Array.from({ length: last }, (_, i) => i + 1)
                .map(p => `<button class="page-btn ${p === current ? 'active' : ''}" data-page="${p}">${p}</button>`)
                .join('');
        }
        return `<button class="page-btn ${current === 1 ? 'active' : ''}" data-page="1">1</button>
                ${current > 3 ? '<span>…</span>' : ''}
                ${current > 2 ? `<button class="page-btn" data-page="${current - 1}">${current - 1}</button>` : ''}
                ${current !== 1 && current !== last ? `<button class="page-btn active" data-page="${current}">${current}</button>` : ''}
                ${current < last - 1 ? `<button class="page-btn" data-page="${current + 1}">${current + 1}</button>` : ''}
                ${current < last - 2 ? '<span>…</span>' : ''}
                <button class="page-btn ${current === last ? 'active' : ''}" data-page="${last}">${last}</button>`;
    }

    // ── Confirm dialog ─────────────────────────────────────
    function confirm(message, title = 'Confirm') {
        return new Promise(resolve => {
            const overlay   = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.innerHTML = `
                <div class="modal" style="max-width:400px">
                    <div class="modal-header">
                        <span class="modal-title">${escHtml(title)}</span>
                        <button class="modal-close" data-action="cancel">&times;</button>
                    </div>
                    <div class="modal-body"><p>${escHtml(message)}</p></div>
                    <div class="modal-footer">
                        <button class="btn btn-ghost" data-action="cancel">Cancel</button>
                        <button class="btn btn-danger" data-action="confirm">Confirm</button>
                    </div>
                </div>
            `;
            overlay.addEventListener('click', e => {
                const action = e.target.dataset.action;
                if (action === 'confirm') { document.body.removeChild(overlay); resolve(true); }
                if (action === 'cancel' || e.target === overlay) { document.body.removeChild(overlay); resolve(false); }
            });
            document.body.appendChild(overlay);
        });
    }

    return {
        init,
        toast,
        formatDate,
        formatDateTime,
        formatNumber,
        statusBadge,
        escHtml,
        renderPagination,
        confirm,
        getUser: () => currentUser,
    };
})();

document.addEventListener('DOMContentLoaded', () => App.init());
