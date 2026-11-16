<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registro de actividad RESMAP</title>
    <style>
        @page { size: landscape; margin: 28px 30px 38px; }
        body { color: #292524; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { color: #9f2f25; font-size: 20px; margin: 0 0 4px; }
        h2 { color: #292524; font-size: 11px; margin: 18px 0 8px; }
        p { margin: 4px 0; }
        .muted { color: #78716c; }
        .header { border-bottom: 3px solid #9f2f25; margin-bottom: 14px; padding-bottom: 10px; width: 100%; }
        .header td { vertical-align: bottom; }
        .header-meta { color: #57534e; line-height: 1.6; text-align: right; }
        table { border-collapse: collapse; width: 100%; }
        .filtros { table-layout: fixed; }
        .filtros td { border: 1px solid #e7e5e4; padding: 6px 8px; }
        .filtros td:first-child { background: #faf7f6; color: #8f241d; font-weight: bold; width: 25%; }
        .datos { font-size: 8px; table-layout: fixed; }
        .datos thead { display: table-header-group; }
        .datos th { background: #292524; color: white; font-size: 7px; padding: 7px 6px; text-align: left; text-transform: uppercase; }
        .datos td { border-bottom: 1px solid #e7e5e4; line-height: 1.45; padding: 7px 6px; vertical-align: top; word-wrap: break-word; }
        .datos tr { page-break-inside: avoid; }
        .fecha { color: #57534e; }
        .actor { font-weight: bold; }
        .ip { color: #78716c; font-size: 7px; }
        .resultado { font-weight: bold; }
        .fallo { color: #b91c1c; }
        .exito { color: #047857; }
        .vacio { color: #78716c; padding: 16px; text-align: center; }
        .footer { border-top: 1px solid #d6d3d1; color: #78716c; font-size: 8px; margin-top: 18px; padding-top: 8px; text-align: center; }
    </style>
</head>
<body>
    @php
        $etiquetasFiltros = [
            'categoria' => 'Categoría',
            'accion' => 'Acción',
            'modelo' => 'Módulo',
            'usuario' => 'ID de usuario',
            'registro' => 'ID de registro',
            'resultado' => 'Resultado',
            'lote' => 'Lote',
            'desde' => 'Desde',
            'hasta' => 'Hasta',
        ];
    @endphp

    <table class="header">
        <tr>
            <td>
                <h1>Registro de actividad</h1>
                <p class="muted">Eventos registrados en RESMAP · Reporte de trazabilidad</p>
            </td>
            <td class="header-meta">
                <strong>Generado:</strong> {{ $fechaGeneracion->format('d/m/Y H:i') }}<br>
                <strong>Registros exportados:</strong> {{ $registros->count() }} de {{ $totalRegistros }}
            </td>
        </tr>
    </table>

    <h2>Filtros aplicados</h2>
    <table class="filtros">
        @forelse ($filtros as $nombre => $valor)
            @php
                $valorPresentado = match ($nombre) {
                    'categoria' => config("actividad.categorias.{$valor}", ucfirst($valor)),
                    'accion' => ucfirst(str_replace('_', ' ', $valor)),
                    'modelo' => config("actividad.modelos.{$valor}.etiqueta") ?? class_basename($valor),
                    'resultado' => $valor === 'fallo' ? 'Fallo' : 'Éxito',
                    'desde', 'hasta' => \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y'),
                    default => $valor,
                };
            @endphp
            <tr>
                <td>{{ $etiquetasFiltros[$nombre] ?? ucfirst($nombre) }}</td>
                <td>{{ $valorPresentado }}</td>
            </tr>
        @empty
            <tr><td>Filtros</td><td>Ninguno</td></tr>
        @endforelse
        <tr><td>Límite del PDF</td><td>{{ $limite }} registros</td></tr>
    </table>

    @if($totalRegistros > $registros->count())
        <p class="muted">Se incluyen los {{ $registros->count() }} registros más recientes de un total filtrado de {{ $totalRegistros }}.</p>
    @endif

    <h2>Detalle de eventos</h2>
    <table class="datos">
        <thead>
            <tr>
                <th style="width: 12%">Fecha / ID</th>
                <th style="width: 16%">Usuario / IP</th>
                <th style="width: 11%">Categoría</th>
                <th style="width: 12%">Módulo</th>
                <th style="width: 11%">Acción</th>
                <th style="width: 30%">Descripción</th>
                <th style="width: 8%">Resultado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $registro)
                <tr>
                    <td class="fecha">
                        {{ $registro->created_at?->format('d/m/Y H:i') }}<br>
                        <strong>#{{ $registro->id_auditoria }}</strong>
                    </td>
                    <td>
                        <span class="actor">{{ $registro->nombre_actor }}</span><br>
                        <span class="ip">IP: {{ $registro->ip ?: 'No registrada' }}</span>
                    </td>
                    <td>{{ $registro->etiqueta_categoria }}</td>
                    <td>{{ $registro->etiqueta_modelo }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $registro->accion)) }}</td>
                    <td>{{ $registro->descripcion ?: '—' }}</td>
                    <td class="resultado {{ $registro->resultado === 'fallo' ? 'fallo' : 'exito' }}">
                        {{ $registro->resultado === 'fallo' ? 'Fallo' : 'Éxito' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="vacio">No hay actividad para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">RESMAP · Registro interno de actividad · Los eventos se presentan en orden cronológico inverso.</p>
</body>
</html>
