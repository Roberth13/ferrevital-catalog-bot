<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-stone-800 leading-tight">
            Editar Producto: {{ $product->codigo }} {{ $product->variante }}
        </h2>
        <p class="text-xs text-stone-500 mt-0.5">Modificación de información técnica y precios</p>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded-xl shadow-xs border border-stone-200">

                @if ($errors->any())
                    <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg text-sm">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('products.update', $product) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Proveedor</label>
                            <input type="text" disabled value="{{ $product->supplier?->name }}" class="mt-1 block w-full bg-stone-100 rounded-lg border-stone-200 shadow-2xs sm:text-sm px-3 py-2 border text-stone-600 cursor-not-allowed">
                        </div>

                        <div>
                            <label for="codigo" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Código *</label>
                            <input type="text" name="codigo" id="codigo" required value="{{ old('codigo', $product->codigo) }}" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                        </div>

                        <div>
                            <label for="variante" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Variante (opcional)</label>
                            <input type="text" name="variante" id="variante" value="{{ old('variante', $product->variante) }}" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                        </div>

                        <div>
                            <label for="nombre" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Nombre *</label>
                            <input type="text" name="nombre" id="nombre" required value="{{ old('nombre', $product->nombre) }}" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                        </div>

                        <div>
                            <label for="precio_divisa" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Costo USD (Divisa)</label>
                            <input type="number" step="0.01" name="precio_divisa" id="precio_divisa" value="{{ old('precio_divisa', $product->precio_divisa) }}" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                        </div>

                        <div>
                            <label for="precio_venta_usd" class="block text-xs font-semibold uppercase tracking-wider text-amber-800">Precio Venta PVP USD ($)</label>
                            <input type="number" step="0.01" name="precio_venta_usd" id="precio_venta_usd" value="{{ old('precio_venta_usd', $product->precio_venta_usd) }}" placeholder="Calculado o manual" class="mt-1 block w-full rounded-lg border-amber-300 bg-amber-50/30 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900 font-bold">
                        </div>

                        <div>
                            <label for="precio_bs" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Costo Bs</label>
                            <input type="number" step="0.01" name="precio_bs" id="precio_bs" value="{{ old('precio_bs', $product->precio_bs) }}" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                        </div>

                        <div>
                            <label for="precio_venta_bs" class="block text-xs font-semibold uppercase tracking-wider text-amber-800">Precio Venta PVP Bs</label>
                            <input type="number" step="0.01" name="precio_venta_bs" id="precio_venta_bs" value="{{ old('precio_venta_bs', $product->precio_venta_bs) }}" placeholder="Calculado o manual" class="mt-1 block w-full rounded-lg border-amber-300 bg-amber-50/30 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900 font-bold">
                        </div>

                        <div>
                            <label for="otros_precios" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Otros Precios (ej. BCV, mayor, detal...)</label>
                            <input type="text" name="otros_precios" id="otros_precios" value="{{ old('otros_precios', $product->otros_precios) }}" placeholder="etiqueta: valor; etiqueta: valor" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                        </div>

                        <div>
                            <label for="condiciones_proveedor" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Condiciones del Producto</label>
                            <input type="text" name="condiciones_proveedor" id="condiciones_proveedor" value="{{ old('condiciones_proveedor', $product->condiciones_proveedor) }}" placeholder="Min: 10 PZA; EMP.MAYOR: 2000" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                        </div>

                        <div>
                            <label for="garantia" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Garantía</label>
                            <input type="text" name="garantia" id="garantia" value="{{ old('garantia', $product->garantia) }}" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                        </div>

                        <div>
                            <label for="pagina" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Página del Catálogo</label>
                            <input type="number" name="pagina" id="pagina" value="{{ old('pagina', $product->pagina) }}" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                        </div>
                    </div>

                    <div>
                        <label for="incluye" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Qué Incluye</label>
                        <textarea name="incluye" id="incluye" rows="2" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">{{ old('incluye', $product->incluye) }}</textarea>
                    </div>

                    <div>
                        <label for="descripcion_tecnica" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Descripción Técnica / Especificaciones</label>
                        <textarea name="descripcion_tecnica" id="descripcion_tecnica" rows="5" class="mt-1 block w-full font-mono rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">{{ old('descripcion_tecnica', $product->descripcion_tecnica) }}</textarea>
                    </div>

                    <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-900">
                        <strong>Nota:</strong> Al guardar los cambios, el producto quedará marcado automáticamente como <strong>revisado</strong> (revisar = false).
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <a href="{{ route('products.index') }}" class="px-4 py-2.5 bg-stone-100 text-stone-700 rounded-lg hover:bg-stone-200 font-semibold text-xs uppercase tracking-wider transition">
                            Cancelar
                        </a>
                        <button type="submit" class="px-5 py-2.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-semibold text-xs uppercase tracking-wider transition">
                            Guardar Producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

