<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Dashboard RESMAP</title>
    <style>
        @page { margin: 30px 36px 36px; }
        body { color: #292524; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        table { border-collapse: collapse; width: 100%; }
        .header { border-bottom: 3px solid #9f2f25; padding-bottom: 14px; }
        .header td { vertical-align: middle; }
        .logo { height: 54px; width: auto; }
        .right { text-align: right; }
        .muted { color: #78716c; }
        h1 { color: #9f2f25; font-size: 20px; margin: 0 0 4px; }
        h2 { color: #292524; font-size: 11px; margin: 20px 0 6px; }
        .period { background: #faf7f6; border-left: 3px solid #9f2f25; margin-top: 16px; padding: 10px 12px; line-height: 1.6; }
        .period strong { color: #9f2f25; font-size: 12px; }
        .cards { border-collapse: separate; border-spacing: 6px 0; margin: 14px -6px 0; width: 103%; }
        .cards td { background: #fff; border: 1px solid #e7e5e4; border-top: 3px solid #9f2f25; padding: 9px 10px; vertical-align: top; width: 25%; }
        .cards .label { color: #78716c; font-size: 8px; text-transform: uppercase; }
        .cards .value { color: #292524; font-size: 15px; font-weight: bold; margin: 4px 0 2px; }
        .chart { margin-top: 6px; page-break-inside: avoid; }
        .bars td { height: 100px; padding: 0 2px; text-align: center; vertical-align: bottom; }
        .bars .bar { background: #9f2f25; margin: 0 auto; width: 70%; }
        .bars .bar-light { background: #e8a3ae; margin: 0 auto; width: 70%; }
        .bars .value-label { color: #57534e; font-size: 6.5px; padding-bottom: 2px; }
        .labels td { color: #57534e; font-size: 7px; padding-top: 3px; text-align: center; }
        .labels-day td { font-size: 6px; }
        .axis { border-top: 1px solid #a8a29e; }
        .top td { border-bottom: 1px solid #f0efee; padding: 5px 4px; vertical-align: middle; }
        .top .rank { color: #9f2f25; font-weight: bold; width: 16px; }
        .top .name { width: 38%; }
        .top .track { width: 46%; }
        .top .fill { background: #9f2f25; height: 9px; }
        .top .qty { font-weight: bold; text-align: right; width: 12%; }
        .empty { color: #78716c; padding: 14px; text-align: center; }
        .footer { border-top: 1px solid #d6d3d1; color: #78716c; margin-top: 22px; padding-top: 8px; text-align: center; }
    </style>
</head>
<body>
    @php
        $dinero = fn (float $monto): string => '$'.number_format($monto, 0, ',', '.');
        $maxMensual = max(1, collect($ventasMensuales)->max('total'));
        $maxDiario = max(1, collect($ventasDiarias)->max('total'));
        $maxProducto = max(1, collect($productosMasVendidos)->max('total_vendido'));
    @endphp

    <table class="header">
        <tr>
            <td><img class="logo" src="{{ $logoDataUri }}" alt="RESMAP"></td>
            <td class="right">
                <h1>Resumen general</h1>
                <span class="muted">Generado el {{ $fechaGeneracion->format('d/m/Y H:i') }}</span>
            </td>
        </tr>
    </table>

    <div class="period">
        <span class="muted">Período de los indicadores</span><br>
        <strong>{{ ucfirst($seleccion['etiqueta']) }}</strong><br>
        <span class="muted">Todos los indicadores y gráficos de este documento corresponden a este período, salvo "Ventas por mes", que muestra los últimos 12 meses.</span>
    </div>

    <table class="cards">
        <tr>
            <td>
                <div class="label">Monto vendido</div>
                <div class="value">{{ $dinero($resumen['total']['monto']) }}</div>
                <div class="muted">Ventas completadas</div>
            </td>
            <td>
                <div class="label">Cantidad de ventas</div>
                <div class="value">{{ number_format($resumen['total']['cantidad'], 0, ',', '.') }}</div>
                <div class="muted">Ventas completadas</div>
            </td>
            <td>
                <div class="label">Ventas en POS</div>
                <div class="value">{{ number_format($resumen['pos']['cantidad'], 0, ',', '.') }}</div>
                <div class="muted">{{ $dinero($resumen['pos']['monto']) }}</div>
            </td>
            <td>
                <div class="label">Ventas por cotización</div>
                <div class="value">{{ number_format($resumen['cotizaciones']['cantidad'], 0, ',', '.') }}</div>
                <div class="muted">{{ $dinero($resumen['cotizaciones']['monto']) }}</div>
            </td>
        </tr>
    </table>

    @if($cantidadStockCritico > 0)
        <p style="color: #9a3412; margin-top: 12px;"><strong>Stock crítico:</strong> {{ number_format($cantidadStockCritico, 0, ',', '.') }} {{ $cantidadStockCritico === 1 ? 'producto con stock bajo' : 'productos con stock bajo' }}.</p>
    @endif

    <div class="chart">
        <h2>Ventas por mes (últimos 12 meses)</h2>
        <table class="bars">
            <tr>
                @foreach($ventasMensuales as $mes)
                    <td>
                        <div class="value-label">{{ $mes['total'] > 0 ? number_format($mes['total'] / 1000, 0, ',', '.').'k' : '' }}</div>
                        <div class="{{ $mes['periodo'] === $seleccion['mes'] ? 'bar' : 'bar-light' }}" style="height: {{ max(1, (int) round(($mes['total'] / $maxMensual) * 80)) }}px;"></div>
                    </td>
                @endforeach
            </tr>
        </table>
        <table class="labels axis">
            <tr>
                @foreach($ventasMensuales as $mes)
                    <td>{{ $mes['etiquetaCorta'] }}</td>
                @endforeach
            </tr>
        </table>
        <p class="muted" style="font-size: 7px;">Montos en miles de pesos (k). El mes seleccionado se destaca en rojo oscuro.</p>
    </div>

    <div class="chart">
        <h2>Ventas por día — {{ ucfirst($seleccion['inicioMes']->copy()->locale('es')->isoFormat('MMMM YYYY')) }}</h2>
        <table class="bars">
            <tr>
                @foreach($ventasDiarias as $dia)
                    <td>
                        <div class="{{ $seleccion['dia'] === null || $seleccion['dia'] === $dia['dia'] ? 'bar' : 'bar-light' }}" style="height: {{ max(1, (int) round(($dia['total'] / $maxDiario) * 90)) }}px; width: 80%;"></div>
                    </td>
                @endforeach
            </tr>
        </table>
        <table class="labels labels-day axis">
            <tr>
                @foreach($ventasDiarias as $dia)
                    <td>{{ $dia['etiqueta'] }}</td>
                @endforeach
            </tr>
        </table>
    </div>

    <div class="chart">
        <h2>{{ $seleccion['dia'] ? 'Top 10 productos del día' : 'Top 10 productos más vendidos del mes' }}</h2>
        @if(count($productosMasVendidos) > 0)
            <table class="top">
                @foreach($productosMasVendidos as $indice => $producto)
                    <tr>
                        <td class="rank">{{ $indice + 1 }}</td>
                        <td class="name">{{ \Illuminate\Support\Str::limit($producto['etiqueta'], 38) }}</td>
                        <td class="track"><div class="fill" style="width: {{ max(2, (int) round(($producto['total_vendido'] / $maxProducto) * 100)) }}%;"></div></td>
                        <td class="qty">{{ number_format($producto['total_vendido'], 0, ',', '.') }} u.</td>
                    </tr>
                @endforeach
            </table>
        @else
            <div class="empty">Sin ventas de productos en este período.</div>
        @endif
    </div>

    <div class="footer">RESMAP · Los Huillines 1041, Los Ángeles · resmap.la@gmail.com</div>
</body>
</html>
