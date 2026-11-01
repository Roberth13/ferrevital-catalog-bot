<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Catálogos de Proveedores
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 bg-emerald-100 border border-emerald-400 text-emerald-800 rounded">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Formulario de Subida -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Subir Nuevo Catálogo (PDF hasta 200MB)</h3>
                <form method="POST" action="{{ route('catalogs.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="supplier_name" class="block text-sm font-medium text-gray-700">Nombre del Proveedor *</label>
                            <input type="text" name="supplier_name" id="supplier_name" required placeholder="Ej. Dong Cheng, INGCO, Jadever..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="pdf" class="block text-sm font-medium text-gray-700">Archivo PDF *</label>
                            <input type="file" name="pdf" id="pdf" accept="application/pdf" required class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        </div>
                    </div>

                    <!-- Bloque Opciones de prueba -->
                    <div class="border border-dashed border-gray-300 rounded p-4 bg-gray-50">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-600 mb-2">Opciones de prueba (Opcional)</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="first_page" class="block text-xs font-medium text-gray-600">Página inicial</label>
                                <input type="number" min="1" name="first_page" id="first_page" placeholder="Ej. 2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                            <div>
                                <label for="last_page" class="block text-xs font-medium text-gray-600">Página final</label>
                                <input type="number" min="1" name="last_page" id="last_page" placeholder="Ej. 5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                            Subir y Procesar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de Catálogos -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-gray-800">Catálogos Registrados</h3>
                    @if($hasActive)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 animate-pulse">
                            Actualizando cada 10 s...
                        </span>
                    @endif
                </div>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archivo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Páginas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Productos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Costo USD</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mensaje / Error</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($catalogs as $catalog)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                    {{ $catalog->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $catalog->supplier?->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600 truncate max-w-xs" title="{{ $catalog->original_name }}">
                                    {{ $catalog->original_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($catalog->status === 'listo')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Listo
                                        </span>
                                    @elseif($catalog->status === 'procesando')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 animate-pulse">
                                            Procesando
                                        </span>
                                    @elseif($catalog->status === 'pendiente')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Pendiente
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Error
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ $catalog->total_pages ?? ($catalog->first_page ? "{$catalog->first_page}..{$catalog->last_page}" : '—') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    {{ $catalog->total_products ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                                    {{ $catalog->cost_usd ? '$' . number_format($catalog->cost_usd, 4) : '—' }}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-600 max-w-sm">
                                    <div class="line-clamp-2" title="{{ $catalog->message }}">
                                        {{ $catalog->message ?: '—' }}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    No se ha subido ningún catálogo todavía.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    @if($hasActive)
        <script>
            setTimeout(() => {
                window.location.reload();
            }, 10000);
        </script>
    @endif
</x-app-layout>
