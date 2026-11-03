<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen interno de servicio #{{ $solicitud->id_solicitud }}</title>
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
        .box { border: 1px solid #e7e5e4; line-height: 1.6; padding: 10px 12px; white-space: pre-line; }
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
                <h1>RESUMEN INTERNO DE SERVICIO</h1>
                <div class="muted">Fecha de solicitud: {{ $solicitud->fecha?->format('d/m/Y H:i') }}</div>
                <div class="muted">Estado: {{ $solicitud->estado }}</div>
            </td>
            <td class="number">Servicio N.º {{ $solicitud->id_solicitud }}</td>
        </tr>
    </table>
    <div class="client">
        <strong>CLIENTE</strong><br>
        {{ $solicitud->cliente->nombre }}<br>
        RUT: {{ $solicitud->cliente->rut_formateado }}<br>
        Correo: {{ $solicitud->cliente->correo ?: 'No informado' }}<br>
        Teléfono: {{ $solicitud->cliente->telefono ?: 'No informado' }}<br>
        Dirección: {{ collect([$solicitud->cliente->direccion, $solicitud->cliente->comuna, $solicitud->cliente->region])->filter()->implode(', ') ?: 'No informada' }}
    </div>
    <p class="section-title">SERVICIO SOLICITADO: {{ strtoupper($solicitud->tipo_servicio) }}</p>
    <div class="box">{{ $solicitud->descripcion_servicio }}</div>
    @if($solicitud->observaciones)
        <p class="section-title">OBSERVACIONES</p>
        <div class="box">{{ $solicitud->observaciones }}</div>
    @endif
    <div class="note"><strong>Documento interno</strong><br>Este resumen no es una boleta, factura ni un documento tributario electrónico.</div>
    <div class="footer">
        <strong>Contacto RESMAP</strong><br>
        John Sanzana Aravena · +56976477124 · +56951523990<br>
        resmap.la@gmail.com · Los Huillines 1041 – Los Ángeles
    </div>
</body>
</html>
