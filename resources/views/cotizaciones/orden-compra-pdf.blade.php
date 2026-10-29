<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de compra {{ $ordenCompra->numero }} · RESMAP</title>
    <style>
        @page { margin: 34px 40px; }
        body { color: #292524; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .header { border-bottom: 3px solid #9f2f25; padding-bottom: 15px; width: 100%; }
        .header td { vertical-align: middle; }
        .logo { height: 58px; width: auto; }
        .company { color: #57534e; font-size: 9px; line-height: 1.6; text-align: right; }
        h1 { color: #9f2f25; font-size: 21px; margin: 24px 0 8px; }
        .muted { color: #78716c; }
        .data { border-collapse: collapse; margin: 18px 0; width: 100%; }
        .data td { border: 1px solid #e7e5e4; padding: 9px; }
        .label { background: #faf7f6; color: #57534e; font-size: 9px; font-weight: bold; width: 30%; }
        .items { border-collapse: collapse; margin-top: 20px; width: 100%; }
        .items th { background: #292524; color: white; padding: 9px; text-align: left; }
        .items td { border-bottom: 1px solid #e7e5e4; padding: 9px; }
        .right { text-align: right; }
        .totals { border-collapse: collapse; margin: 12px 0 0 auto; width: 250px; }
        .totals td { border-bottom: 1px solid #e7e5e4; padding: 7px; }
        .total td { border-top: 2px solid #292524; color: #9f2f25; font-size: 12px; font-weight: bold; }
        .notice { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; line-height: 1.6; margin-top: 24px; padding: 12px; }
        .footer { border-top: 1px solid #d6d3d1; color: #57534e; line-height: 1.6; margin-top: 28px; padding-top: 12px; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td><img class="logo" src="{{ $logoDataUri }}" alt="RESMAP"></td>
            <td class="company"><strong>RESMAP</strong><br>RUT 76.991.966-K<br>Los Huillines 1041 – Los Ángeles<br>resmap.la@gmail.com</td>
        </tr>
    </table>
    <h1>ORDEN DE COMPRA</h1>
    <p class="muted">N.º {{ $ordenCompra->numero }} · Fecha {{ $ordenCompra->created_at->format('d/m/Y') }}</p>
    <table class="data">
        <tr><td class="label">CLIENTE</td><td>{{ $cliente->nombre }}</td></tr>
        <tr><td class="label">RUT</td><td>{{ $cliente->rut }}</td></tr>
        <tr><td class="label">COTIZACIÓN</td><td>#{{ $version->cotizacion_id }} · Versión {{ $version->numero_version }}</td></tr>
        <tr><td class="label">VENTA</td><td>#{{ $venta->id_venta }}</td></tr>
        <tr><td class="label">ENTREGA</td><td>{{ $version->metodo_entrega === 'despacho' ? 'Despacho a domicilio' : 'Retiro en tienda' }}@if($version->metodo_entrega === 'despacho')<br>{{ $version->calle }} {{ $version->numero }}, {{ $version->comuna }}, {{ $version->ciudad }}, {{ $version->region }}@endif</td></tr>
    </table>
    <table class="items">
        <thead><tr><th>Producto</th><th>Código</th><th>Cantidad</th><th class="right">Precio unitario</th><th class="right">Subtotal</th></tr></thead>
        <tbody>
            @foreach($version->detalles as $detalle)
                <tr><td>{{ $detalle->nombre_producto }}</td><td>{{ $detalle->codigo_producto }}</td><td>{{ $detalle->cantidad }}</td><td class="right">$ {{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td><td class="right">$ {{ number_format($detalle->subtotal, 0, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><td>Neto</td><td class="right">$ {{ number_format($version->neto, 0, ',', '.') }}</td></tr>
        <tr><td>IVA</td><td class="right">$ {{ number_format($version->iva, 0, ',', '.') }}</td></tr>
        <tr><td>Despacho</td><td class="right">$ {{ number_format($version->costo_despacho, 0, ',', '.') }}</td></tr>
        <tr class="total"><td>TOTAL</td><td class="right">$ {{ number_format($version->total, 0, ',', '.') }}</td></tr>
    </table>
    <div class="notice"><strong>Autorización registrada:</strong> el cliente autorizó expresamente la generación de este documento al aceptar la cotización. Esta constancia comercial no reemplaza un documento de compra emitido por el cliente ni es un documento tributario.</div>
    <div class="footer">RESMAP · RUT 76.991.966-K · resmap.la@gmail.com<br>Los Huillines 1041 – Los Ángeles</div>
</body>
</html>
