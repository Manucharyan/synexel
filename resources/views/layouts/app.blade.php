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
    @if (auth()->check())
        <meta name="user-can-add-cells" content="{{ auth()->user()->isAdmin() || auth()->user()->can_add_cells ? '1' : '0' }}">
        <meta name="user-can-delete-cells" content="{{ auth()->user()->isAdmin() || auth()->user()->can_delete_cells ? '1' : '0' }}">
    @endif
    <title>@yield('title', 'Synexel') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=4">
</head>
<body>
    @yield('content')
    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
