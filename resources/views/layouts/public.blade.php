<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <nav class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex items-center gap-6">
                            <a href="/" class="font-semibold text-gray-800 dark:text-gray-200">{{ config('app.name', 'Laravel') }}</a>
                            <a href="{{ route('events.index') }}" class="text-sm text-gray-600 dark:text-gray-300 underline">Katalog Event</a>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            @auth
                                <a href="{{ route('dashboard') }}" class="underline">Dasbor</a>
                            @else
                                <a href="{{ route('login') }}" class="underline">Masuk</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="underline">Daftar</a>
                                @endif
                            @endauth
                        </div>
                    </div>
                </div>
            </nav>

            @if (isset($header))
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
