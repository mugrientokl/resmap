@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="header-content">
            <p class="dashboard-eyebrow">Centro de operaciones</p>
            <h1>Resumen general</h1>
            <p class="subtitle">Bienvenido, {{ auth()->user()->name }}. Aquí tienes el estado de tu negocio.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a
                id="dashboard-export-pdf"
                href="{{ route('dashboard.exportar.pdf', ['agrupacion' => 'month']) }}"
                class="relative z-10 inline-flex items-center gap-2 rounded-lg bg-[#b52f25] px-4 py-3 font-bold text-white shadow-sm transition hover:bg-[#8f241d]"
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

    <div class="section-heading">
        <div>
            <h2>Indicadores principales</h2>
            <p>Resumen de montos y cantidades de ventas por período y canal.</p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon icon-revenue">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-content">
                <h3>Monto total vendido</h3>
                <p class="stat-value">${{ number_format($estadisticasGenerales['monto_total_ventas'], 2, ',', '.') }}</p>
                <small>En ventas completadas</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-sales">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-content">
                <h3>Monto vendido esta semana</h3>
                <p class="stat-value">${{ number_format($resumenSemana['monto'], 2, ',', '.') }}</p>
                <small>Ventas completadas desde el lunes</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-today">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-content">
                <h3>Monto vendido hoy</h3>
                <p class="stat-value">${{ number_format($estadisticasGenerales['ventas_hoy'], 2, ',', '.') }}</p>
                <small>Monto de ventas completadas hoy</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-revenue">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-content">
                <h3>Monto vendido este mes</h3>
                <p class="stat-value">${{ number_format($resumenMes['monto'], 2, ',', '.') }}</p>
                <small>Ventas completadas desde el día 1</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-sales">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-content">
                <h3>Cantidad de ventas esta semana</h3>
                <p class="stat-value">{{ number_format($resumenSemana['cantidad'], 0, ',', '.') }}</p>
                <small>Ventas completadas desde el lunes</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-today">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-content">
                <h3>Cantidad de ventas hoy</h3>
                <p class="stat-value">{{ number_format($estadisticasGenerales['cantidad_ventas_hoy'], 0, ',', '.') }}</p>
                <small>Ventas completadas durante el día</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-clients">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-content">
                <h3>Ventas completadas desde cotizaciones</h3>
                <p class="stat-value">{{ number_format($ventasCotizacionesCompletadas, 0, ',', '.') }}</p>
                <small>Ventas originadas en cotizaciones</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-inventory">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <h3>Total de cotizaciones</h3>
                <p class="stat-value">{{ number_format($totalCotizaciones, 0, ',', '.') }}</p>
                <small>Sin importar su estado</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-sales">
                <i class="fas fa-cash-register"></i>
            </div>
            <div class="stat-content">
                <h3>Ventas completadas en POS</h3>
                <p class="stat-value">{{ number_format($ventasPos['cantidad'], 0, ',', '.') }}</p>
                <small>Ventas registradas en el punto de venta</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-revenue">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <h3>Monto total vendido en POS</h3>
                <p class="stat-value">${{ number_format($ventasPos['monto'], 2, ',', '.') }}</p>
                <small>Ventas completadas en el punto de venta</small>
            </div>
        </div>
    </div>

    <div class="section-heading charts-section-heading">
        <div>
            <h2>Análisis de ventas</h2>
            <p>Consulta cuánto vendiste y qué productos tuvieron más salida.</p>
        </div>
    </div>

    <div class="charts-container">
        <div class="chart-card">
            <div class="chart-card-heading">
                <span class="chart-kicker">Últimos 7 días</span>
                <h3>Ventas por día</h3>
                <p>Monto total vendido en cada día. Las fechas incluyen mes y año.</p>
            </div>
            <canvas id="ventasDiariasChart"></canvas>
        </div>

        <div class="chart-card">
            <div class="chart-card-header">
                <div class="chart-card-heading">
                    <span class="chart-kicker">Tendencia</span>
                    <h3 id="ventasPeriodoTitulo">Ventas por período</h3>
                    <p id="ventasPeriodoSubtitulo">Monto total vendido por mes durante los últimos 6 meses.</p>
                </div>
                <label class="chart-period-filter">
                    <span>Agrupar por</span>
                    <select id="ventasPeriodoSelect" aria-label="Filtrar ventas por período">
                        <option value="month">Mes</option>
                        <option value="week">Semana</option>
                    </select>
                </label>
                <label class="chart-period-filter" id="ventasMesFiltroContainer" hidden>
                    <span>Mes</span>
                    <select id="ventasMesSelect" aria-label="Seleccionar mes para ver ventas semanales">
                        @foreach($ventasMensuales as $mes)
                            <option value="{{ $mes['periodo'] }}" @selected($mes['periodo'] === now()->format('Y-m'))>{{ $mes['etiqueta'] }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <canvas id="ventasMensualesChart"></canvas>
        </div>

        <div class="chart-card">
            <div class="chart-card-heading">
                <span class="chart-kicker">Productos con más ventas</span>
                <h3>Productos más vendidos esta semana</h3>
                <p>Los 5 productos con más unidades vendidas esta semana.</p>
            </div>
            <canvas id="productosSemanaChart"></canvas>
        </div>

        <div class="chart-card">
            <div class="chart-card-heading">
                <span class="chart-kicker">Productos con más ventas</span>
                <h3>Productos más vendidos este mes</h3>
                <p>Los 5 productos con más unidades vendidas este mes.</p>
            </div>
            <canvas id="productosMesChart"></canvas>
        </div>
    </div>

    <div class="quick-actions-container">
        @if($cantidadStockCritico > 0)
        <div class="alert-card alert-warning">
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <h3>Stock Crítico</h3>
                <p>{{ number_format($cantidadStockCritico, 0, ',', '.') }} {{ $cantidadStockCritico === 1 ? 'producto con stock bajo' : 'productos con stock bajo' }}</p>
                <a href="{{ route('productos.index', ['stock' => 'critico']) }}" class="btn-alert">Ver stock crítico</a>
            </div>
        </div>
        @endif

        <div class="quick-actions-grid">
            <div class="section-heading">
                <div>
                    <h2>Accesos rápidos</h2>
                    <p>Ve directamente a las tareas más frecuentes.</p>
                </div>
            </div>
            <div class="actions-buttons">
                <a href="{{ route('pos.index') }}" class="action-btn btn-pos">
                    <i class="fas fa-cash-register"></i>
                    <span>POS / Ventas</span>
                </a>

                <a href="{{ route('productos.index') }}" class="action-btn btn-inventory">
                    <i class="fas fa-warehouse"></i>
                    <span>Inventario</span>
                </a>

                @if(auth()->user()->rol === 'Administrador')
                <a href="{{ route('categorias.index') }}" class="action-btn btn-categories">
                    <i class="fas fa-layer-group"></i>
                    <span>Categorías</span>
                </a>

                @endif

                <a href="{{ route('clientes.index') }}" class="action-btn btn-clients">
                    <i class="fas fa-user-tie"></i>
                    <span>Clientes</span>
                </a>

                @if(auth()->user()->rol === 'Administrador')
                <a href="{{ route('reportes.index') }}" class="action-btn btn-reports">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reportes</span>
                </a>
                @endif

                @if(auth()->user()->rol === 'Administrador')
                <a href="{{ route('inventario.movimientos') }}" class="action-btn btn-movements">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Movimientos</span>
                </a>
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
        position: relative;
        overflow: hidden;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 24px;
        margin-bottom: clamp(28px, 4vw, 44px);
        background: linear-gradient(120deg, #fff 0%, #fff 68%, #fff5f3 100%);
        padding: clamp(24px, 3.5vw, 42px);
        border: 1px solid #eee8e7;
        border-left: 5px solid var(--dashboard-accent);
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(36, 24, 23, 0.06);
        box-sizing: border-box;
    }

    .dashboard-header::after {
        position: absolute;
        top: -90px;
        right: 12%;
        width: 230px;
        height: 230px;
        border: 1px solid rgba(196, 30, 58, 0.08);
        border-radius: 50%;
        box-shadow: 0 0 0 28px rgba(196, 30, 58, 0.025), 0 0 0 58px rgba(196, 30, 58, 0.02);
        content: "";
        pointer-events: none;
    }

    .header-content,
    .header-date {
        position: relative;
        z-index: 1;
    }

    .dashboard-eyebrow,
    .chart-kicker {
        margin: 0 0 8px;
        color: var(--dashboard-accent);
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .dashboard-header h1 {
        font-size: clamp(1.85rem, 4vw, 2.7rem);
        line-height: 1.12;
        color: #241817;
        margin: 0;
        overflow-wrap: anywhere;
    }

    .subtitle {
        color: var(--dashboard-muted);
        margin: 10px 0 0;
        font-size: clamp(0.95rem, 1.5vw, 1.05rem);
        line-height: 1.6;
        overflow-wrap: anywhere;
    }

    .header-date {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 11px 15px;
        border: 1px solid #f0dfdc;
        border-radius: 999px;
        background: #fff;
        color: #5f4c49;
        font-size: clamp(0.85rem, 1.5vw, 0.98rem);
        font-weight: 700;
        box-shadow: 0 3px 10px rgba(36, 24, 23, 0.04);
    }

    .header-date i {
        color: var(--dashboard-accent);
    }

    .section-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
    }

    .section-heading h2 {
        margin: 0;
        color: #241817;
        font-size: clamp(1.15rem, 2vw, 1.4rem);
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .section-heading p {
        margin: 5px 0 0;
        color: var(--dashboard-muted);
        font-size: 0.9rem;
        line-height: 1.5;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: clamp(12px, 1.4vw, 20px);
        margin-bottom: clamp(32px, 4vw, 48px);
        min-width: 0;
    }

    .stat-card {
        box-sizing: border-box;
        min-width: 0;
        min-height: 172px;
        background: white;
        border: 1px solid #ececf0;
        border-radius: 14px;
        padding: clamp(16px, 1.7vw, 23px);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        text-align: center;
        box-shadow: 0 4px 14px rgba(36, 24, 23, 0.045);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        border-color: #e6c6c2;
        box-shadow: 0 10px 24px rgba(36, 24, 23, 0.09);
    }

    .stat-icon {
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        color: white;
        box-shadow: 0 5px 12px rgba(164, 22, 26, 0.18);
    }

    .icon-sales {
        background: linear-gradient(135deg, #c41e3a 0%, #a4161a 100%);
    }

    .icon-revenue {
        background: linear-gradient(135deg, #e63946 0%, #c41e3a 100%);
    }

    .icon-inventory {
        background: linear-gradient(135deg, #d62828 0%, #a4161a 100%);
    }

    .icon-clients {
        background: linear-gradient(135deg, #c41e3a 0%, #8b0000 100%);
    }

    .icon-today {
        background: linear-gradient(135deg, #e63946 0%, #d62828 100%);
    }

    .icon-categories {
        background: linear-gradient(135deg, #a4161a 0%, #740001 100%);
    }

    .stat-content h3 {
        margin: 0 0 7px;
        color: #514542;
        font-size: clamp(0.84rem, 1.1vw, 0.96rem);
        font-weight: 700;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .stat-value {
        margin: 0;
        color: var(--dashboard-accent);
        font-size: clamp(1.2rem, 1.75vw, 1.65rem);
        font-weight: 800;
        line-height: 1.2;
        letter-spacing: -0.035em;
        overflow-wrap: anywhere;
    }

    .stat-content small {
        display: block;
        margin-top: 6px;
        color: #85838a;
        font-size: 0.77rem;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .charts-section-heading {
        margin-bottom: 18px;
    }

    .charts-container {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: clamp(14px, 1.7vw, 22px);
        margin-bottom: clamp(28px, 4vw, 48px);
        min-width: 0;
    }

    .chart-card {
        box-sizing: border-box;
        min-width: 0;
        background: white;
        border: 1px solid #ececf0;
        border-radius: 14px;
        padding: clamp(18px, 2vw, 26px);
        box-shadow: 0 4px 14px rgba(36, 24, 23, 0.045);
    }

    .chart-card-heading {
        min-width: 0;
        margin-bottom: 16px;
    }

    .chart-kicker {
        display: block;
        margin-bottom: 5px;
        font-size: 0.65rem;
        letter-spacing: 0.12em;
    }

    .chart-card-heading h3 {
        margin: 0;
        color: #302523;
        font-size: clamp(1rem, 1.5vw, 1.2rem);
        font-weight: 800;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .chart-card-heading p {
        margin: 4px 0 0;
        color: var(--dashboard-muted);
        font-size: 0.82rem;
        line-height: 1.45;
    }

    .chart-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px 16px;
        margin-bottom: 16px;
    }

    .chart-card-header .chart-card-heading {
        flex: 1 1 220px;
    }

    .chart-period-filter {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #4b5563;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .chart-period-filter select {
        min-height: 38px;
        border: 1px solid #d9aaa3;
        border-radius: 8px;
        background: white;
        padding: 7px 30px 7px 10px;
        color: var(--dashboard-red);
        font: inherit;
        cursor: pointer;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .chart-period-filter select:focus {
        border-color: var(--dashboard-accent);
        outline: none;
        box-shadow: 0 0 0 3px rgba(196, 30, 58, 0.12);
    }

    .chart-period-filter[hidden] {
        display: none;
    }

    .chart-card canvas {
        display: block;
        width: 100% !important;
        height: clamp(220px, 26vw, 320px) !important;
    }

    .quick-actions-container {
        box-sizing: border-box;
        min-width: 0;
        background: white;
        border: 1px solid #ececf0;
        border-radius: 14px;
        padding: clamp(18px, 2.4vw, 30px);
        box-shadow: 0 4px 14px rgba(36, 24, 23, 0.045);
    }

    .alert-card {
        display: flex;
        align-items: center;
        min-width: 0;
        gap: 20px;
        padding: 20px;
        border: 1px solid #f1dda5;
        border-left: 5px solid #e8a900;
        border-radius: 12px;
        margin-bottom: 30px;
    }

    .alert-warning {
        background: #fff3cd;
        border-left: 5px solid #ffc107;
    }

    .alert-icon {
        font-size: 2rem;
        color: #ffc107;
    }

    .alert-content h3 {
        margin: 0 0 5px 0;
        color: #856404;
        overflow-wrap: anywhere;
    }

    .alert-content p {
        margin: 0 0 10px 0;
        color: #856404;
        overflow-wrap: anywhere;
    }

    .btn-alert {
        display: inline-block;
        padding: 8px 15px;
        background: #ffc107;
        color: #333;
        text-decoration: none;
        border-radius: 5px;
        font-weight: 600;
        transition: background 0.3s;
    }

    .btn-alert:hover {
        background: #ffb300;
    }

    .quick-actions-grid h2 {
        margin: 0;
        color: #241817;
        font-size: clamp(1.15rem, 2vw, 1.4rem);
    }

    .actions-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 145px), 1fr));
        gap: clamp(10px, 1.4vw, 16px);
    }

    .action-btn {
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 116px;
        padding: 20px 14px;
        border-radius: 12px;
        text-decoration: none;
        color: white;
        font-weight: 600;
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        text-align: center;
        gap: 10px;
        overflow-wrap: anywhere;
        box-shadow: 0 4px 12px rgba(36, 24, 23, 0.1);
    }

    .action-btn i {
        font-size: 1.65rem;
    }

    .action-btn span {
        font-size: 0.95rem;
    }

    .btn-pos {
        background: linear-gradient(135deg, #C41E3A 0%, #A4161A 100%);
    }

    .btn-inventory {
        background: linear-gradient(135deg, #E63946 0%, #C41E3A 100%);
    }

    .btn-categories {
        background: linear-gradient(135deg, #D62828 0%, #A4161A 100%);
    }

    .btn-clients {
        background: linear-gradient(135deg, #C41E3A 0%, #8B0000 100%);
    }

    .btn-reports {
        background: linear-gradient(135deg, #E63946 0%, #D62828 100%);
    }

    .btn-movements {
        background: linear-gradient(135deg, #A4161A 0%, #740001 100%);
    }

    .action-btn:hover {
        transform: translateY(-3px);
        filter: brightness(1.04);
        box-shadow: 0 9px 18px rgba(36, 24, 23, 0.16);
    }

    @media (max-width: 1280px) {
        .stats-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    @media (max-width: 1024px) {
        .stats-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 800px) {
        .dashboard-header {
            align-items: flex-start;
        }

        .header-date {
            text-align: left;
        }

        .charts-container {
            grid-template-columns: minmax(0, 1fr);
        }

        .stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .actions-buttons {
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 130px), 1fr));
        }
    }

    @media (max-width: 520px) {
        .dashboard-header {
            gap: 18px;
            padding: 22px 18px;
        }

        .stats-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .stat-card {
            min-height: 0;
            flex-direction: row;
            align-items: center;
            gap: 14px;
            padding: 16px;
            text-align: left;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            flex-basis: 40px;
            font-size: 1rem;
        }

        .stat-content {
            min-width: 0;
        }

        .chart-card-header {
            align-items: flex-start;
        }

        .chart-period-filter {
            justify-content: space-between;
            width: 100%;
        }

        .header-date {
            font-size: 0.9rem;
        }

        .alert-card {
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .stat-card,
        .action-btn,
        .chart-period-filter select {
            transition: none;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Actualizar fecha
    function updateDate() {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const today = new Date().toLocaleDateString('es-ES', options);
        document.getElementById('current-date').textContent = today.charAt(0).toUpperCase() + today.slice(1);
    }
    updateDate();

    function renderizarGrafico(elementoId, datos, campoValor, etiqueta, tipo, opciones = {}) {
        const colores = ['#C41E3A', '#E63946', '#D62828', '#A4161A', '#8B0000'];

        return new Chart(document.getElementById(elementoId), {
            type: tipo,
            data: {
                labels: datos.map(dato => dato.etiqueta),
                datasets: [{
                    label: etiqueta,
                    data: datos.map(dato => Number(dato[campoValor]) || 0),
                    borderColor: '#C41E3A',
                    backgroundColor: tipo === 'line' ? 'rgba(196, 30, 58, 0.12)' : colores,
                    borderWidth: tipo === 'line' ? 3 : 1,
                    borderRadius: tipo === 'bar' ? 8 : 0,
                    borderSkipped: false,
                    fill: tipo === 'line',
                    tension: 0.35,
                    pointRadius: tipo === 'line' ? 4 : 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: opciones.horizontal ? 'y' : 'x',
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        type: opciones.horizontal ? 'linear' : undefined,
                        beginAtZero: true,
                        ticks: {
                            precision: opciones.horizontal ? 0 : undefined,
                            maxRotation: opciones.horizontal ? 0 : 45,
                            minRotation: opciones.horizontal ? 0 : 0,
                        }
                    },
                    y: {
                        type: opciones.horizontal ? 'category' : undefined,
                        beginAtZero: !opciones.horizontal,
                        ticks: {
                            autoSkip: !opciones.horizontal,
                            callback: opciones.horizontal
                                ? valor => {
                                    const nombreProducto = datos[valor]?.etiqueta ?? String(valor);
                                    return nombreProducto.length > 28
                                        ? `${nombreProducto.slice(0, 25)}...`
                                        : nombreProducto;
                                }
                                : opciones.monetario
                                    ? valor => '$' + Number(valor).toLocaleString('es-CL')
                                    : undefined
                        }
                    }
                }
            }
        });
    }

    renderizarGrafico(
        'ventasDiariasChart',
        @js($ventasDiarias),
        'total',
        'Ventas ($)',
        'line',
        { monetario: true }
    );
    const coloresVentas = ['#C41E3A', '#E63946', '#D62828', '#A4161A', '#8B0000'];
    const ventasPorMes = @js($ventasMensuales);
    const ventasSemanalesPorMes = @js($ventasSemanalesPorMes);
    const ventasPorPeriodoChart = new Chart(document.getElementById('ventasMensualesChart'), {
        type: 'bar',
        data: {
            labels: ventasPorMes.map(dato => dato.etiqueta),
            datasets: [{
                label: 'Ventas ($)',
                data: ventasPorMes.map(dato => Number(dato.total) || 0),
                backgroundColor: ventasPorMes.map((_, indice) => coloresVentas[indice % coloresVentas.length]),
                borderColor: '#C41E3A',
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: valor => '$' + Number(valor).toLocaleString('es-CL')
                    }
                }
            }
        }
    });

    const ventasPeriodoSelect = document.getElementById('ventasPeriodoSelect');
    const ventasMesSelect = document.getElementById('ventasMesSelect');
    const ventasMesFiltroContainer = document.getElementById('ventasMesFiltroContainer');
    const ventasPeriodoSubtitulo = document.getElementById('ventasPeriodoSubtitulo');
    const exportarPdf = document.getElementById('dashboard-export-pdf');
    const urlExportarPdf = @json(route('dashboard.exportar.pdf'));

    function actualizarUrlExportarPdf() {
        const parametros = new URLSearchParams({
            agrupacion: ventasPeriodoSelect.value
        });

        if (ventasPeriodoSelect.value === 'week') {
            parametros.set('mes', ventasMesSelect.value);
        }

        exportarPdf.href = `${urlExportarPdf}?${parametros.toString()}`;
    }

    function actualizarGraficoVentasPorPeriodo() {
        const esVistaSemanal = ventasPeriodoSelect.value === 'week';
        const datos = esVistaSemanal
            ? ventasSemanalesPorMes[ventasMesSelect.value] || []
            : ventasPorMes;
        const etiquetaMes = ventasMesSelect.selectedOptions[0].textContent;

        ventasPorPeriodoChart.data.labels = datos.map(dato => dato.etiqueta);
        ventasPorPeriodoChart.data.datasets[0].data = datos.map(dato => Number(dato.total) || 0);
        ventasPorPeriodoChart.data.datasets[0].backgroundColor = datos.map(
            (_, indice) => coloresVentas[indice % coloresVentas.length]
        );
        ventasPeriodoSubtitulo.textContent = esVistaSemanal
            ? `Monto total vendido por semana de ${etiquetaMes.toLowerCase()}.`
            : 'Monto total vendido por mes durante los últimos 6 meses.';
        ventasMesFiltroContainer.hidden = !esVistaSemanal;
        ventasPorPeriodoChart.update();
        actualizarUrlExportarPdf();
    }

    ventasPeriodoSelect.addEventListener('change', actualizarGraficoVentasPorPeriodo);
    ventasMesSelect.addEventListener('change', actualizarGraficoVentasPorPeriodo);

    renderizarGrafico(
        'productosSemanaChart',
        @js($productosMasVendidosSemana),
        'total_vendido',
        'Unidades vendidas',
        'bar',
        { horizontal: true }
    );
    renderizarGrafico(
        'productosMesChart',
        @js($productosMasVendidosMes),
        'total_vendido',
        'Unidades vendidas',
        'bar',
        { horizontal: true }
    );
</script>
@endsection
