<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\OrdenCompra;
use App\Models\User;
use App\Models\Venta;
use App\Services\RegistroActividad;
use App\Services\ResumenInternoVentaPdf;
use App\Support\PeriodoVentas;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $periodo = PeriodoVentas::resolver($request);
        [$desde, $hasta] = $this->limitesAplicados($request, $periodo);
        $aniosDisponibles = collect(PeriodoVentas::aniosDisponibles())->push($periodo['anio'])->unique()->sortDesc()->values()->all();

        $ventasQuery = $this->ventasQuery($request, $desde, $hasta);

        $ventas = (clone $ventasQuery)
            ->latest('fecha')
            ->paginate(20, ['*'], 'ventas_page')
            ->withQueryString();

        $resumen = (clone $ventasQuery)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(SUM(iva), 0) as iva')
            ->first();

        $ventas->getCollection()->each(function (Venta $venta): void {
            $venta->setAttribute('cliente_snapshot', Cliente::desdeSnapshot($venta->snapshotDatosCliente()));
        });

        $usuarios = User::orderBy('name')->get(['id', 'name']);
        $panel = 'ventas';

        return view('reportes.index', compact(
            'ventas',
            'resumen',
            'usuarios',
            'desde',
            'hasta',
            'periodo',
            'aniosDisponibles',
            'panel'
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

        $cliente = Cliente::desdeSnapshot($venta->snapshotDatosCliente());

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
            'cliente' => $cliente->nombre ?: 'Sin cliente',
            'rut_cliente' => $cliente->rut_formateado,
            'usuario' => $venta->user?->name ?? 'Sin usuario',
            'neto' => (float) $venta->neto,
            'iva' => (float) $venta->iva,
            'total' => (float) $venta->total,
            'cotizacion_id' => $venta->cotizacionVersion?->cotizacion_id,
            'cotizacion_numero' => $venta->cotizacionVersion?->cotizacion?->solicitud_id,
            'cotizacion_version' => $venta->cotizacionVersion?->numero_version,
            'resumen_disponible' => $venta->cotizacion_version_id === null
                || ! empty($venta->datos_resumen) || $venta->resumen_interno_pdf !== null,
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
                'producto' => $detalle->producto?->nombre ?? ($detalle->descripcion ?: 'Producto eliminado'),
                'cantidad' => $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'subtotal' => (float) $detalle->subtotal,
            ])->values(),
        ]);
    }

    public function facturaVenta(int $id, ResumenInternoVentaPdf $resumen)
    {
        return $resumen->descargar($this->ventasCompletadasQuery()->findOrFail($id));
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

        $periodo = PeriodoVentas::resolver($request);
        [$desde, $hasta] = $this->limitesAplicados($request, $periodo);

        app(RegistroActividad::class)->evento('exportacion', 'exportado', 'Exportó el reporte de ventas ('.strtoupper($formato).')', 'Venta', nuevos: ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()]);

        $ventas = $this->ventasQuery($request, $desde, $hasta)
            ->latest('fecha')
            ->get();

        $usuario = $request->filled('usuario')
            ? User::find($request->integer('usuario'))?->name
            : null;

        $filtros = [
            'Año' => $periodo['anio'],
            'Mes' => $periodo['mes'] === null ? 'Todos los meses' : ucfirst($periodo['desde']->copy()->locale('es')->isoFormat('MMMM')),
            'Período aplicado' => $desde->lte($hasta) ? $desde->format('d/m/Y').' - '.$hasta->format('d/m/Y') : 'Sin coincidencia entre el período y el rango de fechas',
            'Fecha desde' => $request->filled('desde') ? Carbon::parse($request->input('desde'))->format('d/m/Y') : 'Sin límite adicional',
            'Fecha hasta' => $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->format('d/m/Y') : 'Sin límite adicional',
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
                        Cliente::desdeSnapshot($venta->snapshotDatosCliente())->nombre ?: 'Sin cliente',
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

    /** @param  array{desde: Carbon, hasta: Carbon}  $periodo
     * @return array{Carbon, Carbon}
     */
    private function limitesAplicados(Request $request, array $periodo): array
    {
        $desde = $request->filled('desde') ? Carbon::createFromFormat('Y-m-d', $request->input('desde'))->startOfDay() : $periodo['desde']->copy();
        $hasta = $request->filled('hasta') ? Carbon::createFromFormat('Y-m-d', $request->input('hasta'))->endOfDay() : $periodo['hasta']->copy();

        return [$desde->max($periodo['desde']), $hasta->min($periodo['hasta'])];
    }

    private function ventasQuery(Request $request, Carbon $desde, Carbon $hasta): Builder
    {
        return $this->ventasCompletadasQuery()->with(['cliente', 'user'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($request->filled('venta_id'), fn (Builder $query) => $query->where(
                'id_venta',
                $request->integer('venta_id')
            ))
            ->when($request->filled('cliente'), function (Builder $query) use ($request): void {
                $cliente = $request->string('cliente')->toString();

                $rutBuscado = preg_replace('/[^0-9kK]/', '', $cliente) ?? '';

                $query->where(function (Builder $query) use ($cliente, $rutBuscado): void {
                    $query->whereHas('cliente', function (Builder $clienteQuery) use ($cliente, $rutBuscado): void {
                        $clienteQuery->where('nombre', 'like', '%'.$cliente.'%')
                            ->orWhere('rut', 'like', '%'.$cliente.'%');

                        if (preg_match('/[0-9]/', $cliente) === 1 && $rutBuscado !== '') {
                            $clienteQuery->orWhereRaw("REPLACE(rut, '-', '') LIKE ?", ['%'.strtoupper($rutBuscado).'%']);
                        }
                    })->orWhere('datos_cliente->nombre', 'like', '%'.$cliente.'%');

                    if (preg_match('/[0-9]/', $cliente) === 1 && $rutBuscado !== '') {
                        $query->orWhere('datos_cliente->rut', 'like', '%'.strtoupper($rutBuscado).'%');
                    }
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
        return Venta::completadas();
    }
}
