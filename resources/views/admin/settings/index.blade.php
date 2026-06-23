@extends('layouts.app')

@section('title', 'Spreadsheet settings')

@section('content')
<div id="admin-settings-app" class="wb-hub">
    @include('partials.app-header', ['active' => 'settings'])

    <main class="wb-main">
        <section class="wb-hero wb-hero-compact">
            <div class="wb-hero-copy">
                <h1>Spreadsheet restrictions</h1>
                <p>Control whether anyone can add or delete data on the Excel board. Applies to all accounts, including administrators. Use Settings to turn restrictions off again.</p>
            </div>
        </section>

        @if ($status)
            <div class="alert-success">{{ $status }}</div>
        @endif

        <div class="admin-grid">
            <section class="admin-panel admin-panel-wide">
                <h2 class="admin-panel-title">Editing restrictions</h2>
                <form method="POST" action="{{ route('admin.settings.update') }}" class="admin-form">
                    @csrf
                    @method('PATCH')

                    <div class="admin-setting-row">
                        <label class="admin-toggle">
                            <input type="hidden" name="block_adding" value="0">
                            <input type="checkbox" name="block_adding" value="1" @checked($blockAdding)>
                            <span class="admin-toggle-label">
                                <strong>Block adding data</strong>
                                <span class="admin-muted">Prevents everyone from creating workbooks, importing files, entering new cell values, pasting, and inserting rows or columns.</span>
                            </span>
                        </label>
                    </div>

                    <div class="admin-setting-row">
                        <label class="admin-toggle">
                            <input type="hidden" name="block_deleting" value="0">
                            <input type="checkbox" name="block_deleting" value="1" @checked($blockDeleting)>
                            <span class="admin-toggle-label">
                                <strong>Block deleting data</strong>
                                <span class="admin-muted">Prevents everyone from deleting workbooks, clearing cells, cutting data, and removing rows or columns.</span>
                            </span>
                        </label>
                    </div>

                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-primary">Save settings</button>
                    </div>
                </form>
            </section>
        </div>
    </main>
</div>
@endsection
