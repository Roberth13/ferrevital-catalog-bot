<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Productos
            </h2>
            <a href="{{ route('products.export', request()->query()) }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-500 focus:outline-none transition ease-in-out duration-150">
                Exportar CSV
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 bg-emerald-100 border border-emerald-400 text-emerald-800 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Filtros -->
            <div class="bg-white p-6 rounded-lg shadow-sm">
                <form method="GET" action="{{ route('products.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label for="q" class="block text-sm font-medium text-gray-700">Buscar (Código o Nombre)</label>
                        <input type="text" name="q" id="q" value="{{ request('q') }}" placeholder="Ej. DCPL208 o taladro" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700">Proveedor</label>
                        <select name="supplier_id" id="supplier_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">Todos los proveedores</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center h-10">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="revisar" value="1" {{ request('revisar') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            <span class="ms-2 text-sm text-gray-600 font-medium">Solo para revisar</span>
                        </label>
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                            Filtrar
                        </button>
                        <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabla de Productos -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variante</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio USD</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Garantía</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            @if(Auth::user()->isAdmin())
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($products as $product)
                            <tr class="hover:bg-gray-50 cursor-pointer" onclick="openProductModal({{ $product->id }})">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $product->supplier?->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                    {{ $product->codigo }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $product->variante ?: '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate" title="{{ $product->nombre }}">
                                    {{ $product->nombre }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    {{ $product->precio_divisa ? '$' . number_format($product->precio_divisa, 2) : '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $product->garantia ?: '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($product->revisar)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800" title="{{ $product->revisar_motivo }}">
                                            Revisar
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            OK
                                        </span>
                                    @endif
                                </td>
                                @if(Auth::user()->isAdmin())
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" onclick="event.stopPropagation()">
                                        <a href="{{ route('products.edit', $product) }}" class="text-indigo-600 hover:text-indigo-900">
                                            Editar
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ Auth::user()->isAdmin() ? 8 : 7 }}" class="px-6 py-8 text-center text-gray-500">
                                    No se encontraron productos con los criterios especificados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="p-4 border-t border-gray-200">
                    {{ $products->links() }}
                </div>
            </div>

        </div>
    </div>

    <!-- Modal Detalle Producto (8 datos + incluye) -->
    <div id="productModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeProductModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white px-6 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start pb-3 border-b border-gray-200">
                        <h3 class="text-lg leading-6 font-bold text-gray-900" id="m_nombre">
                            Cargando...
                        </h3>
                        <button type="button" onclick="closeProductModal()" class="text-gray-400 hover:text-gray-500 text-2xl font-bold">&times;</button>
                    </div>

                    <div id="m_revisar_alert" class="mt-4 p-3 bg-amber-50 border-l-4 border-amber-400 text-amber-800 text-sm hidden">
                        <strong>Motivo de revisión:</strong> <span id="m_revisar_motivo"></span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        <div class="bg-gray-50 p-3 rounded">
                            <span class="block text-xs font-semibold text-gray-500 uppercase">1. Código y Variante</span>
                            <span class="font-mono text-base font-bold text-gray-900" id="m_codigo"></span>
                            <span class="text-gray-600 block text-xs" id="m_variante"></span>
                        </div>

                        <div class="bg-gray-50 p-3 rounded">
                            <span class="block text-xs font-semibold text-gray-500 uppercase">2. Precios</span>
                            <div>USD: <strong class="text-indigo-600" id="m_precio_divisa"></strong></div>
                            <div>Bs: <strong class="text-gray-800" id="m_precio_bs"></strong></div>
                        </div>

                        <div class="bg-gray-50 p-3 rounded">
                            <span class="block text-xs font-semibold text-gray-500 uppercase">3. Garantía</span>
                            <span id="m_garantia" class="text-gray-800"></span>
                        </div>

                        <div class="bg-gray-50 p-3 rounded">
                            <span class="block text-xs font-semibold text-gray-500 uppercase">4. Tiempo de Entrega</span>
                            <span id="m_tiempo_entrega" class="text-gray-800"></span>
                        </div>

                        <div class="bg-gray-50 p-3 rounded col-span-2">
                            <span class="block text-xs font-semibold text-gray-500 uppercase">5. Condiciones del Proveedor</span>
                            <p id="m_condiciones" class="text-gray-800 whitespace-pre-line text-xs"></p>
                        </div>

                        <div class="bg-gray-50 p-3 rounded col-span-2">
                            <span class="block text-xs font-semibold text-gray-500 uppercase">6. Lo que Incluye</span>
                            <p id="m_incluye" class="text-gray-800 whitespace-pre-line"></p>
                        </div>

                        <div class="bg-gray-50 p-3 rounded col-span-2">
                            <span class="block text-xs font-semibold text-gray-500 uppercase">7. Descripción Técnica</span>
                            <p id="m_descripcion_tecnica" class="text-gray-800 whitespace-pre-line font-mono text-xs max-h-48 overflow-y-auto"></p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-3 flex justify-end">
                    <button type="button" onclick="closeProductModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 font-medium text-sm">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openProductModal(id) {
            fetch(`/products/${id}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('m_nombre').innerText = data.nombre;
                    document.getElementById('m_codigo').innerText = data.codigo;
                    document.getElementById('m_variante').innerText = data.variante ? `Variante: ${data.variante}` : '';
                    document.getElementById('m_precio_divisa').innerText = data.precio_divisa !== '—' ? `$${data.precio_divisa}` : '—';
                    document.getElementById('m_precio_bs').innerText = data.precio_bs !== '—' ? `Bs. ${data.precio_bs}` : '—';
                    document.getElementById('m_garantia').innerText = data.garantia;
                    document.getElementById('m_tiempo_entrega').innerText = data.tiempo_entrega;
                    document.getElementById('m_condiciones').innerText = data.condiciones_proveedor;
                    document.getElementById('m_incluye').innerText = data.incluye;
                    document.getElementById('m_descripcion_tecnica').innerText = data.descripcion_tecnica;

                    const revAlert = document.getElementById('m_revisar_alert');
                    if (data.revisar) {
                        revAlert.classList.remove('hidden');
                        document.getElementById('m_revisar_motivo').innerText = data.revisar_motivo || 'Marcado para revisión';
                    } else {
                        revAlert.classList.add('hidden');
                    }

                    document.getElementById('productModal').classList.remove('hidden');
                });
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
        }
    </script>
</x-app-layout>
