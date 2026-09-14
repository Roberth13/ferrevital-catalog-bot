@extends('layouts.app')

@section('title', "Catálogo #{$catalog->id} | Ferrevital")

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <a href="{{ route('catalogs.index') }}" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">&larr; Volver a Catálogos</a>
            </div>
            <h1 style="font-size: 1.8rem; margin: 0; background: linear-gradient(to right, var(--primary), #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                Catálogo #{{ $catalog->id }} — {{ $catalog->original_filename }}
            </h1>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="{{ route('products.export', ['catalog_id' => $catalog->id]) }}" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                Exportar Catálogo
            </a>
            <a href="{{ route('products.index', ['catalog_id' => $catalog->id]) }}" class="btn btn-primary">
                Buscar en Inventario
            </a>
        </div>
    </div>

    @if(session('success'))
        <div style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 0.5rem; background: var(--success-bg); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2);">
            {{ session('success') }}
        </div>
    @endif

    <!-- Cards de Resumen -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div style="background: var(--card-bg); padding: 1.25rem; border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.25rem;">Proveedor</div>
            <div style="font-size: 1.2rem; font-weight: 700; color: var(--text-main);">
                {{ $catalog->supplier ? $catalog->supplier->name : 'Proveedor Genérico' }}
            </div>
        </div>

        <div style="background: var(--card-bg); padding: 1.25rem; border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.25rem;">Estado de Procesamiento</div>
            <div>
                @if($catalog->status === 'completed')
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; background: var(--success-bg); color: var(--success);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Completado
                    </span>
                @elseif($catalog->status === 'processing')
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; background: var(--warning-bg); color: var(--warning);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="animation: spin 1s linear infinite;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                        Procesando
                    </span>
                @elseif($catalog->status === 'pending')
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; background: var(--bg-color); color: var(--text-muted); border: 1px solid var(--border-color);">
                        Pendiente
                    </span>
                @else
                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; background: var(--error-bg); color: var(--error);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        Fallido
                    </span>
                @endif
            </div>
        </div>

        <div style="background: var(--card-bg); padding: 1.25rem; border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.25rem;">Total Productos Extraídos</div>
            <div style="font-size: 1.2rem; font-weight: 700; color: var(--text-main);">
                {{ $catalog->total_products }}
            </div>
        </div>

        <div style="background: var(--card-bg); padding: 1.25rem; border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.25rem;">Fecha de Subida</div>
            <div style="font-size: 1.1rem; font-weight: 600; color: var(--text-main);">
                {{ $catalog->created_at ? $catalog->created_at->format('d/m/Y H:i') : 'N/A' }}
            </div>
        </div>
    </div>

    @if($catalog->status === 'failed')
        <div style="margin-bottom: 2rem; padding: 1.25rem; border-radius: 0.75rem; background: var(--error-bg); color: var(--error); border: 1px solid rgba(239, 68, 68, 0.3);">
            <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; font-size: 1rem; margin-bottom: 0.25rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                No fue posible procesar el catálogo
            </div>
            <p style="margin: 0; font-size: 0.9rem; opacity: 0.9;">
                Ocurrió un error técnico durante la extracción o el documento PDF no pudo ser interpretado. Los administradores pueden verificar los registros del sistema.
            </p>
        </div>
    @endif

    <!-- Tabla de Productos Extraídos -->
    <div style="background: var(--card-bg); border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 1.1rem; margin: 0; color: var(--text-main);">Productos Extraídos ({{ $products->total() }})</h2>
        </div>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="background-color: var(--bg-color); border-bottom: 1px solid var(--border-color); color: var(--text-muted);">
                        <th style="padding: 0.75rem 1rem;">Código SKU</th>
                        <th style="padding: 0.75rem 1rem;">Nombre / Descripción</th>
                        <th style="padding: 0.75rem 1rem; text-align: right;">Precio ($)</th>
                        <th style="padding: 0.75rem 1rem; text-align: right;">Precio (Bs)</th>
                        <th style="padding: 0.75rem 1rem; text-align: center;">Método</th>
                        <th style="padding: 0.75rem 1rem; text-align: center;">Página</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.75rem 1rem; font-family: monospace; font-weight: 700; color: var(--primary);">
                                {{ $product->codigo }}
                            </td>
                            <td style="padding: 0.75rem 1rem; font-weight: 500; color: var(--text-main); max-width: 350px;">
                                {{ $product->nombre }}
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 600; color: var(--success);">
                                ${{ number_format($product->precio_divisa, 2) }}
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right; color: var(--text-muted);">
                                {{ $product->precio_bs ? 'Bs. ' . number_format($product->precio_bs, 2) : '—' }}
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center;">
                                <span style="display: inline-block; padding: 0.2rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-muted);">
                                    {{ $product->extraction_method ?? 'text' }}
                                </span>
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center; color: var(--text-muted);">
                                {{ $product->page_number ? 'Pág. ' . $product->page_number : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                                @if($catalog->status === 'processing')
                                    El catálogo se encuentra en procesamiento. Actualiza esta página en unos instantes.
                                @else
                                    No se encontraron productos registrados para este catálogo.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color);">
                {{ $products->links() }}
            </div>
        @endif
    </div>
@endsection
