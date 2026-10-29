<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orden de compra recibida · RESMAP</title>
</head>
<body style="margin:0;background:#f3f4f6;color:#241817;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:28px 12px;background:#f3f4f6;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:700px;background:#fff;border:1px solid #ead8d5;">
                <tr><td style="background:#8f241d;padding:22px 30px;color:#fff;font-size:17px;font-weight:bold;letter-spacing:3px;">RESMAP · GESTIÓN COMERCIAL</td></tr>
                <tr><td style="padding:30px;">
                    <p style="margin:0;color:#9f2f25;font-size:12px;font-weight:bold;letter-spacing:2px;">NUEVA RESPUESTA DE CLIENTE</p>
                    <h1 style="margin:12px 0;color:#241817;font-size:25px;">Se recibió una orden de compra</h1>
                    <p style="margin:0;color:#57534e;font-size:14px;line-height:1.6;">La cotización fue aceptada por el cliente. Revisa los datos y el documento adjunto para continuar con la confirmación de pago o la autorización de crédito.</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;border-collapse:collapse;font-size:13px;">
                        <tr><td style="width:35%;border:1px solid #ead8d5;background:#fffaf9;padding:11px;font-weight:bold;">CLIENTE</td><td style="border:1px solid #ead8d5;padding:11px;">{{ $cliente->nombre }} · RUT {{ $cliente->rut }}</td></tr>
                        <tr><td style="border:1px solid #ead8d5;background:#fffaf9;padding:11px;font-weight:bold;">CONTACTO</td><td style="border:1px solid #ead8d5;padding:11px;">{{ $cliente->correo ?: 'Sin correo' }} · {{ $cliente->telefono ?: 'Sin teléfono' }}</td></tr>
                        <tr><td style="border:1px solid #ead8d5;background:#fffaf9;padding:11px;font-weight:bold;">ORDEN DE COMPRA</td><td style="border:1px solid #ead8d5;padding:11px;">{{ $ordenCompra->numero ?: 'Sin número informado' }} · {{ $ordenCompra->modalidad === 'automatica_autorizada' ? 'Autorizada expresamente por el cliente' : 'Archivo proporcionado por el cliente' }}</td></tr>
                        <tr><td style="border:1px solid #ead8d5;background:#fffaf9;padding:11px;font-weight:bold;">COTIZACIÓN</td><td style="border:1px solid #ead8d5;padding:11px;">#{{ $version->cotizacion_id }} · Versión {{ $version->numero_version }}</td></tr>
                        <tr><td style="border:1px solid #ead8d5;background:#fffaf9;padding:11px;font-weight:bold;">VENTA</td><td style="border:1px solid #ead8d5;padding:11px;">#{{ $venta->id_venta }} · Total $ {{ number_format($venta->total, 0, ',', '.') }}</td></tr>
                        <tr><td style="border:1px solid #ead8d5;background:#fffaf9;padding:11px;font-weight:bold;">ENTREGA</td><td style="border:1px solid #ead8d5;padding:11px;">{{ $venta->metodo_entrega === 'despacho' ? 'Delivery a domicilio · '.$venta->direccion_despacho.', '.$venta->comuna_despacho.', '.$venta->ciudad_despacho.', '.$venta->region_despacho : 'Retiro en tienda' }}@if($venta->referencias_despacho)<br>Referencias: {{ $venta->referencias_despacho }}@endif</td></tr>
                    </table>
                    <h2 style="margin:0 0 10px;font-size:16px;">Productos incluidos</h2>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
                        <thead><tr style="background:#292524;color:#fff;"><th align="left" style="padding:10px;">Producto</th><th align="center" style="padding:10px;">Cantidad</th><th align="right" style="padding:10px;">Subtotal</th></tr></thead>
                        <tbody>
                            @foreach($version->detalles as $detalle)
                                <tr><td style="border-bottom:1px solid #e7e5e4;padding:10px;">{{ $detalle->nombre_producto }}</td><td align="center" style="border-bottom:1px solid #e7e5e4;padding:10px;">{{ $detalle->cantidad }}</td><td align="right" style="border-bottom:1px solid #e7e5e4;padding:10px;">$ {{ number_format($detalle->subtotal, 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p style="margin:20px 0 0;padding:13px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:12px;line-height:1.6;">
                        @if($ordenCompra->archivo_path)
                            La orden de compra está adjunta a este correo. También puedes abrirla y descargarla desde el detalle de la cotización.
                        @else
                            No se adjuntó un archivo de OC. Abre el detalle en RESMAP para revisar los datos de la autorización recibida.
                        @endif
                    </p>
                    <p style="margin:22px 0 0;"><a href="{{ $urlDetalle }}" style="display:inline-block;background:#b52f25;color:#fff;padding:13px 22px;text-decoration:none;font-weight:bold;">Abrir cotización en RESMAP</a></p>
                    @if($ordenCompra->archivo_path)
                        <p style="margin:14px 0 0;"><a href="{{ $urlOrdenCompra }}" style="color:#9f2f25;font-weight:bold;">Ver / descargar orden de compra</a></p>
                    @endif
                </td></tr>
                <tr><td style="border-top:1px solid #ead8d5;background:#faf7f6;padding:20px 30px;color:#57534e;font-size:12px;line-height:1.7;">
                    RESMAP · RUT 76.991.966-K<br>Los Huillines 1041, Los Ángeles · resmap.la@gmail.com
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
