@extends('layouts.app')

@section('title', 'Precio de Venta | Ferrevital')

@section('extra_css')
    <style>
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .top-bar h1 {
            font-size: 1.8rem;
            margin: 0;
            background: linear-gradient(to right, var(--primary), #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .formula-card {
            background-color: var(--card-bg);
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .formula-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
        }

        .formula-card-header h2 {
            margin: 0;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .formula-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
            align-items: start;
        }

        .form-group-custom {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .form-group-custom label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .form-control-custom {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.95rem;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary);
        }

        .radio-group {
            display: flex;
            gap: 1rem;
            margin-top: 0.25rem;
        }

        .radio-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            padding: 0.5rem 1rem;
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        .radio-label:hover {
            border-color: var(--primary);
        }

        .radio-label input[type="radio"]:checked + span {
            color: var(--primary);
            font-weight: 700;
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
            padding: 1rem 1.25rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 0.9rem;
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: rgba(0,0,0,0.01); }
        html.dark tr:hover { background-color: rgba(255,255,255,0.02); }

        .price-badge-cost {
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .price-badge-sale {
            font-weight: 700;
            color: var(--success);
            font-size: 0.95rem;
        }

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

        .formula-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.375rem;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .pagination-container {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
        }

        .notice-banner {
            padding: 0.85rem 1.25rem;
            background: rgba(245, 158, 11, 0.08);
            border-left: 4px solid var(--warning);
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: var(--text-main);
        }
    </style>
@endsection

@section('content')
    <div class="top-bar">
        <div>
            <h1>Precio de Venta</h1>
            <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.9rem;">
                Cálculo y asignación de precios comerciales sobre los productos del inventario sin alterar los costos del proveedor.
            </p>
        </div>
        
        <a href="{{ route('products.index') }}" class="btn btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Ver Inventario / Explorador
        </a>
    </div>

    <div class="notice-banner">
        <strong>Fórmulas Técnicas Parametrizables:</strong> Las fórmulas mostradas son herramientas de cálculo matemático configurables para el MVP. Las reglas y márgenes comerciales oficiales del negocio se integrarán en una fase posterior.
    </div>

    @if(session('success'))
        <div style="padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem; background: var(--success-bg); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2);">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div style="padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem; background: var(--error-bg); color: var(--error); border: 1px solid rgba(239, 68, 68, 0.2);">
            <ul style="margin: 0; padding-left: 1.25rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- PANEL DE CÁLCULO DE FÓRMULAS -->
    <div class="formula-card">
        <div class="formula-card-header">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                Configuración de Fórmula de Venta
            </h2>
            <span id="selectedCountBadge" style="font-weight: 600; font-size: 0.85rem; color: var(--primary);">
                0 productos seleccionados
            </span>
        </div>

        <form id="salePriceForm" action="{{ route('sale-prices.apply') }}" method="POST">
            @csrf
            
            <div class="formula-grid">
                <!-- Selector de Fórmula -->
                <div class="form-group-custom">
                    <label for="formula_id">Fórmula a Aplicar:</label>
                    <select name="formula_id" id="formula_id" class="form-control-custom" required onchange="updateFormulaParams()">
                        @foreach($formulas as $f)
                            <option value="{{ $f->getId() }}" data-desc="{{ $f->getDescription() }}" data-params='@json($f->getParameterDefinitions())' {{ old('formula_id') === $f->getId() ? 'selected' : '' }}>
                                {{ $f->getName() }}
                            </option>
                        @endforeach
                    </select>
                    <small id="formulaDescription" style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem;"></small>
                </div>

                <!-- Selector de Base de Cálculo -->
                <div class="form-group-custom">
                    <label>Aplicar sobre Base:</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="base" value="divisa" {{ old('base', 'divisa') === 'divisa' ? 'checked' : '' }} required>
                            <span>Precio Divisa ($)</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="base" value="bs" {{ old('base') === 'bs' ? 'checked' : '' }} required>
                            <span>Precio Bs (Bs)</span>
                        </label>
                    </div>
                    <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem;">
                        Toma el precio de costo correspondiente del proveedor y guarda el resultado en el campo de venta de esa misma moneda.
                    </small>
                </div>

                <!-- Parámetros dinámicos de la fórmula -->
                <div class="form-group-custom" id="dynamicParamsContainer">
                    <!-- Rellenado con Javascript -->
                </div>
            </div>

            <!-- IDs ocultos de productos seleccionados -->
            <div id="selectedProductInputs"></div>

            <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
                <button type="submit" id="submitBtn" class="btn btn-primary" disabled style="padding: 0.75rem 1.5rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    Calcular y Guardar Precios de Venta
                </button>
            </div>
        </form>
    </div>

    <!-- BARRA DE FILTROS DE PRODUCTOS -->
    <div style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0; font-size: 1.15rem; color: var(--text-main);">
            Seleccionar Productos a Formular
        </h3>

        <form action="{{ route('sale-prices.index') }}" method="GET" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <select name="per_page" class="form-control-custom" style="width: auto;" onchange="this.form.submit()">
                <option value="10" {{ (isset($perPage) && $perPage == 10) ? 'selected' : '' }}>10 por pág.</option>
                <option value="20" {{ (!isset($perPage) || $perPage == 20) ? 'selected' : '' }}>20 por pág.</option>
                <option value="50" {{ (isset($perPage) && $perPage == 50) ? 'selected' : '' }}>50 por pág.</option>
                <option value="100" {{ (isset($perPage) && $perPage == 100) ? 'selected' : '' }}>100 por pág.</option>
                <option value="all" {{ (isset($perPage) && $perPage === 'all') ? 'selected' : '' }}>Todos</option>
            </select>

            <select name="supplier_id" class="form-control-custom" style="width: auto;" onchange="this.form.submit()">
                <option value="">Todos los Proveedores</option>
                @foreach($suppliers as $sup)
                    <option value="{{ $sup->id }}" {{ request('supplier_id') == $sup->id ? 'selected' : '' }}>
                        {{ $sup->name }}
                    </option>
                @endforeach
            </select>

            <select name="catalog_id" class="form-control-custom" style="width: auto;" onchange="this.form.submit()">
                <option value="">Todos los Catálogos</option>
                @foreach($catalogs as $cat)
                    <option value="{{ $cat->id }}" {{ request('catalog_id') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->original_filename }} ({{ $cat->created_at->format('d/m/Y') }})
                    </option>
                @endforeach
            </select>

            <input type="text" name="search" class="form-control-custom" style="width: 240px;" placeholder="Buscar SKU o nombre..." value="{{ request('search') }}">
            <button type="submit" class="btn btn-secondary">Filtrar</button>
        </form>
    </div>

    <!-- TABLA DE PRODUCTOS CON CHECKBOXES -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">
                        <input type="checkbox" id="selectAll" class="custom-checkbox" title="Seleccionar Todos en esta página">
                    </th>
                    <th>Proveedor</th>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>P. Costo Divisa</th>
                    <th>P. Costo Bs</th>
                    <th>P. Venta Divisa</th>
                    <th>P. Venta Bs</th>
                    <th>Última Fórmula</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr>
                        <td style="text-align: center;">
                            <input type="checkbox" value="{{ $product->id }}" class="custom-checkbox product-checkbox">
                        </td>
                        <td style="font-size: 0.85rem; font-weight: 500;">
                            {{ $product->supplier ? $product->supplier->name : 'Proveedor Genérico' }}
                        </td>
                        <td style="font-weight: 600; font-family: monospace; color: var(--primary);">
                            {{ $product->codigo }}
                        </td>
                        <td>{{ Str::limit($product->nombre, 40) }}</td>
                        <td class="price-badge-cost">
                            {{ $product->precio_divisa !== null ? '$' . number_format($product->precio_divisa, 2) : '-' }}
                        </td>
                        <td class="price-badge-cost">
                            {{ $product->precio_bs !== null ? 'Bs ' . number_format($product->precio_bs, 2) : '-' }}
                        </td>
                        <td class="price-badge-sale">
                            {{ $product->precio_venta_divisa !== null ? '$' . number_format($product->precio_venta_divisa, 2) : '-' }}
                        </td>
                        <td class="price-badge-sale">
                            {{ $product->precio_venta_bs !== null ? 'Bs ' . number_format($product->precio_venta_bs, 2) : '-' }}
                        </td>
                        <td>
                            @if($product->sale_price_formula)
                                <span class="formula-badge">
                                    {{ $product->sale_price_formula }} ({{ strtoupper($product->sale_price_base ?? '') }})
                                </span>
                            @else
                                <span style="color: var(--text-muted); font-size: 0.8rem;">Sin formular</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                            No se encontraron productos para los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="pagination-container">
            {{ $products->links('pagination::bootstrap-4') }}
        </div>
    </div>
@endsection

@section('extra_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const formulaSelect = document.getElementById('formula_id');
            const formulaDesc = document.getElementById('formulaDescription');
            const paramsContainer = document.getElementById('dynamicParamsContainer');
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.product-checkbox');
            const submitBtn = document.getElementById('submitBtn');
            const selectedCountBadge = document.getElementById('selectedCountBadge');
            const selectedProductInputs = document.getElementById('selectedProductInputs');
            const salePriceForm = document.getElementById('salePriceForm');

            window.updateFormulaParams = function () {
                const selectedOption = formulaSelect.options[formulaSelect.selectedIndex];
                if (!selectedOption) return;

                const desc = selectedOption.getAttribute('data-desc');
                const params = JSON.parse(selectedOption.getAttribute('data-params') || '{}');

                formulaDesc.textContent = desc;
                paramsContainer.innerHTML = '';

                for (const [key, config] of Object.entries(params)) {
                    const label = document.createElement('label');
                    label.textContent = config.label + ':';

                    const input = document.createElement('input');
                    input.type = config.type || 'number';
                    input.name = `parameters[${key}]`;
                    input.className = 'form-control-custom';
                    input.placeholder = config.placeholder || '';
                    input.step = config.step || '0.01';
                    if (config.min !== undefined) input.min = config.min;
                    if (config.max !== undefined) input.max = config.max;
                    if (config.default !== undefined) input.value = config.default;
                    if (config.required) input.required = true;

                    const help = document.createElement('small');
                    help.style.color = 'var(--text-muted)';
                    help.style.fontSize = '0.8rem';
                    help.style.marginTop = '0.25rem';
                    help.textContent = config.help || '';

                    paramsContainer.appendChild(label);
                    paramsContainer.appendChild(input);
                    if (config.help) paramsContainer.appendChild(help);
                }
            };

            updateFormulaParams();

            function updateSelection() {
                const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
                const count = selected.length;
                
                selectedCountBadge.textContent = `${count} productos seleccionados`;
                submitBtn.disabled = count === 0;

                selectedProductInputs.innerHTML = '';
                selected.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'product_ids[]';
                    input.value = id;
                    selectedProductInputs.appendChild(input);
                });
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checkboxes.forEach(cb => cb.checked = selectAll.checked);
                    updateSelection();
                });
            }

            checkboxes.forEach(cb => {
                cb.addEventListener('change', function () {
                    const allChecked = Array.from(checkboxes).every(c => c.checked);
                    if (selectAll) selectAll.checked = allChecked;
                    updateSelection();
                });
            });

            salePriceForm.addEventListener('submit', function (e) {
                const selected = Array.from(checkboxes).filter(cb => cb.checked);
                if (selected.length === 0) {
                    e.preventDefault();
                    alert('Debes seleccionar al menos un producto para aplicar la fórmula.');
                }
            });
        });
    </script>
@endsection
