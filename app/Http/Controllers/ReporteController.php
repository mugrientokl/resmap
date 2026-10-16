<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $desde = $request->date('desde')?->startOfDay() ?? now()->startOfMonth();
        $hasta = $request->date('hasta')?->endOfDay() ?? now()->endOfDay();
        $ventasQuery = $this->ventasQuery($request, $desde, $hasta);
        $ventas = (clone $ventasQuery)->latest('fecha')->paginate(20, ['*'], 'ventas_page')->withQueryString();
        $resumen = (clone $ventasQuery)->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total, COALESCE(SUM(iva), 0) as iva')->first();
        $pendientes = SolicitudWeb::whereIn('estado', ['Pendiente', 'Pagado - pendiente de entrega', 'Entregado - pago pendiente'])->count();
        $auditorias = $this->auditoriasQuery($request);
        $usuarios = User::orderBy('name')->get(['id', 'name']);
        $modelos = Auditoria::query()->select('modelo')->distinct()->orderBy('modelo')->pluck('modelo');
        $acciones = Auditoria::query()->select('accion')->distinct()->orderBy('accion')->pluck('accion');
        $panel = $request->string('panel', 'ventas')->toString();

        return view('reportes.index', compact('ventas', 'resumen', 'pendientes', 'auditorias', 'usuarios', 'modelos', 'acciones', 'desde', 'hasta', 'panel'));
    }

    public function auditoria(Request $request)
    {
        return redirect()->route('reportes.index', array_merge($request->query(), ['panel' => 'auditoria']));
    }

    public function detalleVenta(int $id)
    {
        $venta = Venta::with(['cliente', 'user', 'detalles.producto'])->findOrFail($id);

        return response()->json([
            'id_venta' => $venta->id_venta,
            'fecha' => $venta->fecha?->format('d/m/Y H:i'),
            'tipo_documento' => $venta->tipo_documento,
            'folio_sii' => $venta->folio_sii,
            'estado_sii' => $venta->estado_sii,
            'medio_pago' => $venta->medio_pago,
            'cliente' => $venta->cliente?->nombre ?? 'Sin cliente',
            'rut_cliente' => $venta->cliente?->rut,
            'usuario' => $venta->user?->name ?? 'Sin usuario',
            'neto' => (float) $venta->neto,
            'iva' => (float) $venta->iva,
            'total' => (float) $venta->total,
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
        $venta = Venta::with(['cliente', 'user', 'detalles.producto'])->findOrFail($id);

        return Pdf::loadView('reportes.venta-pdf', compact('venta'))
            ->download('factura-venta-'.$venta->id_venta.'.pdf');
    }

    public function exportar(Request $request, string $formato)
    {
        abort_unless(in_array($formato, ['csv', 'pdf'], true), 404);
        $desde = $request->date('desde')?->startOfDay() ?? now()->startOfMonth();
        $hasta = $request->date('hasta')?->endOfDay() ?? now()->endOfDay();
        $ventas = $this->ventasQuery($request, $desde, $hasta)->latest('fecha')->get();

        if ($formato === 'csv') {
            return response()->streamDownload(function () use ($ventas): void {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, ['Fecha', 'Cliente', 'Usuario', 'Medio de pago', 'Total', 'IVA'], ';');
                foreach ($ventas as $venta) {
                    fputcsv($handle, [$venta->fecha->format('Y-m-d H:i'), $venta->cliente?->nombre, $venta->user?->name, $venta->medio_pago, $venta->total, $venta->iva], ';');
                }
                fclose($handle);
            }, 'reporte-ventas-'.$desde->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return Pdf::loadView('reportes.export-pdf', compact('ventas', 'desde', 'hasta'))->download('reporte-ventas-'.$desde->format('Y-m-d').'.pdf');
    }

    public function exportarAuditoria(Request $request, string $formato)
    {
        abort_unless(in_array($formato, ['csv', 'pdf'], true), 404);
        $auditorias = $this->auditoriasCollection($request);

        if ($formato === 'csv') {
            return response()->streamDownload(function () use ($auditorias): void {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, ['Fecha', 'Usuario', 'Cambio', 'Módulo', 'Detalle'], ';');
                foreach ($auditorias as $auditoria) {
                    fputcsv($handle, [
                        $auditoria->created_at->format('Y-m-d H:i'),
                        $auditoria->usuario?->name ?? 'Sistema',
                        $auditoria->es_importacion ? 'Importación de datos' : ucfirst($auditoria->accion),
                        $auditoria->es_importacion ? 'Base de datos' : class_basename($auditoria->modelo),
                        $auditoria->es_importacion
                            ? 'Se importaron '.$auditoria->cantidad_agrupada.' registros'
                            : 'Registro #'.($auditoria->modelo_id ?? 'N/A'),
                    ], ';');
                }
                fclose($handle);
            }, 'reporte-auditoria-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return Pdf::loadView('reportes.auditoria-pdf', compact('auditorias'))
            ->download('reporte-auditoria-'.now()->format('Y-m-d').'.pdf');
    }

    private function ventasQuery(Request $request, $desde, $hasta): Builder
    {
        return Venta::with(['cliente', 'user'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($request->filled('venta_id'), fn (Builder $query) => $query->where('id_venta', $request->integer('venta_id')))
            ->when($request->filled('cliente'), fn (Builder $query) => $query->whereHas('cliente', fn (Builder $clienteQuery) => $clienteQuery->where('nombre', 'like', '%'.$request->string('cliente')->toString().'%')->orWhere('rut', 'like', '%'.$request->string('cliente')->toString().'%')))
            ->when($request->filled('usuario'), fn (Builder $query) => $query->where('user_id', $request->integer('usuario')))
            ->when($request->filled('medio_pago'), fn (Builder $query) => $query->where('medio_pago', $request->string('medio_pago')->toString()));
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
            ['pageName' => 'auditoria_page', 'path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function auditoriasCollection(Request $request): Collection
    {
        return Auditoria::with('usuario')
            ->when($request->filled('audit_accion'), fn (Builder $query) => $query->where('accion', $request->string('audit_accion')->toString()))
            ->when($request->filled('audit_modelo'), fn (Builder $query) => $query->where('modelo', $request->string('audit_modelo')->toString()))
            ->when($request->filled('audit_usuario'), fn (Builder $query) => $query->where('user_id', $request->integer('audit_usuario')))
            ->when($request->filled('audit_registro'), fn (Builder $query) => $query->where('modelo_id', $request->integer('audit_registro')))
            ->when($request->filled('audit_desde'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('audit_desde')))
            ->when($request->filled('audit_hasta'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('audit_hasta')))
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
