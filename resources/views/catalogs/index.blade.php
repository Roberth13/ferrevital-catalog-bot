<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-stone-800 leading-tight">
            Catálogos de Proveedores
        </h2>
        <p class="text-xs text-stone-500 mt-0.5">Sube catálogos PDF para procesar y consultar su historial</p>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Formulario de Subida -->
            <div class="bg-white p-5 sm:p-6 rounded-xl shadow-xs border border-stone-200">
                <h3 class="text-base font-bold text-stone-900 mb-4">Subir Nuevo Catálogo (PDF hasta 200MB)</h3>
                <form method="POST" action="{{ route('catalogs.store') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="supplier_name" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Nombre del Proveedor *</label>
                            <input type="text" name="supplier_name" id="supplier_name" required placeholder="Ej. Dong Cheng, INGCO, Jadever..." class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                        </div>

                        <div>
                            <label for="pdf" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Archivo PDF *</label>
                            <input type="file" name="pdf" id="pdf" accept="application/pdf" required class="mt-1 block w-full text-sm text-stone-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-800 hover:file:bg-amber-100 cursor-pointer">
                        </div>
                    </div>

                    <!-- Bloque Opciones de prueba -->
                    <div class="border border-dashed border-stone-300 rounded-lg p-4 bg-[#FAF8F5]">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-stone-500 mb-2">Opciones de prueba (Opcional)</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="first_page" class="block text-xs font-medium text-stone-600">Página inicial</label>
                                <input type="number" min="1" name="first_page" id="first_page" placeholder="Ej. 2" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs sm:text-sm px-3 py-1.5 border text-stone-900">
                            </div>
                            <div>
                                <label for="last_page" class="block text-xs font-medium text-stone-600">Página final</label>
                                <input type="number" min="1" name="last_page" id="last_page" placeholder="Ej. 5" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs sm:text-sm px-3 py-1.5 border text-stone-900">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-amber-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-wider hover:bg-amber-700 transition">
                            Subir y Procesar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de Catálogos -->
            <div class="bg-white rounded-xl shadow-xs border border-stone-200 overflow-hidden">
                <div class="p-4 border-b border-stone-200 flex justify-between items-center bg-[#FBF9F7]">
                    <h3 class="text-sm font-bold text-stone-800 uppercase tracking-wider">Historial de Catálogos</h3>
                    @if($hasActive)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 animate-pulse">
                            Actualizando cada 10 s...
                        </span>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200">
                        <thead class="bg-[#FBF9F7]">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Proveedor</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Archivo</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Estado</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Páginas</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Productos</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Costo USD</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Mensaje / Error</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-stone-100">
                            @forelse($catalogs as $catalog)
                                <tr class="hover:bg-[#FAF8F5] transition-colors">
                                    <td class="px-5 py-3.5 whitespace-nowrap text-xs text-stone-500">
                                        {{ $catalog->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm font-semibold text-stone-800">
                                        {{ $catalog->supplier?->name }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-xs text-stone-600 truncate max-w-xs" title="{{ $catalog->original_name }}">
                                        {{ $catalog->original_name }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm">
                                        @if($catalog->status === 'listo')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Listo
                                            </span>
                                        @elseif($catalog->status === 'procesando')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200 animate-pulse">
                                                Procesando
                                            </span>
                                        @elseif($catalog->status === 'pendiente')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-stone-100 text-stone-700 border border-stone-200">
                                                Pendiente
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                                Error
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-stone-700">
                                        {{ $catalog->total_pages ?? ($catalog->first_page ? "{$catalog->first_page}..{$catalog->last_page}" : '—') }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm font-bold text-stone-800">
                                        {{ $catalog->total_products ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-stone-800 font-mono">
                                        {{ $catalog->cost_usd ? '$' . number_format($catalog->cost_usd, 4) : '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-stone-500 max-w-sm">
                                        <div class="line-clamp-2" title="{{ $catalog->message }}">
                                            {{ $catalog->message ?: '—' }}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-stone-500 text-sm">
                                        No se ha subido ningún catálogo todavía.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
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
