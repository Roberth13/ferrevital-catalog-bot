<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-stone-800 leading-tight">
            Proveedores
        </h2>
        <p class="text-xs text-stone-500 mt-0.5">Configuración de condiciones comerciales, garantías y tiempos de entrega</p>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-xs border border-stone-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200">
                        <thead class="bg-[#FBF9F7]">
                            <tr>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Proveedor</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Tiempo de Entrega</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Garantía por Defecto</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Condiciones</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold text-stone-500 uppercase tracking-wider">Productos</th>
                                <th class="px-5 py-3.5 text-right text-xs font-semibold text-stone-500 uppercase tracking-wider">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-stone-100">
                            @forelse($suppliers as $supplier)
                                <tr class="hover:bg-[#FAF8F5] transition-colors">
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm font-bold text-stone-800">
                                        {{ $supplier->name }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-stone-600">
                                        {{ $supplier->tiempo_entrega ?: '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-stone-600">
                                        {{ $supplier->garantia_default ?: '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-stone-600 max-w-xs truncate" title="{{ $supplier->condiciones }}">
                                        {{ $supplier->condiciones ?: '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-sm font-medium text-stone-700">
                                        {{ $supplier->products_count }}
                                    </td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('suppliers.edit', $supplier) }}" class="text-amber-700 hover:text-amber-900 font-semibold">
                                            Editar
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-stone-500 text-sm">
                                        No hay proveedores registrados aún.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
