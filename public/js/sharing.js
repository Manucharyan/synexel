(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const apiToken = document.querySelector('meta[name="api-token"]')?.content;
    const root = document.getElementById('sharing-app');
    if (!root) return;

    const focusWorkbookId = root.dataset.focusWorkbook || '';

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

    function permBadge(permission) {
        const cls = permission === 'write' ? 'perm-write' : 'perm-read';
        const label = permission === 'write' ? 'Can edit' : 'View only';
        return `<span class="perm-badge ${cls}">${label}</span>`;
    }

    function renderOwned(workbooks) {
        const wrap = document.getElementById('owned-workbooks');
        if (!workbooks.length) {
            wrap.innerHTML = '<div class="sharing-empty">You have no workbooks yet. <a href="/workbooks">Create one</a>.</div>';
            return;
        }

        wrap.innerHTML = workbooks.map((wb) => {
            const open = focusWorkbookId && focusWorkbookId === wb.id;
            return `
            <article class="sharing-card ${open ? 'sharing-card-open' : ''}" data-workbook-id="${wb.id}">
                <header class="sharing-card-head">
                    <div>
                        <h3><a href="/workbooks/${wb.id}">${esc(wb.name)}</a></h3>
                        <p class="sharing-card-meta">${wb.shares_count} ${wb.shares_count === 1 ? 'person' : 'people'} shared with</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-toggle-share="${wb.id}">
                        ${open ? 'Hide' : 'Manage sharing'}
                    </button>
                </header>
                <div class="sharing-card-body" ${open ? '' : 'hidden'}>
                    <form class="sharing-add-form" data-workbook-id="${wb.id}">
                        <input type="email" class="field" name="email" placeholder="user@example.com" required>
                        <select class="field" name="permission">
                            <option value="read">View only — cannot add/delete cells</option>
                            <option value="write">Can edit — add, edit, delete cells</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Share workbook</button>
                    </form>
                    <div class="sharing-people">
                        ${wb.shares.length ? wb.shares.map((s) => `
                            <div class="sharing-person" data-share-id="${s.id}">
                                <div class="sharing-person-info">
                                    <strong>${esc(s.user.name)}</strong>
                                    <span class="audit-muted">${esc(s.user.email)}</span>
                                </div>
                                <select class="field sharing-perm-select" data-workbook-id="${wb.id}" data-share-id="${s.id}">
                                    <option value="read" ${s.permission === 'read' ? 'selected' : ''}>View only</option>
                                    <option value="write" ${s.permission === 'write' ? 'selected' : ''}>Can edit</option>
                                </select>
                                <button type="button" class="btn btn-secondary btn-sm sharing-remove" data-workbook-id="${wb.id}" data-share-id="${s.id}">Remove</button>
                            </div>
                        `).join('') : '<p class="audit-muted">Not shared with anyone yet.</p>'}
                    </div>
                </div>
            </article>`;
        }).join('');
    }

    function renderSharedWithMe(items) {
        const wrap = document.getElementById('shared-with-me');
        if (!items.length) {
            wrap.innerHTML = '<div class="sharing-empty">No workbooks have been shared with you yet.</div>';
            return;
        }

        wrap.innerHTML = items.map((item) => `
            <article class="sharing-card sharing-card-flat">
                <div class="sharing-card-head">
                    <div>
                        <h3><a href="/workbooks/${item.workbook.id}">${esc(item.workbook.name)}</a></h3>
                        <p class="sharing-card-meta">Shared by ${esc(item.shared_by.name)} (${esc(item.shared_by.email)})</p>
                    </div>
                    ${permBadge(item.permission)}
                </div>
                <p class="sharing-access-note">
                    ${item.can_edit
                        ? 'You can add, edit, and delete cells in this workbook.'
                        : 'You can open this workbook but <strong>cannot</strong> add, edit, or delete cells.'}
                </p>
                <a href="/workbooks/${item.workbook.id}" class="btn btn-secondary btn-sm">Open workbook</a>
            </article>
        `).join('');
    }

    async function load() {
        const data = await api('/sharing');
        renderOwned(data.data.owned || []);
        renderSharedWithMe(data.data.shared_with_me || []);

        if (focusWorkbookId) {
            const card = document.querySelector(`[data-workbook-id="${focusWorkbookId}"]`);
            card?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    document.getElementById('owned-workbooks')?.addEventListener('click', async (e) => {
        const toggle = e.target.closest('[data-toggle-share]');
        if (toggle) {
            const card = toggle.closest('.sharing-card');
            const body = card?.querySelector('.sharing-card-body');
            if (!body) return;
            const open = body.hasAttribute('hidden');
            body.toggleAttribute('hidden', !open);
            card.classList.toggle('sharing-card-open', open);
            toggle.textContent = open ? 'Hide' : 'Manage sharing';
            return;
        }

        const remove = e.target.closest('.sharing-remove');
        if (remove) {
            if (!confirm('Remove sharing for this user?')) return;
            try {
                await api(`/workbooks/${remove.dataset.workbookId}/shares/${remove.dataset.shareId}`, { method: 'DELETE' });
                await load();
            } catch (err) {
                alert(err.message);
            }
        }
    });

    document.getElementById('owned-workbooks')?.addEventListener('change', async (e) => {
        const sel = e.target.closest('.sharing-perm-select');
        if (!sel) return;
        try {
            await api(`/workbooks/${sel.dataset.workbookId}/shares/${sel.dataset.shareId}`, {
                method: 'PATCH',
                body: { permission: sel.value },
            });
        } catch (err) {
            alert(err.message);
            await load();
        }
    });

    document.getElementById('owned-workbooks')?.addEventListener('submit', async (e) => {
        const form = e.target.closest('.sharing-add-form');
        if (!form) return;
        e.preventDefault();
        const email = form.email.value.trim();
        const permission = form.permission.value;
        if (!email) return;
        try {
            await api(`/workbooks/${form.dataset.workbookId}/shares`, {
                method: 'POST',
                body: { email, permission },
            });
            form.reset();
            await load();
        } catch (err) {
            alert(err.message);
        }
    });

    load().catch((err) => {
        document.getElementById('owned-workbooks').innerHTML = '<div class="sharing-empty">Failed to load: ' + esc(err.message) + '</div>';
        document.getElementById('shared-with-me').innerHTML = '';
    });
})();
