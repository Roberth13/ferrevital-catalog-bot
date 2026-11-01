<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Catálogos') }}</title>

        <!-- Tailwind CSS CDN con paleta cálida y suave -->
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            warm: {
                                50: '#FAF8F5',
                                100: '#F4EFEA',
                                200: '#E8DED5',
                                300: '#D5C4B5',
                                600: '#8C6D58',
                                700: '#6E5343',
                                800: '#4A372D',
                            },
                            amberbrand: {
                                500: '#D97706',
                                600: '#B45309',
                                700: '#92400E',
                            }
                        }
                    }
                }
            }
        </script>
    </head>
    <body class="font-sans antialiased bg-[#FAF8F5] text-stone-800">
        <div class="min-h-screen">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-[#F4EFEA] border-b border-stone-200 shadow-sm">
                    <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
