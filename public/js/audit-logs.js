(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const apiToken = document.querySelector('meta[name="api-token"]')?.content;
    const root = document.getElementById('audit-app');
    if (!root) return;

    let currentPage = 1;
    let lastPage = 1;

    async function api(path) {
        const res = await fetch('/api/v1' + path, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...(apiToken ? { Authorization: 'Bearer ' + apiToken } : {}),
            },
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
        const d = new Date(iso);
        return d.toLocaleString(undefined, {
            month: 'short', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit',
        });
    }

    function formatDetails(log) {
        const parts = [];
        if (log.sheet_name) parts.push('Sheet: ' + log.sheet_name);
        if (log.details?.samples?.length) {
            log.details.samples.forEach((s) => {
                const from = s.from ?? '(empty)';
                const to = s.to ?? '(empty)';
                parts.push(s.cell + ': ' + from + ' → ' + to);
            });
            if (log.details.count > log.details.samples.length) {
                parts.push('+' + (log.details.count - log.details.samples.length) + ' more cells');
            }
        } else if (log.details?.count) {
            parts.push(log.details.count + ' cells');
        }
        if (log.operation_id) parts.push('Op: ' + log.operation_id);
        return parts.length ? parts.join(' · ') : '—';
    }

    function renderRows(logs) {
        const body = document.getElementById('audit-body');
        if (!logs.length) {
            body.innerHTML = '<tr><td colspan="7" class="audit-empty">No activity recorded yet.</td></tr>';
            return;
        }

        body.innerHTML = logs.map((log) => `
            <tr>
                <td class="audit-when">${esc(formatWhen(log.created_at))}</td>
                <td class="audit-who">
                    <strong>${esc(log.user?.name || 'Unknown')}</strong>
                    ${log.user?.email ? '<br><span class="audit-muted">' + esc(log.user.email) + '</span>' : ''}
                </td>
                <td><span class="audit-badge">${esc(log.action_label || log.action)}</span></td>
                <td><span class="audit-badge audit-outcome-${esc(log.outcome || 'success')}">${esc(log.outcome || 'success')}</span></td>
                <td>${esc(log.workbook_name || '—')}</td>
                <td class="audit-target">${esc(log.target || log.summary || '—')}</td>
                <td class="audit-details">${esc(formatDetails(log))}</td>
            </tr>
        `).join('');
    }

    async function loadLogs(page = 1) {
        const workbook = document.getElementById('filter-workbook')?.value || '';
        const action = document.getElementById('filter-action')?.value || '';
        const outcome = document.getElementById('filter-outcome')?.value || '';
        const search = document.getElementById('filter-search')?.value.trim() || '';

        const params = new URLSearchParams({ page: String(page), per_page: '50' });
        if (workbook) params.set('workbook_id', workbook);
        if (action) params.set('action', action);
        if (outcome) params.set('outcome', outcome);
        if (search) params.set('search', search);

        const body = document.getElementById('audit-body');
        body.innerHTML = '<tr><td colspan="7" class="audit-loading">Loading activity…</td></tr>';

        try {
            const data = await api('/audit-logs?' + params.toString());
            const logs = data.data || [];
            currentPage = data.meta?.current_page || 1;
            lastPage = data.meta?.last_page || 1;

            renderRows(logs);

            document.getElementById('audit-page-info').textContent =
                'Page ' + currentPage + (lastPage > 1 ? ' of ' + lastPage : '');
            document.getElementById('btn-prev').disabled = currentPage <= 1;
            document.getElementById('btn-next').disabled = currentPage >= lastPage;
        } catch (err) {
            const msg = String(err?.message || err);
            const hint = msg.includes('401') || msg.includes('Unauthenticated')
                ? 'Session expired — please sign in again.'
                : 'Could not load activity log.';
            body.innerHTML = '<tr><td colspan="7" class="audit-error">' + esc(hint) + '</td></tr>';
        }
    }

    document.getElementById('btn-refresh')?.addEventListener('click', () => loadLogs(1));
    document.getElementById('btn-prev')?.addEventListener('click', () => {
        if (currentPage > 1) loadLogs(currentPage - 1);
    });
    document.getElementById('btn-next')?.addEventListener('click', () => {
        if (currentPage < lastPage) loadLogs(currentPage + 1);
    });

    ['filter-workbook', 'filter-action', 'filter-outcome'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => loadLogs(1));
    });

    let searchTimer;
    document.getElementById('filter-search')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadLogs(1), 350);
    });

    loadLogs(1);
})();
