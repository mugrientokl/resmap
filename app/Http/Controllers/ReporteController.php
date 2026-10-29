<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\OrdenCompra;
use App\Models\User;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $desde = $request->date('desde')?->startOfDay() ?? now()->startOfMonth();
        $hasta = $request->date('hasta')?->endOfDay() ?? now()->endOfDay();

        $ventasQuery = $this->ventasQuery($request, $desde, $hasta);

        $ventas = (clone $ventasQuery)
            ->latest('fecha')
            ->paginate(20, ['*'], 'ventas_page')
            ->withQueryString();

        $resumen = (clone $ventasQuery)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(SUM(iva), 0) as iva')
            ->first();

        $auditorias = $this->auditoriasQuery($request);
        $usuarios = User::orderBy('name')->get(['id', 'name']);
        $modelos = Auditoria::query()->select('modelo')->distinct()->orderBy('modelo')->pluck('modelo');
        $acciones = Auditoria::query()->select('accion')->distinct()->orderBy('accion')->pluck('accion');
        $panel = $request->string('panel', 'ventas')->toString();

        return view('reportes.index', compact(
            'ventas',
            'resumen',
            'auditorias',
            'usuarios',
            'modelos',
            'acciones',
            'desde',
            'hasta',
            'panel'
        ));
    }

    public function auditoria(Request $request)
    {
        return redirect()->route('reportes.index', array_merge(
            $request->query(),
            ['panel' => 'auditoria']
        ));
    }

    public function detalleVenta(int $id)
    {
        $venta = $this->ventasCompletadasQuery()->with([
            'cliente',
            'user',
            'detalles.producto',
            'cotizacionVersion.cotizacion',
            'ordenesCompra',
            'eventosFacturacion.usuario',
        ])->findOrFail($id);

        return response()->json([
            'id_venta' => $venta->id_venta,
            'fecha' => $venta->fecha?->format('d/m/Y H:i'),
            'tipo_documento' => $venta->tipo_documento,
            'folio_sii' => $venta->folio_sii,
            'estado_sii' => $venta->estado_sii,
            'estado_venta' => $venta->estado_venta ?? 'histórica',
            'estado_pago' => $venta->estado_pago ?? 'histórico',
            'estado_facturacion' => $venta->estado_facturacion ?? 'sin registro histórico',
            'estado_entrega' => $venta->estado_entrega ?? 'sin registro histórico',
            'medio_pago' => $venta->medio_pago,
            'cliente' => $venta->cliente?->nombre ?? 'Sin cliente',
            'rut_cliente' => $venta->cliente?->rut,
            'usuario' => $venta->user?->name ?? 'Sin usuario',
            'neto' => (float) $venta->neto,
            'iva' => (float) $venta->iva,
            'total' => (float) $venta->total,
            'cotizacion_id' => $venta->cotizacionVersion?->cotizacion_id,
            'cotizacion_version' => $venta->cotizacionVersion?->numero_version,
            'ordenes_compra' => $venta->ordenesCompra->map(fn (OrdenCompra $orden): array => [
                'id' => $orden->id_orden_compra,
                'numero' => $orden->numero,
                'modalidad' => $orden->modalidad,
                'archivo' => $orden->archivo_path !== null,
            ])->values(),
            'facturacion' => $venta->eventosFacturacion->map(fn ($evento): array => [
                'proveedor' => $evento->proveedor,
                'resultado' => $evento->resultado,
                'fecha' => $evento->created_at?->format('d/m/Y H:i'),
            ])->values(),
            'detalles' => $venta->detalles->map(fn ($detalle): array => [
                'producto' => $detalle->producto?->nombre ?? 'Producto eliminado',
                'cantidad' => $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'subtotal' => (float) $detalle->subtotal,
            ])->values(),
        ]);
    }

    public function facturaVenta(int $id)
    {
        $venta = $this->ventasCompletadasQuery()->with([
            'cliente',
            'user',
            'detalles.producto',
            'cotizacionVersion.cotizacion',
            'ordenesCompra',
            'eventosFacturacion',
        ])->findOrFail($id);

        return Pdf::loadView('reportes.venta-pdf', compact('venta'))
            ->download('resumen-interno-venta-'.$venta->id_venta.'.pdf');
    }

    public function descargarOrdenCompra(OrdenCompra $ordenCompra)
    {
        abort_unless($ordenCompra->archivo_path !== null, 404);
        abort_unless(Storage::disk('local')->exists($ordenCompra->archivo_path), 404);

        return Storage::disk('local')->download(
            $ordenCompra->archivo_path,
            'orden-compra-'.($ordenCompra->numero ?: $ordenCompra->id_orden_compra).'.'.$this->extensionOrden($ordenCompra->archivo_path),
        );
    }

    private function extensionOrden(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION) ?: 'bin';
    }

    public function exportar(Request $request, string $formato)
    {
        abort_unless(in_array($formato, ['csv', 'pdf'], true), 404);

        $desde = $request->date('desde')?->startOfDay() ?? now()->startOfMonth();
        $hasta = $request->date('hasta')?->endOfDay() ?? now()->endOfDay();

        abort_if($desde->gt($hasta), 422, 'La fecha inicial no puede ser posterior a la fecha final.');

        $ventas = $this->ventasQuery($request, $desde, $hasta)
            ->latest('fecha')
            ->get();

        $usuario = $request->filled('usuario')
            ? User::find($request->integer('usuario'))?->name
            : null;

        $filtros = [
            'Fecha desde' => $desde->format('d/m/Y'),
            'Fecha hasta' => $hasta->format('d/m/Y'),
            'Número de venta' => $request->filled('venta_id')
                ? $request->input('venta_id')
                : 'Todas',
            'Cliente' => $request->filled('cliente')
                ? $request->string('cliente')->toString()
                : 'Todos',
            'Usuario' => $request->filled('usuario')
                ? ($usuario ?? 'Usuario no encontrado')
                : 'Todos',
            'Medio de pago' => $request->filled('medio_pago')
                ? $request->string('medio_pago')->toString()
                : 'Todos',
        ];

        $fechaGeneracion = now();

        if ($formato === 'csv') {
            return response()->streamDownload(function () use ($ventas, $filtros, $fechaGeneracion): void {
                $handle = fopen('php://output', 'w');

                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv($handle, ['REPORTE DE VENTAS'], ';');
                fputcsv($handle, ['Fecha de generación', $fechaGeneracion->format('d/m/Y H:i')], ';');

                foreach ($filtros as $nombre => $valor) {
                    fputcsv($handle, [$nombre, $valor], ';');
                }

                fputcsv($handle, ['Cantidad de ventas', $ventas->count()], ';');
                fputcsv($handle, ['Total vendido', $ventas->sum('total')], ';');
                fputcsv($handle, ['IVA total', $ventas->sum('iva')], ';');
                fputcsv($handle, [], ';');

                fputcsv($handle, [
                    'N.º de venta',
                    'Fecha',
                    'Cliente',
                    'Usuario',
                    'Medio de pago',
                    'Total',
                    'IVA',
                ], ';');

                foreach ($ventas as $venta) {
                    fputcsv($handle, [
                        $venta->id_venta,
                        $venta->fecha?->format('d/m/Y H:i'),
                        $venta->cliente?->nombre ?? 'Sin cliente',
                        $venta->user?->name ?? 'Sin usuario',
                        $venta->medio_pago,
                        $venta->total,
                        $venta->iva,
                    ], ';');
                }

                fclose($handle);
            }, 'reporte-ventas-'.$desde->format('Y-m-d').'.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return Pdf::loadView('reportes.export-pdf', compact(
            'ventas',
            'desde',
            'hasta',
            'filtros',
            'fechaGeneracion'
        ))->download('reporte-ventas-'.$desde->format('Y-m-d').'.pdf');
    }

    public function exportarAuditoria(Request $request, string $formato)
    {
        abort_unless(in_array($formato, ['csv', 'pdf'], true), 404);

        $auditorias = $this->auditoriasCollection($request);
        $fechaGeneracion = now();

        $usuario = $request->filled('audit_usuario')
            ? User::find($request->integer('audit_usuario'))?->name
            : null;

        $filtros = [
            'Acción' => $request->filled('audit_accion')
                ? $request->string('audit_accion')->toString()
                : 'Todas',
            'Módulo o modelo' => $request->filled('audit_modelo')
                ? class_basename($request->string('audit_modelo')->toString())
                : 'Todos',
            'Usuario' => $request->filled('audit_usuario')
                ? ($usuario ?? 'Usuario no encontrado')
                : 'Todos',
            'ID del registro' => $request->filled('audit_registro')
                ? $request->input('audit_registro')
                : 'Todos',
            'Fecha desde' => $request->filled('audit_desde')
                ? $request->date('audit_desde')->format('d/m/Y')
                : 'Sin límite inicial',
            'Fecha hasta' => $request->filled('audit_hasta')
                ? $request->date('audit_hasta')->format('d/m/Y')
                : 'Sin límite final',
        ];

        if ($formato === 'csv') {
            return response()->streamDownload(function () use ($auditorias, $filtros, $fechaGeneracion): void {
                $handle = fopen('php://output', 'w');

                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv($handle, ['REPORTE DE AUDITORÍA'], ';');
                fputcsv($handle, ['Fecha de generación', $fechaGeneracion->format('d/m/Y H:i')], ';');

                foreach ($filtros as $nombre => $valor) {
                    fputcsv($handle, [$nombre, $valor], ';');
                }

                fputcsv($handle, ['Cantidad de registros agrupados', $auditorias->count()], ';');
                fputcsv($handle, [], ';');

                fputcsv($handle, [
                    'Fecha',
                    'Usuario',
                    'Cambio',
                    'Módulo',
                    'Detalle',
                ], ';');

                foreach ($auditorias as $auditoria) {
                    fputcsv($handle, [
                        $auditoria->created_at?->format('d/m/Y H:i'),
                        $auditoria->usuario?->name ?? 'Sistema',
                        $auditoria->es_importacion
                            ? 'Importación de datos'
                            : ucfirst($auditoria->accion),
                        $auditoria->es_importacion
                            ? 'Base de datos'
                            : class_basename($auditoria->modelo),
                        $auditoria->es_importacion
                            ? 'Se agruparon '.$auditoria->cantidad_agrupada.' registros'
                            : 'Registro #'.($auditoria->modelo_id ?? 'N/A'),
                    ], ';');
                }

                fclose($handle);
            }, 'reporte-auditoria-'.$fechaGeneracion->format('Y-m-d').'.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return Pdf::loadView('reportes.auditoria-pdf', compact(
            'auditorias',
            'filtros',
            'fechaGeneracion'
        ))->download('reporte-auditoria-'.$fechaGeneracion->format('Y-m-d').'.pdf');
    }

    private function ventasQuery(Request $request, $desde, $hasta): Builder
    {
        return $this->ventasCompletadasQuery()->with(['cliente', 'user'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($request->filled('venta_id'), fn (Builder $query) => $query->where(
                'id_venta',
                $request->integer('venta_id')
            ))
            ->when($request->filled('cliente'), function (Builder $query) use ($request): void {
                $cliente = $request->string('cliente')->toString();

                $query->whereHas('cliente', function (Builder $clienteQuery) use ($cliente): void {
                    $clienteQuery->where('nombre', 'like', '%'.$cliente.'%')
                        ->orWhere('rut', 'like', '%'.$cliente.'%');
                });
            })
            ->when($request->filled('usuario'), fn (Builder $query) => $query->where(
                'user_id',
                $request->integer('usuario')
            ))
            ->when($request->filled('medio_pago'), fn (Builder $query) => $query->where(
                'medio_pago',
                $request->string('medio_pago')->toString()
            ));
    }

    private function ventasCompletadasQuery(): Builder
    {
        return Venta::query()->where(function (Builder $query): void {
            $query->whereNull('cotizacion_version_id')
                ->orWhere(function (Builder $query): void {
                    $query->where('estado_venta', 'confirmada')
                        ->where('estado_facturacion', 'simulada_exitosa')
                        ->where('estado_entrega', 'entregada');
                });
        });
    }

    private function auditoriasQuery(Request $request): LengthAwarePaginator
    {
        $registros = $this->auditoriasCollection($request);

        $porPagina = 20;
        $pagina = LengthAwarePaginator::resolveCurrentPage('auditoria_page');

        return new LengthAwarePaginator(
            $registros->forPage($pagina, $porPagina)->values(),
            $registros->count(),
            $porPagina,
            $pagina,
            [
                'pageName' => 'auditoria_page',
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    private function auditoriasCollection(Request $request): Collection
    {
        return Auditoria::with('usuario')
            ->when($request->filled('audit_accion'), fn (Builder $query) => $query->where(
                'accion',
                $request->string('audit_accion')->toString()
            ))
            ->when($request->filled('audit_modelo'), fn (Builder $query) => $query->where(
                'modelo',
                $request->string('audit_modelo')->toString()
            ))
            ->when($request->filled('audit_usuario'), fn (Builder $query) => $query->where(
                'user_id',
                $request->integer('audit_usuario')
            ))
            ->when($request->filled('audit_registro'), fn (Builder $query) => $query->where(
                'modelo_id',
                $request->integer('audit_registro')
            ))
            ->when($request->filled('audit_desde'), fn (Builder $query) => $query->whereDate(
                'created_at',
                '>=',
                $request->date('audit_desde')
            ))
            ->when($request->filled('audit_hasta'), fn (Builder $query) => $query->whereDate(
                'created_at',
                '<=',
                $request->date('audit_hasta')
            ))
            ->latest()
            ->get()
            ->groupBy(fn (Auditoria $auditoria): string => $auditoria->created_at->format('Y-m-d H:i').'|'.$auditoria->user_id.'|'.$auditoria->accion)
            ->map(function ($grupo): Auditoria {
                $principal = $grupo->first();
                $principal->cantidad_agrupada = $grupo->count();
                $principal->es_importacion = $grupo->count() > 5;

                return $principal;
            })
            ->values();
    }
}
