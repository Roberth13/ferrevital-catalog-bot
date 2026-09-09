@extends('layouts.app')

@section('title', 'Dashboard | Ferrevital')

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

        /* KPIs */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
        }

        .kpi-title {
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .kpi-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Charts */
        .chart-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 900px) {
            .chart-grid { grid-template-columns: 1fr; }
        }

        .chart-card {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
        }

        .chart-card h2 {
            font-size: 1.1rem;
            margin-top: 0;
            margin-bottom: 1.5rem;
            color: var(--text-main);
        }

        /* Lists */
        .lists-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .list-card {
            background-color: var(--card-bg);
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .list-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            background-color: rgba(0,0,0,0.02);
            color: var(--text-main);
        }

        .list-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .list-item:last-child { border-bottom: none; }

        .list-item-content h4 {
            margin: 0 0 0.25rem 0;
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .list-item-content p {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        .badge-error { background: var(--error-bg); color: var(--error); }
        .badge-primary { background: rgba(99,102,241,0.1); color: var(--primary); }
    </style>
@endsection

@section('head_scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    <div class="top-bar">
        <h1>Dashboard</h1>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-title">Productos Activos</div>
            <div class="kpi-value">{{ number_format($totalProducts) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Catálogos Procesados</div>
            <div class="kpi-value">{{ number_format($totalCatalogs) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Nuevos Extraídos (Histórico)</div>
            <div class="kpi-value" style="color: var(--success);">+{{ number_format($created) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Precios Actualizados (Histórico)</div>
            <div class="kpi-value" style="color: var(--primary);">{{ number_format($updated) }}</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="chart-grid">
        <div class="chart-card">
            <h2>Rendimiento Últimos 7 Días</h2>
            <canvas id="lineChart" height="100"></canvas>
        </div>
        <div class="chart-card">
            <h2>Distribución de Lecturas</h2>
            <div style="height: 250px; display: flex; justify-content: center;">
                <canvas id="donutChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Lists -->
    <div class="lists-grid">
        <!-- Ultimos Creados -->
        <div class="list-card">
            <div class="list-header">Recién Agregados al Inventario</div>
            @forelse($recentCreatedProducts as $prod)
                <div class="list-item">
                    <div class="list-item-content">
                        <h4>{{ Str::limit($prod->nombre, 40) }}</h4>
                        <p>SKU: {{ $prod->codigo }} &bull; ${{ number_format($prod->precio_divisa, 2) }}</p>
                    </div>
                    <span class="badge badge-success">Nuevo</span>
                </div>
            @empty
                <div class="list-item"><p class="text-muted">No hay datos</p></div>
            @endforelse
        </div>

        <!-- Ultimos Actualizados -->
        <div class="list-card">
            <div class="list-header">Últimos Precios Actualizados</div>
            @forelse($recentUpdatedProducts as $prod)
                <div class="list-item">
                    <div class="list-item-content">
                        <h4>{{ Str::limit($prod->nombre, 40) }}</h4>
                        <p>SKU: {{ $prod->codigo }} &bull; Ahora: ${{ number_format($prod->precio_divisa, 2) }}</p>
                    </div>
                    <span class="badge badge-primary">Actualizado</span>
                </div>
            @empty
                <div class="list-item"><p class="text-muted">No hay datos</p></div>
            @endforelse
        </div>

        <!-- Últimos Catálogos -->
        <div class="list-card">
            <div class="list-header">Últimas Subidas de Catálogos</div>
            @forelse($recentCatalogs as $cat)
                <div class="list-item">
                    <div class="list-item-content">
                        <h4>{{ $cat->original_filename }}</h4>
                        <p>{{ $cat->created_at->diffForHumans() }} &bull; {{ $cat->total_products }} productos</p>
                    </div>
                    @if($cat->status === 'completed')
                        <span class="badge badge-success">Completado</span>
                    @elseif($cat->status === 'failed')
                        <span class="badge badge-error">Error</span>
                    @else
                        <span class="badge badge-warning">Procesando</span>
                    @endif
                </div>
            @empty
                <div class="list-item"><p class="text-muted">No hay catálogos aún</p></div>
            @endforelse
        </div>
    </div>
@endsection

@section('extra_scripts')
    <script>
        function getChartColors() {
            const isDark = htmlEl.classList.contains('dark');
            return {
                text: isDark ? '#94a3b8' : '#64748b',
                grid: isDark ? '#334155' : '#e2e8f0',
                created: isDark ? '#10b981' : '#059669',
                updated: isDark ? '#6366f1' : '#4f46e5',
                failed: isDark ? '#ef4444' : '#dc2626',
                duplicated: isDark ? '#fbbf24' : '#f59e0b'
            };
        }

        let lineChartInstance = null;
        let donutChartInstance = null;

        function initCharts() {
            const colors = getChartColors();
            
            // Common Options
            Chart.defaults.color = colors.text;
            Chart.defaults.font.family = "'Inter', sans-serif";

            // Data from PHP
            const lineData = @json($lineChart);
            const donutData = {
                created: {{ $created }},
                updated: {{ $updated }},
                failed: {{ $failed }},
                duplicated: {{ $duplicated }}
            };

            // Line Chart
            const ctxLine = document.getElementById('lineChart')?.getContext('2d');
            if(ctxLine) {
                lineChartInstance = new Chart(ctxLine, {
                    type: 'line',
                    data: {
                        labels: lineData.labels,
                        datasets: [
                            {
                                label: 'Nuevos',
                                data: lineData.created,
                                borderColor: colors.created,
                                backgroundColor: colors.created,
                                tension: 0.3
                            },
                            {
                                label: 'Actualizados',
                                data: lineData.updated,
                                borderColor: colors.updated,
                                backgroundColor: colors.updated,
                                tension: 0.3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { 
                                beginAtZero: true,
                                grid: { color: colors.grid }
                            },
                            x: {
                                grid: { color: colors.grid }
                            }
                        }
                    }
                });
            }

            // Donut Chart
            const ctxDonut = document.getElementById('donutChart')?.getContext('2d');
            if(ctxDonut) {
                donutChartInstance = new Chart(ctxDonut, {
                    type: 'doughnut',
                    data: {
                        labels: ['Nuevos', 'Actualizados', 'Fallidos', 'Duplicados'],
                        datasets: [{
                            data: [donutData.created, donutData.updated, donutData.failed, donutData.duplicated],
                            backgroundColor: [colors.created, colors.updated, colors.failed, colors.duplicated],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        },
                        cutout: '70%'
                    }
                });
            }
        }

        // Hook into the global layout's theme change event
        window.onThemeChange = function(theme) {
            if(lineChartInstance) lineChartInstance.destroy();
            if(donutChartInstance) donutChartInstance.destroy();
            initCharts();
        };

        // Init
        setTimeout(initCharts, 100);
    </script>
@endsection
