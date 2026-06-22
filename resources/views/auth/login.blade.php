@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
<div class="login-wrap">
    <div class="login-card">
        <div class="brand-logo">S</div>
        <h1 class="brand-title">Synexel</h1>
        <p class="brand-tagline">Cloud spreadsheet workspace</p>

        @if ($errors->any())
            <div class="error-box">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="login-form">
            @csrf
            <div>
                <label for="login">Username</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus class="field" autocomplete="username">
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required class="field" autocomplete="current-password">
            </div>
            <label class="remember-row">
                <input type="checkbox" name="remember"> Remember me
            </label>
            <button type="submit" class="btn btn-primary">Sign in to Synexel</button>
        </form>
        <p class="login-footer">
            API: <a href="/docs/api">Synexel API docs</a>
        </p>
    </div>
</div>
@endsection
