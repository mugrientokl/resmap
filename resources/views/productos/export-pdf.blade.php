
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Inventario RESMAP</title>
    <style>
        @page { margin: 25px 30px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #241817;
        }
        h1 {
            color: #8f241d;
            margin-bottom: 5px;
        }
        h2 {
            color: #8f241d;
            font-size: 13px;
            margin-bottom: 7px;
        }
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
        .filtros {
            margin: 12px 0 18px;
        }
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
    <h1>Inventario RESMAP</h1>

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
            <td>Total de productos</td>
            <td>{{ $productos->count() }}</td>
        </tr>
    </table>

    <h2>Productos del inventario</h2>

    <table class="datos">
        <thead>
            <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Precio</th>
                <th>Stock actual</th>
                <th>Stock crítico</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($productos as $producto)
                <tr>
                    <td>{{ $producto->codigo_barra }}</td>
                    <td>{{ $producto->nombre }}</td>
                    <td>{{ $producto->categoria?->nombre_categoria ?? 'Sin categoría' }}</td>
                    <td>$ {{ number_format($producto->precio, 0, ',', '.') }}</td>
                    <td>{{ $producto->stock }}</td>
                    <td>{{ $producto->stock_critico }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="vacio">
                        No hay productos que coincidan con los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
