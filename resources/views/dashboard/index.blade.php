@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $parametrosPeriodo = array_filter(['mes' => $seleccion['mes'], 'dia' => $seleccion['dia']]);
    $dinero = fn (float $monto): string => '$'.number_format($monto, 0, ',', '.');
@endphp
<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="header-content">
            <p class="dashboard-eyebrow">Centro de operaciones</p>
            <h1>Resumen general</h1>
            <p class="subtitle">Bienvenido, {{ auth()->user()->name }}. Aquí tienes el estado de tu negocio.</p>
        </div>
        <div class="header-actions">
            <a
                id="dashboard-export-pdf"
                href="{{ route('dashboard.exportar.pdf', $parametrosPeriodo) }}"
                class="btn-pdf"
            >
                <i class="fas fa-file-pdf" aria-hidden="true"></i>
                Descargar PDF
            </a>
            <div class="header-date">
                <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                <span id="current-date"></span>
            </div>
        </div>
    </div>

    @if($cantidadStockCritico > 0)
    <div class="alert-card">
        <div class="alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="alert-content">
            <h3>Stock Crítico</h3>
            <p>{{ number_format($cantidadStockCritico, 0, ',', '.') }} {{ $cantidadStockCritico === 1 ? 'producto con stock bajo' : 'productos con stock bajo' }}</p>
            <a href="{{ route('productos.index', ['stock' => 'critico']) }}" class="btn-alert">Ver stock crítico</a>
        </div>
    </div>
    @endif

    <div id="dashboard-live" aria-live="polite">
    @php
        $datosPanel = ['mes' => $seleccion['mes'], 'dia' => $seleccion['dia'], 'ventasMensuales' => $ventasMensuales, 'ventasDiarias' => $ventasDiarias, 'productos' => $productosMasVendidos, 'pdf' => route('dashboard.exportar.pdf', $parametrosPeriodo)];
    @endphp
    <script type="application/json" id="dashboard-data">@json($datosPanel)</script>
    <form method="GET" action="{{ route('dashboard') }}" class="period-bar" id="dashboard-period-form">
        <div class="period-current">
            <span class="chart-kicker">Período de los indicadores</span>
            <strong id="dashboard-period-label">{{ ucfirst($seleccion['etiqueta']) }}</strong>
            <small>Los indicadores, el gráfico diario y el top 10 siguen este período. Haz clic en una barra para cambiarlo.</small>
        </div>
        <label class="period-select">
            <span>Mes</span>
            <select name="mes" id="dashboard-mes" aria-label="Seleccionar mes">
                @foreach($ventasMensuales as $mes)
                    <option value="{{ $mes['periodo'] }}" @selected($mes['periodo'] === $seleccion['mes'])>{{ ucfirst($mes['etiqueta']) }}</option>
                @endforeach
            </select>
        </label>
        @if($seleccion['dia'])
            <a href="{{ route('dashboard', ['mes' => $seleccion['mes']]) }}" class="btn-clear-day" id="dashboard-clear-day">
                <i class="fas fa-times" aria-hidden="true"></i> Ver todo el mes
            </a>
        @endif
    </form>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon icon-revenue"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-content">
                <h3>Monto vendido</h3>
                <p class="stat-value">{{ $dinero($resumen['total']['monto']) }}</p>
                <small>{{ $seleccion['descripcion'] }}</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-sales"><i class="fas fa-receipt"></i></div>
            <div class="stat-content">
                <h3>Cantidad de ventas</h3>
                <p class="stat-value">{{ number_format($resumen['total']['cantidad'], 0, ',', '.') }}</p>
                <small>{{ $seleccion['descripcion'] }}</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-today"><i class="fas fa-cash-register"></i></div>
            <div class="stat-content">
                <h3>Ventas en POS</h3>
                <p class="stat-value">{{ number_format($resumen['pos']['cantidad'], 0, ',', '.') }}</p>
                <small>{{ $dinero($resumen['pos']['monto']) }} vendidos en el punto de venta</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-clients"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-content">
                <h3>Ventas por cotización</h3>
                <p class="stat-value">{{ number_format($resumen['cotizaciones']['cantidad'], 0, ',', '.') }}</p>
                <small>{{ $dinero($resumen['cotizaciones']['monto']) }} originados en cotizaciones</small>
            </div>
        </div>
    </div>

    <div class="chart-card chart-wide">
        <div class="chart-card-heading">
            <span class="chart-kicker">Últimos 12 meses</span>
            <h3>Ventas por mes</h3>
            <p>Monto vendido en cada mes. Haz clic en una barra para filtrar todo el dashboard por ese mes.</p>
        </div>
        <canvas id="ventasMensualesChart" aria-label="Ventas por mes"></canvas>
    </div>

    <div class="charts-container">
        <div class="chart-card">
            <div class="chart-card-heading">
                <span class="chart-kicker">{{ ucfirst($seleccion['inicioMes']->copy()->locale('es')->isoFormat('MMMM YYYY')) }}</span>
                <h3>Ventas por día</h3>
                <p>Monto vendido cada día del mes. Haz clic en un día para ver solo ese día.</p>
            </div>
            <canvas id="ventasDiariasChart" aria-label="Ventas por día"></canvas>
        </div>

        <div class="chart-card">
            <div class="chart-card-heading">
                <span class="chart-kicker">{{ $seleccion['dia'] ? 'Día seleccionado' : 'Top 10 del mes' }}</span>
                <h3>{{ $seleccion['dia'] ? 'Top 10 productos del día' : 'Top 10 productos más vendidos del mes' }}</h3>
                <p>Unidades vendidas en {{ $seleccion['etiqueta'] }}.</p>
            </div>
            @if(count($productosMasVendidos) > 0)
                <canvas id="productosChart" aria-label="Productos más vendidos"></canvas>
            @else
                <p class="chart-empty">Sin ventas de productos en este período.</p>
            @endif
        </div>
    </div>
    </div>
</div>

<style>
    .dashboard-container {
        --dashboard-red: #8f241d;
        --dashboard-accent: #c41e3a;
        --dashboard-muted: #72727d;
        box-sizing: border-box;
        width: 100%;
        max-width: 1600px;
        min-width: 0;
        margin: 0 auto;
        padding: clamp(16px, 2.4vw, 36px);
        background: #f5f6f8;
        min-height: 100vh;
    }

    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 24px;
        margin-bottom: 24px;
        background: linear-gradient(120deg, #fff 0%, #fff 68%, #fff5f3 100%);
        padding: clamp(22px, 3vw, 36px);
        border: 1px solid #eee8e7;
        border-left: 5px solid var(--dashboard-accent);
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(36, 24, 23, 0.06);
        box-sizing: border-box;
    }

    .dashboard-eyebrow,
    .chart-kicker {
        display: block;
        margin: 0 0 6px;
        color: var(--dashboard-accent);
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .dashboard-header h1 {
        margin: 0;
        color: #241817;
        font-size: clamp(1.6rem, 3vw, 2.2rem);
    }

    .subtitle {
        margin: 6px 0 0;
        color: var(--dashboard-muted);
    }

    .header-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 14px;
    }

    .btn-pdf {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 8px;
        background: #b52f25;
        padding: 12px 16px;
        color: #fff;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .btn-pdf:hover {
        background: #8f241d;
    }

    .header-date {
        color: var(--dashboard-muted);
        font-weight: 600;
    }

    .header-date i {
        margin-right: 6px;
        color: var(--dashboard-accent);
    }

    .alert-card {
        display: flex;
        align-items: center;
        gap: 18px;
        margin-bottom: 24px;
        padding: 16px 20px;
        border: 1px solid #f1dda5;
        border-left: 5px solid #e8a900;
        border-radius: 12px;
        background: #fff3cd;
    }

    .alert-icon {
        color: #e8a900;
        font-size: 1.8rem;
    }

    .alert-content h3 {
        margin: 0 0 3px;
        color: #856404;
    }

    .alert-content p {
        margin: 0 0 8px;
        color: #856404;
    }

    .btn-alert {
        display: inline-block;
        padding: 7px 14px;
        border-radius: 6px;
        background: #ffc107;
        color: #333;
        font-weight: 600;
        text-decoration: none;
    }

    .btn-alert:hover {
        background: #ffb300;
    }

    .period-bar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px 20px;
        margin-bottom: 20px;
        padding: 16px 20px;
        border: 1px solid #ececf0;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(36, 24, 23, 0.045);
    }

    .period-current {
        flex: 1 1 280px;
        min-width: 0;
    }

    .period-current strong {
        display: block;
        color: #241817;
        font-size: 1.25rem;
    }

    .period-current small {
        color: var(--dashboard-muted);
    }

    .period-select {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #4b5563;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .period-select select {
        min-height: 38px;
        padding: 7px 30px 7px 10px;
        border: 1px solid #d9aaa3;
        border-radius: 8px;
        background: #fff;
        color: var(--dashboard-red);
        font: inherit;
        cursor: pointer;
    }

    .btn-clear-day {
        padding: 9px 14px;
        border: 1px solid #d9aaa3;
        border-radius: 8px;
        color: var(--dashboard-red);
        font-size: 0.875rem;
        font-weight: 700;
        text-decoration: none;
    }

    .btn-clear-day:hover {
        background: #fff5f3;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: clamp(12px, 1.4vw, 20px);
        margin-bottom: 24px;
    }

    .stat-card {
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 0;
        padding: 20px;
        border: 1px solid #ececf0;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(36, 24, 23, 0.045);
    }

    .stat-icon {
        display: flex;
        flex: 0 0 48px;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 12px;
        font-size: 1.2rem;
    }

    .icon-sales { background: #fdecea; color: #c41e3a; }
    .icon-revenue { background: #e8f5ec; color: #1f7a3d; }
    .icon-clients { background: #eaf1fb; color: #2a5db0; }
    .icon-today { background: #fff4e0; color: #b7791f; }

    .stat-content { min-width: 0; }

    .stat-content h3 {
        margin: 0 0 4px;
        color: var(--dashboard-muted);
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .stat-value {
        margin: 0;
        color: #241817;
        font-size: clamp(1.3rem, 2vw, 1.8rem);
        font-weight: 800;
        overflow-wrap: anywhere;
    }

    .stat-content small {
        display: block;
        margin-top: 4px;
        color: var(--dashboard-muted);
        font-size: 0.76rem;
        line-height: 1.35;
    }

    .charts-container {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: clamp(14px, 1.7vw, 22px);
        margin-top: clamp(14px, 1.7vw, 22px);
    }

    .chart-card {
        box-sizing: border-box;
        min-width: 0;
        padding: clamp(18px, 2vw, 26px);
        border: 1px solid #ececf0;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(36, 24, 23, 0.045);
    }

    .chart-card-heading { margin-bottom: 16px; }

    .chart-card-heading h3 {
        margin: 0;
        color: #302523;
        font-size: clamp(1rem, 1.5vw, 1.2rem);
        font-weight: 800;
    }

    .chart-card-heading p {
        margin: 4px 0 0;
        color: var(--dashboard-muted);
        font-size: 0.82rem;
    }

    .chart-card canvas {
        display: block;
        width: 100% !important;
        height: clamp(240px, 26vw, 340px) !important;
    }

    .chart-empty {
        padding: 60px 0;
        color: var(--dashboard-muted);
        text-align: center;
    }

    @media (max-width: 1100px) {
        .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 800px) {
        .charts-container { grid-template-columns: minmax(0, 1fr); }
    }

    @media (max-width: 520px) {
        .stats-grid { grid-template-columns: minmax(0, 1fr); }
    }
</style>

<script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
<script>
    (function () {
        const opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const hoy = new Date().toLocaleDateString('es-ES', opciones);
        document.getElementById('current-date').textContent = hoy.charAt(0).toUpperCase() + hoy.slice(1);

        const urlDashboard = @json(route('dashboard'));
        const contenedor = document.getElementById('dashboard-live');
        let graficos = [];
        let solicitud = null;
        const rojo = '#C41E3A';
        const rojoClaro = '#E8A3AE';
        const formatoDinero = valor => '$' + Number(valor).toLocaleString('es-CL');
        const escalaDinero = { beginAtZero: true, ticks: { callback: formatoDinero } };

        async function irA(parametros, historial = true) {
            const url = urlDashboard + '?' + new URLSearchParams(parametros).toString();
            if (solicitud) {
                solicitud.abort();
            }
            solicitud = new AbortController();
            contenedor.style.opacity = '0.55';

            try {
                const respuesta = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                    signal: solicitud.signal,
                });
                if (!respuesta.ok) {
                    throw new Error('HTTP ' + respuesta.status);
                }
                const documento = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
                const nuevo = documento.getElementById('dashboard-live');
                if (!nuevo) {
                    throw new Error('Respuesta inesperada');
                }
                contenedor.innerHTML = nuevo.innerHTML;
                if (historial) {
                    history.pushState(parametros, '', url);
                }
                montar();
                contenedor.style.opacity = '';
            } catch (error) {
                if (error.name !== 'AbortError') {
                    window.location.href = url;
                }
            }
        }

        function graficoBarras(id, datos, esSeleccionada, alHacerClic, tooltipExtra) {
            const lienzo = document.getElementById(id);
            return new Chart(lienzo, {
                type: 'bar',
                data: {
                    labels: datos.map(d => d.etiquetaCorta ?? d.etiqueta),
                    datasets: [{
                        label: 'Ventas ($)',
                        data: datos.map(d => Number(d.total) || 0),
                        backgroundColor: datos.map(d => esSeleccionada(d) ? rojo : rojoClaro),
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', axis: 'x', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: contexto => formatoDinero(contexto.parsed.y) + ' · ' + tooltipExtra(datos[contexto.dataIndex])
                            }
                        }
                    },
                    scales: { y: escalaDinero },
                    onClick: (evento, elementos) => {
                        if (elementos.length) {
                            alHacerClic(datos[elementos[0].index]);
                        }
                    },
                    onHover: (evento, elementos) => {
                        evento.native.target.style.cursor = elementos.length ? 'pointer' : 'default';
                    }
                }
            });
        }

        function montar() {
        graficos.forEach(grafico => grafico.destroy());
        graficos = [];
        const { mes: mesSeleccionado, dia: diaSeleccionado, ventasMensuales, ventasDiarias, productos, pdf } =
            JSON.parse(document.getElementById('dashboard-data').textContent);
        document.getElementById('dashboard-export-pdf').href = pdf;

        graficos.push(graficoBarras(
            'ventasMensualesChart',
            ventasMensuales,
            d => d.periodo === mesSeleccionado,
            d => irA({ mes: d.periodo }),
            d => d.cantidad + (d.cantidad === 1 ? ' venta' : ' ventas')
        ));

        graficos.push(graficoBarras(
            'ventasDiariasChart',
            ventasDiarias,
            d => diaSeleccionado === null || d.dia === diaSeleccionado,
            d => irA({ mes: mesSeleccionado, dia: d.dia }),
            d => d.cantidad + (d.cantidad === 1 ? ' venta' : ' ventas')
        ));

        const lienzoProductos = document.getElementById('productosChart');
        if (lienzoProductos) {
            graficos.push(new Chart(lienzoProductos, {
                type: 'bar',
                data: {
                    labels: productos.map(p => p.etiqueta.length > 30 ? p.etiqueta.slice(0, 27) + '...' : p.etiqueta),
                    datasets: [{
                        label: 'Unidades vendidas',
                        data: productos.map(p => p.total_vendido),
                        backgroundColor: rojo,
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            }));
        }
        }

        contenedor.addEventListener('change', evento => {
            if (evento.target.id === 'dashboard-mes') {
                irA({ mes: evento.target.value });
            }
        });

        contenedor.addEventListener('click', evento => {
            const enlace = evento.target.closest('#dashboard-clear-day');
            if (enlace && !evento.metaKey && !evento.ctrlKey) {
                evento.preventDefault();
                irA({ mes: JSON.parse(document.getElementById('dashboard-data').textContent).mes });
            }
        });

        window.addEventListener('popstate', () => {
            irA(Object.fromEntries(new URLSearchParams(window.location.search)), false);
        });

        montar();
    })();
</script>
@endsection
