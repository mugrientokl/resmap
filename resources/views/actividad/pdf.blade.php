<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registro de actividad RESMAP</title>
    <style>
        @page { margin: 28px 34px; }
        body { color: #241817; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { color: #b52f25; font-size: 20px; margin: 0 0 4px; }
        h2 { color: #b52f25; font-size: 12px; margin-bottom: 7px; }
        .muted { color: #756967; }
        p { margin: 5px 0; }
        table { border-collapse: collapse; width: 100%; }
        .filtros { margin: 12px 0 18px; }
        .filtros td { border: 1px solid #ead8d5; padding: 6px; }
        .filtros td:first-child { width: 35%; font-weight: bold; background: #f7e8e6; color: #8f241d; }
        .datos th { background: #241817; color: white; padding: 8px; text-align: left; text-transform: uppercase; }
        .datos td { border-bottom: 1px solid #ead8d5; padding: 8px; }
        .vacio { text-align: center; padding: 12px; }
    </style>
</head>
<body>
    <h1>Registro de actividad</h1>
    <p class="muted">Eventos registrados en RESMAP</p>
    <p><strong>Fecha de generación:</strong> {{ $fechaGeneracion->format('d/m/Y H:i') }}</p>

    <h2>Filtros aplicados</h2>
    <table class="filtros">
        @forelse ($filtros as $nombre => $valor)
            <tr><td>{{ ucfirst($nombre) }}</td><td>{{ $valor }}</td></tr>
        @empty
            <tr><td>Filtros</td><td>Ninguno</td></tr>
        @endforelse
        <tr><td>Registros incluidos</td><td>{{ $registros->count() }} (máximo {{ $limite }})</td></tr>
    </table>

    <h2>Detalle</h2>
    <table class="datos">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Categoría</th><th>Módulo</th><th>Descripción</th><th>Resultado</th></tr></thead>
        <tbody>
            @forelse ($registros as $registro)
                <tr>
                    <td>{{ $registro->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $registro->nombre_actor }}</td>
                    <td>{{ $registro->etiqueta_categoria }}</td>
                    <td>{{ $registro->etiqueta_modelo }}</td>
                    <td>{{ $registro->descripcion }}</td>
                    <td>{{ $registro->resultado === 'fallo' ? 'Fallo' : 'Éxito' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="vacio">No hay actividad para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
