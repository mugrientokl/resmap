<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de auditoría</title>
    <style>
        @page { margin: 28px 34px; }
        body { color: #241817; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { color: #b52f25; font-size: 20px; margin: 0 0 4px; }
        .muted { color: #756967; }
        table { border-collapse: collapse; margin-top: 20px; width: 100%; }
        th { background: #241817; color: white; padding: 8px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #ead8d5; padding: 8px; }
    </style>
</head>
<body>
    <h1>Reporte de auditoría</h1>
    <p class="muted">Cambios registrados en RESMAP · {{ now()->format('d/m/Y H:i') }}</p>
    <table>
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Cambio</th><th>Módulo</th><th>Detalle</th></tr></thead>
        <tbody>
            @forelse($auditorias as $auditoria)
                <tr><td>{{ $auditoria->created_at->format('d/m/Y H:i') }}</td><td>{{ $auditoria->usuario?->name ?? 'Sistema' }}</td><td>{{ $auditoria->es_importacion ? 'Importación de datos' : ucfirst($auditoria->accion) }}</td><td>{{ $auditoria->es_importacion ? 'Base de datos' : class_basename($auditoria->modelo) }}</td><td>{{ $auditoria->es_importacion ? 'Se importaron '.$auditoria->cantidad_agrupada.' registros' : 'Registro #'.($auditoria->modelo_id ?? 'N/A') }}</td></tr>
            @empty
                <tr><td colspan="5">No hay cambios para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>