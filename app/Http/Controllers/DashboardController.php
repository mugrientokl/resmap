<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\DetalleVenta;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\SolicitudWeb;
use App\Models\Venta;
use App\Support\PeriodoVentas;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.index', $this->obtenerDatosDashboard($request));
    }

    public function exportarPdf(Request $request)
    {
        $ahora = now();
        $logo = file_get_contents(public_path('images/resmap sin fondo.png'));

        return Pdf::loadView('dashboard.pdf', [
            ...$this->obtenerDatosDashboard($request),
            'fechaGeneracion' => $ahora,
            'logoDataUri' => 'data:image/png;base64,'.base64_encode($logo === false ? '' : $logo),
        ])->setPaper('a4')->download('dashboard-resmap-'.$ahora->format('Y-m-d').'.pdf');
    }

    private function obtenerDatosDashboard(Request $request): array
    {
        $seleccion = $this->resolverSeleccion($request);

        return [
            'seleccion' => $seleccion,
            'aniosDisponibles' => collect(PeriodoVentas::aniosDisponibles())->push($seleccion['anio'])->unique()->sortDesc()->values()->all(),
            'ventasMensuales' => $seleccion['mes'] === null ? $this->obtenerVentasMensuales($seleccion['desde'], $seleccion['hasta']) : [],
            'ventasDiarias' => $seleccion['mes'] !== null ? $this->obtenerVentasDiarias($seleccion['desde'], $seleccion['hasta']) : [],
            'ventasSemanales' => $seleccion['mes'] !== null ? $this->obtenerVentasSemanales($seleccion['desde'], $seleccion['hasta']) : [],
            'productosMasVendidos' => $this->obtenerProductosMasVendidos($seleccion['desde'], $seleccion['hasta']),
            'resumen' => $this->obtenerResumen($seleccion['desde'], $seleccion['hasta']),
            'cotizacionesNoCompletadas' => $this->contarCotizacionesNoCompletadas($seleccion['desde'], $seleccion['hasta']),
            'porcentajeGanancia' => $this->obtenerPorcentajeGanancia($seleccion['desde'], $seleccion['hasta']),
            'rentabilidad' => $this->obtenerIndicadoresRentabilidad($seleccion['desde'], $seleccion['hasta']),
            'cantidadStockCritico' => Producto::query()->whereColumn('stock', '<=', 'stock_critico')->count(),
        ];
    }

    /**
     * @return array{anio: int, mes: ?int, dia: ?int, etiqueta: string, descripcion: string, desde: Carbon, hasta: Carbon}
     */
    private function resolverSeleccion(Request $request): array
    {
        $periodo = PeriodoVentas::resolver($request);
        $dia = null;
        if ($request->filled('dia')) {
            $dia = filter_var($request->query('dia'), FILTER_VALIDATE_INT);
            abort_if($periodo['mes'] === null || $dia === false || $dia < 1 || $dia > $periodo['desde']->daysInMonth, 422, 'El día seleccionado no es válido.');
            $periodo['desde']->day($dia)->startOfDay();
            $periodo['hasta'] = $periodo['desde']->copy()->endOfDay();
        }

        $etiqueta = $dia !== null
            ? $periodo['desde']->copy()->locale('es')->isoFormat('D [de] MMMM YYYY')
            : ($periodo['mes'] !== null ? $periodo['desde']->copy()->locale('es')->isoFormat('MMMM YYYY') : (string) $periodo['anio']);

        return [
            ...$periodo,
            'dia' => $dia,
            'etiqueta' => $etiqueta,
            'descripcion' => 'Ventas completadas de '.$etiqueta,
        ];
    }

    /**
     * @return array{
     *     total: array{cantidad: int, monto: float},
     *     pos: array{cantidad: int, monto: float},
     *     cotizaciones: array{cantidad: int, monto: float}
     * }
     */
    private function obtenerResumen(Carbon $desde, Carbon $hasta): array
    {
        $ventas = fn (): Builder => $this->ventasCompletadasEntre($desde, $hasta);

        return [
            'total' => $this->resumirVentas($ventas()),
            'pos' => $this->resumirVentas(
                $ventas()
                    ->whereNull('cotizacion_version_id')
                    ->whereNotIn('id_venta', SolicitudWeb::query()->select('venta_id')->whereNotNull('venta_id'))
            ),
            'cotizaciones' => $this->resumirVentas($ventas()->whereNotNull('cotizacion_version_id')),
        ];
    }

    /**
     * @return array{cantidad: int, monto: float}
     */
    private function resumirVentas(Builder $consulta): array
    {
        $resumen = $consulta->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto')->firstOrFail();

        return ['cantidad' => (int) $resumen->cantidad, 'monto' => (float) $resumen->monto];
    }

    /**
     * @return array<int, array{etiqueta: string, periodo: string, total: float, cantidad: int}>
     */
    private function obtenerVentasMensuales(Carbon $desde, Carbon $hasta): array
    {
        $ventasPorMes = $this->ventasCompletadasEntre($desde, $hasta)
            ->get(['fecha', 'total'])
            ->groupBy(fn (Venta $venta): string => $venta->fecha->format('Y-m'));
        $ventasMensuales = [];

        for ($mes = $desde->copy()->startOfMonth(); $mes->lte($hasta); $mes->addMonth()) {
            $clave = $mes->format('Y-m');
            $ventas = $ventasPorMes->get($clave, collect());

            $ventasMensuales[] = [
                'etiqueta' => $mes->copy()->locale('es')->isoFormat('MMMM YYYY'),
                'etiquetaCorta' => ucfirst($mes->copy()->locale('es')->isoFormat('MMM YY')),
                'periodo' => $clave,
                'total' => (float) $ventas->sum('total'),
                'cantidad' => $ventas->count(),
            ];
        }

        return $ventasMensuales;
    }

    /**
     * Compatibilidad de datos: conserva el detalle diario para consultas/API existentes.
     *
     * @return array<int, array{etiqueta: string, dia: int, total: float, cantidad: int}>
     */
    private function obtenerVentasDiarias(Carbon $inicioMes, Carbon $hasta): array
    {
        $ventasPorDia = $this->ventasCompletadasEntre($inicioMes, $hasta->copy()->endOfDay())
            ->get(['fecha', 'total'])
            ->groupBy(fn (Venta $venta): int => $venta->fecha->day);
        $ventasDiarias = [];

        for ($dia = $inicioMes->day; $dia <= $hasta->day; $dia++) {
            $ventas = $ventasPorDia->get($dia, collect());
            $ventasDiarias[] = [
                'etiqueta' => (string) $dia,
                'dia' => $dia,
                'total' => (float) $ventas->sum('total'),
                'cantidad' => $ventas->count(),
            ];
        }

        return $ventasDiarias;
    }

    /**
     * @return array<int, array{etiqueta: string, semana: int, total: float, cantidad: int}>
     */
    private function obtenerVentasSemanales(Carbon $inicioMes, Carbon $hasta): array
    {
        $ventasPorFecha = $this->ventasCompletadasEntre($inicioMes->copy()->startOfMonth(), $hasta->copy()->endOfDay())
            ->get(['fecha', 'total'])
            ->groupBy(fn (Venta $venta): string => $venta->fecha->format('Y-m-d'));
        $ventasSemanales = [];
        $inicio = $inicioMes->copy()->startOfMonth()->startOfWeek(1);
        $finMes = $hasta->copy()->endOfMonth();

        for ($numero = 1; $inicio->lte($finMes); $numero++) {
            $finSemana = $inicio->copy()->endOfWeek(0);
            $dentroMesDesde = $inicio->copy()->max($inicioMes->copy()->startOfMonth());
            $dentroMesHasta = $finSemana->copy()->min($finMes);
            $ventas = collect();

            for ($dia = $dentroMesDesde->copy()->startOfDay(); $dia->lte($dentroMesHasta); $dia->addDay()) {
                $ventas = $ventas->concat($ventasPorFecha->get($dia->format('Y-m-d'), collect()));
            }

            $ventasSemanales[] = [
                'etiqueta' => 'Sem. '.$numero.' ('.$dentroMesDesde->format('d').'-'.$dentroMesHasta->format('d').')',
                'semana' => $numero,
                'total' => (float) $ventas->sum('total'),
                'cantidad' => $ventas->count(),
            ];
            $inicio->addWeek();
        }

        return $ventasSemanales;
    }

    private function contarCotizacionesNoCompletadas(Carbon $desde, Carbon $hasta): int
    {
        return Cotizacion::query()
            ->whereBetween('created_at', [$desde, $hasta])
            ->whereNotIn('estado', ['aceptada', 'rechazada', 'cancelada', 'vencida'])
            ->count();
    }

    private function obtenerPorcentajeGanancia(Carbon $desde, Carbon $hasta): float
    {
        return $this->obtenerIndicadoresRentabilidad($desde, $hasta)['margenBruto'] ?? 0.0;
    }

    /**
     * @return array{
     *     ventasNetas: float,
     *     costoEstimado: float,
     *     utilidadBruta: ?float,
     *     margenBruto: ?float,
     *     detallesSinCosto: int
     * }
     */
    private function obtenerIndicadoresRentabilidad(Carbon $desde, Carbon $hasta): array
    {
        $ventas = $this->ventasCompletadasEntre($desde, $hasta)
            ->with('detalles')
            ->get(['id_venta', 'fecha', 'neto', 'total']);
        if ($ventas->isEmpty()) {
            return [
                'ventasNetas' => 0.0,
                'costoEstimado' => 0.0,
                'utilidadBruta' => null,
                'margenBruto' => null,
                'detallesSinCosto' => 0,
            ];
        }

        $productoIds = $ventas->flatMap(fn (Venta $venta): Collection => $venta->detalles->pluck('id_producto'))
            ->filter()
            ->unique()
            ->values();
        $adquisiciones = InventarioMovimiento::query()
            ->whereIn('id_producto', $productoIds)
            ->whereIn('tipo', ['ingreso', 'adquisicion'])
            ->whereNotNull('precio_entrada')
            ->whereNotNull('cantidad_adquirida')
            ->orderBy('fecha_evento')
            ->orderBy('created_at')
            ->get(['id_producto', 'fecha_evento', 'created_at', 'precio_entrada', 'cantidad_adquirida', 'costo_unitario_neto']);

        $porProducto = $adquisiciones->groupBy('id_producto');
        $ingresos = 0.0;
        $costos = 0.0;
        $detallesSinCosto = 0;
        $detallesConCosto = 0;

        foreach ($ventas as $venta) {
            $ingresos += (float) $venta->neto;
            if ($venta->detalles->isEmpty()) {
                $detallesSinCosto++;
            }
            foreach ($venta->detalles as $detalle) {
                $movimiento = $this->adquisicionAnterior(
                    $porProducto->get($detalle->id_producto, collect()),
                    $venta->fecha,
                );

                if ($movimiento !== null && (int) $movimiento->cantidad_adquirida > 0) {
                    $unitario = $movimiento->costo_unitario_neto !== null
                        ? (float) $movimiento->costo_unitario_neto
                        : ((float) $movimiento->precio_entrada / (int) $movimiento->cantidad_adquirida);
                    $costos += $unitario * (float) $detalle->cantidad;
                    $detallesConCosto++;
                } else {
                    $detallesSinCosto++;
                }
            }
        }

        $utilidadBruta = $detallesSinCosto === 0
            ? round($ingresos - $costos, 2)
            : null;
        $margenBruto = $detallesSinCosto === 0 && $detallesConCosto > 0 && $ingresos > 0
            ? round((($ingresos - $costos) / $ingresos) * 100, 2)
            : null;

        return [
            'ventasNetas' => round($ingresos, 2),
            'costoEstimado' => round($costos, 2),
            'utilidadBruta' => $utilidadBruta,
            'margenBruto' => $margenBruto,
            'detallesSinCosto' => $detallesSinCosto,
        ];
    }

    /**
     * @return array<int, array{etiqueta: string, total_vendido: int, stock: int}>
     */
    private function obtenerProductosMasVendidos(Carbon $desde, Carbon $hasta): array
    {
        return DetalleVenta::query()
            ->join('productos', 'productos.id_producto', '=', 'detalle_ventas.id_producto')
            ->whereHas('venta', function (Builder $query) use ($desde, $hasta): void {
                Venta::aplicarFiltroCompletadas($query)->whereBetween('fecha', [$desde, $hasta]);
            })
            ->selectRaw('productos.nombre, productos.stock, SUM(detalle_ventas.cantidad) as total_vendido')
            ->groupBy('detalle_ventas.id_producto', 'productos.nombre', 'productos.stock')
            ->orderByDesc('total_vendido')
            ->orderBy('productos.nombre')
            ->limit(10)
            ->get()
            ->map(fn (object $producto): array => [
                'etiqueta' => (string) $producto->nombre,
                'total_vendido' => (int) $producto->total_vendido,
                'stock' => (int) $producto->stock,
            ])
            ->all();
    }

    private function ventasCompletadasEntre(Carbon $desde, Carbon $hasta): Builder
    {
        return Venta::aplicarFiltroCompletadas(Venta::query())
            ->whereBetween('fecha', [$desde, $hasta]);
    }

    /**
     * @param  Collection<int, InventarioMovimiento>  $adquisiciones
     */
    private function adquisicionAnterior(Collection $adquisiciones, Carbon $fechaVenta): ?InventarioMovimiento
    {
        return $adquisiciones
            ->filter(function (InventarioMovimiento $movimiento) use ($fechaVenta): bool {
                $fecha = $movimiento->fecha_evento !== null
                    ? $movimiento->fecha_evento->copy()->endOfDay()
                    : $movimiento->created_at;

                return $fecha !== null && $fecha->lte($fechaVenta);
            })
            ->sortByDesc(fn (InventarioMovimiento $movimiento): int => $movimiento->fecha_evento?->timestamp
                ?? $movimiento->created_at?->timestamp
                ?? 0)
            ->first();
    }
}
