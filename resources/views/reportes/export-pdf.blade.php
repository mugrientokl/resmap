
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de ventas RESMAP</title>
    <style>
        @page { margin: 25px 30px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #241817;
        }
        h1, h2 { color: #8f241d; }
        h1 { margin-bottom: 5px; }
        h2 { font-size: 13px; margin-bottom: 7px; }
        p { margin: 5px 0; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f7e8e6;
            color: #8f241d;
        }
        th, td {
            border: 1px solid #d9aaa3;
            padding: 6px;
            text-align: left;
        }
        .filtros { margin: 12px 0 18px; }
        .filtros td:first-child {
            width: 35%;
            font-weight: bold;
            background: #fcf4f3;
        }
        .datos th {
            background: #8f241d;
            color: white;
        }
        .vacio {
            text-align: center;
            padding: 12px;
        }
    </style>
</head>
<body>
    <h1>Reporte de ventas RESMAP</h1>

    <p>
        <strong>Fecha de generación:</strong>
        {{ $fechaGeneracion->format('d/m/Y H:i') }}
    </p>

    <h2>Filtros aplicados</h2>

    <table class="filtros">
        @foreach ($filtros as $nombre => $valor)
            <tr>
                <td>{{ $nombre }}</td>
                <td>{{ $valor }}</td>
            </tr>
        @endforeach
        <tr>
            <td>Cantidad de ventas</td>
            <td>{{ $ventas->count() }}</td>
        </tr>
        <tr>
            <td>Total vendido</td>
            <td>$ {{ number_format((float) $ventas->sum('total'), 0, ',', '.') }}</td>
        </tr>
    </table>

    <h2>Detalle de ventas</h2>

    <table class="datos">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Usuario</th>
                <th>Medio de pago</th>
                <th>Total</th>
                <th>IVA</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ventas as $venta)
                <tr>
                    <td>{{ $venta->fecha?->format('d/m/Y H:i') }}</td>
                    <td>{{ $venta->cliente?->nombre ?? 'Sin cliente' }}</td>
                    <td>{{ $venta->user?->name ?? 'Sin usuario' }}</td>
                    <td>{{ $venta->medio_pago }}</td>
                    <td>$ {{ number_format((float) $venta->total, 0, ',', '.') }}</td>
                    <td>$ {{ number_format((float) $venta->iva, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="vacio">
                        No hay ventas que coincidan con los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
