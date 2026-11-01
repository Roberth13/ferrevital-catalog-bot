<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-stone-700">Correo electrónico</label>
            <input id="email" class="mt-1 block w-full rounded-md border-stone-300 shadow-xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm text-stone-900 px-3 py-2 border" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-stone-700">Contraseña</label>
            <input id="password" class="mt-1 block w-full rounded-md border-stone-300 shadow-xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm text-stone-900 px-3 py-2 border"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between pt-1">
            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                <input id="remember_me" type="checkbox" class="rounded border-stone-300 text-amber-600 shadow-xs focus:ring-amber-500" name="remember">
                <span class="ms-2 text-sm text-stone-600">Recordarme</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-xs text-amber-700 hover:text-amber-900 hover:underline" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <div class="pt-2">
            <button type="submit" class="w-full justify-center inline-flex items-center px-4 py-2.5 bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white font-medium text-sm rounded-lg shadow-sm transition duration-150 ease-in-out cursor-pointer">
                Iniciar Sesión
            </button>
        </div>
    </form>
</x-guest-layout>
