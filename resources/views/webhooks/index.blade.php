@extends('layouts.app')

@section('title', 'Webhooks')

@section('content')
<div id="webhooks-app" class="wb-hub">
    @include('partials.app-header', ['active' => 'webhooks'])

    <main class="wb-main audit-main">
        <section class="wb-hero wb-hero-compact">
            <div class="wb-hero-copy">
                <h1>Webhooks</h1>
                <p>Manage subscriptions and inspect delivery history.</p>
            </div>
            <button id="btn-new-webhook" type="button" class="btn btn-primary">New webhook</button>
        </section>

        <div class="webhooks-layout">
            <section class="webhooks-panel">
                <h2 class="panel-title">Subscriptions</h2>
                <div id="webhooks-list" class="webhooks-list">
                    <p class="audit-loading">Loading subscriptions…</p>
                </div>
            </section>

            <section class="webhooks-panel">
                <h2 class="panel-title">Delivery log</h2>
                <div class="audit-toolbar">
                    <select id="filter-subscription" class="field audit-field">
                        <option value="">All subscriptions</option>
                    </select>
                    <button id="btn-refresh-deliveries" type="button" class="btn btn-secondary">Refresh</button>
                </div>
                <div class="audit-table-wrap">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Event</th>
                                <th>Status</th>
                                <th>Code</th>
                                <th>Duration</th>
                                <th>Attempt</th>
                            </tr>
                        </thead>
                        <tbody id="deliveries-body">
                            <tr><td colspan="6" class="audit-loading">Loading deliveries…</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>

<div id="modal-webhook" class="xl-modal" style="display:none">
    <div class="xl-modal-box">
        <div class="xl-modal-hdr">
            <span class="xl-modal-title" id="webhook-modal-title">New webhook</span>
            <button class="xl-modal-x" data-close="modal-webhook">✕</button>
        </div>
        <div class="xl-modal-body">
            <div class="xl-field-row"><label>URL</label><input id="webhook-url" class="xl-field" type="url" placeholder="https://example.com/webhook"></div>
            <div class="xl-field-row">
                <label>Events</label>
                <div class="webhook-events" id="webhook-events"></div>
            </div>
            <label class="xl-check-row"><input type="checkbox" id="webhook-active" checked> Active</label>
            <div id="webhook-secret" class="webhook-secret" style="display:none"></div>
        </div>
        <div class="xl-modal-ftr">
            <button id="btn-save-webhook" class="xl-mbtn xl-mbtn-primary">Save</button>
            <button data-close="modal-webhook" class="xl-mbtn">Cancel</button>
        </div>
    </div>
</div>

<script src="{{ asset('js/webhooks.js') }}?v=1" defer></script>
@endsection
