@php($active = $active ?? '')

<header class="wb-header">
    <div class="wb-header-inner">
        <a href="{{ route('workbooks.index') }}" class="wb-brand">
            <div class="brand-logo brand-logo-sm">S</div>
            <div class="wb-brand-text">
                <span class="wb-brand-name">Synexel</span>
                <span class="wb-brand-tag">Spreadsheets</span>
            </div>
        </a>

        <nav class="wb-nav">
            <a href="{{ route('workbooks.index') }}" class="wb-nav-link {{ $active === 'workbooks' ? 'active' : '' }}">Workbooks</a>
            <a href="{{ route('webhooks.index') }}" class="wb-nav-link {{ $active === 'webhooks' ? 'active' : '' }}">Webhooks</a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.users.index') }}" class="wb-nav-link {{ $active === 'users' ? 'active' : '' }}">Users</a>
            @endif
            <a href="{{ route('audit.index') }}" class="wb-nav-link {{ $active === 'audit' ? 'active' : '' }}">Activity Log</a>
            <a href="/docs/api" class="wb-nav-link {{ $active === 'api' ? 'active' : '' }}">API Docs</a>
        </nav>

        <div class="wb-user">
            <div class="wb-user-meta">
                <span class="wb-user-name">{{ auth()->user()->name }}</span>
                <span class="wb-user-email">{{ auth()->user()->email }}</span>
            </div>
            <div class="wb-avatar" title="{{ auth()->user()->name }}">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-ghost">Sign out</button>
            </form>
        </div>
    </div>
</header>
