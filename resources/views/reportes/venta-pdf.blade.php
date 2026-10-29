<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen interno de venta #{{ $venta->id_venta }}</title>
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
    <div class="top"><table><tr><td style="border: 0; padding: 0;"><div class="brand">RESMAP</div><div class="muted">Venta de repuestos eléctricos para maquinaria pesada</div></td><td class="right" style="border: 0; padding: 0;"><div class="label">Resumen interno de venta</div><strong style="font-size: 16px;">#{{ $venta->id_venta }}</strong><br><span class="muted">{{ $venta->fecha?->format('d/m/Y H:i') }}</span></td></tr></table></div>
    <div class="box" style="margin: 18px 0;"><table><tr><td style="border: 0; padding: 0;"><div class="label">Cliente</div><strong>{{ $venta->cliente?->nombre ?? 'Sin cliente' }}</strong><br>RUT: {{ $venta->cliente?->rut ?? 'N/A' }}</td><td style="border: 0; padding: 0;"><div class="label">Emisión</div>{{ $venta->tipo_documento }}<br>Medio de pago: {{ $venta->medio_pago }}<br>Vendedor: {{ $venta->user?->name ?? 'N/A' }}</td></tr></table></div>
    <table><thead><tr><th>Descripción</th><th style="text-align: center;">Cantidad</th><th class="number">Precio unitario</th><th class="number">Subtotal</th></tr></thead><tbody>@foreach($venta->detalles as $detalle)<tr><td><strong>{{ $detalle->producto?->nombre ?? 'Producto eliminado' }}</strong></td><td style="text-align: center;">{{ $detalle->cantidad }}</td><td class="number">$ {{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td><td class="number">$ {{ number_format($detalle->subtotal, 0, ',', '.') }}</td></tr>@endforeach</tbody></table>
    <table class="totals"><tr><td class="muted">Neto</td><td class="number">$ {{ number_format($venta->neto, 0, ',', '.') }}</td></tr><tr><td class="muted">IVA</td><td class="number">$ {{ number_format($venta->iva, 0, ',', '.') }}</td></tr><tr class="grand"><td>Total</td><td class="number">$ {{ number_format($venta->total, 0, ',', '.') }}</td></tr></table>
    @if($venta->cotizacionVersion)<p class="muted">Cotización #{{ $venta->cotizacionVersion->cotizacion_id }} · Versión {{ $venta->cotizacionVersion->numero_version }}</p>@endif
    <p class="muted">Pago: {{ $venta->estado_pago ?? 'Histórico' }} · Facturación: {{ $venta->estado_facturacion ?? 'Sin registro histórico' }} · Entrega: {{ $venta->estado_entrega ?? 'Sin registro histórico' }}</p>
    @if($venta->metodo_entrega)<p class="muted">Método de entrega: {{ $venta->metodo_entrega }} @if($venta->direccion_despacho) · {{ $venta->direccion_despacho }}, {{ $venta->comuna_despacho }}, {{ $venta->ciudad_despacho }}, {{ $venta->region_despacho }}@endif</p>@endif
    @if($venta->ordenesCompra->isNotEmpty())<p class="muted">Orden(es) de compra: {{ $venta->ordenesCompra->pluck('numero')->filter()->implode(', ') ?: 'Adjunta(s)' }}</p>@endif
    <p class="muted" style="margin-top: 44px; text-align: center;">Documento interno — No válido como documento tributario.</p>
</body>
</html>