import api from './api';

function colLabel(col) {
    let label = '';
    let n = col;
    while (n > 0) {
        n -= 1;
        label = String.fromCharCode(65 + (n % 26)) + label;
        n = Math.floor(n / 26);
    }
    return label;
}

function colIndex(label) {
    let index = 0;
    for (const char of label.toUpperCase()) {
        index = index * 26 + (char.charCodeAt(0) - 64);
    }
    return index;
}

function parseAddress(address) {
    const match = address.match(/^([A-Z]+)(\d+)$/i);
    if (!match) {
        return { row: 1, col: 1 };
    }
    return { row: parseInt(match[2], 10), col: colIndex(match[1]) };
}

export class SpreadsheetApp {
    constructor(root) {
        this.root = root;
        this.workbookId = root.dataset.workbookId;
        this.sheets = JSON.parse(root.dataset.sheets || '[]');
        this.activeSheetId = this.sheets[0]?.id ?? null;
        this.rows = 100;
        this.cols = 26;
        this.cells = new Map();
        this.selected = { row: 1, col: 1 };
        this.pendingSave = null;
        this.saving = false;

        this.gridBody = root.querySelector('#grid-body');
        this.colHeaders = root.querySelector('#col-headers');
        this.formulaBar = root.querySelector('#formula-bar');
        this.cellAddress = root.querySelector('#cell-address');
        this.saveStatus = root.querySelector('#save-status');
        this.sheetTabs = root.querySelector('#sheet-tabs');
        this.workbookNameInput = root.querySelector('#workbook-name');

        this.buildGrid();
        this.renderSheetTabs();
        this.bindEvents();
        this.loadSheet();
    }

    cellKey(row, col) {
        return `${row}:${col}`;
    }

    buildGrid() {
        this.colHeaders.innerHTML = '<th class="sticky left-0 z-20 w-12 min-w-12 border border-slate-300 bg-slate-200"></th>';
        for (let c = 1; c <= this.cols; c += 1) {
            const th = document.createElement('th');
            th.className = 'min-w-24 border border-slate-300 bg-slate-100 px-2 py-1 text-center text-xs font-semibold text-slate-600';
            th.textContent = colLabel(c);
            this.colHeaders.appendChild(th);
        }

        this.gridBody.innerHTML = '';
        for (let r = 1; r <= this.rows; r += 1) {
            const tr = document.createElement('tr');
            const rowHeader = document.createElement('th');
            rowHeader.className = 'sticky left-0 z-10 w-12 min-w-12 border border-slate-300 bg-slate-200 text-center text-xs font-medium text-slate-600';
            rowHeader.textContent = r;
            tr.appendChild(rowHeader);

            for (let c = 1; c <= this.cols; c += 1) {
                const td = document.createElement('td');
                td.className = 'cell min-w-24 max-w-48 border border-slate-200 bg-white px-1 py-0.5 outline-none';
                td.dataset.row = r;
                td.dataset.col = c;
                td.tabIndex = 0;
                tr.appendChild(td);
            }
            this.gridBody.appendChild(tr);
        }
    }

    renderSheetTabs() {
        this.sheetTabs.innerHTML = '';
        this.sheets.forEach((sheet) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = sheet.name;
            btn.dataset.sheetId = sheet.id;
            btn.className = sheet.id === this.activeSheetId
                ? 'rounded-t bg-white px-4 py-1.5 text-xs font-medium text-emerald-700 shadow-sm'
                : 'rounded-t px-4 py-1.5 text-xs text-slate-600 hover:bg-slate-200';
            btn.addEventListener('click', () => this.switchSheet(sheet.id));
            this.sheetTabs.appendChild(btn);
        });
    }

    bindEvents() {
        this.gridBody.addEventListener('click', (e) => {
            const cell = e.target.closest('.cell');
            if (!cell) return;
            this.selectCell(parseInt(cell.dataset.row, 10), parseInt(cell.dataset.col, 10));
        });

        this.gridBody.addEventListener('dblclick', (e) => {
            const cell = e.target.closest('.cell');
            if (!cell) return;
            this.startEdit(cell);
        });

        this.gridBody.addEventListener('keydown', (e) => {
            if (e.target.classList.contains('cell') && e.key.length === 1 && !e.ctrlKey && !e.metaKey) {
                this.startEdit(e.target, e.key);
                e.preventDefault();
            }
            if (e.key === 'Enter' && e.target.classList.contains('cell-input')) {
                e.preventDefault();
                this.commitEdit(e.target);
                this.moveSelection(1, 0);
            }
            if (e.key === 'Escape' && e.target.classList.contains('cell-input')) {
                e.target.closest('.cell')?.querySelector('.cell-display')?.classList.remove('hidden');
                e.target.remove();
            }
        });

        this.formulaBar.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.commitFromFormulaBar();
            }
        });

        this.formulaBar.addEventListener('blur', () => this.commitFromFormulaBar());

        document.addEventListener('keydown', (e) => {
            if (document.activeElement?.classList.contains('cell-input') || document.activeElement === this.formulaBar) {
                return;
            }
            const moves = {
                ArrowUp: [-1, 0], ArrowDown: [1, 0], ArrowLeft: [0, -1], ArrowRight: [0, 1],
            };
            if (moves[e.key]) {
                e.preventDefault();
                this.moveSelection(moves[e.key][0], moves[e.key][1]);
            }
            if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                this.clearSelectedCell();
            }
        });

        this.root.querySelector('#btn-export').addEventListener('click', () => {
            window.location.href = `/api/v1/workbooks/${this.workbookId}/export`;
        });

        this.root.querySelector('#input-import-sheet').addEventListener('change', async (e) => {
            const file = e.target.files?.[0];
            if (!file || !this.activeSheetId) return;
            const form = new FormData();
            form.append('file', file);
            this.setStatus('Importing…');
            try {
                await api.post(`/workbooks/${this.workbookId}/sheets/${this.activeSheetId}/import`, form);
                await this.loadSheet();
                this.setStatus('Imported');
            } catch {
                this.setStatus('Import failed', true);
            }
            e.target.value = '';
        });

        this.root.querySelector('#btn-add-sheet').addEventListener('click', () => this.addSheet());

        let nameTimeout;
        this.workbookNameInput.addEventListener('input', () => {
            clearTimeout(nameTimeout);
            nameTimeout = setTimeout(() => this.saveWorkbookName(), 500);
        });
    }

    async switchSheet(sheetId) {
        if (sheetId === this.activeSheetId) return;
        this.activeSheetId = sheetId;
        this.renderSheetTabs();
        await this.loadSheet();
    }

    async addSheet() {
        const name = prompt('Sheet name', `Sheet ${this.sheets.length + 1}`);
        if (!name) return;
        try {
            const { data } = await api.post(`/workbooks/${this.workbookId}/sheets`, { name });
            this.sheets.push(data.data);
            this.activeSheetId = data.data.id;
            this.renderSheetTabs();
            await this.loadSheet();
        } catch {
            alert('Could not create sheet.');
        }
    }

    async saveWorkbookName() {
        const name = this.workbookNameInput.value.trim();
        if (!name) return;
        try {
            await api.patch(`/workbooks/${this.workbookId}`, { name });
            this.setStatus('Saved');
        } catch {
            this.setStatus('Save failed', true);
        }
    }

    async loadSheet() {
        if (!this.activeSheetId) return;
        this.setStatus('Loading…');
        this.cells.clear();
        const endCol = colLabel(this.cols);
        try {
            const { data } = await api.get(
                `/workbooks/${this.workbookId}/sheets/${this.activeSheetId}/cells`,
                { params: { range: `A1:${endCol}${this.rows}` } },
            );
            data.data.forEach((cell) => {
                this.cells.set(this.cellKey(cell.row, cell.col), cell);
            });
            this.renderAllCells();
            this.selectCell(this.selected.row, this.selected.col);
            this.setStatus('');
        } catch {
            this.setStatus('Load failed', true);
        }
    }

    renderAllCells() {
        this.root.querySelectorAll('.cell').forEach((td) => {
            const row = parseInt(td.dataset.row, 10);
            const col = parseInt(td.dataset.col, 10);
            this.renderCell(td, row, col);
        });
    }

    renderCell(td, row, col) {
        td.innerHTML = '';
        td.classList.remove('ring-2', 'ring-emerald-500', 'bg-emerald-50');
        const cell = this.cells.get(this.cellKey(row, col));
        const display = document.createElement('div');
        display.className = 'cell-display truncate px-1 py-0.5';
        const text = cell?.formula
            ? (cell.computed ?? '')
            : (cell?.value ?? cell?.computed ?? '');
        display.textContent = text === null || text === undefined ? '' : String(text);
        if (cell?.formula) {
            display.title = `${cell.formula} → ${cell.computed ?? ''}`;
        }
        td.appendChild(display);
        if (row === this.selected.row && col === this.selected.col) {
            td.classList.add('ring-2', 'ring-emerald-500', 'bg-emerald-50');
        }
    }

    selectCell(row, col) {
        this.selected = { row, col };
        this.root.querySelectorAll('.cell').forEach((td) => {
            td.classList.remove('ring-2', 'ring-emerald-500', 'bg-emerald-50');
        });
        const td = this.getCellEl(row, col);
        if (td) {
            td.classList.add('ring-2', 'ring-emerald-500', 'bg-emerald-50');
            td.focus();
        }
        this.cellAddress.textContent = `${colLabel(col)}${row}`;
        const cell = this.cells.get(this.cellKey(row, col));
        this.formulaBar.value = cell?.formula ?? cell?.value ?? cell?.computed ?? '';
    }

    getCellEl(row, col) {
        return this.gridBody.querySelector(`.cell[data-row="${row}"][data-col="${col}"]`);
    }

    startEdit(td, initialChar = '') {
        const row = parseInt(td.dataset.row, 10);
        const col = parseInt(td.dataset.col, 10);
        this.selectCell(row, col);
        const cell = this.cells.get(this.cellKey(row, col));
        const current = initialChar || cell?.formula || cell?.value || cell?.computed || '';
        td.innerHTML = '';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'cell-input w-full border-0 bg-transparent px-1 py-0.5 text-sm outline-none';
        input.value = current;
        td.appendChild(input);
        input.focus();
        if (initialChar) {
            input.setSelectionRange(input.value.length, input.value.length);
        } else {
            input.select();
        }
        input.addEventListener('blur', () => this.commitEdit(input), { once: true });
    }

    commitEdit(input) {
        const td = input.closest('.cell');
        if (!td) return;
        const row = parseInt(td.dataset.row, 10);
        const col = parseInt(td.dataset.col, 10);
        this.queueSave(row, col, input.value);
    }

    commitFromFormulaBar() {
        const { row, col } = this.selected;
        this.queueSave(row, col, this.formulaBar.value);
    }

    queueSave(row, col, rawValue) {
        const trimmed = rawValue.trim();
        const key = this.cellKey(row, col);
        const existing = this.cells.get(key);
        const existingDisplay = existing?.formula ?? existing?.value ?? existing?.computed ?? '';

        if (String(existingDisplay) === trimmed) {
            this.renderCell(this.getCellEl(row, col), row, col);
            return;
        }

        const update = { row, col };
        if (trimmed === '') {
            update.clear = true;
        } else if (trimmed.startsWith('=')) {
            update.formula = trimmed;
        } else {
            update.value = trimmed;
        }

        this.cells.set(key, {
            row, col,
            value: update.value ?? null,
            formula: update.formula ?? null,
            computed: trimmed.startsWith('=') ? '…' : trimmed,
        });
        this.renderCell(this.getCellEl(row, col), row, col);
        this.selectCell(row, col);

        clearTimeout(this.pendingSave);
        this.pendingSave = setTimeout(() => this.flushSave([update]), 300);
    }

    async flushSave(updates) {
        if (!this.activeSheetId || updates.length === 0) return;
        this.saving = true;
        this.setStatus('Saving…');
        try {
            await api.patch(
                `/workbooks/${this.workbookId}/sheets/${this.activeSheetId}/cells`,
                { updates, recalculate: true },
            );
            await this.loadSheet();
            this.setStatus('Saved');
        } catch {
            this.setStatus('Save failed', true);
        } finally {
            this.saving = false;
        }
    }

    async clearSelectedCell() {
        const { row, col } = this.selected;
        this.cells.delete(this.cellKey(row, col));
        this.renderCell(this.getCellEl(row, col), row, col);
        await this.flushSave([{ row, col, clear: true }]);
    }

    moveSelection(dRow, dCol) {
        const row = Math.max(1, Math.min(this.rows, this.selected.row + dRow));
        const col = Math.max(1, Math.min(this.cols, this.selected.col + dCol));
        this.selectCell(row, col);
        this.getCellEl(row, col)?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }

    setStatus(text, isError = false) {
        this.saveStatus.textContent = text;
        this.saveStatus.className = isError ? 'text-xs text-red-200' : 'text-xs text-emerald-200';
    }
}

export function initSpreadsheet() {
    const root = document.getElementById('spreadsheet-app');
    if (root) {
        new SpreadsheetApp(root);
    }
}
