
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de auditoría RESMAP</title>
    <style>
        @page { margin: 28px 34px; }
        body {
            color: #241817;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
        }
        h1 {
            color: #b52f25;
            font-size: 20px;
            margin: 0 0 4px;
        }
        h2 {
            color: #b52f25;
            font-size: 12px;
            margin-bottom: 7px;
        }
        .muted { color: #756967; }
        p { margin: 5px 0; }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        .filtros { margin: 12px 0 18px; }
        .filtros th {
            background: #f7e8e6;
            color: #8f241d;
            text-align: left;
        }
        .filtros td, .filtros th {
            border: 1px solid #ead8d5;
            padding: 6px;
        }
        .filtros td:first-child {
            width: 35%;
            font-weight: bold;
        }
        .datos { margin-top: 10px; }
        .datos th {
            background: #241817;
            color: white;
            padding: 8px;
            text-align: left;
            text-transform: uppercase;
        }
        .datos td {
            border-bottom: 1px solid #ead8d5;
            padding: 8px;
        }
        .vacio {
            text-align: center;
            padding: 12px;
        }
    </style>
</head>
<body>
    <h1>Reporte de auditoría</h1>

    <p class="muted">
        Cambios registrados en RESMAP
    </p>

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
            <td>Registros agrupados</td>
            <td>{{ $auditorias->count() }}</td>
        </tr>
    </table>

    <h2>Detalle de auditoría</h2>

    <table class="datos">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Cambio</th>
                <th>Módulo</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($auditorias as $auditoria)
                <tr>
                    <td>{{ $auditoria->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $auditoria->usuario?->name ?? 'Sistema' }}</td>
                    <td>
                        {{ $auditoria->es_importacion
                            ? 'Importación de datos'
                            : ucfirst($auditoria->accion) }}
                    </td>
                    <td>
                        {{ $auditoria->es_importacion
                            ? 'Base de datos'
                            : class_basename($auditoria->modelo) }}
                    </td>
                    <td>
                        {{ $auditoria->es_importacion
                            ? 'Se importaron '.$auditoria->cantidad_agrupada.' registros'
                            : 'Registro #'.($auditoria->modelo_id ?? 'N/A') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="vacio">
                        No hay cambios para los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
