@extends('layouts.app')

@section('title', 'Workbooks')

@section('content')
<div id="workbooks-app" class="wb-hub">
    @include('partials.app-header', ['active' => 'workbooks'])

    <main class="wb-main">
        <section class="wb-hero">
            <div class="wb-hero-copy">
                <h1>Your workbooks</h1>
                <p>Create, import, and manage spreadsheets — all in one place.</p>
            </div>
            <div class="wb-stats">
                <div class="wb-stat">
                    <span class="wb-stat-value">{{ $workbooks->count() }}</span>
                    <span class="wb-stat-label">{{ Str::plural('Workbook', $workbooks->count()) }}</span>
                </div>
            </div>
        </section>

        <section class="wb-actions">
            <div class="wb-actions-primary">
                <button id="btn-new-workbook" type="button" class="btn btn-primary btn-lg">
                    <svg class="wb-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    New workbook
                </button>
                <label class="btn btn-secondary btn-lg upload-btn">
                    <svg class="wb-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 13V4m0 0L6 8m4-4l4 4M4 14v1a2 2 0 002 2h8a2 2 0 002-2v-1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Import .xlsx
                    <input id="input-import" type="file" accept=".xlsx,.xls" class="hidden">
                </label>
            </div>
        </section>

        <div id="workbooks-list" class="wb-grid">
            @forelse ($workbooks as $workbook)
                <article class="wb-card" data-id="{{ $workbook->id }}">
                    <a href="{{ route('workbooks.show', $workbook->id) }}" class="wb-card-link">
                        <div class="wb-preview" aria-hidden="true">
                            <div class="wb-preview-bar"></div>
                            <div class="wb-preview-grid">
                                @for ($i = 0; $i < 24; $i++)
                                    <span class="wb-cell {{ $i % 7 === 0 ? 'wb-cell-accent' : '' }}"></span>
                                @endfor
                            </div>
                        </div>
                        <div class="wb-card-body">
                            <div class="wb-card-top">
                                <h2>{{ $workbook->name }}</h2>
                                @if ($workbook->sheets_count ?? 0)
                                    <span class="wb-badge">{{ $workbook->sheets_count }} {{ Str::plural('sheet', $workbook->sheets_count) }}</span>
                                @endif
                            </div>
                            <p class="wb-card-meta">
                                <svg class="wb-icon-sm" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.2"/><path d="M8 4.5V8l2.5 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                                Updated {{ $workbook->updated_at->diffForHumans() }}
                            </p>
                        </div>
                    </a>
                    <div class="workbook-actions wb-card-actions">
                        <button type="button" class="btn-export wb-action" data-id="{{ $workbook->id }}" title="Export as .xlsx">
                            <svg class="wb-icon-sm" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 2v8m0 0l-3-3m3 3l3-3M3 12v1.5A1.5 1.5 0 004.5 15h7a1.5 1.5 0 001.5-1.5V12" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Export
                        </button>
                        <button type="button" class="btn-delete wb-action danger" data-id="{{ $workbook->id }}" title="Delete workbook">
                            <svg class="wb-icon-sm" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 4h10M6 4V2.5h4V4m-7.5 0l.6 9.5h8.8l.6-9.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Delete
                        </button>
                    </div>
                </article>
            @empty
                <div class="empty-state wb-empty">
                    <div class="wb-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 64 64" fill="none"><rect x="8" y="12" width="48" height="40" rx="4" stroke="currentColor" stroke-width="2"/><path d="M8 22h48M20 12v40M32 12v40M44 12v40M8 34h48M8 46h48" stroke="currentColor" stroke-width="1.5" opacity=".4"/></svg>
                    </div>
                    <p class="empty-title">No workbooks yet</p>
                    <p class="empty-text">Create a new workbook or import an .xlsx file to get started.</p>
                    <div class="wb-empty-actions">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('btn-new-workbook').click()">Create workbook</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('input-import').click()">Import file</button>
                    </div>
                </div>
            @endforelse
        </div>
    </main>
</div>
@endsection
