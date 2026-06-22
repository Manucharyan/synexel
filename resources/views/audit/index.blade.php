@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
<div id="audit-app" class="wb-hub">
    @include('partials.app-header', ['active' => 'audit'])

    <main class="wb-main audit-main">
        <section class="wb-hero wb-hero-compact">
            <div class="wb-hero-copy">
                <h1>Activity Log</h1>
                <p>Who did what, when — across your workbooks.</p>
            </div>
        </section>

        <div class="audit-toolbar">
            <select id="filter-workbook" class="field audit-field">
                <option value="">All workbooks</option>
                @foreach ($workbooks as $workbook)
                    <option value="{{ $workbook->id }}">{{ $workbook->name }}</option>
                @endforeach
            </select>
            <select id="filter-action" class="field audit-field">
                <option value="">All actions</option>
                <option value="cells.updated">Cell updates</option>
                <option value="range.cleared">Range cleared</option>
                <option value="range.sorted">Sort</option>
                <option value="filter.applied">Filter</option>
                <option value="sheet.layout_changed">Merge / layout</option>
                <option value="rows.inserted">Insert rows</option>
                <option value="rows.deleted">Delete rows</option>
                <option value="columns.inserted">Insert columns</option>
                <option value="columns.deleted">Delete columns</option>
                <option value="workbook.created">Workbook created</option>
                <option value="workbook.updated">Workbook updated</option>
                <option value="workbook.deleted">Workbook deleted</option>
                <option value="workbook.imported">Import</option>
                <option value="workbook.exported">Export</option>
                <option value="operation.reverted">Revert</option>
            </select>
            <input id="filter-search" type="search" class="field audit-field audit-search" placeholder="Search summary, target, sheet…">
            <button id="btn-refresh" type="button" class="btn btn-primary">Refresh</button>
        </div>

        <div class="audit-table-wrap">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Who</th>
                        <th>Action</th>
                        <th>Workbook</th>
                        <th>Target</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody id="audit-body">
                    <tr><td colspan="6" class="audit-loading">Loading activity…</td></tr>
                </tbody>
            </table>
        </div>

        <div class="audit-pagination">
            <button id="btn-prev" type="button" class="btn btn-secondary" disabled>Previous</button>
            <span id="audit-page-info">Page 1</span>
            <button id="btn-next" type="button" class="btn btn-secondary" disabled>Next</button>
        </div>
    </main>
</div>
<script src="{{ asset('js/audit-logs.js') }}?v=2" defer></script>
@endsection
