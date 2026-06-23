(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const apiToken = document.querySelector('meta[name="api-token"]')?.content;
    const root = document.getElementById('webhooks-app');
    if (!root) return;

    const WEBHOOK_EVENTS = [
        'workbook.created', 'workbook.deleted', 'workbook.imported', 'workbook.exported',
        'sheet.created', 'sheet.renamed', 'cells.updated', 'range.cleared',
        'named_range.changed', 'conditional_format.changed', 'chart.changed',
    ];

    let subscriptions = [];
    let editingId = null;

    async function api(path, opts = {}) {
        const res = await fetch('/api/v1' + path, {
            credentials: 'same-origin',
            method: opts.method || 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...(apiToken ? { Authorization: 'Bearer ' + apiToken } : {}),
            },
            body: opts.body ? JSON.stringify(opts.body) : undefined,
        });
        if (!res.ok) throw new Error(await res.text());
        return res.json();
    }

    function esc(text) {
        const el = document.createElement('span');
        el.textContent = text ?? '';
        return el.innerHTML;
    }

    function formatWhen(iso) {
        if (!iso) return '—';
        return new Date(iso).toLocaleString();
    }

    function renderEventsCheckboxes(selected = []) {
        const wrap = document.getElementById('webhook-events');
        wrap.innerHTML = WEBHOOK_EVENTS.map((ev) => `
            <label class="xl-check-row">
                <input type="checkbox" value="${ev}" ${selected.includes(ev) ? 'checked' : ''}> ${ev}
            </label>
        `).join('');
    }

    function renderSubscriptions() {
        const list = document.getElementById('webhooks-list');
        const filter = document.getElementById('filter-subscription');

        if (!subscriptions.length) {
            list.innerHTML = '<p class="audit-empty">No webhook subscriptions yet.</p>';
            filter.innerHTML = '<option value="">All subscriptions</option>';
            return;
        }

        list.innerHTML = subscriptions.map((sub) => `
            <article class="webhook-card" data-id="${sub.id}">
                <div class="webhook-card-head">
                    <strong>${esc(sub.url)}</strong>
                    <span class="audit-badge ${sub.active ? '' : 'audit-badge-muted'}">${sub.active ? 'Active' : 'Inactive'}</span>
                </div>
                <p class="webhook-card-events">${esc((sub.events || []).join(', '))}</p>
                <div class="webhook-card-actions">
                    <button type="button" class="btn btn-secondary btn-sm" data-action="edit" data-id="${sub.id}">Edit</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-action="test" data-id="${sub.id}">Test</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-action="deliveries" data-id="${sub.id}">Deliveries</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-action="delete" data-id="${sub.id}">Delete</button>
                </div>
            </article>
        `).join('');

        filter.innerHTML = '<option value="">All subscriptions</option>' + subscriptions.map((sub) =>
            `<option value="${sub.id}">${esc(sub.url)}</option>`
        ).join('');
    }

    async function loadSubscriptions() {
        const res = await api('/webhooks');
        subscriptions = res.data || [];
        renderSubscriptions();
    }

    async function loadDeliveries(subscriptionId = '') {
        const path = subscriptionId
            ? `/webhooks/${subscriptionId}/deliveries?per_page=50`
            : '/webhooks/deliveries?per_page=50';
        const res = await api(path);
        const body = document.getElementById('deliveries-body');
        const rows = res.data || [];

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="6" class="audit-empty">No deliveries yet.</td></tr>';
            return;
        }

        body.innerHTML = rows.map((d) => `
            <tr>
                <td>${esc(formatWhen(d.created_at))}</td>
                <td>${esc(d.event)}</td>
                <td><span class="audit-badge">${esc(d.status)}</span></td>
                <td>${esc(d.response_code ?? '—')}</td>
                <td>${d.duration_ms != null ? d.duration_ms + ' ms' : '—'}</td>
                <td>${esc(d.attempt)}</td>
            </tr>
        `).join('');
    }

    function openModal(sub = null) {
        editingId = sub?.id || null;
        document.getElementById('webhook-modal-title').textContent = sub ? 'Edit webhook' : 'New webhook';
        document.getElementById('webhook-url').value = sub?.url || '';
        document.getElementById('webhook-active').checked = sub?.active ?? true;
        renderEventsCheckboxes(sub?.events || ['cells.updated']);
        document.getElementById('webhook-secret').style.display = 'none';
        document.getElementById('modal-webhook').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('modal-webhook').style.display = 'none';
        editingId = null;
    }

    async function saveWebhook() {
        const url = document.getElementById('webhook-url').value.trim();
        const events = [...document.querySelectorAll('#webhook-events input:checked')].map((el) => el.value);
        const active = document.getElementById('webhook-active').checked;

        if (!url || !events.length) {
            alert('URL and at least one event are required.');
            return;
        }

        const body = { url, events, active };
        const res = editingId
            ? await api('/webhooks/' + editingId, { method: 'PATCH', body })
            : await api('/webhooks', { method: 'POST', body });

        if (res.data?.secret) {
            const secretBox = document.getElementById('webhook-secret');
            secretBox.style.display = 'block';
            secretBox.innerHTML = '<strong>Signing secret (shown once):</strong> <code>' + esc(res.data.secret) + '</code>';
        } else {
            closeModal();
        }

        await loadSubscriptions();
    }

    document.getElementById('btn-new-webhook')?.addEventListener('click', () => openModal());
    document.getElementById('btn-save-webhook')?.addEventListener('click', () => saveWebhook().catch((e) => alert(e.message)));
    document.getElementById('btn-refresh-deliveries')?.addEventListener('click', () => {
        loadDeliveries(document.getElementById('filter-subscription').value).catch((e) => alert(e.message));
    });
    document.getElementById('filter-subscription')?.addEventListener('change', (e) => {
        loadDeliveries(e.target.value).catch((err) => alert(err.message));
    });

    document.querySelectorAll('[data-close="modal-webhook"]').forEach((btn) => {
        btn.addEventListener('click', closeModal);
    });

    document.getElementById('webhooks-list')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const id = btn.dataset.id;
        const action = btn.dataset.action;

        try {
            if (action === 'edit') {
                openModal(subscriptions.find((s) => s.id === id));
            } else if (action === 'test') {
                await api('/webhooks/' + id + '/test', { method: 'POST', body: {} });
                alert('Test webhook queued.');
                await loadDeliveries();
            } else if (action === 'deliveries') {
                document.getElementById('filter-subscription').value = id;
                await loadDeliveries(id);
            } else if (action === 'delete') {
                if (!confirm('Delete this webhook subscription?')) return;
                await api('/webhooks/' + id, { method: 'DELETE' });
                await loadSubscriptions();
                await loadDeliveries();
            }
        } catch (err) {
            alert(err.message);
        }
    });

    loadSubscriptions()
        .then(() => loadDeliveries())
        .catch((e) => {
            document.getElementById('webhooks-list').innerHTML = '<p class="audit-empty">Failed to load: ' + esc(e.message) + '</p>';
        });
})();
