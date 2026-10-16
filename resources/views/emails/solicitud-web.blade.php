<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva solicitud RESMAP</title>
</head>
<body style="margin:0;background:#f3f4f6;color:#1f2933;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="background:#8f241d;padding:22px 30px;">
                            <img src="{{ $logoUrl }}" alt="RESMAP" width="190" style="display:block;width:190px;max-width:100%;height:auto;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px;">
                            <p style="margin:0 0 8px;color:#8f241d;font-size:13px;font-weight:bold;letter-spacing:1.5px;text-transform:uppercase;">Nueva solicitud web</p>
                            <h1 style="margin:0 0 24px;color:#17211f;font-size:25px;line-height:1.25;">Solicitud #{{ $solicitud->id_solicitud }}</h1>
                            <p style="margin:0 0 24px;color:#4b5563;font-size:15px;line-height:1.6;">Hola {{ $notifiable->name }}, se ha recibido una nueva solicitud en RESMAP.</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:26px;border-collapse:collapse;">
                                <tr>
                                    <td style="width:50%;padding:12px 14px;border:1px solid #e5e7eb;background:#f9fafb;color:#6b7280;font-size:12px;">CLIENTE</td>
                                    <td style="padding:12px 14px;border:1px solid #e5e7eb;color:#17211f;font-size:14px;font-weight:bold;">{{ $cliente->nombre }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;border:1px solid #e5e7eb;background:#f9fafb;color:#6b7280;font-size:12px;">CORREO</td>
                                    <td style="padding:12px 14px;border:1px solid #e5e7eb;color:#17211f;font-size:14px;">{{ $cliente->correo ?: 'No informado' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;border:1px solid #e5e7eb;background:#f9fafb;color:#6b7280;font-size:12px;">TELÉFONO</td>
                                    <td style="padding:12px 14px;border:1px solid #e5e7eb;color:#17211f;font-size:14px;">{{ $cliente->telefono ?: 'No informado' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;border:1px solid #e5e7eb;background:#f9fafb;color:#6b7280;font-size:12px;">ESTADO</td>
                                    <td style="padding:12px 14px;border:1px solid #e5e7eb;color:#17211f;font-size:14px;">{{ $solicitud->estado }}</td>
                                </tr>
                            </table>

                            @if($faltantes)
                                <div style="margin-bottom:26px;border-left:4px solid #d97706;background:#fffbeb;padding:14px;color:#92400e;font-size:13px;line-height:1.5;">
                                    <strong>Advertencia de disponibilidad:</strong> la solicitud supera el stock actual y no reserva unidades. Revisa estos productos antes de prometer una fecha de entrega:
                                    <ul style="margin:8px 0 0;padding-left:20px;">
                                        @foreach($faltantes as $faltante)
                                            <li>{{ $faltante['nombre'] }}: solicitadas {{ $faltante['solicitado'] }}, disponibles {{ $faltante['disponible'] }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <h2 style="margin:0 0 12px;color:#17211f;font-size:18px;">Productos solicitados</h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:26px;">
                                <thead>
                                    <tr>
                                        <th align="left" style="padding:11px 10px;background:#17211f;color:#ffffff;font-size:12px;">Producto</th>
                                        <th align="left" style="padding:11px 10px;background:#17211f;color:#ffffff;font-size:12px;">Código</th>
                                        <th align="center" style="padding:11px 10px;background:#17211f;color:#ffffff;font-size:12px;">Cantidad</th>
                                        <th align="right" style="padding:11px 10px;background:#17211f;color:#ffffff;font-size:12px;">Precio</th>
                                        <th align="right" style="padding:11px 10px;background:#17211f;color:#ffffff;font-size:12px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $item)
                                        <tr>
                                            <td style="padding:12px 10px;border-bottom:1px solid #e5e7eb;color:#17211f;font-size:13px;">{{ $item['nombre'] }}</td>
                                            <td style="padding:12px 10px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:12px;">{{ $item['codigo'] }}</td>
                                            <td align="center" style="padding:12px 10px;border-bottom:1px solid #e5e7eb;color:#17211f;font-size:13px;">{{ $item['cantidad'] }}</td>
                                            <td align="right" style="padding:12px 10px;border-bottom:1px solid #e5e7eb;color:#17211f;font-size:13px;">${{ number_format($item['precio'], 0, ',', '.') }}</td>
                                            <td align="right" style="padding:12px 10px;border-bottom:1px solid #e5e7eb;color:#17211f;font-size:13px;font-weight:bold;">${{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <div style="text-align:center;">
                                <a href="{{ $urlSolicitud }}" style="display:inline-block;background:#c41e3a;color:#ffffff;text-decoration:none;border-radius:5px;padding:13px 24px;font-size:14px;font-weight:bold;">Ver solicitud</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 30px;background:#f9fafb;border-top:1px solid #e5e7eb;">
                            <p style="margin:0 0 5px;color:#17211f;font-size:14px;font-weight:bold;">Equipo RESMAP</p>
                            <p style="margin:0;color:#6b7280;font-size:12px;line-height:1.5;">Repuestos, servicio y soluciones para tu operación.</p>
                            <p style="margin:12px 0 0;color:#9ca3af;font-size:11px;">Este mensaje fue generado automáticamente por RESMAP.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
