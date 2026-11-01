<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Editar Producto: {{ $product->codigo }} {{ $product->variante }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 rounded-lg shadow-sm">

                @if ($errors->any())
                    <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('products.update', $product) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Proveedor</label>
                            <input type="text" disabled value="{{ $product->supplier?->name }}" class="mt-1 block w-full bg-gray-100 rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>

                        <div>
                            <label for="codigo" class="block text-sm font-medium text-gray-700">Código *</label>
                            <input type="text" name="codigo" id="codigo" required value="{{ old('codigo', $product->codigo) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="variante" class="block text-sm font-medium text-gray-700">Variante (dejar vacío si no aplica)</label>
                            <input type="text" name="variante" id="variante" value="{{ old('variante', $product->variante) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre *</label>
                            <input type="text" name="nombre" id="nombre" required value="{{ old('nombre', $product->nombre) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="precio_divisa" class="block text-sm font-medium text-gray-700">Precio USD (Divisa)</label>
                            <input type="number" step="0.01" name="precio_divisa" id="precio_divisa" value="{{ old('precio_divisa', $product->precio_divisa) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="precio_bs" class="block text-sm font-medium text-gray-700">Precio Bs</label>
                            <input type="number" step="0.01" name="precio_bs" id="precio_bs" value="{{ old('precio_bs', $product->precio_bs) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="otros_precios" class="block text-sm font-medium text-gray-700">Otros Precios (ej. BCV, mayor, detal...)</label>
                            <input type="text" name="otros_precios" id="otros_precios" value="{{ old('otros_precios', $product->otros_precios) }}" placeholder="etiqueta: valor; etiqueta: valor" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="garantia" class="block text-sm font-medium text-gray-700">Garantía</label>
                            <input type="text" name="garantia" id="garantia" value="{{ old('garantia', $product->garantia) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="pagina" class="block text-sm font-medium text-gray-700">Página del Catálogo</label>
                            <input type="number" name="pagina" id="pagina" value="{{ old('pagina', $product->pagina) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="incluye" class="block text-sm font-medium text-gray-700">Qué Incluye</label>
                        <textarea name="incluye" id="incluye" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('incluye', $product->incluye) }}</textarea>
                    </div>

                    <div>
                        <label for="descripcion_tecnica" class="block text-sm font-medium text-gray-700">Descripción Técnica / Especificaciones</label>
                        <textarea name="descripcion_tecnica" id="descripcion_tecnica" rows="6" class="mt-1 block w-full font-mono rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('descripcion_tecnica', $product->descripcion_tecnica) }}</textarea>
                    </div>

                    <div class="p-4 bg-amber-50 rounded border border-amber-200 text-sm text-amber-800">
                        <strong>Nota:</strong> Al guardar los cambios, el producto quedará marcado automáticamente como <strong>revisado</strong> (revisar = false).
                    </div>

                    <div class="flex justify-end space-x-3">
                        <a href="{{ route('products.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 font-medium text-sm">
                            Cancelar
                        </a>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium text-sm">
                            Guardar Producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

