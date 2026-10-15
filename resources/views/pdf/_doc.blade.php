{{-- Shared shell of every PDF document. mPDF understands HTML 4 + a CSS 2 subset: no flexbox / grid, so layouts are tables. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>@yield('title', config('app.name'))</title>
<style>
    body { font-size: 11pt; color: #111827; margin: 0; padding: 0; }
    table { border-collapse: collapse; }
    td, th { vertical-align: top; }
    .center { text-align: center; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .muted { color: #64748b; }
    .nowrap { white-space: nowrap; }
    @yield('css')
</style>
</head>
<body>
@yield('body')
</body>
</html>
