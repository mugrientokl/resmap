<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen interno de venta #{{ $venta->id_venta }}</title>
    <style>
        @page { margin: 32px 38px 42px; }
        body { color: #292524; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .header { border-bottom: 3px solid #9f2f25; border-collapse: collapse; table-layout: fixed; width: 100%; }
        .header td { vertical-align: middle; }
        .logo { height: 58px; width: auto; }
        .company { color: #57534e; font-size: 8px; line-height: 1.45; padding-bottom: 8px; text-align: right; }
        .company strong { color: #292524; font-size: 11px; }
        .document { padding: 0 8px 8px; text-align: center; }
        h1 { color: #9f2f25; font-size: 15px; margin: 0 0 4px; }
        .muted { color: #78716c; }
        .number { color: #9f2f25; font-size: 11px; font-weight: bold; }
        .document .muted { font-size: 8px; line-height: 1.45; }
        .issuer { color: #57534e; font-size: 8px; line-height: 1.45; padding: 4px 0 8px; }
        .info { background: #faf7f6; border-left: 3px solid #9f2f25; line-height: 1.45; margin: 14px 0; padding: 8px 10px; }
        .info-grid { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .info-grid td { overflow-wrap: break-word; padding: 2px 6px 2px 0; vertical-align: top; width: 33.33%; }
        .info-grid strong { color: #57534e; }
        .section-title { color: #292524; font-size: 11px; font-weight: bold; margin: 16px 0 8px; }
        table.items { border-collapse: collapse; width: 100%; }
        .items th { background: #292524; color: white; font-size: 9px; padding: 9px 7px; text-align: left; }
        .items td { border-bottom: 1px solid #e7e5e4; padding: 9px 7px; }
        .items th:last-child, .items td:last-child { width: 20%; }
        .right { text-align: right !important; }
        .center { text-align: center !important; }
        .totals-layout { border-collapse: collapse; margin-top: 14px; table-layout: fixed; width: 100%; }
        .totals-layout > tbody > tr > td { padding: 0; vertical-align: top; }
        .totals-column { width: 30%; }
        .totals { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .totals td { border-bottom: 1px solid #e7e5e4; padding: 7px; }
        .totals td:first-child { text-align: right; width: 33.333333%; }
        .grand-total td { border-top: 2px solid #292524; color: #9f2f25; font-size: 12px; font-weight: bold; }
        .note { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; line-height: 1.6; margin-top: 22px; padding: 10px 12px; text-align: center; }
        .footer { border-top: 1px solid #d6d3d1; color: #57534e; font-size: 9px; line-height: 1.6; margin-top: 30px; padding-top: 12px; text-align: center; }
    </style>
</head>
<body>
    @php
        $version = $venta->cotizacionVersion;
        $montoPagado = (float) ($venta->monto_pagado ?? 0);
        $saldo = max(0, (float) $venta->total - $montoPagado);
        $esCredito = in_array($venta->estado_pago, ['credito_autorizado', 'credito_parcial'], true);
        $datosResumen = $venta->datos_resumen ?? [];
    @endphp
    <table class="header">
        <tr>
            <td style="width: 20%;"><img class="logo" src="{{ $logoDataUri }}" alt="RESMAP"></td>
            <td class="document" style="width: 45%;">
                <h1>RESUMEN INTERNO<br>DE VENTA</h1>
                <div class="number">Venta N.º {{ $venta->id_venta }}</div>
                <div class="muted">Fecha de venta: {{ $venta->fecha?->format('d/m/Y H:i') }}</div>
                <div class="muted">Origen: {{ $version ? 'Cotización #'.$version->cotizacion_id.' · Versión '.$version->numero_version : ($venta->solicitudServicio ? 'Servicio #'.$venta->solicitudServicio->id_solicitud : 'Venta en punto de venta (POS)') }}</div>
            </td>
            <td class="company" style="width: 35%;">
                <strong>RESMAP</strong><br>
                RUT 76.991.966-K<br>
                Av. Gabriela Mistral 1255, Bodega 48<br>
                Los Ángeles<br>
                resmap.la@gmail.com · +56 9 76477124<br>
                S.I.I. – Los Ángeles
            </td>
        </tr>
        <tr>
            <td colspan="3" class="issuer">
                <strong>EMISOR</strong>
                @if(!empty($datosResumen['giro_emisor'])) · Giro: {{ $datosResumen['giro_emisor'] }}@endif
                @if(!empty($datosResumen['tipo_venta'])) · Tipo de venta: Del giro @endif
            </td>
        </tr>
    </table>
    <div class="info">
        <strong>CLIENTE / RECEPTOR</strong>
        <table class="info-grid">
            <tr>
                <td><strong>Razón social:</strong> {{ $datosResumen['nombre'] ?? $cliente?->nombre ?? 'Sin cliente' }}</td>
                <td><strong>RUT:</strong> {{ $datosResumen['rut'] ?? $cliente?->rut_formateado ?? 'N/A' }}</td>
                <td><strong>Giro:</strong> {{ $datosResumen['giro'] ?? $cliente?->giro ?? 'No informado' }}</td>
            </tr>
            <tr>
                <td><strong>Atención / contacto:</strong> {{ $datosResumen['contacto'] ?? $venta->contacto ?? $cliente?->contacto ?? 'No informado' }}</td>
                <td><strong>Correo:</strong> {{ $datosResumen['correo'] ?? $cliente?->correo ?? 'No informado' }}</td>
                <td><strong>Teléfono:</strong> {{ $datosResumen['telefono'] ?? $cliente?->telefono ?? 'No informado' }}</td>
            </tr>
            <tr>
                <td><strong>Región:</strong> {{ $datosResumen['region'] ?? $cliente?->region ?? 'No informada' }}</td>
                <td><strong>Comuna:</strong> {{ $datosResumen['comuna'] ?? $cliente?->comuna ?? 'No informada' }}</td>
                <td><strong>Ciudad:</strong> {{ $datosResumen['ciudad'] ?? $cliente?->ciudad ?? 'No informada' }}</td>
            </tr>
            <tr>
                <td colspan="3"><strong>Dirección:</strong> {{ $datosResumen['direccion'] ?? $cliente?->direccion ?? 'No informada' }}</td>
            </tr>
        </table>
    </div>
    <div class="info">
        <strong>DETALLE DE LA VENTA</strong>
        <table class="info-grid">
            <tr>
                <td><strong>Vendedor:</strong> {{ $venta->user?->name ?? 'N/A' }}</td>
                <td colspan="2"><strong>Documento de referencia:</strong> {{ $venta->tipo_documento }}</td>
            </tr>
            <tr>
                <td><strong>Forma de pago:</strong> {{ $datosResumen['forma_pago'] ?? 'Contado' }}</td>
                <td><strong>Medio de pago:</strong> {{ $venta->medio_pago }}</td>
                <td>@if(!empty($datosResumen['tipo_compra']))<strong>Tipo de compra:</strong> Del giro @endif</td>
            </tr>
        </table>
    </div>
    @if($venta->solicitudServicio)
        <p><strong>Servicio solicitado: {{ $venta->solicitudServicio->tipo_servicio }}</strong><br>
            {!! nl2br(e($venta->solicitudServicio->descripcion_servicio)) !!}</p>
        @if($venta->solicitudServicio->observaciones)
            <p><strong>Observaciones finales:</strong><br>{!! nl2br(e($venta->solicitudServicio->observaciones)) !!}</p>
        @endif
    @endif
    <p class="section-title">DETALLE DE {{ $venta->solicitudServicio ? 'SERVICIO Y REPUESTOS' : 'PRODUCTOS' }}</p>
    <table class="items">
        <thead>
            <tr><th>Código</th><th>Producto</th><th class="center">Cantidad</th><th class="right">Precio unitario</th><th class="right">Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->producto?->codigo_origen ?: ($detalle->producto?->codigo_barra ?? '—') }}</td>
                    <td>{{ $detalle->descripcion ?: ($detalle->producto?->nombre ?? 'Producto eliminado') }}</td>
                    <td class="center">{{ $detalle->cantidad }}</td>
                    <td class="right">$ {{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td>
                    <td class="right">$ {{ number_format($detalle->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="totals-layout">
        <colgroup><col style="width: 70%;"><col style="width: 30%;"></colgroup>
        <tbody><tr><td></td><td class="totals-column">
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
        </td></tr></tbody>
    </table>
    @if(!empty($datosResumen['numero_oc']))
        <p><strong>Orden de compra:</strong> {{ $datosResumen['numero_oc'] }}</p>
    @elseif($venta->ordenesCompra->isNotEmpty())
        <p><strong>Orden(es) de compra:</strong> {{ $venta->ordenesCompra->pluck('numero')->filter()->implode(', ') ?: 'Adjunta(s)' }}</p>
    @endif
    @if($esCredito && $venta->creditoAutorizadoPor)
        <p class="muted">Crédito autorizado por {{ $venta->creditoAutorizadoPor->name }} el {{ $venta->credito_autorizado_at?->format('d/m/Y H:i') }}.</p>
    @endif
    <div class="note"><strong>Documento interno</strong><br>Este resumen no es una boleta, factura ni un documento tributario electrónico.</div>
    <div class="footer">
        <strong>Contacto RESMAP</strong><br>
        John Sanzana Aravena · +56976477124<br>
        resmap.la@gmail.com · Av. Gabriela Mistral 1255, Bodega 48 – Los Ángeles
    </div>
</body>
</html>
