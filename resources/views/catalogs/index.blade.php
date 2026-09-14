@extends('layouts.app')

@section('title', 'Catálogos | Ferrevital')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.8rem; margin: 0; background: linear-gradient(to right, var(--primary), #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                Catálogos Subidos
            </h1>
            <p style="color: var(--text-muted); margin: 0.25rem 0 0;">Historial y estado de procesamiento de los documentos PDF.</p>
        </div>
        <a href="{{ route('catalogs.create') }}" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
            Subir Catálogo
        </a>
    </div>

    @if(session('success'))
        <div style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 0.5rem; background: var(--success-bg); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2);">
            {{ session('success') }}
        </div>
    @endif

    <div style="background: var(--card-bg); border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="background-color: var(--bg-color); border-bottom: 1px solid var(--border-color); color: var(--text-muted);">
                        <th style="padding: 0.75rem 1rem;">ID</th>
                        <th style="padding: 0.75rem 1rem;">Proveedor</th>
                        <th style="padding: 0.75rem 1rem;">Archivo</th>
                        <th style="padding: 0.75rem 1rem;">Fecha</th>
                        <th style="padding: 0.75rem 1rem; text-align: center;">Estado</th>
                        <th style="padding: 0.75rem 1rem; text-align: center;">Productos</th>
                        <th style="padding: 0.75rem 1rem; text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($catalogs as $catalog)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 0.75rem 1rem; color: var(--text-muted); font-size: 0.85rem;">#{{ $catalog->id }}</td>
                            <td style="padding: 0.75rem 1rem; font-weight: 600; color: var(--text-main);">
                                {{ $catalog->supplier ? $catalog->supplier->name : 'Proveedor Genérico' }}
                            </td>
                            <td style="padding: 0.75rem 1rem; color: var(--text-main); font-weight: 500;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color: var(--error);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                    {{ $catalog->original_filename }}
                                </div>
                            </td>
                            <td style="padding: 0.75rem 1rem; color: var(--text-muted); font-size: 0.85rem;">
                                {{ $catalog->created_at ? $catalog->created_at->format('d/m/Y H:i') : 'N/A' }}
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center;">
                                @if($catalog->status === 'completed')
                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; background: var(--success-bg); color: var(--success);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        Completado
                                    </span>
                                @elseif($catalog->status === 'processing')
                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; background: var(--warning-bg); color: var(--warning);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="animation: spin 1s linear infinite;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                        Procesando
                                    </span>
                                @elseif($catalog->status === 'pending')
                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; background: var(--bg-color); color: var(--text-muted); border: 1px solid var(--border-color);">
                                        Pendiente
                                    </span>
                                @else
                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; background: var(--error-bg); color: var(--error);" title="No fue posible procesar el catálogo.">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        Fallido
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 600;">
                                {{ $catalog->total_products }}
                            </td>
                            <td style="padding: 0.75rem 1rem; text-align: right;">
                                <a href="{{ route('catalogs.show', $catalog) }}" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                                    Ver Detalle
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 2.5rem; text-align: center; color: var(--text-muted);">
                                No se ha subido ningún catálogo todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($catalogs->hasPages())
            <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color);">
                {{ $catalogs->links() }}
            </div>
        @endif
    </div>
@endsection
