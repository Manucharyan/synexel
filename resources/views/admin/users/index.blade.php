@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div id="admin-users-app" class="wb-hub">
    @include('partials.app-header', ['active' => 'users'])

    <main class="wb-main">
        <section class="wb-hero wb-hero-compact">
            <div class="wb-hero-copy">
                <h1>User management</h1>
                <p>Add accounts and control whether users can add or delete cell data in workbooks.</p>
            </div>
        </section>

        @if ($status)
            <div class="alert-success">{{ $status }}</div>
        @endif

        @if ($errors->has('capabilities'))
            <div class="error-box">{{ $errors->first('capabilities') }}</div>
        @endif

        <div class="admin-capability-legend">
            <span><strong>Can add</strong> — type, paste, import, edit cells</span>
            <span><strong>Can delete</strong> — clear cells, delete rows/columns</span>
        </div>

        <div class="admin-grid">
            <section class="admin-panel">
                <h2 class="admin-panel-title">Add user</h2>
                <form method="POST" action="{{ route('admin.users.store') }}" class="admin-form">
                    @csrf
                    <div class="admin-form-row">
                        <div>
                            <label for="name">Username</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" required class="field" autocomplete="off" placeholder="jane.doe">
                        </div>
                        <div>
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required class="field" placeholder="jane@company.com">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <div>
                            <label for="password">Password</label>
                            <input id="password" name="password" type="password" required class="field" minlength="8">
                        </div>
                        <div>
                            <label for="password_confirmation">Confirm password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" required class="field">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <div>
                            <label for="role">Role</label>
                            <select id="role" name="role" class="field">
                                <option value="user" @selected(old('role', 'user') === 'user')>User</option>
                                <option value="admin" @selected(old('role') === 'admin')>Administrator</option>
                            </select>
                        </div>
                        <div class="admin-form-actions">
                            <button type="submit" class="btn btn-primary">Create user</button>
                        </div>
                    </div>
                    @if ($errors->any())
                        <div class="error-box">
                            <ul class="error-list">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </form>
            </section>

            <section class="admin-panel admin-panel-wide">
                <h2 class="admin-panel-title">All users ({{ $users->count() }})</h2>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Cell permissions</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>
                                        <strong>{{ $user->name }}</strong>
                                        <span class="admin-muted">{{ $user->email }}</span>
                                    </td>
                                    <td>
                                        <span class="role-badge role-badge-{{ $user->role->value }}">{{ $user->role->label() }}</span>
                                    </td>
                                    <td>
                                        @if ($user->isAdmin())
                                            <span class="perm-badge perm-write">Full access</span>
                                        @elseif (auth()->id() !== $user->id)
                                            <form method="POST" action="{{ route('admin.users.capabilities', $user) }}" class="capability-form">
                                                @csrf
                                                @method('PATCH')
                                                <label class="cap-check">
                                                    <input type="hidden" name="can_add_cells" value="0">
                                                    <input type="checkbox" name="can_add_cells" value="1" @checked($user->can_add_cells) onchange="this.form.submit()">
                                                    Can add
                                                </label>
                                                <label class="cap-check">
                                                    <input type="hidden" name="can_delete_cells" value="0">
                                                    <input type="checkbox" name="can_delete_cells" value="1" @checked($user->can_delete_cells) onchange="this.form.submit()">
                                                    Can delete
                                                </label>
                                            </form>
                                        @else
                                            <span class="admin-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($user->isActive())
                                            <span class="status-badge status-active">Active</span>
                                        @else
                                            <span class="status-badge status-inactive">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="admin-muted">{{ $user->created_at->format('M j, Y') }}</td>
                                    <td class="admin-actions">
                                        @if (auth()->id() !== $user->id)
                                            <form method="POST" action="{{ route('admin.users.toggle', $user) }}" class="inline-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="is_active" value="{{ $user->isActive() ? '0' : '1' }}">
                                                <button type="submit" class="btn btn-secondary btn-sm">
                                                    {{ $user->isActive() ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline-form" onsubmit="return confirm('Delete user {{ $user->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        @else
                                            <span class="admin-muted">You</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>
@endsection
