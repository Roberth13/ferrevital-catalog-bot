<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h2 class="font-bold text-xl text-stone-800 leading-tight">
                    Catálogo de Productos
                </h2>
                <p class="text-xs text-stone-500 mt-0.5">Explora y busca productos extraídos de los catálogos</p>
            </div>
            <a href="{{ route('products.export', request()->query()) }}" class="inline-flex items-center px-4 py-2 bg-stone-700 border border-transparent rounded-lg font-medium text-xs text-stone-100 uppercase tracking-wider hover:bg-stone-800 focus:outline-none transition shadow-xs">
                Exportar XLSX
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Filtros -->
            <div class="bg-white p-5 sm:p-6 rounded-xl shadow-xs border border-stone-200">
                <form id="filterForm" method="GET" action="{{ route('products.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label for="q" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Buscar (Código, Nombre, Detalle)</label>
                        <div class="relative mt-1">
                            <input type="text" name="q" id="q" value="{{ request('q') }}" placeholder="Escribe 3 o más letras..." autocomplete="off" class="block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900 pr-8">
                            <div id="searchSpinner" class="hidden absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none">
                                <svg class="animate-spin h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="supplier_id" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Proveedor</label>
                        <select name="supplier_id" id="supplier_id" onchange="document.getElementById('filterForm').submit()" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900 bg-white">
                            <option value="">Todos los proveedores</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center h-10">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="revisar" value="1" onchange="document.getElementById('filterForm').submit()" {{ request('revisar') ? 'checked' : '' }} class="rounded border-stone-300 text-amber-600 shadow-2xs focus:ring-amber-500">
                            <span class="ms-2 text-sm text-stone-700 font-medium">Solo para revisar</span>
                        </label>
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" class="flex-1 inline-flex justify-center items-center px-4 py-2.5 bg-amber-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-wider hover:bg-amber-700 transition">
                            Buscar
                        </button>
                        <a href="{{ route('products.index') }}" class="inline-flex items-center px-3 py-2.5 bg-stone-100 border border-stone-200 rounded-lg font-medium text-xs text-stone-700 hover:bg-stone-200 transition">
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabla de Productos -->
            <div id="tableContainer" class="bg-white rounded-xl shadow-xs border border-stone-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200">
                        <thead class="bg-[#FBF9F7]">
                            <tr>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Proveedor</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Código</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Variante</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Nombre</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Costo USD</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-amber-900 uppercase tracking-wider">PVP Venta USD</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Garantía</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Estado</th>
                                @if(Auth::user()->isAdmin())
                                    <th class="px-5 py-3.5 text-right text-xs font-semibold text-stone-500 uppercase tracking-wider">Acción</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-stone-100">
                            @forelse($products as $product)
                                <tr class="hover:bg-[#FAF8F5] transition-colors cursor-pointer" onclick="openProductModal({{ $product->id }})">
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm font-semibold text-stone-800">
                                        {{ $product->supplier?->name }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm font-mono text-stone-700">
                                        {{ $product->codigo }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-stone-500">
                                        {{ $product->variante ?: '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-sm text-stone-800 max-w-xs truncate" title="{{ $product->nombre }}">
                                        {{ $product->nombre }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-stone-600">
                                        {{ $product->precio_divisa ? '$' . number_format($product->precio_divisa, 2) : '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm font-bold text-amber-800 bg-amber-50/50">
                                        @if($product->precio_venta_usd)
                                            ${{ number_format($product->precio_venta_usd, 2) }}
                                        @elseif($product->precio_venta_bs)
                                            Bs. {{ number_format($product->precio_venta_bs, 2) }}
                                        @else
                                            <span class="text-stone-400 font-normal text-xs italic">Sin calcular</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-stone-500">
                                        {{ $product->garantia ?: '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm">
                                        @if($product->revisar)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200" title="{{ $product->revisar_motivo }}">
                                                Revisar
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                OK
                                            </span>
                                        @endif
                                    </td>
                                    @if(Auth::user()->isAdmin())
                                        <td class="px-5 py-3.5 whitespace-nowrap text-right text-sm font-medium" onclick="event.stopPropagation()">
                                            <a href="{{ route('products.edit', $product) }}" class="text-amber-700 hover:text-amber-900 font-semibold">
                                                Editar
                                            </a>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ Auth::user()->isAdmin() ? 8 : 7 }}" class="px-6 py-8 text-center text-stone-500 text-sm">
                                        No se encontraron productos con los criterios especificados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-stone-200 bg-[#FAF8F5]">
                    {{ $products->links() }}
                </div>
            </div>

        </div>
    </div>

    <!-- Modal Detalle Producto (8 datos + incluye) -->
    <div id="productModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-stone-900/50 transition-opacity" onclick="closeProductModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-lg transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-stone-200">
                <div class="bg-white px-6 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-start pb-3 border-b border-stone-200">
                        <h3 class="text-lg leading-6 font-bold text-stone-900" id="m_nombre">
                            Cargando...
                        </h3>
                        <button type="button" onclick="closeProductModal()" class="text-stone-400 hover:text-stone-600 text-2xl font-bold">&times;</button>
                    </div>

                    <div id="m_revisar_alert" class="mt-4 p-3 bg-amber-50 border-l-4 border-amber-500 text-amber-800 text-sm rounded-r hidden">
                        <strong>Motivo de revisión:</strong> <span id="m_revisar_motivo"></span>
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div class="bg-stone-50 p-3 rounded-lg border border-stone-100">
                            <span class="block text-xs font-semibold text-stone-500 uppercase tracking-wider">1. Código y Variante</span>
                            <span class="font-mono text-base font-bold text-stone-900" id="m_codigo"></span>
                            <span class="text-stone-600 block text-xs mt-0.5" id="m_variante"></span>
                        </div>

                        <div class="bg-stone-50 p-3 rounded-lg border border-stone-100">
                            <span class="block text-xs font-semibold text-stone-500 uppercase tracking-wider">2. Precios Costo vs Venta</span>
                            <div class="text-stone-700 text-xs">Costo USD: <span class="text-stone-900 font-medium" id="m_precio_divisa"></span></div>
                            <div class="text-stone-700 text-xs">Costo Bs: <span class="text-stone-900 font-medium" id="m_precio_bs"></span></div>
                            <div class="mt-1 pt-1 border-t border-stone-200">
                                <div class="text-amber-900 text-sm">PVP Venta USD: <strong class="font-bold text-amber-800" id="m_precio_venta_usd"></strong></div>
                                <div class="text-amber-900 text-sm">PVP Venta Bs: <strong class="font-bold text-amber-800" id="m_precio_venta_bs"></strong></div>
                            </div>
                            <div class="mt-1 text-xs text-stone-500">Otros: <span id="m_otros_precios" class="font-medium text-stone-700"></span></div>
                        </div>

                        <div class="bg-stone-50 p-3 rounded-lg border border-stone-100">
                            <span class="block text-xs font-semibold text-stone-500 uppercase tracking-wider">3. Garantía</span>
                            <span id="m_garantia" class="text-stone-800 font-medium"></span>
                        </div>

                        <div class="bg-stone-50 p-3 rounded-lg border border-stone-100">
                            <span class="block text-xs font-semibold text-stone-500 uppercase tracking-wider">4. Tiempo de Entrega</span>
                            <span id="m_tiempo_entrega" class="text-stone-800 font-medium"></span>
                        </div>

                        <div class="bg-stone-50 p-3 rounded-lg border border-stone-100 sm:col-span-2">
                            <span class="block text-xs font-semibold text-stone-500 uppercase tracking-wider">5. Condiciones del Proveedor</span>
                            <p id="m_condiciones" class="text-stone-800 whitespace-pre-line text-xs mt-1"></p>
                        </div>

                        <div class="bg-stone-50 p-3 rounded-lg border border-stone-100 sm:col-span-2">
                            <span class="block text-xs font-semibold text-stone-500 uppercase tracking-wider">6. Lo que Incluye</span>
                            <p id="m_incluye" class="text-stone-800 whitespace-pre-line text-xs mt-1"></p>
                        </div>

                        <div class="bg-stone-50 p-3 rounded-lg border border-stone-100 sm:col-span-2">
                            <span class="block text-xs font-semibold text-stone-500 uppercase tracking-wider">7. Descripción Técnica</span>
                            <p id="m_descripcion_tecnica" class="text-stone-800 whitespace-pre-line font-mono text-xs max-h-48 overflow-y-auto mt-1"></p>
                        </div>
                    </div>
                </div>

                <div class="bg-stone-50 px-6 py-3 flex justify-end border-t border-stone-200">
                    <button type="button" onclick="closeProductModal()" class="px-4 py-2 bg-stone-200 text-stone-800 rounded-lg hover:bg-stone-300 font-medium text-xs uppercase tracking-wider transition">
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
                    document.getElementById('m_precio_venta_usd').innerText = data.precio_venta_usd !== '—' ? `$${data.precio_venta_usd}` : 'Sin calcular';
                    document.getElementById('m_precio_venta_bs').innerText = data.precio_venta_bs !== '—' ? `Bs. ${data.precio_venta_bs}` : 'Sin calcular';
                    document.getElementById('m_otros_precios').innerText = data.otros_precios;
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

        // Búsqueda en vivo (a medida que escribe con mínimo 3 caracteres)
        const qInput = document.getElementById('q');
        const spinner = document.getElementById('searchSpinner');
        let searchTimer = null;

        qInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            const val = this.value.trim();

            if (val.length >= 3 || val.length === 0) {
                if (spinner) spinner.classList.remove('hidden');

                searchTimer = setTimeout(() => {
                    const form = document.getElementById('filterForm');
                    const params = new URLSearchParams(new FormData(form));
                    const targetUrl = `${form.action}?${params.toString()}`;

                    fetch(targetUrl, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(res => res.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newTable = doc.getElementById('tableContainer');
                        const currentTable = document.getElementById('tableContainer');
                        if (newTable && currentTable) {
                            currentTable.innerHTML = newTable.innerHTML;
                        }
                        window.history.replaceState({}, '', targetUrl);
                    })
                    .catch(err => {
                        console.error('Error en búsqueda:', err);
                    })
                    .finally(() => {
                        if (spinner) spinner.classList.add('hidden');
                    });
                }, 350);
            }
        });
    </script>
</x-app-layout>
