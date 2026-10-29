<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización RESMAP #{{ $version->cotizacion_id }}</title>
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
        .client { background: #faf7f6; border-left: 3px solid #9f2f25; line-height: 1.7; margin: 20px 0; padding: 12px 14px; }
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
                <h1>COTIZACIÓN</h1>
                <div class="muted">Fecha de emisión: {{ $version->created_at->format('d/m/Y') }}</div>
                <div class="muted">Válida hasta: {{ $version->vigente_hasta?->format('d/m/Y H:i') ?? 'No especificada' }}</div>
            </td>
            <td class="number">N.º {{ $version->cotizacion_id }}<br><span class="muted">Versión {{ $version->numero_version }}</span></td>
        </tr>
    </table>
    <div class="client">
        <strong>CLIENTE</strong><br>
        {{ $cliente->nombre }}<br>
        RUT: {{ $cliente->rut }}<br>
        @if($cliente->correo)Correo: {{ $cliente->correo }}<br>@endif
        Entrega: {{ $version->metodo_entrega === 'despacho' ? 'Despacho a domicilio' : 'Retiro en tienda' }}
        @if($version->metodo_entrega === 'despacho')
            <br>Dirección: {{ $version->calle }} {{ $version->numero }}, {{ $version->comuna }}, {{ $version->ciudad }}, {{ $version->region }}
            @if($version->referencias)<br>Referencias: {{ $version->referencias }}@endif
        @endif
    </div>
    <p class="section-title">DETALLE DE PRODUCTOS</p>
    <table class="items">
        <thead>
            <tr><th>Producto</th><th>Código</th><th class="center">Cantidad</th><th class="right">Precio unitario</th><th class="right">Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach($version->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->nombre_producto }}</td>
                    <td>{{ $detalle->codigo_producto }}</td>
                    <td class="center">{{ $detalle->cantidad }}</td>
                    <td class="right">$ {{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td>
                    <td class="right">$ {{ number_format($detalle->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><td>Neto</td><td class="right">$ {{ number_format($version->neto, 0, ',', '.') }}</td></tr>
        <tr><td>IVA</td><td class="right">$ {{ number_format($version->iva, 0, ',', '.') }}</td></tr>
        <tr><td>Despacho</td><td class="right">$ {{ number_format($version->costo_despacho, 0, ',', '.') }}</td></tr>
        <tr class="grand-total"><td>TOTAL</td><td class="right">$ {{ number_format($version->total, 0, ',', '.') }}</td></tr>
    </table>
    <div class="note"><strong>Documento comercial</strong><br>Esta cotización no es una factura ni un documento tributario electrónico.</div>
    <div class="footer">
        <strong>Contacto RESMAP</strong><br>
        John Sanzana Aravena · +56976477124 · +56951523990<br>
        resmap.la@gmail.com · Los Huillines 1041 – Los Ángeles
    </div>
</body>
</html>
