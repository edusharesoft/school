@php
    $brandColor = isset($currentBranch) && $currentBranch ? $currentBranch->brandColor() : (auth()->user()?->institution?->primary_color ?? '#1f6f5c');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ isset($currentBranch) && $currentBranch ? $currentBranch->displayName() : config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script>
        try { const t = localStorage.getItem('theme'); if (t) document.documentElement.setAttribute('data-bs-theme', t); } catch (e) {}
    </script>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body style="--brand: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) $brandColor) ? $brandColor : '#1f6f5c' }}" class="@yield('body-class')">
    @yield('body')

    @include('partials.toasts')
    @include('partials.confirm-modal')
    @stack('scripts')
</body>
</html>
