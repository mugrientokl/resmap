<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\SolicitudWeb;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $ahora = now();

        return view('dashboard.index', $this->obtenerDatosDashboard($ahora));
    }

    public function exportarPdf(Request $request)
    {
        $ahora = now();
        $datos = $this->obtenerDatosDashboard($ahora);
        $periodosDisponibles = array_column($datos['ventasMensuales'], 'periodo');
        $filtros = $request->validate([
            'agrupacion' => ['nullable', Rule::in(['month', 'week'])],
            'mes' => ['nullable', Rule::in($periodosDisponibles)],
        ]);
        $agrupacion = $filtros['agrupacion'] ?? 'month';
        $mesSeleccionado = $filtros['mes'] ?? $ahora->format('Y-m');
        $ventasPorPeriodo = $agrupacion === 'week'
            ? $datos['ventasSemanalesPorMes'][$mesSeleccionado]
            : $datos['ventasMensuales'];
        $etiquetaMesSeleccionado = collect($datos['ventasMensuales'])
            ->firstWhere('periodo', $mesSeleccionado)['etiqueta'];

        return Pdf::loadView('dashboard.pdf', [
            ...$datos,
            'fechaGeneracion' => $ahora,
            'agrupacion' => $agrupacion,
            'mesSeleccionado' => $etiquetaMesSeleccionado,
            'ventasPorPeriodo' => $ventasPorPeriodo,
        ])->download('dashboard-resmap-'.$ahora->format('Y-m-d').'.pdf');
    }

    private function obtenerDatosDashboard(Carbon $ahora): array
    {
        $inicioSemana = $ahora->copy()->startOfWeek();
        $inicioMes = $ahora->copy()->startOfMonth();
        $resumenSemana = $this->obtenerResumenVentas($inicioSemana, $ahora);
        $resumenMes = $this->obtenerResumenVentas($inicioMes, $ahora);
        $inicioUltimosSieteDias = $ahora->copy()->startOfDay()->subDays(6);
        $inicioUltimosSeisMeses = $inicioMes->copy()->subMonths(5);
        $cantidadStockCritico = $this->obtenerCantidadProductosStockCritico();
        $estadisticasGenerales = $this->obtenerEstadisticasGenerales($ahora);
        $ventasPorMes = $this->obtenerVentasMensuales($inicioUltimosSeisMeses, $ahora);

        return [
            'ventasDiarias' => $this->obtenerVentasDiarias($inicioUltimosSieteDias, $ahora),
            'ventasMensuales' => $ventasPorMes,
            'ventasSemanalesPorMes' => $this->obtenerVentasSemanalesPorMes($inicioUltimosSeisMeses, $ahora),
            'productosMasVendidosSemana' => $this->obtenerProductosMasVendidos($inicioSemana, $ahora),
            'productosMasVendidosMes' => $this->obtenerProductosMasVendidos($inicioMes, $ahora),
            'resumenSemana' => $resumenSemana,
            'resumenMes' => $resumenMes,
            'estadisticasGenerales' => $estadisticasGenerales,
            'totalCotizaciones' => Cotizacion::query()->count(),
            'ventasCotizacionesCompletadas' => Venta::completadas()->whereNotNull('cotizacion_version_id')->count(),
            'ventasPos' => $this->obtenerResumenVentasPos(),
            'cantidadStockCritico' => $cantidadStockCritico,
        ];
    }

    /**
     * @return array{cantidad: int, monto: float}
     */
    private function obtenerResumenVentas(Carbon $desde, Carbon $hasta): array
    {
        $resumen = $this->ventasCompletadasEntre($desde, $hasta)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto')
            ->firstOrFail();

        return [
            'cantidad' => (int) $resumen->cantidad,
            'monto' => (float) $resumen->monto,
        ];
    }

    /**
     * @return array<int, array{etiqueta: string, total: float}>
     */
    private function obtenerVentasDiarias(Carbon $desde, Carbon $hasta): array
    {
        $ventasPorFecha = $this->ventasCompletadasEntre($desde, $hasta)
            ->get(['fecha', 'total'])
            ->groupBy(fn (Venta $venta): string => $venta->fecha->toDateString());
        $ventasDiarias = [];

        for ($fecha = $desde->copy(); $fecha->lte($hasta); $fecha->addDay()) {
            $fechaClave = $fecha->toDateString();

            $ventasDiarias[] = [
                'etiqueta' => $fecha->locale('es')->isoFormat('D [de] MMMM YYYY'),
                'total' => (float) $ventasPorFecha->get($fechaClave, collect())->sum('total'),
            ];
        }

        return $ventasDiarias;
    }

    /**
     * @return array<int, array{etiqueta: string, periodo: string, total: float}>
     */
    private function obtenerVentasMensuales(Carbon $desde, Carbon $hasta): array
    {
        $ventasPorMes = $this->ventasCompletadasEntre($desde, $hasta)
            ->get(['fecha', 'total'])
            ->groupBy(fn (Venta $venta): string => $venta->fecha->format('Y-m'));
        $ventasMensuales = [];

        for ($mes = $desde->copy()->startOfMonth(); $mes->lte($hasta); $mes->addMonth()) {
            $mesClave = $mes->format('Y-m');

            $ventasMensuales[] = [
                'etiqueta' => $mes->locale('es')->isoFormat('MMMM YYYY'),
                'periodo' => $mesClave,
                'total' => (float) $ventasPorMes->get($mesClave, collect())->sum('total'),
            ];
        }

        return $ventasMensuales;
    }

    /**
     * @return array<string, array<int, array{etiqueta: string, total: float}>>
     */
    private function obtenerVentasSemanalesPorMes(Carbon $desde, Carbon $hasta): array
    {
        $ventasEnPeriodo = $this->ventasCompletadasEntre($desde, $hasta)->get(['fecha', 'total']);
        $ventasSemanalesPorMes = [];

        for ($mes = $desde->copy()->startOfMonth(); $mes->lte($hasta); $mes->addMonth()) {
            $inicioMes = $mes->copy()->startOfMonth();
            $finMes = $mes->copy()->endOfMonth()->min($hasta);
            $ventasPorSemana = $ventasEnPeriodo
                ->filter(fn (Venta $venta): bool => $venta->fecha->gte($inicioMes) && $venta->fecha->lte($finMes))
                ->groupBy(fn (Venta $venta): string => $venta->fecha->copy()->startOfWeek()->toDateString());
            $semanasDelMes = [];

            for ($semana = $inicioMes->copy()->startOfWeek(); $semana->lte($finMes); $semana->addWeek()) {
                $semanaClave = $semana->toDateString();
                $inicioPeriodo = $semana->copy()->max($inicioMes);
                $finPeriodo = $semana->copy()->endOfWeek()->min($finMes);

                $semanasDelMes[] = [
                    'etiqueta' => $inicioPeriodo->locale('es')->isoFormat('D MMM')
                        .' - '.$finPeriodo->locale('es')->isoFormat('D MMM'),
                    'total' => (float) $ventasPorSemana->get($semanaClave, collect())->sum('total'),
                ];
            }

            $ventasSemanalesPorMes[$mes->format('Y-m')] = $semanasDelMes;
        }

        return $ventasSemanalesPorMes;
    }

    /**
     * @return array<int, array{etiqueta: string, total_vendido: int}>
     */
    private function obtenerProductosMasVendidos(Carbon $desde, Carbon $hasta): array
    {
        return DetalleVenta::query()
            ->join('productos', 'productos.id_producto', '=', 'detalle_ventas.id_producto')
            ->whereHas('venta', function (Builder $query) use ($desde, $hasta): void {
                $query->completadas()->whereBetween('fecha', [$desde, $hasta]);
            })
            ->selectRaw('productos.nombre, SUM(detalle_ventas.cantidad) as total_vendido')
            ->groupBy('detalle_ventas.id_producto', 'productos.nombre')
            ->orderByDesc('total_vendido')
            ->orderBy('productos.nombre')
            ->limit(5)
            ->get()
            ->map(fn (object $producto): array => [
                'etiqueta' => (string) $producto->nombre,
                'total_vendido' => (int) $producto->total_vendido,
            ])
            ->all();
    }

    /**
     * @return array{cantidad: int, monto: float}
     */
    private function obtenerResumenVentasPos(): array
    {
        $ventasPos = Venta::completadas()
            ->whereNull('cotizacion_version_id')
            ->whereNotIn('id_venta', SolicitudWeb::query()->select('venta_id')->whereNotNull('venta_id'));

        return [
            'cantidad' => (clone $ventasPos)->count(),
            'monto' => (float) $ventasPos->sum('total'),
        ];
    }

    /**
     * @return array{
     *     monto_total_ventas: float,
     *     ventas_hoy: float,
     *     cantidad_ventas_hoy: int
     * }
     */
    private function obtenerEstadisticasGenerales(Carbon $ahora): array
    {
        $ventasHoy = $this->obtenerResumenVentas($ahora->copy()->startOfDay(), $ahora);

        return [
            'monto_total_ventas' => (float) Venta::completadas()->sum('total'),
            'ventas_hoy' => $ventasHoy['monto'],
            'cantidad_ventas_hoy' => $ventasHoy['cantidad'],
        ];
    }

    private function ventasCompletadasEntre(Carbon $desde, Carbon $hasta): Builder
    {
        return Venta::completadas()->whereBetween('fecha', [$desde, $hasta]);
    }

    private function obtenerCantidadProductosStockCritico(): int
    {
        return Producto::query()
            ->whereColumn('stock', '<=', 'stock_critico')
            ->count();
    }
}
