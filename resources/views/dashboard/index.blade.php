@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $parametrosPeriodo = array_filter(['anio' => $seleccion['anio'], 'mes' => $seleccion['mes'], 'dia' => $seleccion['dia']]);
    $dinero = fn (float $monto): string => '$'.number_format($monto, 0, ',', '.');
@endphp
<div class="dashboard-container">
    @if($errors->any())
        <div class="alert-card" role="alert">{{ $errors->first() }}</div>
    @endif
    <div class="dashboard-header">
        <div class="header-content">
            <h1>Dashboard</h1>
            <p class="subtitle">Bienvenido, {{ auth()->user()->name }}. Aquí tienes el estado de tu negocio.</p>
        </div>
        <div class="header-actions">
            <div class="pdf-action">
                <p>Descargar PDF con el resumen general de los períodos filtrados.</p>
                <a
                    id="dashboard-export-pdf"
                    href="{{ route('dashboard.exportar.pdf', $parametrosPeriodo) }}"
                    class="btn-pdf"
                >
                    <i class="fas fa-file-pdf" aria-hidden="true"></i>
                    Descargar PDF
                </a>
            </div>
            <div class="header-date">
                <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                <span id="current-date"></span>
            </div>
        </div>
    </div>

    <div id="dashboard-live" aria-live="polite">
    @php
        $datosPanel = ['anio' => $seleccion['anio'], 'mes' => $seleccion['mes'], 'dia' => $seleccion['dia'], 'ventasMensuales' => $ventasMensuales, 'ventasSemanales' => $ventasSemanales, 'productos' => $productosMasVendidos, 'pdf' => route('dashboard.exportar.pdf', $parametrosPeriodo)];
    @endphp
    <script type="application/json" id="dashboard-data">@json($datosPanel)</script>
    <div class="period-row">
    <form method="GET" action="{{ route('dashboard') }}" class="period-bar" id="dashboard-period-form">
        <div class="period-current">
            <span class="chart-kicker">Período de los indicadores</span>
            <strong id="dashboard-period-label">{{ ucfirst($seleccion['etiqueta']) }}</strong>
            <small>Las ventas y gráficos corresponden a este período. Haz clic en una barra para detallarlo.</small>
        </div>
        <label class="period-select">
            <span>Año</span>
            <select name="anio" id="dashboard-anio" aria-label="Seleccionar año">
                @foreach($aniosDisponibles as $anio)
                    <option value="{{ $anio }}" @selected($anio === $seleccion['anio'])>{{ $anio }}</option>
                @endforeach
            </select>
        </label>
        <label class="period-select">
            <span>Mes</span>
            <select name="mes" id="dashboard-mes" aria-label="Seleccionar mes">
                <option value="">Todos los meses</option>
                @foreach(range(1, 12) as $mes)
                    <option value="{{ $mes }}" @selected($mes === $seleccion['mes'])>{{ ucfirst(\Carbon\Carbon::create(2000, $mes, 1)->locale('es')->isoFormat('MMMM')) }}</option>
                @endforeach
            </select>
        </label>
        @if($seleccion['dia'])
            <a href="{{ route('dashboard', ['anio' => $seleccion['anio'], 'mes' => $seleccion['mes']]) }}" class="btn-clear-day" id="dashboard-clear-day">
                <i class="fas fa-times" aria-hidden="true"></i> Ver todo el mes
            </a>
        @endif
        <noscript><button type="submit" class="btn-clear-day">Aplicar filtros</button></noscript>
    </form>
    @if($cantidadStockCritico > 0)
    <div class="alert-card stock-alert">
        <div class="alert-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></div>
        <div class="alert-content">
            <h3>Stock crítico actual</h3>
            <p>{{ number_format($cantidadStockCritico, 0, ',', '.') }} {{ $cantidadStockCritico === 1 ? 'producto con stock bajo' : 'productos con stock bajo' }}</p>
            @if(auth()->user()->tienePermiso('productos.ver'))
                <a href="{{ route('productos.index', ['stock' => 'critico']) }}" class="btn-alert">Ver stock crítico</a>
            @endif
        </div>
    </div>
    @endif
    </div>

    @if($resumen['total']['cantidad'] === 0)
        <p class="chart-empty">No hay ventas completadas para el período seleccionado.</p>
    @endif
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
            <div class="stat-icon icon-clients"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-content">
                <h3>Ventas por cotización</h3>
                <p class="stat-value">{{ number_format($resumen['cotizaciones']['cantidad'], 0, ',', '.') }}</p>
                <small>{{ $dinero($resumen['cotizaciones']['monto']) }} originados en cotizaciones</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-sales"><i class="fas fa-receipt"></i></div>
            <div class="stat-content">
                <h3>Cantidad de ventas totales</h3>
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
    </div>

    <div class="charts-container">
        @if($seleccion['mes'] === null)
        <div class="chart-card">
            <div class="chart-card-heading">
                <span class="chart-kicker">{{ $seleccion['anio'] }}</span>
                <h3>Ventas por mes</h3>
                <p>Las barras muestran el monto vendido y las etiquetas, la cantidad de ventas. Haz clic en una barra para filtrar por ese mes.</p>
            </div>
            <canvas id="ventasMensualesChart" aria-label="Ventas por mes"></canvas>
        </div>
        @endif
        @if($seleccion['mes'] !== null)
        <div class="chart-card">
            <div class="chart-card-heading">
                <span class="chart-kicker">{{ ucfirst($seleccion['etiqueta']) }}</span>
                <h3>Ventas por semana</h3>
                <p>Las barras muestran el monto vendido y las etiquetas, la cantidad de ventas por semana.</p>
            </div>
            <canvas id="ventasDiariasChart" aria-label="Ventas por semana"></canvas>
        </div>
        @endif

        <div class="chart-card">
            <div class="chart-card-heading">
                <span class="chart-kicker">Período seleccionado</span>
                <h3>Top 10 productos más vendidos</h3>
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
        --dashboard-red: #b52f25;
        --dashboard-accent: #b52f25;
        --dashboard-muted: #000;
        box-sizing: border-box;
        width: 100%;
        max-width: 1600px;
        min-width: 0;
        margin: 0 auto;
        padding: clamp(16px, 2.4vw, 36px);
        background: transparent;
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
        color: #000;
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

    .pdf-action p {
        max-width: 260px;
        margin: 0 0 8px;
        color: #000;
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
        color: #000;
    }

    .alert-content p {
        margin: 0 0 8px;
        color: #000;
    }

    .period-row {
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
        gap: 12px;
        margin-bottom: 20px;
    }

    .stock-alert {
        flex: 0 1 270px;
        gap: 12px;
        margin: 0;
        padding: 12px 16px;
    }

    .stock-alert .alert-icon { font-size: 1.25rem; }
    .stock-alert h3 { font-size: 0.9rem; }
    .stock-alert p { font-size: 0.8rem; }

    .btn-alert {
        display: inline-block;
        padding: 7px 14px;
        border-radius: 6px;
        background: #ffc107;
        color: #000;
        font-weight: 600;
        text-decoration: none;
    }

    .btn-alert:hover {
        background: #ffb300;
    }

    .period-bar {
        display: flex;
        flex: 1 1 550px;
        min-width: 0;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px 20px;
        margin: 0;
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
        color: #000;
        font-size: 1.25rem;
    }

    .period-current small {
        color: var(--dashboard-muted);
    }

    .period-select {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #000;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .period-select select {
        min-height: 38px;
        padding: 7px 30px 7px 10px;
        border: 1px solid #d9aaa3;
        border-radius: 8px;
        background: #fff;
        color: #000;
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
        grid-template-columns: repeat(2, minmax(0, 1fr));
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
        border: 1px solid rgba(143, 36, 29, .12);
        background: rgba(255, 255, 255, .72);
        color: #8f241d;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .9);
        -webkit-backdrop-filter: blur(10px);
        backdrop-filter: blur(10px);
    }

    .icon-sales,
    .icon-revenue,
    .icon-clients,
    .icon-today {
        background: rgba(255, 255, 255, .72);
        color: #8f241d;
    }

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
        color: #000;
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
        color: #000;
        font-size: clamp(1rem, 1.5vw, 1.2rem);
        font-weight: 800;
    }

    .chart-card-heading p {
        margin: 4px 0 0;
        color: var(--dashboard-muted);
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

    @media (max-width: 800px) {
        .charts-container { grid-template-columns: minmax(0, 1fr); }
        .stock-alert { flex: 1 1 100%; }
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
        const rojo = '#B52F25';
        const rojoClaro = '#D66B60';
        const formatoDinero = valor => '$' + Number(valor).toLocaleString('es-CL');
        const escalaDinero = { beginAtZero: true, ticks: { color: '#000', callback: formatoDinero } };
        const etiquetasBarras = {
            id: 'etiquetasBarras',
            afterDatasetsDraw(grafico, argumentos, opciones) {
                const { ctx } = grafico;
                ctx.save();
                ctx.fillStyle = '#000';
                ctx.font = 'bold 12px sans-serif';
                grafico.getDatasetMeta(0).data.forEach((barra, indice) => {
                    const texto = Number(opciones.valores[indice]).toLocaleString('es-CL');
                    if (grafico.options.indexAxis === 'y') {
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(texto, Math.min(barra.x + 6, grafico.width - ctx.measureText(texto).width - 2), barra.y);
                    } else {
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        ctx.fillText(texto, barra.x, barra.y - 5);
                    }
                });
                ctx.restore();
            }
        };

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
                plugins: [etiquetasBarras],
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
                    layout: { padding: { top: 20 } },
                    interaction: { mode: 'index', axis: 'x', intersect: false },
                    plugins: {
                        legend: { display: false },
                        etiquetasBarras: { valores: datos.map(d => d.cantidad) },
                        tooltip: {
                            callbacks: {
                                label: contexto => formatoDinero(contexto.parsed.y) + ' · ' + tooltipExtra(datos[contexto.dataIndex])
                            }
                        }
                    },
                    scales: { x: { ticks: { color: '#000' } }, y: escalaDinero },
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
        const { anio, mes: mesSeleccionado, dia: diaSeleccionado, ventasMensuales, ventasSemanales, productos, pdf } =
            JSON.parse(document.getElementById('dashboard-data').textContent);
        document.getElementById('dashboard-export-pdf').href = pdf;

        if (document.getElementById('ventasMensualesChart')) graficos.push(graficoBarras(
            'ventasMensualesChart',
            ventasMensuales,
            () => true,
            d => irA({ anio, mes: Number(d.periodo.slice(5)) }),
            d => d.cantidad + (d.cantidad === 1 ? ' venta' : ' ventas')
        ));

        if (document.getElementById('ventasDiariasChart')) graficos.push(graficoBarras(
            'ventasDiariasChart',
            ventasSemanales,
            () => true,
            () => {},
            d => d.cantidad + (d.cantidad === 1 ? ' venta' : ' ventas')
        ));

        const lienzoProductos = document.getElementById('productosChart');
        if (lienzoProductos) {
            graficos.push(new Chart(lienzoProductos, {
                type: 'bar',
                plugins: [etiquetasBarras],
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
                    layout: { padding: { right: 48 } },
                    plugins: {
                        legend: { display: false },
                        etiquetasBarras: { valores: productos.map(p => p.total_vendido) },
                        tooltip: {
                            callbacks: {
                                title: elementos => productos[elementos[0].dataIndex].etiqueta,
                                afterLabel: contexto => 'Stock actual: ' + Number(productos[contexto.dataIndex].stock).toLocaleString('es-CL'),
                            }
                        }
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0, color: '#000' } },
                        y: { ticks: { color: '#000' } },
                    }
                }
            }));
        }
        }

        contenedor.addEventListener('change', evento => {
            if (evento.target.id === 'dashboard-mes' || evento.target.id === 'dashboard-anio') {
                irA({ anio: document.getElementById('dashboard-anio').value, mes: document.getElementById('dashboard-mes').value });
            }
        });

        contenedor.addEventListener('click', evento => {
            const enlace = evento.target.closest('#dashboard-clear-day');
            if (enlace && !evento.metaKey && !evento.ctrlKey) {
                evento.preventDefault();
                const { anio, mes } = JSON.parse(document.getElementById('dashboard-data').textContent);
                irA({ anio, mes });
            }
        });

        window.addEventListener('popstate', () => {
            irA(Object.fromEntries(new URLSearchParams(window.location.search)), false);
        });

        montar();
    })();
</script>
@endsection
