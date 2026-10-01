<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'ORWeMS')) — Orphans Registration and Welfare Management System</title>

    <!-- Meta Description for Humanitarian Platform -->
    <meta name="description" content="@yield('meta_description', 'ORWeMS — Humanitarian information-management and welfare decision-support platform for WICHOV.')">

    <!-- Styles & Scripts via Vite -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="@yield('body-class', 'bg-light')">
    <div id="app">
        @yield('content')
    </div>

    @stack('scripts')
</body>
</html>
