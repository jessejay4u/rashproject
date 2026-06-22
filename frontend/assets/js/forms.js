/**
 * Forms list page + Form Builder logic.
 */
(function () {
    'use strict';

    let currentPage = 1;
    let formFields  = [];  // working list of fields for builder

    // ── Load forms list ────────────────────────────────────
    async function load(page = 1) {
        currentPage = page;
        const params = {
            page,
            per_page: 20,
            status:   document.getElementById('filter-status').value,
            category: document.getElementById('filter-category').value,
            search:   document.getElementById('search-forms').value,
        };
        const tbody = document.getElementById('forms-table');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:24px;"><div class="spinner" style="margin:auto;"></div></td></tr>';

        try {
            const res = await API.get('/forms', params);
            renderTable(res.data);
            App.renderPagination(document.getElementById('pagination-container'), res.meta, load);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger" style="padding:24px;">${App.escHtml(err.message)}</td></tr>`;
        }
    }

    function renderTable(rows) {
        const tbody = document.getElementById('forms-table');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No forms found.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(f => `
            <tr>
                <td>
                    <div style="font-weight:500;">${App.escHtml(f.name)}</div>
                    <div class="text-muted" style="font-size:var(--text-xs);">${App.escHtml(f.code)}</div>
                </td>
                <td>${f.category ? `<span class="badge badge-info">${App.escHtml(f.category)}</span>` : '—'}</td>
                <td>${App.escHtml(f.field_count || 0)}</td>
                <td>v${App.escHtml(f.version)}</td>
                <td>${App.statusBadge(f.status)}</td>
                <td class="text-muted">${App.formatDate(f.updated_at)}</td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="/pages/submit.html?form_id=${f.id}" class="btn btn-ghost btn-sm">Fill</a>
                        ${f.status === 'draft' ? `<button class="btn btn-success btn-sm publish-btn" data-id="${f.id}">Publish</button>` : ''}
                        ${f.status !== 'archived' ? `<button class="btn btn-ghost btn-sm archive-btn" data-id="${f.id}">Archive</button>` : ''}
                    </div>
                </td>
            </tr>
        `).join('');

        tbody.querySelectorAll('.publish-btn').forEach(btn =>
            btn.addEventListener('click', () => publishForm(btn.dataset.id)));
        tbody.querySelectorAll('.archive-btn').forEach(btn =>
            btn.addEventListener('click', () => archiveForm(btn.dataset.id)));
    }

    async function publishForm(id) {
        if (!await App.confirm('Publish this form? It will immediately be available to all hospital users.', 'Publish Form')) return;
        try {
            await API.post(`/forms/${id}/publish`);
            App.toast('Form published successfully', 'success');
            load(currentPage);
        } catch (err) {
            App.toast('Failed to publish: ' + err.message, 'error');
        }
    }

    async function archiveForm(id) {
        if (!await App.confirm('Archive this form? It will no longer accept new submissions.', 'Archive Form')) return;
        try {
            await API.post(`/forms/${id}/archive`);
            App.toast('Form archived', 'success');
            load(currentPage);
        } catch (err) {
            App.toast('Failed to archive: ' + err.message, 'error');
        }
    }

    // ── Form Builder ───────────────────────────────────────
    function openBuilder() {
        formFields = [];
        document.getElementById('form-name').value        = '';
        document.getElementById('form-code').value        = '';
        document.getElementById('form-description').value = '';
        document.getElementById('form-category').value    = '';
        document.getElementById('form-recurrence').value  = '';
        renderFieldsList();
        document.getElementById('form-builder-modal').classList.remove('hidden');
    }

    function closeBuilder() {
        document.getElementById('form-builder-modal').classList.add('hidden');
    }

    function renderFieldsList() {
        const container = document.getElementById('fields-list');
        const noMsg     = document.getElementById('no-fields-msg');

        if (!formFields.length) {
            container.innerHTML = '';
            noMsg.style.display = '';
            return;
        }

        noMsg.style.display = 'none';
        container.innerHTML = formFields.map((f, i) => `
            <div class="field-item" draggable="true" data-index="${i}">
                <div class="field-drag-handle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>
                        <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                        <circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>
                    </svg>
                </div>
                <div class="field-body">
                    <div class="field-label-row">
                        <strong>${App.escHtml(f.label)}</strong>
                        <span class="field-type-badge">${App.escHtml(f.field_type)}</span>
                        ${f.is_required ? '<span class="field-required">Required</span>' : ''}
                    </div>
                    ${f.help_text ? `<div class="text-muted" style="font-size:var(--text-xs);">${App.escHtml(f.help_text)}</div>` : ''}
                </div>
                <div class="field-actions">
                    <button title="Move up" data-action="up" data-index="${i}" ${i === 0 ? 'disabled' : ''}>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>
                    </button>
                    <button title="Move down" data-action="down" data-index="${i}" ${i === formFields.length - 1 ? 'disabled' : ''}>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <button title="Remove" data-action="remove" data-index="${i}" style="color:var(--color-danger);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    </button>
                </div>
            </div>
        `).join('');

        container.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx    = parseInt(btn.dataset.index);
                const action = btn.dataset.action;
                if (action === 'up'     && idx > 0)                           [formFields[idx - 1], formFields[idx]] = [formFields[idx], formFields[idx - 1]];
                if (action === 'down'   && idx < formFields.length - 1)       [formFields[idx + 1], formFields[idx]] = [formFields[idx], formFields[idx + 1]];
                if (action === 'remove')                                        formFields.splice(idx, 1);
                renderFieldsList();
            });
        });
    }

    // ── Field Editor ───────────────────────────────────────
    function openFieldEditor() {
        document.getElementById('field-label').value    = '';
        document.getElementById('field-type').value     = 'text';
        document.getElementById('field-required').value = '0';
        document.getElementById('field-help').value     = '';
        document.getElementById('field-options').value  = '';
        document.getElementById('field-min').value      = '';
        document.getElementById('field-max').value      = '';
        toggleOptionsGroup('text');
        document.getElementById('field-editor-modal').classList.remove('hidden');
    }

    function closeFieldEditor() {
        document.getElementById('field-editor-modal').classList.add('hidden');
    }

    function toggleOptionsGroup(type) {
        const needsOptions = ['select', 'multiselect', 'radio', 'checkbox'].includes(type);
        document.getElementById('options-group').style.display     = needsOptions ? '' : 'none';
        document.getElementById('validation-group').style.display  = ['text', 'textarea', 'number'].includes(type) ? '' : 'none';
    }

    document.getElementById('field-type')?.addEventListener('change', function () {
        toggleOptionsGroup(this.value);
    });

    document.getElementById('field-editor-save')?.addEventListener('click', function () {
        const label = document.getElementById('field-label').value.trim();
        if (!label) {
            App.toast('Field label is required', 'warning');
            return;
        }

        const type    = document.getElementById('field-type').value;
        const options = document.getElementById('field-options').value
            .split('\n').map(s => s.trim()).filter(Boolean)
            .map(s => ({ label: s, value: s.toLowerCase().replace(/\s+/g, '_') }));

        formFields.push({
            name:        label.toLowerCase().replace(/[^a-z0-9]+/g, '_'),
            label,
            field_type:  type,
            is_required: document.getElementById('field-required').value === '1',
            help_text:   document.getElementById('field-help').value.trim() || null,
            options:     options,
            validation:  {
                min: document.getElementById('field-min').value || null,
                max: document.getElementById('field-max').value || null,
            },
            conditions: [],
        });

        renderFieldsList();
        closeFieldEditor();
    });

    document.getElementById('field-editor-close')?.addEventListener('click', closeFieldEditor);
    document.getElementById('field-editor-cancel')?.addEventListener('click', closeFieldEditor);

    // ── Save Form ──────────────────────────────────────────
    async function saveForm(publish = false) {
        const name = document.getElementById('form-name').value.trim();
        const code = document.getElementById('form-code').value.trim();

        if (!name || !code) {
            App.toast('Form name and code are required', 'warning');
            return;
        }
        if (!formFields.length) {
            App.toast('Add at least one field before saving', 'warning');
            return;
        }

        const payload = {
            name,
            code,
            description:    document.getElementById('form-description').value.trim(),
            category:       document.getElementById('form-category').value,
            recurrence_type:document.getElementById('form-recurrence').value || null,
            is_recurring:   !!document.getElementById('form-recurrence').value,
            sections: [{
                title:  'Main Section',
                fields: formFields,
            }],
        };

        const saveBtns = document.querySelectorAll('#btn-save-draft, #btn-publish-form');
        saveBtns.forEach(b => b.disabled = true);

        try {
            const res = await API.post('/forms', payload);
            App.toast('Form created', 'success');

            if (publish) {
                await API.post(`/forms/${res.data.id}/publish`);
                App.toast('Form published', 'success');
            }

            closeBuilder();
            load(1);
        } catch (err) {
            App.toast('Failed to save form: ' + err.message, 'error');
        } finally {
            saveBtns.forEach(b => b.disabled = false);
        }
    }

    // Auto-generate code from name
    document.getElementById('form-name')?.addEventListener('input', function () {
        const codeField = document.getElementById('form-code');
        if (!codeField.dataset.userEdited) {
            codeField.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
        }
    });
    document.getElementById('form-code')?.addEventListener('input', function () {
        this.dataset.userEdited = '1';
    });

    // Event bindings
    document.getElementById('btn-new-form')?.addEventListener('click',    openBuilder);
    document.getElementById('builder-close')?.addEventListener('click',   closeBuilder);
    document.getElementById('builder-cancel')?.addEventListener('click',  closeBuilder);
    document.getElementById('btn-add-field')?.addEventListener('click',   openFieldEditor);
    document.getElementById('btn-save-draft')?.addEventListener('click',  () => saveForm(false));
    document.getElementById('btn-publish-form')?.addEventListener('click',() => saveForm(true));

    // Filters
    let searchTimer;
    ['filter-status', 'filter-category'].forEach(id =>
        document.getElementById(id)?.addEventListener('change', () => load(1)));
    document.getElementById('search-forms')?.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(1), 400);
    });

    load();
})();
