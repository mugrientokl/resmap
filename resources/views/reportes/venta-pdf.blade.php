<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen interno de venta #{{ $venta->id_venta }}</title>
    <style>
        @page { margin: 32px 38px 42px; }
        body { color: #292524; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .header { border-bottom: 3px solid #9f2f25; padding-bottom: 18px; width: 100%; }
        .header td { vertical-align: middle; }
        .logo { height: 66px; width: auto; }
        .company { color: #57534e; font-size: 9px; line-height: 1.6; text-align: right; }
        .company strong { color: #292524; font-size: 12px; }
        .document { margin-top: 24px; width: 100%; }
        .document td { vertical-align: top; }
        h1 { color: #9f2f25; font-size: 21px; margin: 0 0 8px; }
        .muted { color: #78716c; }
        .number { color: #9f2f25; font-size: 12px; font-weight: bold; text-align: right; }
        .info { border-collapse: separate; border-spacing: 0; margin: 20px 0; width: 100%; }
        .info td { background: #faf7f6; border-left: 3px solid #9f2f25; line-height: 1.7; padding: 12px 14px; vertical-align: top; width: 50%; }
        .section-title { color: #292524; font-size: 11px; font-weight: bold; margin: 22px 0 8px; }
        table.items { border-collapse: collapse; width: 100%; }
        .items th { background: #292524; color: white; font-size: 9px; padding: 9px 7px; text-align: left; }
        .items td { border-bottom: 1px solid #e7e5e4; padding: 9px 7px; }
        .right { text-align: right !important; }
        .center { text-align: center !important; }
        .totals { border-collapse: collapse; margin: 14px 0 0 auto; width: 250px; }
        .totals td { border-bottom: 1px solid #e7e5e4; padding: 7px 4px; }
        .grand-total td { border-top: 2px solid #292524; color: #9f2f25; font-size: 12px; font-weight: bold; }
        .note { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; line-height: 1.6; margin-top: 22px; padding: 10px 12px; text-align: center; }
        .footer { border-top: 1px solid #d6d3d1; color: #57534e; font-size: 9px; line-height: 1.6; margin-top: 30px; padding-top: 12px; text-align: center; }
    </style>
</head>
<body>
    @php
        $cliente = $venta->cliente;
        $version = $venta->cotizacionVersion;
        $montoPagado = (float) ($venta->monto_pagado ?? 0);
        $saldo = max(0, (float) $venta->total - $montoPagado);
        $esCredito = in_array($venta->estado_pago, ['credito_autorizado', 'credito_parcial'], true);
        $etiquetaPago = match ($venta->estado_pago) {
            'credito_autorizado' => 'Crédito autorizado',
            'credito_parcial' => 'Crédito con abonos',
            'pagado' => 'Pagado',
            'registrado' => 'Pagado en caja',
            default => 'Histórico',
        };
        $direccionCliente = collect([$cliente?->direccion, $cliente?->comuna, $cliente?->region])->filter()->implode(', ');
    @endphp
    <table class="header">
        <tr>
            <td><img class="logo" src="{{ $logoDataUri }}" alt="RESMAP"></td>
            <td class="company">
                <strong>RESMAP</strong><br>
                RUT 76.991.966-K<br>
                Los Huillines 1041 – Los Ángeles<br>
                resmap.la@gmail.com
            </td>
        </tr>
    </table>
    <table class="document">
        <tr>
            <td>
                <h1>RESUMEN INTERNO DE VENTA</h1>
                <div class="muted">Fecha de venta: {{ $venta->fecha?->format('d/m/Y H:i') }}</div>
                <div class="muted">Origen: {{ $version ? 'Cotización #'.$version->cotizacion_id.' · Versión '.$version->numero_version : 'Venta en punto de venta (POS)' }}</div>
            </td>
            <td class="number">Venta N.º {{ $venta->id_venta }}</td>
        </tr>
    </table>
    <table class="info">
        <tr>
            <td>
                <strong>CLIENTE</strong><br>
                {{ $cliente?->nombre ?? 'Sin cliente' }}<br>
                RUT: {{ $cliente?->rut_formateado ?? 'N/A' }}<br>
                @if($cliente?->correo)Correo: {{ $cliente->correo }}<br>@endif
                @if($cliente?->telefono)Teléfono: {{ $cliente->telefono }}<br>@endif
                @if($direccionCliente)Dirección: {{ $direccionCliente }}@endif
            </td>
            <td>
                <strong>DETALLE DE LA VENTA</strong><br>
                Vendedor: {{ $venta->user?->name ?? 'N/A' }}<br>
                Documento de referencia: {{ $venta->tipo_documento }}<br>
                Medio de pago: {{ $venta->medio_pago }}<br>
                Estado de pago: {{ $etiquetaPago }}<br>
                @if($venta->estado_entrega)Entrega: {{ ucfirst(str_replace('_', ' ', $venta->estado_entrega)) }}@endif
            </td>
        </tr>
    </table>
    @if($venta->metodo_entrega)
        <p><strong>Modalidad de entrega:</strong> {{ $venta->metodo_entrega === 'despacho' ? 'Despacho a domicilio' : 'Retiro en tienda' }}
            @if($venta->metodo_entrega === 'despacho')
                · {{ collect([$venta->direccion_despacho, $venta->comuna_despacho, $venta->ciudad_despacho, $venta->region_despacho])->filter()->implode(', ') }}
                @if($venta->referencias_despacho)<br><span class="muted">Referencias: {{ $venta->referencias_despacho }}</span>@endif
            @endif
        </p>
    @endif
    <p class="section-title">DETALLE DE PRODUCTOS</p>
    <table class="items">
        <thead>
            <tr><th>Producto</th><th>Código</th><th class="center">Cantidad</th><th class="right">Precio unitario</th><th class="right">Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->descripcion ?: ($detalle->producto?->nombre ?? 'Producto eliminado') }}</td>
                    <td>{{ $detalle->producto?->codigo_origen ?: ($detalle->producto?->codigo_barra ?? '—') }}</td>
                    <td class="center">{{ $detalle->cantidad }}</td>
                    <td class="right">$ {{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td>
                    <td class="right">$ {{ number_format($detalle->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><td>Neto</td><td class="right">$ {{ number_format($venta->neto, 0, ',', '.') }}</td></tr>
        <tr><td>IVA</td><td class="right">$ {{ number_format($venta->iva, 0, ',', '.') }}</td></tr>
        @if($despacho > 0)<tr><td>Despacho</td><td class="right">$ {{ number_format($despacho, 0, ',', '.') }}</td></tr>@endif
        <tr class="grand-total"><td>TOTAL</td><td class="right">$ {{ number_format($venta->total, 0, ',', '.') }}</td></tr>
        @if($esCredito)
            <tr><td>Pagado</td><td class="right">$ {{ number_format($montoPagado, 0, ',', '.') }}</td></tr>
            <tr><td>Saldo</td><td class="right">$ {{ number_format($saldo, 0, ',', '.') }}</td></tr>
        @endif
    </table>
    @if($venta->ordenesCompra->isNotEmpty())
        <p><strong>Orden(es) de compra:</strong> {{ $venta->ordenesCompra->pluck('numero')->filter()->implode(', ') ?: 'Adjunta(s)' }}</p>
    @endif
    @if($esCredito && $venta->creditoAutorizadoPor)
        <p class="muted">Crédito autorizado por {{ $venta->creditoAutorizadoPor->name }} el {{ $venta->credito_autorizado_at?->format('d/m/Y H:i') }}.</p>
    @endif
    <div class="note"><strong>Documento interno</strong><br>Este resumen no es una boleta, factura ni un documento tributario electrónico.</div>
    <div class="footer">
        <strong>Contacto RESMAP</strong><br>
        John Sanzana Aravena · +56976477124 · +56951523990<br>
        resmap.la@gmail.com · Los Huillines 1041 – Los Ángeles
    </div>
</body>
</html>
