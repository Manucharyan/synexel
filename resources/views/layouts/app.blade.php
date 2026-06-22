<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4338ca">
    @if (session('api_token'))
        <meta name="api-token" content="{{ session('api_token') }}">
    @endif
    <title>@yield('title', 'Synexel') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=4">
</head>
<body>
    @yield('content')
    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
