@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="dashboard-container">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="header-content">
            <h1>Dashboard</h1>
            <p class="subtitle">Bienvenido, {{ auth()->user()->name }}</p>
        </div>
        <div class="header-date">
            <span id="current-date"></span>
        </div>
    </div>

    <!-- Estadísticas Generales -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon icon-sales">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <h3>Total Ventas</h3>
                <p class="stat-value">${{ number_format($estadisticasGenerales['total_ventas'], 0, ',', '.') }}</p>
                <small>Registros: {{ $estadisticasGenerales['total_ventas'] }}</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-revenue">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-content">
                <h3>Monto Total</h3>
                <p class="stat-value">${{ number_format($estadisticasGenerales['monto_total_ventas'], 2, ',', '.') }}</p>
                <small>Generado en ventas</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-inventory">
                <i class="fas fa-boxes"></i>
            </div>
            <div class="stat-content">
                <h3>Productos</h3>
                <p class="stat-value">{{ $estadisticasGenerales['total_productos'] }}</p>
                <small>En inventario</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-clients">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3>Clientes</h3>
                <p class="stat-value">{{ $estadisticasGenerales['total_clientes'] }}</p>
                <small>Registrados</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-today">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-content">
                <h3>Ventas Hoy</h3>
                <p class="stat-value">${{ number_format($estadisticasGenerales['ventas_hoy'], 2, ',', '.') }}</p>
                <small>{{ $estadisticasGenerales['cantidad_vendidas_hoy'] }} unidades</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon icon-categories">
                <i class="fas fa-tags"></i>
            </div>
            <div class="stat-content">
                <h3>Categorías</h3>
                <p class="stat-value">{{ $estadisticasGenerales['total_categorias'] }}</p>
                <small>Activas</small>
            </div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="charts-container">
        <div class="chart-card">
            <h2>Ventas Últimos 7 Días</h2>
            <canvas id="ventasChart"></canvas>
        </div>

        <div class="chart-card">
            <h2>Productos Más Vendidos</h2>
            <canvas id="productosChart"></canvas>
        </div>
    </div>

    <!-- Alertas y Acciones Rápidas -->
    <div class="quick-actions-container">
        @if($stockCritico->count() > 0)
        <div class="alert-card alert-warning">
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <h3>Stock Crítico</h3>
                <p>{{ $stockCritico->count() }} producto(s) con stock bajo</p>
                <a href="{{ route('productos.index') }}" class="btn-alert">Ver inventario</a>
            </div>
        </div>
        @endif

        <div class="quick-actions-grid">
            <h2>Acciones Rápidas</h2>
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
        padding: 30px;
        background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
        min-height: 100vh;
    }

    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-top: 5px solid #C41E3A;
    }

    .dashboard-header h1 {
        font-size: 2.5rem;
        color: #C41E3A;
        margin: 0;
    }

    .subtitle {
        color: #666;
        margin: 5px 0 0 0;
        font-size: 1.1rem;
    }

    .header-date {
        font-size: 1.2rem;
        color: #666;
        font-weight: 500;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
    }

    .icon-sales {
        background: linear-gradient(135deg, #C41E3A 0%, #A4161A 100%);
    }

    .icon-revenue {
        background: linear-gradient(135deg, #E63946 0%, #C41E3A 100%);
    }

    .icon-inventory {
        background: linear-gradient(135deg, #D62828 0%, #A4161A 100%);
    }

    .icon-clients {
        background: linear-gradient(135deg, #C41E3A 0%, #8B0000 100%);
    }

    .icon-today {
        background: linear-gradient(135deg, #E63946 0%, #D62828 100%);
    }

    .icon-categories {
        background: linear-gradient(135deg, #A4161A 0%, #740001 100%);
    }

    .stat-content h3 {
        margin: 0 0 10px 0;
        color: #333;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .stat-value {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 700;
        color: #C41E3A;
    }

    .stat-content small {
        color: #999;
        font-size: 0.9rem;
    }

    .charts-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .chart-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .chart-card h2 {
        margin: 0 0 20px 0;
        color: #333;
        font-size: 1.3rem;
    }

    .quick-actions-container {
        background: white;
        border-radius: 10px;
        padding: 30px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .alert-card {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 20px;
        border-radius: 8px;
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
    }

    .alert-content p {
        margin: 0 0 10px 0;
        color: #856404;
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
        margin: 0 0 20px 0;
        color: #333;
        font-size: 1.3rem;
    }

    .actions-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }

    .action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 25px 15px;
        border-radius: 10px;
        text-decoration: none;
        color: white;
        font-weight: 600;
        transition: transform 0.3s, box-shadow 0.3s;
        text-align: center;
        gap: 10px;
    }

    .action-btn i {
        font-size: 2rem;
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
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    @media (max-width: 768px) {
        .dashboard-header {
            flex-direction: column;
            text-align: center;
        }

        .dashboard-header h1 {
            font-size: 2rem;
        }

        .charts-container {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .actions-buttons {
            grid-template-columns: repeat(2, 1fr);
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

    // Gráfico de Ventas
    const ventasData = {!! json_encode($ventasUltimos7Dias) !!};
    const ventasCtx = document.getElementById('ventasChart').getContext('2d');
    new Chart(ventasCtx, {
        type: 'line',
        data: {
            labels: ventasData.map(v => new Date(v.fecha).toLocaleDateString('es-ES', { month: 'short', day: 'numeric' })),
            datasets: [{
                label: 'Ventas ($)',
                data: ventasData.map(v => parseFloat(v.total) || 0),
                borderColor: '#C41E3A',
                backgroundColor: 'rgba(196, 30, 58, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 6,
                pointBackgroundColor: '#C41E3A',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: { size: 12, weight: 'bold' }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString('es-ES');
                        }
                    }
                }
            }
        }
    });

    // Gráfico de Productos Más Vendidos
    const productosData = {!! json_encode($productosMasVendidos) !!};
    const productosCtx = document.getElementById('productosChart').getContext('2d');
    new Chart(productosCtx, {
        type: 'bar',
        data: {
            labels: productosData.map(p => p.nombre),
            datasets: [{
                label: 'Cantidad Vendida',
                data: productosData.map(p => p.total_vendido),
                backgroundColor: [
                    '#C41E3A',
                    '#E63946',
                    '#D62828',
                    '#A4161A',
                    '#8B0000'
                ],
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: { size: 12, weight: 'bold' }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                }
            }
        }
    });
</script>
@endsection
