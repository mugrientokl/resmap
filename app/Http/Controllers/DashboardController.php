<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\SolicitudWeb;
use App\Models\Venta;
use App\Support\PeriodoVentas;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
            'productosMasVendidos' => $this->obtenerProductosMasVendidos($seleccion['desde'], $seleccion['hasta']),
            'resumen' => $this->obtenerResumen($seleccion['desde'], $seleccion['hasta']),
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
            ->limit(10)
            ->get()
            ->map(fn (object $producto): array => [
                'etiqueta' => (string) $producto->nombre,
                'total_vendido' => (int) $producto->total_vendido,
            ])
            ->all();
    }

    private function ventasCompletadasEntre(Carbon $desde, Carbon $hasta): Builder
    {
        return Venta::completadas()->whereBetween('fecha', [$desde, $hasta]);
    }
}
