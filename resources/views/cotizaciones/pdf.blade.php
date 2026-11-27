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
        h1 { color: #9f2f25; font-size: 21px; margin: 0 0 4px; }
        .muted { color: #78716c; }
        .number { color: #9f2f25; font-size: 21px; font-weight: bold; text-align: right; }
        .number h1 { display: inline; font-size: 21px; margin: 0; }
        .number .quote-number { display: inline; margin-left: 8px; }
        .client { background: #faf7f6; border-left: 3px solid #9f2f25; line-height: 1.45; margin: 20px 0; padding: 8px 10px; }
        .client-grid { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .client-grid td { overflow-wrap: break-word; padding: 2px 6px 2px 0; vertical-align: top; }
        .client-grid strong.label { color: #57534e; }
        .section-title { color: #292524; font-size: 11px; font-weight: bold; margin: 22px 0 8px; }
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
        .footer { border-top: 1px solid #d6d3d1; color: #57534e; font-size: 9px; line-height: 1.6; margin-top: 30px; padding-top: 12px; text-align: center; }
    </style>
</head>
<body>
    @php($versionReemplazada = $version->estado === 'reemplazada' || $version->numero_version < $version->cotizacion->ultima_version)
    <table class="header">
        <tr>
            <td style="width: 30%;"><img class="logo" src="{{ $logoDataUri }}" alt="RESMAP"></td>
            <td class="number" style="width: 40%; text-align: center;">
                <h1>COTIZACIÓN</h1><span class="quote-number">N.º {{ $version->cotizacion_id }}</span><br>
                <span class="muted" style="font-size: 11px;">Versión {{ $version->numero_version }}</span><br>
                <span class="muted" style="font-size: 8px; font-weight: normal;">
                    Fecha de emisión: {{ $version->created_at->format('d/m/Y') }}<br>
                    @if($versionReemplazada)
                        <strong>INVALIDADA POR REEMPLAZO</strong><br>
                        Esta versión ya no tiene validez.
                    @else
                        Válida hasta: {{ $version->vigente_hasta?->format('d/m/Y H:i') ?? 'No especificada' }}
                    @endif
                </span>
            </td>
            <td class="company" style="width: 30%;">
                <strong>RESMAP</strong><br>
                RUT 76.991.966-K<br>
                Av. Gabriela Mistral 1255, B48<br>
                Los Ángeles<br>
                resmap.la@gmail.com
            </td>
        </tr>
    </table>
    <div class="client">
        <strong>CLIENTE</strong>
        <table class="client-grid">
            <tr>
                <td style="width: 34%;"><strong class="label">Razón social:</strong> {{ $cliente->nombre }}</td>
                <td style="width: 33%;"><strong class="label">RUT:</strong> {{ $cliente->rut_formateado }}</td>
                <td style="width: 33%;"><strong class="label">Giro:</strong> {{ $cliente->giro ?: 'No informado' }}</td>
            </tr>
            <tr>
                <td>@if(filled($cliente->contacto))<strong class="label">Atención / contacto:</strong> {{ $cliente->contacto }}@endif</td>
                <td><strong class="label">Correo:</strong> {{ $cliente->correo ?: 'No informado' }}</td>
                <td><strong class="label">Teléfono:</strong> {{ $cliente->telefono ?: 'No informado' }}</td>
            </tr>
            @if(filled($version->cotizacion->solicitud->cargo))
                <tr>
                    <td><strong class="label">Cargo / área:</strong> {{ $version->cotizacion->solicitud->cargo }}</td>
                    <td><strong class="label">Región:</strong> {{ $cliente->region ?: 'No informada' }}</td>
                    <td><strong class="label">Comuna:</strong> {{ $cliente->comuna ?: 'No informada' }}</td>
                </tr>
            @else
                <tr>
                    <td><strong class="label">Región:</strong> {{ $cliente->region ?: 'No informada' }}</td>
                    <td><strong class="label">Comuna:</strong> {{ $cliente->comuna ?: 'No informada' }}</td>
                    <td><strong class="label">Ciudad:</strong> {{ $cliente->ciudad ?: 'No informada' }}</td>
                </tr>
            @endif
            @if(filled($version->cotizacion->solicitud->cargo))
                <tr>
                    <td><strong class="label">Comuna:</strong> {{ $cliente->comuna ?: 'No informada' }}</td>
                    <td><strong class="label">Ciudad:</strong> {{ $cliente->ciudad ?: 'No informada' }}</td>
                    <td><strong class="label">Dirección:</strong> {{ $cliente->direccion ?: 'No informada' }}</td>
                </tr>
            @else
                <tr>
                    <td colspan="3"><strong class="label">Dirección:</strong> {{ $cliente->direccion ?: 'No informada' }}</td>
                </tr>
            @endif
        </table>
    </div>
    <p class="section-title">DETALLE DE PRODUCTOS</p>
    <table class="items">
        <thead>
            <tr><th>Producto</th><th class="center">Cantidad</th><th class="right">Precio unitario</th><th class="right">Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach($version->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->nombre_producto }}</td>
                    <td class="center">{{ $detalle->cantidad }}</td>
                    <td class="right">$ {{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td>
                    <td class="right">$ {{ number_format($detalle->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="totals-layout">
        <colgroup><col style="width: 70%;"><col style="width: 30%;"></colgroup>
        <tbody>
        <tr>
            <td></td>
            <td class="totals-column">
                <table class="totals">
                    <tr><td>Neto</td><td class="right">$ {{ number_format($version->neto, 0, ',', '.') }}</td></tr>
                    <tr><td>IVA</td><td class="right">$ {{ number_format($version->iva, 0, ',', '.') }}</td></tr>
                    <tr><td>Despacho</td><td class="right">$ {{ number_format($version->costo_despacho, 0, ',', '.') }}</td></tr>
                    <tr class="grand-total"><td>TOTAL</td><td class="right">$ {{ number_format($version->total, 0, ',', '.') }}</td></tr>
                </table>
            </td>
        </tr>
        </tbody>
    </table>
    <div class="footer">
        Esperando tener una buena acogida por parte de ustedes<br>
        Atte.<br>
        John Sanzana Aravena · +56976477124<br>
        resmap.la@gmail.com · Av. Gabriela Mistral 1255, Bodega 48 – Los Ángeles
    </div>
</body>
</html>
