<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prueba de correo · RESMAP-APP</title>
</head>
<body style="margin:0;background:#f3f4f6;color:#241817;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:28px 12px;background:#f3f4f6;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border:1px solid #ead8d5;">
                <tr><td style="background:#8f241d;padding:22px 30px;"><img src="{{ $logoUrl }}" alt="RESMAP" width="180" style="display:block;width:180px;max-width:100%;height:auto;"></td></tr>
                <tr><td style="padding:32px 30px;">
                    <p style="margin:0 0 8px;color:#9f2f25;font-size:12px;font-weight:bold;letter-spacing:1.6px;text-transform:uppercase;">Prueba de integración</p>
                    <h1 style="margin:0 0 16px;color:#241817;font-size:25px;line-height:1.3;">Correo enviado correctamente</h1>
                    <p style="margin:0;color:#57534e;font-size:15px;line-height:1.7;">Este mensaje confirma que RESMAP-APP puede conectarse a Mailtrap Email Sandbox mediante SMTP.</p>
                    <p style="margin:20px 0 0;padding:14px;border-left:4px solid #b52f25;background:#fff7f5;color:#6b5b58;font-size:13px;line-height:1.6;">Destinatario de prueba: {{ $recipient }}</p>
                </td></tr>
                <tr><td style="border-top:1px solid #ead8d5;background:#faf7f6;padding:20px 30px;color:#756967;font-size:12px;line-height:1.7;"><strong style="color:#241817;">RESMAP-APP</strong><br>Mensaje manual de prueba. No corresponde a una operación del sistema.</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>