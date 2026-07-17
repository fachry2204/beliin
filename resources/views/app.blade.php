<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $companyName = data_get($page, 'props.company.name', config('app.name', 'Sistem Invoice'));
            $appIcon = data_get($page, 'props.company.favicon_url') ?: data_get($page, 'props.company.logo_url') ?: asset('favicon.ico');
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0ea5e9">
        <meta name="application-name" content="{{ $companyName }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ $companyName }}">

        <title inertia>{{ $companyName }}</title>
        <link rel="icon" href="{{ $appIcon }}">
        <link rel="apple-touch-icon" href="{{ $appIcon }}">
        <link rel="manifest" href="{{ route('app.manifest') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
