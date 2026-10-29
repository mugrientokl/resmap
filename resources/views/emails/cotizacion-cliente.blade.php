<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cotización RESMAP #{{ $version->cotizacion_id }}</title>
</head>
<body style="margin:0;background:#f3f4f6;color:#241817;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:28px 12px;background:#f3f4f6;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:700px;background:#fff;border:1px solid #ead8d5;">
                <tr><td style="background:#8f241d;padding:22px 30px;"><img src="{{ $logoUrl }}" alt="RESMAP" width="180" style="display:block;width:180px;max-width:100%;height:auto;"></td></tr>
                <tr><td style="padding:30px;">
                    <p style="margin:0;color:#9f2f25;font-size:12px;font-weight:bold;letter-spacing:2px;">PROPUESTA COMERCIAL · RESMAP</p>
                    <h1 style="margin:12px 0 8px;color:#241817;font-size:26px;">Hola {{ $cliente->nombre }}</h1>
                    <p style="margin:0;color:#57534e;font-size:14px;line-height:1.6;">Te enviamos la cotización <strong>#{{ $version->cotizacion_id }} · versión {{ $version->numero_version }}</strong>. Revísala y registra tu respuesta desde el enlace seguro.</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;border-collapse:collapse;">
                        <tr>
                            <td style="width:50%;border:1px solid #ead8d5;background:#fffaf9;padding:14px;">
                                <span style="color:#78716c;font-size:11px;font-weight:bold;">VIGENCIA</span><br>
                                <strong style="display:inline-block;margin-top:5px;">{{ $version->vigente_hasta->format('d/m/Y H:i') }}</strong>
                            </td>
                            <td style="border:1px solid #ead8d5;background:#fffaf9;padding:14px;">
                                <span style="color:#78716c;font-size:11px;font-weight:bold;">TOTAL CON IVA</span><br>
                                <strong style="display:inline-block;margin-top:5px;color:#9f2f25;font-size:18px;">$ {{ number_format($version->total, 0, ',', '.') }}</strong>
                            </td>
                        </tr>
                    </table>
                    <h2 style="margin:0 0 10px;color:#241817;font-size:16px;">Resumen de la propuesta</h2>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
                        <thead><tr style="background:#292524;color:#fff;"><th align="left" style="padding:10px;">Producto</th><th align="center" style="padding:10px;">Cant.</th><th align="right" style="padding:10px;">Subtotal</th></tr></thead>
                        <tbody>
                            @foreach($version->detalles as $detalle)
                                <tr>
                                    <td style="border-bottom:1px solid #e7e5e4;padding:10px;">{{ $detalle->nombre_producto }}<br><span style="color:#78716c;font-size:11px;">Código: {{ $detalle->codigo_producto ?: 'No informado' }}</span></td>
                                    <td align="center" style="border-bottom:1px solid #e7e5e4;padding:10px;">{{ $detalle->cantidad }}</td>
                                    <td align="right" style="border-bottom:1px solid #e7e5e4;padding:10px;">$ {{ number_format($detalle->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0 24px;font-size:13px;">
                        <tr><td style="padding:4px 0;color:#57534e;">Neto</td><td align="right" style="padding:4px 0;">$ {{ number_format($version->neto, 0, ',', '.') }}</td></tr>
                        <tr><td style="padding:4px 0;color:#57534e;">IVA</td><td align="right" style="padding:4px 0;">$ {{ number_format($version->iva, 0, ',', '.') }}</td></tr>
                        <tr><td style="padding:4px 0;color:#57534e;">Entrega</td><td align="right" style="padding:4px 0;">{{ $version->metodo_entrega === 'despacho' ? 'Despacho a domicilio' : 'Retiro en tienda' }}</td></tr>
                        @if($version->metodo_entrega === 'despacho')
                            <tr><td colspan="2" align="right" style="padding:4px 0;color:#57534e;">{{ $version->calle }} {{ $version->numero }}, {{ $version->comuna }}, {{ $version->ciudad }}</td></tr>
                        @endif
                    </table>
                    <p style="margin:0 0 18px;font-size:13px;line-height:1.6;color:#57534e;">La cotización incluye los productos, valores, condiciones de entrega y vigencia indicados en el PDF adjunto. Para aceptar, rechazar o solicitar cambios, confirma el teléfono registrado en RESMAP.</p>
                    <p style="margin:0 0 12px;"><a href="{{ $urlRespuesta }}" style="display:inline-block;background:#b52f25;color:#fff;padding:14px 22px;text-decoration:none;font-weight:bold;">Revisar y responder cotización</a></p>
                    <p style="margin:0;"><a href="{{ $urlPdf }}" style="color:#9f2f25;font-weight:bold;">Abrir cotización PDF</a></p>
                    <p style="margin:20px 0 0;padding:14px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:12px;line-height:1.6;"><strong>Importante:</strong> este documento es una cotización comercial, no una factura ni un documento tributario.</p>
                </td></tr>
                <tr><td style="border-top:1px solid #ead8d5;background:#faf7f6;padding:20px 30px;color:#57534e;font-size:12px;line-height:1.7;">
                    <strong style="color:#241817;">RESMAP</strong> · RUT 76.991.966-K<br>
                    Los Huillines 1041, Los Ángeles · resmap.la@gmail.com<br>
                    John Sanzana Aravena · +56976477124 · +56951523990
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
