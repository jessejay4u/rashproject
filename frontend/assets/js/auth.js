/**
 * Auth page logic — login form, password visibility, error display.
 */
(function () {
    'use strict';

    // Redirect if already authenticated
    if (API.isAuthenticated() && window.location.pathname === '/') {
        window.location.href = '/pages/dashboard.html';
        return;
    }

    const form        = document.getElementById('login-form');
    const emailInput  = document.getElementById('email');
    const passInput   = document.getElementById('password');
    const loginBtn    = document.getElementById('login-btn');
    const alertBox    = document.getElementById('alert-container');

    if (!form) return;

    // Toggle password visibility
    document.querySelector('.toggle-password')?.addEventListener('click', function () {
        const isText = passInput.type === 'text';
        passInput.type = isText ? 'password' : 'text';
        this.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
    });

    // Check for session_expired flag
    if (new URLSearchParams(window.location.search).get('session_expired')) {
        showAlert('Your session has expired. Please log in again.', 'warning');
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearErrors();

        const email    = emailInput.value.trim();
        const password = passInput.value;
        let valid      = true;

        if (!email) {
            showFieldError('email-error', 'Email is required.');
            valid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showFieldError('email-error', 'Enter a valid email address.');
            valid = false;
        }

        if (!password) {
            showFieldError('password-error', 'Password is required.');
            valid = false;
        }

        if (!valid) return;

        setLoading(true);

        try {
            const deviceInfo = {
                device_id:   getOrCreateDeviceId(),
                user_agent:  navigator.userAgent,
                platform:    navigator.platform,
            };

            const res = await API.login(email, password, deviceInfo);
            API.setTokens(res.data);
            localStorage.setItem('user', JSON.stringify(res.data.user));

            // Redirect based on role
            const role = res.data.user?.role_name;
            if (res.data.mfa_required) {
                window.location.href = '/pages/mfa.html';
            } else {
                window.location.href = '/pages/dashboard.html';
            }
        } catch (err) {
            if (err.status === 429) {
                showAlert('Too many login attempts. Please wait a few minutes.', 'error');
            } else if (err.status === 423) {
                showAlert('Your account is temporarily locked. Please contact your administrator.', 'error');
            } else {
                showAlert(err.message || 'Invalid email or password.', 'error');
            }
        } finally {
            setLoading(false);
        }
    });

    function setLoading(loading) {
        loginBtn.disabled = loading;
        loginBtn.querySelector('.btn-text').classList.toggle('hidden', loading);
        loginBtn.querySelector('.btn-spinner').classList.toggle('hidden', !loading);
    }

    function clearErrors() {
        document.querySelectorAll('.field-error').forEach(el => (el.textContent = ''));
        document.querySelectorAll('input').forEach(el => el.classList.remove('error'));
        alertBox.innerHTML = '';
    }

    function showFieldError(id, msg) {
        const el = document.getElementById(id);
        if (el) el.textContent = msg;
    }

    function showAlert(msg, type = 'error') {
        alertBox.innerHTML = `<div class="alert alert-${type}" role="alert">${escapeHtml(msg)}</div>`;
    }

    function escapeHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function getOrCreateDeviceId() {
        let id = localStorage.getItem('device_id');
        if (!id) {
            id = crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2);
            localStorage.setItem('device_id', id);
        }
        return id;
    }
})();
