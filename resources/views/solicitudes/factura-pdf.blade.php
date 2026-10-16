<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen de solicitud #{{ $solicitud->id_solicitud }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { color: #241817; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .top { border-bottom: 3px solid #241817; padding-bottom: 16px; }
        .brand { color: #b52f25; font-size: 20px; font-weight: bold; }
        .muted { color: #756967; }
        .right { text-align: right; }
        .label { color: #9f2f25; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .box { background: #fff8f6; border: 1px solid #ead8d5; padding: 14px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #241817; color: white; font-size: 9px; padding: 9px 8px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #ead8d5; padding: 10px 8px; }
        .number { text-align: right; }
        .totals { margin-left: auto; margin-top: 18px; width: 235px; }
        .totals td { border: 0; padding: 5px 0; }
        .grand td { border-top: 2px solid #241817; font-size: 13px; font-weight: bold; padding-top: 10px; }
        .grand .number { color: #b52f25; }
    </style>
</head>
<body>
    <div class="top">
        <table><tr>
            <td style="border: 0; padding: 0;"><div class="brand">RESMAP</div><div class="muted">Venta de repuestos eléctricos para maquinaria pesada</div></td>
            <td class="right" style="border: 0; padding: 0;"><div class="label">Resumen de solicitud</div><strong style="font-size: 16px;">Solicitud #{{ $solicitud->id_solicitud }}</strong><br><span class="muted">{{ $solicitud->fecha?->format('d/m/Y') }}</span></td>
        </tr></table>
    </div>
    <div class="box" style="margin: 18px 0;">
        <table><tr>
            <td style="border: 0; padding: 0;"><div class="label">Cliente</div><strong>{{ $solicitud->cliente->nombre }}</strong><br>RUT: {{ $solicitud->cliente->rut }}<br>{{ $solicitud->cliente->direccion ?: 'Dirección no informada' }}</td>
            <td style="border: 0; padding: 0;"><div class="label">Contacto</div>{{ $solicitud->cliente->correo ?: 'Correo no informado' }}<br>{{ $solicitud->cliente->telefono ?: 'Teléfono no informado' }}</td>
        </tr></table>
    </div>
    @if($solicitud->tipo_solicitud === 'servicio')
        <div class="label">Servicio solicitado</div><h2>{{ $solicitud->tipo_servicio }}</h2><p>{{ $solicitud->descripcion_servicio }}</p>
    @else
        <table>
            <thead><tr><th>Descripción</th><th style="text-align: center;">Cantidad</th><th class="number">Valor neto</th><th class="number">Valor c/IVA</th><th class="number">Total c/IVA</th></tr></thead>
            <tbody>@foreach($productos as $item)<tr><td><strong>{{ $item['producto']->nombre ?? 'Producto no encontrado' }}</strong><br><span class="muted">Código: {{ $item['producto']->codigo_origen ?? 'Sin código' }}</span></td><td style="text-align: center;">{{ $item['cantidad'] }}</td><td class="number">$ {{ number_format($item['precio_neto'], 0, ',', '.') }}</td><td class="number">$ {{ number_format($item['precio_iva'], 0, ',', '.') }}</td><td class="number">$ {{ number_format($item['total_iva'], 0, ',', '.') }}</td></tr>@endforeach</tbody>
        </table>
        <table class="totals"><tr><td class="muted">Neto</td><td class="number">$ {{ number_format($totales['neto'], 0, ',', '.') }}</td></tr><tr><td class="muted">IVA 19%</td><td class="number">$ {{ number_format($totales['iva'], 0, ',', '.') }}</td></tr><tr class="grand"><td>Total</td><td class="number">$ {{ number_format($totales['total'], 0, ',', '.') }}</td></tr></table>
    @endif
    @if($solicitud->observaciones)<div style="margin-top: 24px;"><div class="label">Observaciones</div><p>{{ $solicitud->observaciones }}</p></div>@endif
    <p class="muted" style="margin-top: 44px; text-align: center;">Este documento es un resumen informativo de la solicitud y no acredita una emisión tributaria.</p>
    <p class="muted" style="margin-top: 12px; text-align: center;">Gracias por preferir RESMAP · Venta de repuestos eléctricos para maquinaria pesada</p>
</body>
</html>