<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Historial de ventas - {{ $cliente->nombre }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { color: #241817; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .top { border-bottom: 3px solid #241817; padding-bottom: 14px; }
        .brand { color: #b52f25; font-size: 20px; font-weight: bold; }
        .muted { color: #756967; }
        .right { text-align: right; }
        .label { color: #9f2f25; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .summary { background: #fff8f6; border: 1px solid #ead8d5; margin: 16px 0; padding: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #241817; color: white; font-size: 8px; padding: 7px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #ead8d5; padding: 7px; }
        .number { text-align: right; }
        .sale { margin: 18px 0; }
        .sale-heading { background: #f7e8e6; border-bottom: 1px solid #ead8d5; font-weight: bold; padding: 8px; }
        .sale-totals { margin: 8px 0 0 auto; width: 220px; }
        .sale-totals td { border: 0; padding: 3px 0; }
        .grand-total { border-top: 2px solid #241817; color: #b52f25; font-size: 11px; font-weight: bold; }
        .grand-total td { padding-top: 7px; }
        .empty { color: #756967; padding: 16px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="top">
        <table>
            <tr>
                <td style="border: 0; padding: 0;">
                    <div class="brand">RESMAP</div>
                    <div class="muted">Historial consolidado de ventas</div>
                </td>
                <td class="right" style="border: 0; padding: 0;">
                    <div class="label">Fecha de emisión</div>
                    {{ now()->format('d/m/Y H:i') }}
                </td>
            </tr>
        </table>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td style="border: 0; padding: 0;">
                    <div class="label">Cliente</div>
                    <strong>{{ $cliente->nombre }}</strong><br>
                    RUT: {{ $cliente->rut }}
                </td>
                <td class="right" style="border: 0; padding: 0;">
                    <div class="label">Resumen</div>
                    Ventas registradas: {{ $ventas->count() }}<br>
                    Total acumulado: <strong>$ {{ number_format($ventas->sum('total'), 0, ',', '.') }}</strong>
                </td>
            </tr>
        </table>
    </div>

    @forelse($ventas as $venta)
        <div class="sale">
            <div class="sale-heading">
                Venta #{{ $venta->id_venta }}
                &nbsp;·&nbsp; {{ $venta->fecha?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                &nbsp;·&nbsp; {{ $venta->tipo_documento }}
                &nbsp;·&nbsp; Pago: {{ $venta->medio_pago }}
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="number">Cantidad</th>
                        <th class="number">Precio unitario</th>
                        <th class="number">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($venta->detalles as $detalle)
                        <tr>
                            <td>{{ $detalle->producto?->nombre ?? $detalle->descripcion ?? 'Producto eliminado' }}</td>
                            <td class="number">{{ $detalle->cantidad }}</td>
                            <td class="number">$ {{ number_format($detalle->precio_unitario, 0, ',', '.') }}</td>
                            <td class="number">$ {{ number_format($detalle->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">Esta venta no tiene detalle de productos disponible.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <table class="sale-totals">
                <tr>
                    <td class="muted">Neto</td>
                    <td class="number">$ {{ number_format($venta->neto, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="muted">IVA</td>
                    <td class="number">$ {{ number_format($venta->iva, 0, ',', '.') }}</td>
                </tr>
                <tr class="grand-total">
                    <td>Total</td>
                    <td class="number">$ {{ number_format($venta->total, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    @empty
        <p class="empty">Este cliente todavía no tiene ventas registradas.</p>
    @endforelse

    <p class="muted" style="margin-top: 30px; text-align: center;">
        Documento interno — No válido como documento tributario.
    </p>
</body>
</html>
