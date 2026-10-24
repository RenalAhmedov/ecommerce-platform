<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (config('broadcasting.default') === 'reverb')
            <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
            <meta name="user-id" content="{{ auth()->id() }}">
        @endif

        <title>{{ config('app.name', 'Laravel') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{ $head ?? '' }}
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white shadow-sm">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main>
                @if (session('success'))
                    <div class="max-w-7xl mx-auto pt-6 px-4 sm:px-6 lg:px-8">
                        <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
                            {{ session('success') }}
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>

        {{-- Live order notifications (resources/js/live-orders.js) --}}
        <div id="toasts" class="fixed bottom-4 right-4 z-50 flex w-80 flex-col gap-2" aria-live="polite"></div>
    </body>
</html>
