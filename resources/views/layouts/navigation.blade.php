<nav class="bg-white border-b border-stone-200 shadow-xs" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <!-- Logo / Title -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('products.index') }}" class="font-bold text-xl text-stone-800 flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded-full bg-amber-600"></span>
                        Catálogos MVP
                    </a>
                </div>

                <!-- Navigation Links (Desktop) -->
                <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                    <a href="{{ route('products.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ request()->routeIs('products.*') ? 'border-amber-600 text-stone-900 font-semibold' : 'border-transparent text-stone-500 hover:text-stone-700 hover:border-stone-300' }}">
                        Productos
                    </a>

                    @if(Auth::user()->isAdmin())
                        <a href="{{ route('catalogs.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ request()->routeIs('catalogs.*') ? 'border-amber-600 text-stone-900 font-semibold' : 'border-transparent text-stone-500 hover:text-stone-700 hover:border-stone-300' }}">
                            Catálogos
                        </a>

                        <a href="{{ route('suppliers.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ request()->routeIs('suppliers.*') ? 'border-amber-600 text-stone-900 font-semibold' : 'border-transparent text-stone-500 hover:text-stone-700 hover:border-stone-300' }}">
                            Proveedores
                        </a>

                        <a href="{{ route('pricing.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ request()->routeIs('pricing.*') ? 'border-amber-600 text-stone-900 font-semibold' : 'border-transparent text-stone-500 hover:text-stone-700 hover:border-stone-300' }}">
                            Precios de Venta
                        </a>
                    @endif
                </div>
            </div>

            <!-- User Menu (Desktop) -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <span class="text-sm font-medium text-stone-600 mr-4 px-2.5 py-1 bg-stone-100 rounded-md border border-stone-200">
                    {{ Auth::user()->name }} <span class="text-xs text-stone-400">({{ Auth::user()->role }})</span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-amber-700 hover:text-amber-900 px-3 py-1.5 rounded-md hover:bg-stone-100 transition">
                        Cerrar Sesión
                    </button>
                </form>
            </div>

            <!-- Mobile Hamburger Button -->
            <div class="-me-2 flex items-center sm:hidden">
                <button type="button" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="inline-flex items-center justify-center p-2 rounded-md text-stone-500 hover:text-stone-700 hover:bg-stone-100 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden sm:hidden border-t border-stone-200 bg-stone-50 px-4 pt-2 pb-3 space-y-1">
        <a href="{{ route('products.index') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('products.*') ? 'bg-stone-200 text-stone-900 font-semibold' : 'text-stone-600 hover:bg-stone-100' }}">
            Productos
        </a>
        @if(Auth::user()->isAdmin())
            <a href="{{ route('catalogs.index') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('catalogs.*') ? 'bg-stone-200 text-stone-900 font-semibold' : 'text-stone-600 hover:bg-stone-100' }}">
                Catálogos
            </a>
            <a href="{{ route('suppliers.index') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('suppliers.*') ? 'bg-stone-200 text-stone-900 font-semibold' : 'text-stone-600 hover:bg-stone-100' }}">
                Proveedores
            </a>
            <a href="{{ route('pricing.index') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('pricing.*') ? 'bg-stone-200 text-stone-900 font-semibold' : 'text-stone-600 hover:bg-stone-100' }}">
                Precios de Venta
            </a>
        @endif
        <div class="pt-3 border-t border-stone-200 flex justify-between items-center px-3">
            <span class="text-sm font-medium text-stone-600">
                {{ Auth::user()->name }} ({{ Auth::user()->role }})
            </span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm font-medium text-amber-700 hover:text-amber-900">
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </div>
</nav>
