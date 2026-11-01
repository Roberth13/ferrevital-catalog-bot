<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Catálogos') }}</title>

        <!-- Tailwind CSS CDN -->
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
                                600: '#8C6D58',
                                800: '#4A372D',
                            }
                        }
                    }
                }
            }
        </script>
    </head>
    <body class="font-sans text-stone-800 antialiased bg-[#FAF8F5]">
        <div class="min-h-screen flex flex-col justify-center items-center px-4 py-8">
            <div class="w-full sm:max-w-md bg-white p-8 rounded-xl shadow-sm border border-stone-200">
                <div class="mb-6 text-center">
                    <h1 class="text-2xl font-bold tracking-tight text-stone-800">Catálogos de Proveedores</h1>
                    <p class="text-sm text-stone-500 mt-1">Inicia sesión para continuar</p>
                </div>
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
