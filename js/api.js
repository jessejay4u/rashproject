/* global fetch, localStorage */
const API = {
    async request(method, url, data) {
        const opts = {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        };
        if (data !== undefined && data !== null) opts.body = JSON.stringify(data);

        let res;
        try {
            res = await fetch(url, opts);
        } catch (e) {
            throw new Error('Network error — check your connection');
        }

        if (res.status === 401) {
            // Not logged in — redirect to login
            if (!window.location.pathname.endsWith('index.html') && window.location.pathname !== '/') {
                window.location.href = 'index.html';
            }
            throw new Error('Session expired');
        }

        let json;
        try {
            json = await res.json();
        } catch {
            throw new Error('Invalid server response');
        }

        if (!res.ok) throw new Error(json.error || `Request failed (${res.status})`);
        return json;
    },

    get:    (url)       => API.request('GET',    url),
    post:   (url, data) => API.request('POST',   url, data),
    put:    (url, data) => API.request('PUT',    url, data),
    patch:  (url, data) => API.request('PATCH',  url, data),
    delete: (url)       => API.request('DELETE', url),
};
