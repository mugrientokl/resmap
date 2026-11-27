<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva cotización B2B · RESMAP-APP</title>
</head>
<body style="margin:0;background:#f3f4f6;color:#241817;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:28px 12px;background:#f3f4f6;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border:1px solid #ead8d5;">
                <tr><td style="background:#8f241d;padding:22px 30px;"><img src="{{ $logoUrl }}" alt="RESMAP" width="180" style="display:block;width:180px;max-width:100%;height:auto;"></td></tr>
                <tr><td style="padding:32px 30px;">
                    <p style="margin:0 0 8px;color:#9f2f25;font-size:12px;font-weight:bold;letter-spacing:1.6px;text-transform:uppercase;">Gestión comercial</p>
                    <h1 style="margin:0 0 16px;color:#241817;font-size:25px;line-height:1.3;">Nueva cotización B2B pendiente</h1>
                    <p style="margin:0 0 18px;color:#57534e;font-size:15px;line-height:1.7;">Hola {{ $name }}, se recibió la cotización <strong>#{{ $cotizacion->solicitud_id }}</strong> y requiere revisión.</p>
                    @if($venceAt)
                        <p style="margin:0 0 24px;padding:14px;border-left:4px solid #b52f25;background:#fff7f5;color:#6b5b58;font-size:14px;line-height:1.6;">La reserva inicial vence el {{ \Illuminate\Support\Carbon::parse($venceAt)->format('d/m/Y H:i') }}.</p>
                    @endif
                    <p style="margin:0;text-align:center;"><a href="{{ $urlSolicitud }}" style="display:inline-block;background:#b52f25;color:#ffffff;text-decoration:none;padding:14px 24px;font-size:14px;font-weight:bold;">Revisar cotización</a></p>
                </td></tr>
                <tr><td style="border-top:1px solid #ead8d5;background:#faf7f6;padding:20px 30px;color:#756967;font-size:12px;line-height:1.7;"><strong style="color:#241817;">Equipo RESMAP</strong><br>Mensaje automático de RESMAP-APP.</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>