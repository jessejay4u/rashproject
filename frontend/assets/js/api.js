/**
 * API Client — wraps all backend calls, handles auth tokens,
 * refresh token rotation, and error normalization.
 */
const API = (() => {
    const BASE = '/api';

    function getToken()        { return localStorage.getItem('access_token'); }
    function getRefreshToken() { return localStorage.getItem('refresh_token'); }
    function setTokens(data)   {
        localStorage.setItem('access_token',  data.access_token);
        localStorage.setItem('refresh_token', data.refresh_token);
    }
    function clearTokens() {
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
        localStorage.removeItem('user');
    }

    let isRefreshing  = false;
    let refreshQueue  = [];

    function drainQueue(token, error) {
        refreshQueue.forEach(cb => error ? cb.reject(error) : cb.resolve(token));
        refreshQueue = [];
    }

    async function refreshTokens() {
        const refresh = getRefreshToken();
        if (!refresh) throw new Error('No refresh token');

        const res = await fetch(`${BASE}/auth/refresh`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ refresh_token: refresh }),
        });
        if (!res.ok) throw new Error('Refresh failed');
        const data = await res.json();
        setTokens(data.data);
        return data.data.access_token;
    }

    async function request(method, path, body = null, options = {}) {
        const url     = `${BASE}${path}`;
        const headers = { 'Content-Type': 'application/json' };
        const token   = getToken();
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const config = { method, headers };
        if (body !== null) config.body = JSON.stringify(body);

        let res = await fetch(url, config);

        // Access token expired — try refresh
        if (res.status === 401 && !options._retried) {
            if (isRefreshing) {
                return new Promise((resolve, reject) => {
                    refreshQueue.push({ resolve, reject });
                }).then(newToken => {
                    headers['Authorization'] = `Bearer ${newToken}`;
                    return fetch(url, { ...config, headers });
                }).then(r => r.json());
            }

            isRefreshing = true;
            try {
                const newToken = await refreshTokens();
                drainQueue(newToken, null);
                headers['Authorization'] = `Bearer ${newToken}`;
                res = await fetch(url, { ...config, headers });
            } catch (err) {
                drainQueue(null, err);
                clearTokens();
                window.location.href = '/?session_expired=1';
                throw err;
            } finally {
                isRefreshing = false;
            }
        }

        const data = await res.json().catch(() => ({ success: false, message: 'Invalid response' }));

        if (!res.ok) {
            const error     = new Error(data.message || 'Request failed');
            error.status    = res.status;
            error.errors    = data.errors || {};
            error.response  = data;
            throw error;
        }

        return data;
    }

    return {
        get:    (path, query = {})    => {
            const qs = new URLSearchParams(query).toString();
            return request('GET', path + (qs ? '?' + qs : ''));
        },
        post:   (path, body)          => request('POST',   path, body),
        put:    (path, body)          => request('PUT',    path, body),
        patch:  (path, body)          => request('PATCH',  path, body),
        delete: (path)                => request('DELETE', path),

        // Auth helpers
        login:  (email, password, deviceInfo = {}) =>
            request('POST', '/auth/login', { email, password, device_info: deviceInfo }),
        logout: (refreshToken) =>
            request('POST', '/auth/logout', { refresh_token: refreshToken }),
        me:     () => request('GET', '/auth/me'),

        setTokens,
        getToken,
        getRefreshToken,
        clearTokens,

        isAuthenticated: () => !!getToken(),
    };
})();
