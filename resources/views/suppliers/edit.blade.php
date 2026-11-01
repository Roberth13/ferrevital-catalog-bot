<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Editar Proveedor: {{ $supplier->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
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

                <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre del Proveedor</label>
                        <input type="text" disabled value="{{ $supplier->name }}" class="mt-1 block w-full bg-gray-100 rounded-md border-gray-300 shadow-sm sm:text-sm">
                    </div>

                    <div>
                        <label for="tiempo_entrega" class="block text-sm font-medium text-gray-700">Tiempo de Entrega</label>
                        <input type="text" name="tiempo_entrega" id="tiempo_entrega" value="{{ old('tiempo_entrega', $supplier->tiempo_entrega) }}" placeholder="Ej. 24 a 48 horas / Inmediato" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="garantia_default" class="block text-sm font-medium text-gray-700">Garantía por Defecto</label>
                        <input type="text" name="garantia_default" id="garantia_default" value="{{ old('garantia_default', $supplier->garantia_default) }}" placeholder="Ej. 1 Año de garantía en centros autorizados" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>

                    <div>
                        <label for="condiciones" class="block text-sm font-medium text-gray-700">Condiciones de Venta / Pago / Mínimos</label>
                        <textarea name="condiciones" id="condiciones" rows="4" placeholder="Ej. Pago contra entrega, pedido mínimo $50..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('condiciones', $supplier->condiciones) }}</textarea>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <a href="{{ route('suppliers.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 font-medium text-sm">
                            Cancelar
                        </a>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium text-sm">
                            Guardar Cambios
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>

