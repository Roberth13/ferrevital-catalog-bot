<nav class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo / Title -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('products.index') }}" class="font-bold text-xl text-gray-800">
                        Catálogos MVP
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <a href="{{ route('products.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ request()->routeIs('products.*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        Productos
                    </a>

                    @if(Auth::user()->isAdmin())
                        <a href="{{ route('catalogs.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ request()->routeIs('catalogs.*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            Catálogos
                        </a>

                        <a href="{{ route('suppliers.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ request()->routeIs('suppliers.*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                            Proveedores
                        </a>
                    @endif
                </div>
            </div>

            <!-- User Menu -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <span class="text-sm font-medium text-gray-700 mr-4">
                    {{ Auth::user()->name }} ({{ Auth::user()->role }})
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-red-600 hover:text-red-900">
                        Cerrar Sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
