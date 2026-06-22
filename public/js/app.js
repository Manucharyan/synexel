(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const apiToken = document.querySelector('meta[name="api-token"]')?.content;

    async function api(path, options = {}) {
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            ...(apiToken ? { Authorization: 'Bearer ' + apiToken } : {}),
            ...(options.headers || {}),
        };
        if (!(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }
        const res = await fetch('/api/v1' + path, {
            credentials: 'same-origin',
            ...options,
            headers,
            body: options.body instanceof FormData
                ? options.body
                : options.body ? JSON.stringify(options.body) : undefined,
        });
        if (!res.ok) throw new Error(await res.text());
        const ct = res.headers.get('content-type') || '';
        return ct.includes('json') ? res.json() : res;
    }

    async function downloadExport(workbookId) {
        const res = await fetch('/api/v1/workbooks/' + workbookId + '/export', {
            headers: { Authorization: 'Bearer ' + apiToken },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('Export failed');
        const blob = await res.blob();
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'workbook.xlsx';
        a.click();
    }

    const workbooksApp = document.getElementById('workbooks-app');
    if (!workbooksApp) return;

    document.getElementById('btn-new-workbook')?.addEventListener('click', async () => {
        const name = prompt('Workbook name', 'Untitled workbook');
        if (!name) return;
        try {
            const data = await api('/workbooks', { method: 'POST', body: { name } });
            location.href = '/workbooks/' + data.data.id;
        } catch { alert('Could not create workbook.'); }
    });

    document.getElementById('input-import')?.addEventListener('change', async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const form = new FormData();
        form.append('file', file);
        try {
            const data = await api('/workbooks/import', { method: 'POST', body: form, headers: {} });
            location.href = '/workbooks/' + data.data.id;
        } catch { alert('Import failed.'); }
        e.target.value = '';
    });

    document.querySelectorAll('.btn-export').forEach((btn) => {
        btn.addEventListener('click', async () => {
            try { await downloadExport(btn.dataset.id); } catch { alert('Export failed.'); }
        });
    });

    document.querySelectorAll('.btn-delete').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('Delete this workbook?')) return;
            try {
                await api('/workbooks/' + btn.dataset.id, { method: 'DELETE' });
                btn.closest('.wb-card')?.remove();
            } catch { alert('Delete failed.'); }
        });
    });
})();
