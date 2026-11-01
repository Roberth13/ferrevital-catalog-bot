<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-stone-800 leading-tight">
            Fórmulas y Precios de Venta
        </h2>
        <p class="text-xs text-stone-500 mt-0.5">Calcula y actualiza masivamente los precios de venta en divisa y bolívares</p>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

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

            <!-- Resumen de Catálogo -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                    <span class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Productos en Catálogo</span>
                    <p class="text-2xl font-bold text-stone-800 mt-1">{{ number_format($totalProducts) }}</p>
                </div>
                <div class="bg-white p-5 rounded-xl border border-stone-200 shadow-xs">
                    <span class="text-xs font-semibold uppercase tracking-wider text-stone-500">Productos con Precio de Venta Calculado</span>
                    <p class="text-2xl font-bold text-amber-700 mt-1">{{ number_format($productsWithSalePrice) }}</p>
                </div>
            </div>

            <!-- Formulario de Regla de Precios -->
            <div class="bg-white p-6 sm:p-8 rounded-xl border border-stone-200 shadow-xs">
                <h3 class="text-base font-bold text-stone-900 mb-4 pb-2 border-b border-stone-100">
                    Aplicar Fórmula a Productos
                </h3>

                <form method="POST" action="{{ route('pricing.apply') }}" class="space-y-6">
                    @csrf

                    <!-- 1. Alcance (Proveedor o Todos) -->
                    <div>
                        <label for="supplier_id" class="block text-xs font-semibold uppercase tracking-wider text-stone-600 mb-1">
                            1. ¿A qué proveedor aplicar?
                        </label>
                        <select name="supplier_id" id="supplier_id" class="block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2.5 border text-stone-900 bg-white">
                            <option value="">Todos los proveedores (Todo el inventario)</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->name }} ({{ $supplier->products_count }} productos)
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-stone-500 mt-1">Puedes aplicar la regla a una marca específica o a todo el catálogo al mismo tiempo.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <!-- 2. Precio Base -->
                        <div>
                            <label for="base_price" class="block text-xs font-semibold uppercase tracking-wider text-stone-600 mb-1">
                                2. Tomar como Costo Base:
                            </label>
                            <select name="base_price" id="base_price" class="block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2.5 border text-stone-900 bg-white">
                                <option value="divisa" {{ old('base_price') == 'divisa' ? 'selected' : '' }}>Precio Divisa (USD / Ref)</option>
                                <option value="bs" {{ old('base_price') == 'bs' ? 'selected' : '' }}>Precio en Bolívares (Bs)</option>
                            </select>
                        </div>

                        <!-- 3. Destino del Cálculo -->
                        <div>
                            <label for="target_price" class="block text-xs font-semibold uppercase tracking-wider text-stone-600 mb-1">
                                3. Actualizar en columna:
                            </label>
                            <select name="target_price" id="target_price" class="block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2.5 border text-stone-900 bg-white">
                                <option value="precio_venta_usd" {{ old('target_price') == 'precio_venta_usd' ? 'selected' : '' }}>Precio Venta USD (PVP $)</option>
                                <option value="precio_venta_bs" {{ old('target_price') == 'precio_venta_bs' ? 'selected' : '' }}>Precio Venta Bolívares (PVP Bs)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 4. Tipo de Fórmula -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="formula" class="block text-xs font-semibold uppercase tracking-wider text-stone-600 mb-1">
                                4. Tipo de Fórmula:
                            </label>
                            <select name="formula" id="formula" onchange="updateFormulaLabel()" class="block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2.5 border text-stone-900 bg-white">
                                <option value="margin_percent" {{ old('formula') == 'margin_percent' ? 'selected' : '' }}>Porcentaje de Ganancia (+ % Margen)</option>
                                <option value="markup_percent" {{ old('formula') == 'markup_percent' ? 'selected' : '' }}>Margen sobre precio final (Markup %)</option>
                                <option value="multiplier" {{ old('formula') == 'multiplier' ? 'selected' : '' }}>Multiplicador directo (Factor X)</option>
                                <option value="bcv_tasa" {{ old('formula') == 'bcv_tasa' ? 'selected' : '' }}>Tasa de Cambio BCV / Divisa a Bs</option>
                                <option value="fixed_add" {{ old('formula') == 'fixed_add' ? 'selected' : '' }}>Monto fijo adicional (+ Valor fijo)</option>
                            </select>
                        </div>

                        <!-- 5. Valor -->
                        <div>
                            <label for="value" id="valLabel" class="block text-xs font-semibold uppercase tracking-wider text-stone-600 mb-1">
                                Porcentaje / Valor a aplicar (%):
                            </label>
                            <input type="number" step="0.01" min="0" name="value" id="value" required value="{{ old('value', '30') }}" placeholder="Ej. 30" class="block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2.5 border text-stone-900">
                        </div>
                    </div>

                    <!-- Redondeo -->
                    <div class="pt-2">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="round_cents" value="1" {{ old('round_cents') ? 'checked' : '' }} class="rounded border-stone-300 text-amber-600 shadow-2xs focus:ring-amber-500">
                            <span class="ms-2 text-sm text-stone-700">Redondear al entero superior más cercano (eliminar centavos, ej. 12.30 -> 13.00)</span>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-stone-100 flex justify-between items-center">
                        <span class="text-xs text-stone-500">Los cambios se reflejarán instantáneamente en el buscador.</span>
                        <button type="submit" onclick="return confirm('¿Confirmas que deseas calcular y actualizar los precios de venta seleccionados?')" class="px-5 py-2.5 bg-amber-600 text-white font-semibold text-xs uppercase tracking-wider rounded-lg hover:bg-amber-700 transition">
                            Calcular y Actualizar Precios
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script>
        function updateFormulaLabel() {
            const f = document.getElementById('formula').value;
            const lbl = document.getElementById('valLabel');
            const target = document.getElementById('target_price');
            const base = document.getElementById('base_price');

            if (f === 'margin_percent') {
                lbl.innerText = 'Margen Porcentual a sumar (Ej. 35 para +35%):';
            } else if (f === 'markup_percent') {
                lbl.innerText = 'Margen deseado sobre venta (Ej. 30 para 30%):';
            } else if (f === 'multiplier') {
                lbl.innerText = 'Factor multiplicador (Ej. 1.35):';
            } else if (f === 'bcv_tasa') {
                lbl.innerText = 'Tasa de cambio (Bs por cada 1 USD):';
                base.value = 'divisa';
                target.value = 'precio_venta_bs';
            } else if (f === 'fixed_add') {
                lbl.innerText = 'Monto fijo a sumar:';
            }
        }
    </script>
</x-app-layout>

