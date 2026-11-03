<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Dashboard RESMAP</title>
    <style>
        @page { margin: 28px 32px; }
        body { color: #241817; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1, h2 { color: #8f241d; }
        h1 { margin: 0; font-size: 22px; }
        h2 { margin: 18px 0 8px; font-size: 14px; }
        p { margin: 5px 0; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #8f241d; color: white; text-align: left; }
        th, td { border: 1px solid #e4d0cd; padding: 7px; }
        .header { border-bottom: 3px solid #b52f25; padding-bottom: 12px; }
        .brand { color: #b52f25; font-size: 11px; font-weight: bold; letter-spacing: 2px; }
        .muted { color: #756967; }
        .right { text-align: right; }
        .filters td:first-child { background: #fcf4f3; font-weight: bold; width: 32%; }
        .metrics th { background: #241817; }
        .metrics .value { font-weight: bold; text-align: right; white-space: nowrap; }
        .section { page-break-inside: avoid; }
        .empty { color: #756967; padding: 12px; text-align: center; }
        .footer { margin-top: 20px; color: #756967; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td style="border: 0; padding: 0;">
                    <div class="brand">RESMAP · CENTRO DE OPERACIONES</div>
                    <h1>Resumen general</h1>
                </td>
                <td class="right muted" style="border: 0; padding: 0;">
                    Fecha de generación<br>
                    <strong>{{ $fechaGeneracion->format('d/m/Y H:i') }}</strong>
                </td>
            </tr>
        </table>
    </div>

    <h2>Filtros aplicados</h2>
    <table class="filters">
        <tr>
            <td>Agrupar ventas por</td>
            <td>{{ $agrupacion === 'week' ? 'Semana' : 'Mes' }}</td>
        </tr>
        @if($agrupacion === 'week')
            <tr>
                <td>Mes seleccionado</td>
                <td>{{ $mesSeleccionado }}</td>
            </tr>
        @endif
        <tr>
            <td>Período de los indicadores</td>
            <td>Hoy, semana actual, mes actual y últimos 6 meses, según corresponda.</td>
        </tr>
    </table>

    <div class="section">
        <h2>Indicadores principales</h2>
        <table class="metrics">
            <thead>
                <tr>
                    <th>Indicador</th>
                    <th>Período</th>
                    <th class="right">Cantidad</th>
                    <th class="right">Monto</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Ventas completadas</td>
                    <td>Histórico</td>
                    <td class="value">—</td>
                    <td class="value">$ {{ number_format($estadisticasGenerales['monto_total_ventas'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Ventas</td>
                    <td>Semana actual</td>
                    <td class="value">{{ number_format($resumenSemana['cantidad'], 0, ',', '.') }}</td>
                    <td class="value">$ {{ number_format($resumenSemana['monto'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Ventas</td>
                    <td>Hoy</td>
                    <td class="value">{{ number_format($estadisticasGenerales['cantidad_ventas_hoy'], 0, ',', '.') }}</td>
                    <td class="value">$ {{ number_format($estadisticasGenerales['ventas_hoy'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Ventas</td>
                    <td>Mes actual</td>
                    <td class="value">{{ number_format($resumenMes['cantidad'], 0, ',', '.') }}</td>
                    <td class="value">$ {{ number_format($resumenMes['monto'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Ventas originadas en cotizaciones</td>
                    <td>Histórico</td>
                    <td class="value">{{ number_format($ventasCotizacionesCompletadas, 0, ',', '.') }}</td>
                    <td class="value">—</td>
                </tr>
                <tr>
                    <td>Cotizaciones registradas</td>
                    <td>Histórico</td>
                    <td class="value">{{ number_format($totalCotizaciones, 0, ',', '.') }}</td>
                    <td class="value">—</td>
                </tr>
                <tr>
                    <td>Ventas registradas en POS</td>
                    <td>Histórico</td>
                    <td class="value">{{ number_format($ventasPos['cantidad'], 0, ',', '.') }}</td>
                    <td class="value">$ {{ number_format($ventasPos['monto'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Productos con stock crítico</td>
                    <td>Actual</td>
                    <td class="value">{{ number_format($cantidadStockCritico, 0, ',', '.') }}</td>
                    <td class="value">—</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Ventas por día · últimos 7 días</h2>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th class="right">Total vendido</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventasDiarias as $venta)
                    <tr>
                        <td>{{ $venta['etiqueta'] }}</td>
                        <td class="right">$ {{ number_format($venta['total'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Ventas por {{ $agrupacion === 'week' ? 'semana · '.$mesSeleccionado : 'mes · últimos 6 meses' }}</h2>
        <table>
            <thead>
                <tr>
                    <th>Período</th>
                    <th class="right">Total vendido</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ventasPorPeriodo as $venta)
                    <tr>
                        <td>{{ $venta['etiqueta'] }}</td>
                        <td class="right">$ {{ number_format($venta['total'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="empty">No hay ventas para el período seleccionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Productos más vendidos</h2>
        <table>
            <thead>
                <tr>
                    <th>Esta semana</th>
                    <th class="right">Unidades</th>
                    <th>Este mes</th>
                    <th class="right">Unidades</th>
                </tr>
            </thead>
            <tbody>
                @for($indice = 0; $indice < max(count($productosMasVendidosSemana), count($productosMasVendidosMes)); $indice++)
                    <tr>
                        <td>{{ $productosMasVendidosSemana[$indice]['etiqueta'] ?? '—' }}</td>
                        <td class="right">{{ isset($productosMasVendidosSemana[$indice]) ? number_format($productosMasVendidosSemana[$indice]['total_vendido'], 0, ',', '.') : '—' }}</td>
                        <td>{{ $productosMasVendidosMes[$indice]['etiqueta'] ?? '—' }}</td>
                        <td class="right">{{ isset($productosMasVendidosMes[$indice]) ? number_format($productosMasVendidosMes[$indice]['total_vendido'], 0, ',', '.') : '—' }}</td>
                    </tr>
                @endfor
                @if(count($productosMasVendidosSemana) === 0 && count($productosMasVendidosMes) === 0)
                    <tr>
                        <td colspan="4" class="empty">No hay productos vendidos en los períodos seleccionados.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <p class="footer">Reporte interno generado desde el dashboard RESMAP.</p>
</body>
</html>
