import api from './api';

function initWorkbooks() {
    const root = document.getElementById('workbooks-app');
    if (!root) return;

    root.querySelector('#btn-new-workbook')?.addEventListener('click', async () => {
        const name = prompt('Workbook name', 'Untitled workbook');
        if (!name) return;
        try {
            const { data } = await api.post('/workbooks', { name });
            window.location.href = `/workbooks/${data.data.id}`;
        } catch {
            alert('Could not create workbook.');
        }
    });

    root.querySelector('#input-import')?.addEventListener('change', async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        const form = new FormData();
        form.append('file', file);
        try {
            const { data } = await api.post('/workbooks/import', form);
            window.location.href = `/workbooks/${data.data.id}`;
        } catch {
            alert('Import failed.');
        }
        e.target.value = '';
    });

    root.querySelectorAll('.btn-export').forEach((btn) => {
        btn.addEventListener('click', () => {
            window.location.href = `/api/v1/workbooks/${btn.dataset.id}/export`;
        });
    });

    root.querySelectorAll('.btn-delete').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('Delete this workbook?')) return;
            try {
                await api.delete(`/workbooks/${btn.dataset.id}`);
                btn.closest('.workbook-card')?.remove();
            } catch {
                alert('Delete failed.');
            }
        });
    });
}

export { initWorkbooks };
