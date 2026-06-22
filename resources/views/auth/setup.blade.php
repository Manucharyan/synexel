@extends('layouts.app')

@section('title', 'Install Synexel')

@section('content')
<div class="login-wrap">
    <div class="login-card login-card-wide">
        <div class="brand-logo">S</div>
        <h1 class="brand-title">Welcome to Synexel</h1>
        <p class="brand-tagline">Create the first administrator account to finish installation.</p>

        <div class="setup-steps">
            <div class="setup-step setup-step-done">1. Server ready</div>
            <div class="setup-step setup-step-active">2. Admin account</div>
            <div class="setup-step">3. Add users &amp; start</div>
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

        <form method="POST" action="{{ route('setup.store') }}" class="login-form">
            @csrf
            <div>
                <label for="name">Admin username</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="field" autocomplete="username" placeholder="e.g. admin">
                <p class="field-hint">Used to sign in (letters, numbers, . _ -)</p>
            </div>
            <div>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="field" autocomplete="email" placeholder="admin@company.com">
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required class="field" autocomplete="new-password" minlength="8">
                <p class="field-hint">Minimum 8 characters</p>
            </div>
            <div>
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="field" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Create administrator &amp; continue</button>
        </form>
    </div>
</div>
@endsection
