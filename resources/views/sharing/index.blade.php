@extends('layouts.app')

@section('title', 'Sharing')

@section('content')
<div id="sharing-app" class="wb-hub" data-focus-workbook="{{ $focusWorkbookId ?? '' }}">
    @include('partials.app-header', ['active' => 'sharing'])

    <main class="wb-main sharing-main">
        <section class="wb-hero wb-hero-compact">
            <div class="wb-hero-copy">
                <h1>Workbook sharing</h1>
                <p>Control who can view or edit your workbooks. <strong>View only</strong> blocks adding, editing, and deleting cells.</p>
            </div>
        </section>

        <div class="sharing-perm-legend">
            <span class="sharing-legend-item"><span class="perm-badge perm-write">Can edit</span> Add, edit, and delete cells</span>
            <span class="sharing-legend-item"><span class="perm-badge perm-read">View only</span> Open workbook but cannot change data</span>
        </div>

        <section class="sharing-section">
            <div class="sharing-section-head">
                <h2>Workbooks you own</h2>
                <p>Share with system users and set their permission.</p>
            </div>
            <div id="owned-workbooks" class="sharing-stack">
                <p class="audit-loading">Loading your workbooks…</p>
            </div>
        </section>

        <section class="sharing-section">
            <div class="sharing-section-head">
                <h2>Shared with you</h2>
                <p>Workbooks other users shared with your account.</p>
            </div>
            <div id="shared-with-me" class="sharing-stack">
                <p class="audit-loading">Loading shared workbooks…</p>
            </div>
        </section>
    </main>
</div>

<script src="{{ asset('js/sharing.js') }}?v=1" defer></script>
@endsection
