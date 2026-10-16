<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Obtener datos para los gráficos
        $ventasUltimos7Dias = $this->obtenerVentasUltimos7Dias();
        $productosMasVendidos = $this->obtenerProductosMasVendidos();
        $estadisticasGenerales = $this->obtenerEstadisticasGenerales();
        $stockCritico = $this->obtenerProductosStockCritico();

        return view('dashboard.index', [
            'ventasUltimos7Dias' => $ventasUltimos7Dias,
            'productosMasVendidos' => $productosMasVendidos,
            'estadisticasGenerales' => $estadisticasGenerales,
            'stockCritico' => $stockCritico,
        ]);
    }

    private function obtenerVentasUltimos7Dias()
    {
        $ventas = Venta::selectRaw('DATE(created_at) as fecha, SUM(total) as total')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('fecha')
            ->get();

        return $ventas;
    }

    private function obtenerProductosMasVendidos()
    {
        return DetalleVenta::selectRaw('productos.nombre, SUM(detalle_ventas.cantidad) as total_vendido, SUM(detalle_ventas.subtotal) as total_generado')
            ->join('productos', 'productos.id_producto', '=', 'detalle_ventas.id_producto')
            ->groupBy('detalle_ventas.id_producto', 'productos.nombre')
            ->orderByDesc('total_vendido')
            ->limit(5)
            ->get();
    }

    private function obtenerEstadisticasGenerales()
    {
        return [
            'total_ventas' => Venta::count(),
            'monto_total_ventas' => Venta::sum('total') ?? 0,
            'total_productos' => Producto::count(),
            'total_categorias' => Categoria::count(),
            'total_clientes' => Cliente::count(),
            'ventas_hoy' => Venta::whereDate('created_at', Carbon::today())->sum('total') ?? 0,
            'cantidad_vendidas_hoy' => DetalleVenta::whereDate('created_at', Carbon::today())->sum('cantidad') ?? 0,
        ];
    }

    private function obtenerProductosStockCritico()
    {
        return Producto::where('stock', '<=', DB::raw('stock_critico'))
            ->orderBy('stock')
            ->limit(5)
            ->get();
    }
}
