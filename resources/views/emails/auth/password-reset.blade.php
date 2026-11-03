<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña · RESMAP</title>
</head>
<body style="margin:0;background:#f3f4f6;color:#241817;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border:1px solid #ead8d5;">
                    <tr>
                        <td style="background:#8f241d;padding:22px 30px;color:#ffffff;font-size:19px;font-weight:bold;letter-spacing:3px;">
                            RESMAP
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:34px 30px;">
                            <p style="margin:0 0 8px;color:#9f2f25;font-size:12px;font-weight:bold;letter-spacing:1.6px;text-transform:uppercase;">
                                Seguridad de tu cuenta
                            </p>
                            <h1 style="margin:0 0 18px;color:#241817;font-size:26px;line-height:1.3;">
                                Restablece tu contraseña
                            </h1>
                            <p style="margin:0 0 16px;color:#57534e;font-size:15px;line-height:1.7;">
                                Hola {{ $name }}, recibimos una solicitud para cambiar la contraseña de tu cuenta RESMAP.
                            </p>
                            <p style="margin:0 0 24px;color:#57534e;font-size:15px;line-height:1.7;">
                                Usa el siguiente botón para crear una nueva contraseña. Por seguridad, el enlace estará disponible durante {{ $expirationMinutes }} minutos y solo se puede utilizar una vez.
                            </p>
                            <p style="margin:0 0 24px;text-align:center;">
                                <a href="{{ $url }}" style="display:inline-block;background:#b52f25;color:#ffffff;text-decoration:none;padding:14px 26px;font-size:15px;font-weight:bold;">
                                    Crear nueva contraseña
                                </a>
                            </p>
                            <p style="margin:0 0 8px;color:#756967;font-size:12px;line-height:1.6;">
                                Si el botón no funciona, copia y pega este enlace en tu navegador:
                            </p>
                            <p style="margin:0;color:#9f2f25;font-size:12px;line-height:1.6;word-break:break-all;">
                                <a href="{{ $url }}" style="color:#9f2f25;">{{ $url }}</a>
                            </p>
                            <div style="margin-top:26px;border-left:4px solid #d9aaa3;background:#fff7f5;padding:13px 15px;color:#6b5b58;font-size:12px;line-height:1.6;">
                                Si no solicitaste este cambio, puedes ignorar este correo. Tu contraseña actual seguirá siendo válida.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #ead8d5;background:#faf7f6;padding:20px 30px;color:#756967;font-size:12px;line-height:1.7;">
                            <strong style="color:#241817;">Equipo RESMAP</strong><br>
                            Repuestos, servicio y soluciones para tu operación.<br>
                            Este mensaje fue generado automáticamente. Por favor, no respondas a este correo.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
