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

    const canAdd = workbooksApp.dataset.canAdd !== '0';
    const canDelete = workbooksApp.dataset.canDelete !== '0';

    function applyWorkbookRestrictions() {
        if (!canAdd) {
            document.getElementById('btn-new-workbook')?.setAttribute('disabled', 'disabled');
            document.querySelectorAll('#input-import, .upload-btn input[type="file"]').forEach((el) => {
                el.disabled = true;
                el.closest('.upload-btn')?.classList.add('disabled');
            });
            document.querySelectorAll('.wb-empty-actions .btn').forEach((btn) => {
                if (btn.textContent.toLowerCase().includes('create') || btn.textContent.toLowerCase().includes('import')) {
                    btn.setAttribute('disabled', 'disabled');
                }
            });
        }
        if (!canDelete) {
            document.querySelectorAll('.btn-delete').forEach((btn) => btn.setAttribute('disabled', 'disabled'));
        }
    }

    applyWorkbookRestrictions();

    document.getElementById('btn-new-workbook')?.addEventListener('click', async () => {
        if (!canAdd) {
            alert('Adding workbooks is currently disabled by an administrator.');
            return;
        }
        const name = prompt('Workbook name', 'Untitled workbook');
        if (!name) return;
        try {
            const data = await api('/workbooks', { method: 'POST', body: { name } });
            location.href = '/workbooks/' + data.data.id;
        } catch { alert('Could not create workbook.'); }
    });

    document.getElementById('input-import')?.addEventListener('change', async (e) => {
        if (!canAdd) {
            alert('Importing workbooks is currently disabled by an administrator.');
            e.target.value = '';
            return;
        }
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
            if (!canDelete) {
                alert('Deleting workbooks is currently disabled by an administrator.');
                return;
            }
            if (!confirm('Delete this workbook?')) return;
            try {
                await api('/workbooks/' + btn.dataset.id, { method: 'DELETE' });
                btn.closest('.wb-card')?.remove();
            } catch { alert('Delete failed.'); }
        });
    });
})();
