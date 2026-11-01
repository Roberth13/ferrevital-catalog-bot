<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-stone-800 leading-tight">
            Editar Proveedor: {{ $supplier->name }}
        </h2>
        <p class="text-xs text-stone-500 mt-0.5">Actualiza las condiciones comerciales predeterminadas</p>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
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

                <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Nombre del Proveedor</label>
                        <input type="text" disabled value="{{ $supplier->name }}" class="mt-1 block w-full bg-stone-100 rounded-lg border-stone-200 shadow-2xs sm:text-sm px-3 py-2 border text-stone-600 cursor-not-allowed">
                    </div>

                    <div>
                        <label for="tiempo_entrega" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Tiempo de Entrega</label>
                        <input type="text" name="tiempo_entrega" id="tiempo_entrega" value="{{ old('tiempo_entrega', $supplier->tiempo_entrega) }}" placeholder="Ej. 24 a 48 horas / Inmediato" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                    </div>

                    <div>
                        <label for="garantia_default" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Garantía por Defecto</label>
                        <input type="text" name="garantia_default" id="garantia_default" value="{{ old('garantia_default', $supplier->garantia_default) }}" placeholder="Ej. 1 Año de garantía en centros autorizados" class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">
                    </div>

                    <div>
                        <label for="condiciones" class="block text-xs font-semibold uppercase tracking-wider text-stone-600">Condiciones de Venta / Pago / Mínimos</label>
                        <textarea name="condiciones" id="condiciones" rows="4" placeholder="Ej. Pago contra entrega, pedido mínimo $50..." class="mt-1 block w-full rounded-lg border-stone-300 shadow-2xs focus:border-amber-600 focus:ring-amber-600 sm:text-sm px-3 py-2 border text-stone-900">{{ old('condiciones', $supplier->condiciones) }}</textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <a href="{{ route('suppliers.index') }}" class="px-4 py-2.5 bg-stone-100 text-stone-700 rounded-lg hover:bg-stone-200 font-semibold text-xs uppercase tracking-wider transition">
                            Cancelar
                        </a>
                        <button type="submit" class="px-5 py-2.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-semibold text-xs uppercase tracking-wider transition">
                            Guardar Cambios
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>

