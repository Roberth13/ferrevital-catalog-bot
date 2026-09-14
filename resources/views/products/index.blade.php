@extends('layouts.app')

@section('title', 'Inventario | Ferrevital')

@section('extra_css')
    <style>
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .top-bar h1 {
            font-size: 1.8rem;
            margin: 0;
            background: linear-gradient(to right, var(--primary), #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
        }

        .search-form {
            display: flex;
            gap: 0.5rem;
        }

        .search-input {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            background-color: var(--card-bg);
            color: var(--text-main);
            width: 300px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
        }

        .table-card {
            background-color: var(--card-bg);
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            overflow-x: auto;
            margin-bottom: 2rem;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background-color: rgba(0,0,0,0.02);
            color: var(--text-muted);
            font-weight: 600;
            padding: 1rem 1.5rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 0.95rem;
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        
        tr:hover { background-color: rgba(0,0,0,0.01); }
        html.dark tr:hover { background-color: rgba(255,255,255,0.02); }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-active { background-color: var(--success-bg); color: var(--success); }
        .status-inactive { background-color: var(--error-bg); color: var(--error); }

        .row-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .icon-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.25rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .icon-btn:hover { background-color: rgba(0,0,0,0.05); color: var(--primary); }
        html.dark .icon-btn:hover { background-color: rgba(255,255,255,0.1); }
        
        .icon-btn.danger:hover { color: var(--error); }
        .icon-btn.warning:hover { color: var(--warning); }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: 1rem;
            width: 100%;
            max-width: 500px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 { margin: 0; font-size: 1.25rem; }
        
        form { padding: 0; margin: 0; }
        .form-group { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.5rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-main); font-family: inherit; font-size: 0.95rem; box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: var(--primary); }
        
        .modal-footer {
            padding: 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            background: rgba(0,0,0,0.02);
            border-bottom-left-radius: 1rem;
            border-bottom-right-radius: 1rem;
        }

        /* Pagination Styles */
        .pagination-container {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
        }

        /* Custom Checkbox */
        .custom-checkbox {
            appearance: none;
            background-color: var(--card-bg);
            margin: 0;
            font: inherit;
            color: currentColor;
            width: 1.15em;
            height: 1.15em;
            border: 2px solid var(--border-color);
            border-radius: 0.25em;
            display: grid;
            place-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .custom-checkbox::before {
            content: "";
            width: 0.65em;
            height: 0.65em;
            transform: scale(0);
            transition: 120ms transform ease-in-out;
            box-shadow: inset 1em 1em white;
            background-color: white;
            transform-origin: center;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
        }
        .custom-checkbox:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .custom-checkbox:checked::before {
            transform: scale(1);
        }
        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .actions {
                flex-direction: column;
            }
            .search-form {
                flex-direction: column;
            }
            .search-input {
                width: 100%;
            }
            .bulk-toolbar {
                flex-direction: column;
                align-items: stretch !important;
            }
            .bulk-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
@endsection

@section('content')
    <div class="top-bar">
        <h1>Inventario</h1>
        
        <div class="actions">
            <form action="{{ route('products.index') }}" method="GET" class="search-form" style="flex-wrap: wrap;">
                <select name="per_page" class="search-input" style="width: auto;" onchange="this.form.submit()">
                    <option value="10" {{ (isset($perPage) && $perPage == 10) ? 'selected' : '' }}>10 por pág.</option>
                    <option value="15" {{ (isset($perPage) && $perPage == 15) ? 'selected' : '' }}>15 por pág.</option>
                    <option value="20" {{ (!isset($perPage) || $perPage == 20) ? 'selected' : '' }}>20 por pág.</option>
                    <option value="50" {{ (isset($perPage) && $perPage == 50) ? 'selected' : '' }}>50 por pág.</option>
                    <option value="100" {{ (isset($perPage) && $perPage == 100) ? 'selected' : '' }}>100 por pág.</option>
                    <option value="all" {{ (isset($perPage) && $perPage === 'all') ? 'selected' : '' }}>Todos</option>
                </select>

                <select name="supplier_id" class="search-input" style="width: auto;" onchange="this.form.submit()">
                    <option value="">Todos los Proveedores</option>
                    @if(isset($suppliers))
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}" {{ request('supplier_id') == $sup->id ? 'selected' : '' }}>
                                {{ $sup->name }}
                            </option>
                        @endforeach
                    @endif
                </select>

                <select name="catalog_id" class="search-input" style="width: auto;" onchange="this.form.submit()">
                    <option value="">Todos los Catálogos</option>
                    @foreach($catalogs as $cat)
                        <option value="{{ $cat->id }}" {{ request('catalog_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->original_filename }} ({{ $cat->created_at->format('d/m/Y') }})
                        </option>
                    @endforeach
                </select>
                
                <input type="text" name="search" class="search-input" placeholder="Buscar por código, nombre o descripción..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </form>

            <a href="{{ route('products.export', request()->query()) }}" class="btn btn-success" style="background: var(--success); color: white; border: none; height: fit-content; align-self: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                Excel
            </a>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 1rem; margin-bottom: 1rem; border-radius: 0.5rem; background: var(--success-bg); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2);">
            {{ session('success') }}
        </div>
    @endif

    <div id="bulkToolbar" class="bulk-toolbar" style="padding: 1rem; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.5rem; margin-bottom: 1rem; display: flex; gap: 1rem; align-items: center; box-shadow: var(--shadow-sm);">
        <span id="bulkCount" style="font-weight: 600; color: var(--text-muted);">0 seleccionados</span>
        <button type="button" class="btn btn-success bulk-btn" data-action="activate" disabled style="background: var(--success); color: white; border: none;">Activar</button>
        <button type="button" class="btn btn-warning bulk-btn" data-action="deactivate" disabled style="background: var(--warning); color: white; border: none;">Desactivar</button>
        <button type="button" class="btn btn-danger bulk-btn" data-action="delete" style="background: var(--error); color: white; border: none;" disabled>Eliminar</button>
    </div>

    <form id="bulkForm" action="{{ route('products.bulkAction') }}" method="POST" style="display: none;">
        @csrf
        <input type="hidden" name="action" id="bulkActionInput" value="">
        <div id="bulkFormInputs"></div>
    </form>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <input type="checkbox" id="selectAll" class="custom-checkbox" title="Seleccionar Todos">
                    </th>
                    <th>Proveedor</th>
                    <th>Catálogo</th>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>P. Prov. Divisa</th>
                    <th>P. Prov. Bs</th>
                    <th>P. Venta Divisa</th>
                    <th>P. Venta Bs</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr>
                        <td style="text-align: center;">
                            <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" class="custom-checkbox product-checkbox">
                        </td>
                        <td style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);">
                            {{ $product->supplier ? $product->supplier->name : 'Proveedor Genérico' }}
                        </td>
                        <td>{{ $product->catalog ? $product->catalog->original_filename : 'N/A' }}</td>
                        <td style="font-weight: 600; font-family: monospace; color: var(--primary);">{{ $product->codigo }}</td>
                        <td>{{ Str::limit($product->nombre, 45) }}</td>
                        <td>${{ number_format($product->precio_divisa, 2) }}</td>
                        <td>{{ $product->precio_bs ? 'Bs ' . number_format($product->precio_bs, 2) : '-' }}</td>
                        <td style="font-weight: 700; color: var(--success);">
                            {{ $product->precio_venta_divisa !== null ? '$' . number_format($product->precio_venta_divisa, 2) : '-' }}
                        </td>
                        <td style="font-weight: 700; color: var(--success);">
                            {{ $product->precio_venta_bs !== null ? 'Bs ' . number_format($product->precio_venta_bs, 2) : '-' }}
                        </td>
                        <td>
                            <span class="status-badge {{ $product->is_active ? 'status-active' : 'status-inactive' }}">
                                {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <div class="row-actions">
                                <!-- Edit Btn -->
                                <button class="icon-btn" onclick="openEditModal({{ $product->id }}, '{{ addslashes($product->nombre) }}', '{{ $product->precio_divisa }}', '{{ $product->precio_bs }}')" title="Editar Datos Básicos">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                </button>

                                <!-- Toggle Active -->
                                <form action="{{ route('products.toggleActive', $product) }}" method="POST" style="margin:0;">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="icon-btn warning" title="{{ $product->is_active ? 'Desactivar' : 'Activar' }}">
                                        @if($product->is_active)
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        @endif
                                    </button>
                                </form>

                                <!-- Delete -->
                                <form action="{{ route('products.destroy', $product) }}" method="POST" style="margin:0;" onsubmit="return confirm('¿Seguro que deseas eliminar este producto?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn danger" title="Eliminar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align:center; color:var(--text-muted); padding: 3rem;">
                            No se encontraron productos.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="pagination-container">
            {{ $products->links('pagination::bootstrap-4') }} 
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Producto</h2>
                <button class="icon-btn" onclick="closeEditModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            
            <form id="editForm" method="POST">
                @csrf
                @method('PUT')
                
                <div class="form-group">
                    <label for="edit_nombre">Nombre</label>
                    <input type="text" id="edit_nombre" name="nombre" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_precio_divisa">Precio (Divisa)</label>
                    <input type="number" step="0.01" id="edit_precio_divisa" name="precio_divisa" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="edit_precio_bs">Precio (Bs) - Opcional</label>
                    <input type="number" step="0.01" id="edit_precio_bs" name="precio_bs" class="form-control">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('extra_scripts')
    <script>
        // Bulk Actions Logic
        const selectAllCheckbox = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.product-checkbox');
        const bulkToolbar = document.getElementById('bulkToolbar');
        const bulkCountLabel = document.getElementById('bulkCount');
        const bulkForm = document.getElementById('bulkForm');
        const bulkActionInput = document.getElementById('bulkActionInput');
        const bulkBtns = document.querySelectorAll('.bulk-btn');

        function updateBulkToolbar() {
            const checkedCount = document.querySelectorAll('.product-checkbox:checked').length;
            
            if (checkedCount > 0) {
                bulkCountLabel.innerText = checkedCount + ' seleccionado(s)';
                bulkCountLabel.style.color = 'var(--text-main)';
                bulkBtns.forEach(btn => btn.removeAttribute('disabled'));
            } else {
                bulkCountLabel.innerText = '0 seleccionados';
                bulkCountLabel.style.color = 'var(--text-muted)';
                bulkBtns.forEach(btn => btn.setAttribute('disabled', 'true'));
            }
        }

        selectAllCheckbox.addEventListener('change', (e) => {
            checkboxes.forEach(cb => cb.checked = e.target.checked);
            updateBulkToolbar();
        });

        checkboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                const checkedCount = document.querySelectorAll('.product-checkbox:checked').length;
                selectAllCheckbox.checked = checkedCount === checkboxes.length && checkboxes.length > 0;
                updateBulkToolbar();
            });
        });

        bulkBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                
                if (action === 'delete') {
                    if (!confirm('¿Estás seguro de eliminar los productos seleccionados? Esta acción no se puede deshacer.')) return;
                }
                
                bulkActionInput.value = action;

                // Move selected IDs to the hidden form
                const inputsContainer = document.getElementById('bulkFormInputs');
                inputsContainer.innerHTML = '';
                document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'product_ids[]';
                    input.value = cb.value;
                    inputsContainer.appendChild(input);
                });

                bulkForm.submit();
            });
        });

        // Lógica del Modal
        const modal = document.getElementById('editModal');
        const editForm = document.getElementById('editForm');
        
        function openEditModal(id, nombre, divisa, bs) {
            // Configurar el action URL del formulario
            editForm.action = `/products/${id}`;
            
            // Llenar campos
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_precio_divisa').value = divisa;
            document.getElementById('edit_precio_bs').value = bs || '';
            
            // Mostrar modal
            modal.classList.add('active');
        }

        function closeEditModal() {
            modal.classList.remove('active');
        }
    </script>
@endsection
