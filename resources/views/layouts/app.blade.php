<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Goodgoods') }}</title>
    @viteReactRefresh
    @vite(['resources/js/app.js'])
</head>
<body>
    <div class="legacy-auth-shell">
        <header class="legacy-auth-header">
            <a class="wordmark" href="{{ route('store.home') }}">goodgoods<span>.</span></a>
            <nav aria-label="Account navigation">
                @guest
                    <a href="{{ route('login') }}">Sign in</a>
                    <a href="{{ route('register') }}">Create account</a>
                @else
                    <a href="{{ route('account') }}">My account</a>
                @endguest
            </nav>
        </header>
        <main class="legacy-auth-content">
            <section class="legacy-auth-card">
                @yield('content')
            </section>
        </main>
    </div>
</body>
</html>
