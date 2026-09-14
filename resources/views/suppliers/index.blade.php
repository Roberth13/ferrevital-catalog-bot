@extends('layouts.app')

@section('title', 'Proveedores | Ferrevital')

@section('content')
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.8rem; margin: 0; background: linear-gradient(to right, var(--primary), #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                Proveedores
            </h1>
            <p style="color: var(--text-muted); margin: 0.25rem 0 0;">Administra los proveedores de catálogos y consulta sus productos asociados.</p>
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

    @if($errors->any())
        <div style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 0.5rem; background: var(--error-bg); color: var(--error); border: 1px solid rgba(239, 68, 68, 0.2);">
            <ul style="margin: 0; padding-left: 1.5rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; align-items: start;">
        <!-- Formulario Crear Proveedor -->
        <div style="background: var(--card-bg); padding: 1.5rem; border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
            <h2 style="font-size: 1.2rem; margin-top: 0; margin-bottom: 1rem; color: var(--text-main);">Nuevo Proveedor</h2>
            <form action="{{ route('suppliers.store') }}" method="POST">
                @csrf
                <div style="margin-bottom: 1rem;">
                    <label for="name" style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-main);">Nombre del Proveedor *</label>
                    <input type="text" name="name" id="name" required placeholder="Ej. Jadever, Dong Cheng, Ronix..." style="width: 100%; box-sizing: border-box; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-main); font-size: 0.95rem;">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label for="slug" style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-main);">Identificador Slug (Opcional)</label>
                    <input type="text" name="slug" id="slug" placeholder="Ej. jadever-ve (se genera auto si está vacío)" style="width: 100%; box-sizing: border-box; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-main); font-size: 0.95rem;">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    Guardar Proveedor
                </button>
            </form>
        </div>

        <!-- Tabla de Proveedores -->
        <div style="background: var(--card-bg); border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h2 style="font-size: 1.1rem; margin: 0; color: var(--text-main);">Listado de Proveedores ({{ $suppliers->count() }})</h2>
            </div>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                    <thead>
                        <tr style="background-color: var(--bg-color); border-bottom: 1px solid var(--border-color); color: var(--text-muted);">
                            <th style="padding: 0.75rem 1rem;">ID</th>
                            <th style="padding: 0.75rem 1rem;">Nombre</th>
                            <th style="padding: 0.75rem 1rem;">Slug</th>
                            <th style="padding: 0.75rem 1rem; text-align: center;">Catálogos</th>
                            <th style="padding: 0.75rem 1rem; text-align: center;">Productos</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suppliers as $supplier)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.75rem 1rem; color: var(--text-muted); font-size: 0.85rem;">#{{ $supplier->id }}</td>
                                <td style="padding: 0.75rem 1rem; font-weight: 600; color: var(--text-main);">{{ $supplier->name }}</td>
                                <td style="padding: 0.75rem 1rem; font-family: monospace; color: var(--text-muted);">{{ $supplier->slug }}</td>
                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                    <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; background: var(--bg-color); color: var(--text-main); border: 1px solid var(--border-color);">
                                        {{ $supplier->catalogs_count }}
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                    <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; background: var(--success-bg); color: var(--success);">
                                        {{ $supplier->products_count }}
                                    </span>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right;">
                                    <a href="{{ route('products.index', ['supplier_id' => $supplier->id]) }}" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                                        Ver Productos
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                    No hay proveedores registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
