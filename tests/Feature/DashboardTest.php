<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionVersion;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function venta(User $usuario, float $total, $fecha = null, array $extra = []): Venta
    {
        return Venta::create([
            'fecha' => $fecha ?? now(),
            'tipo_documento' => 'Boleta Electrónica',
            'neto' => round($total / 1.19, 2),
            'iva' => $total - round($total / 1.19, 2),
            'total' => $total,
            'medio_pago' => 'Efectivo',
            'user_id' => $usuario->id,
            ...$extra,
        ]);
    }

    private function producto(string $codigo, string $nombre): Producto
    {
        $categoria = Categoria::firstOrCreate(['nombre_categoria' => 'Accesorios'], ['descripcion' => 'Pruebas']);

        return Producto::create([
            'codigo_barra' => $codigo,
            'nombre' => $nombre,
            'precio' => 1000,
            'stock' => 100,
            'stock_critico' => 2,
            'id_categoria' => $categoria->id_categoria,
        ]);
    }

    private function detalle(Venta $venta, Producto $producto, int $cantidad): void
    {
        DetalleVenta::create([
            'id_venta' => $venta->id_venta,
            'id_producto' => $producto->id_producto,
            'cantidad' => $cantidad,
            'precio_unitario' => 1000,
            'subtotal' => 1000 * $cantidad,
        ]);
    }

    public function test_dashboard_shows_only_the_essential_cards_and_no_quick_actions(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $this->venta($admin, 2380);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Monto vendido')
            ->assertSee('Cantidad de ventas')
            ->assertSee('Ventas en POS')
            ->assertSee('Ventas por cotización')
            ->assertSee('$2.380')
            ->assertSee('Período de los indicadores')
            ->assertSee(ucfirst(now()->locale('es')->isoFormat('MMMM YYYY')))
            ->assertDontSee('Accesos rápidos')
            ->assertDontSee('Monto vendido hoy')
            ->assertDontSee('Total de cotizaciones')
            ->assertDontSee('Productos más vendidos esta semana')
            ->assertSee('Top 10 productos más vendidos del mes')
            ->assertSee('ventasMensualesChart', false)
            ->assertSee('ventasDiariasChart', false);
    }

    public function test_selecting_a_month_filters_cards_charts_and_top_products(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $actual = $this->producto('DASH-001', 'Producto del mes actual');
        $anterior = $this->producto('DASH-002', 'Producto del mes anterior');
        $fechaAnterior = now()->subMonthNoOverflow()->startOfMonth()->addDays(2)->setTime(11, 0);

        $ventaActual = $this->venta($admin, 2380, now()->startOfMonth()->setTime(10, 0));
        $ventaAnterior = $this->venta($admin, 5950, $fechaAnterior);
        $this->detalle($ventaActual, $actual, 3);
        $this->detalle($ventaAnterior, $anterior, 5);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('resumen', fn (array $r): bool => $r['total'] === ['cantidad' => 1, 'monto' => 2380.0])
            ->assertSee('Producto del mes actual')
            ->assertDontSee('Producto del mes anterior');

        $this->actingAs($admin)
            ->get(route('dashboard', ['mes' => $fechaAnterior->format('Y-m')]))
            ->assertOk()
            ->assertViewHas('resumen', fn (array $r): bool => $r['total'] === ['cantidad' => 1, 'monto' => 5950.0])
            ->assertViewHas('ventasDiarias', fn (array $d): bool => count($d) === $fechaAnterior->daysInMonth
                && $d[2]['total'] === 5950.0)
            ->assertViewHas('productosMasVendidos', fn (array $p): bool => $p === [['etiqueta' => 'Producto del mes anterior', 'total_vendido' => 5]])
            ->assertSee('$5.950')
            ->assertSee(ucfirst($fechaAnterior->locale('es')->isoFormat('MMMM YYYY')));
    }

    public function test_selecting_a_day_filters_cards_and_top_products_to_that_day(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $producto = $this->producto('DASH-003', 'Producto de un día');
        $otro = $this->producto('DASH-004', 'Producto de otro día');
        $fecha = now()->subMonthNoOverflow()->startOfMonth()->addDays(9)->setTime(12, 0);
        $ventaDia = $this->venta($admin, 1190, $fecha);
        $ventaOtroDia = $this->venta($admin, 3570, $fecha->copy()->addDay());
        $this->detalle($ventaDia, $producto, 2);
        $this->detalle($ventaOtroDia, $otro, 4);

        $this->actingAs($admin)
            ->get(route('dashboard', ['mes' => $fecha->format('Y-m'), 'dia' => $fecha->day]))
            ->assertOk()
            ->assertViewHas('resumen', fn (array $r): bool => $r['total'] === ['cantidad' => 1, 'monto' => 1190.0])
            ->assertViewHas('productosMasVendidos', fn (array $p): bool => count($p) === 1 && $p[0]['etiqueta'] === 'Producto de un día')
            ->assertSee('Top 10 productos del día')
            ->assertSee('Ver todo el mes');
    }

    public function test_invalid_filters_fall_back_to_the_current_month(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->get(route('dashboard', ['mes' => '1999-01', 'dia' => '99']))
            ->assertOk()
            ->assertViewHas('seleccion', fn (array $s): bool => $s['mes'] === now()->format('Y-m') && $s['dia'] === null);
    }

    public function test_top_products_are_limited_to_ten(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $venta = $this->venta($admin, 5000, now()->startOfMonth()->setTime(9, 0));

        foreach (range(1, 12) as $numero) {
            $this->detalle($venta, $this->producto('TOP-'.$numero, 'Producto top '.$numero), 20 - $numero);
        }

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertViewHas('productosMasVendidos', fn (array $p): bool => count($p) === 10 && $p[0]['etiqueta'] === 'Producto top 1');
    }

    public function test_dashboard_separates_completed_quotation_sales_and_pos_sales(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $cliente = Cliente::create(['rut' => '11.111.111-1', 'nombre' => 'Cliente de prueba']);
        $solicitud = SolicitudWeb::create(['fecha' => now(), 'id_cliente' => $cliente->id_cliente, 'detalles_productos' => []]);
        $cotizacion = Cotizacion::create(['solicitud_id' => $solicitud->id_solicitud, 'creada_por' => $admin->id, 'estado' => 'aceptada']);
        $version = CotizacionVersion::create([
            'cotizacion_id' => $cotizacion->id_cotizacion,
            'numero_version' => 1,
            'estado' => 'aceptada',
            'neto' => 1000,
            'iva' => 190,
            'total' => 1190,
            'metodo_entrega' => 'retiro',
            'vigente_hasta' => now()->addWeek(),
        ]);
        $completada = [
            'estado_venta' => 'confirmada',
            'estado_facturacion' => 'simulada_exitosa',
            'estado_entrega' => 'entregada',
            'cotizacion_version_id' => $version->id_version,
            'id_cliente' => $cliente->id_cliente,
        ];
        $this->venta($admin, 1190, null, $completada);
        $this->venta($admin, 3570, null, [...$completada, 'estado_venta' => 'pendiente_confirmacion', 'estado_facturacion' => 'pendiente', 'estado_entrega' => 'pendiente']);
        $this->venta($admin, 2380);
        $ventaWeb = $this->venta($admin, 4760, null, ['id_cliente' => $cliente->id_cliente]);
        SolicitudWeb::create(['fecha' => now(), 'id_cliente' => $cliente->id_cliente, 'detalles_productos' => [], 'venta_id' => $ventaWeb->id_venta]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('resumen', fn (array $r): bool => $r['cotizaciones'] === ['cantidad' => 1, 'monto' => 1190.0]
                && $r['pos'] === ['cantidad' => 1, 'monto' => 2380.0]
                && $r['total']['cantidad'] === 3);
    }

    public function test_dashboard_counts_all_products_with_critical_stock_including_zero_stock(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = Categoria::create(['nombre_categoria' => 'Stock de prueba', 'descripcion' => 'Conteo']);

        foreach (range(1, 7) as $numeroProducto) {
            Producto::create([
                'codigo_barra' => 'STOCK-BAJO-'.$numeroProducto,
                'nombre' => 'Producto con stock bajo '.$numeroProducto,
                'precio' => 1000,
                'stock' => $numeroProducto === 1 ? 0 : 1,
                'stock_critico' => 3,
                'id_categoria' => $categoria->id_categoria,
            ]);
        }

        Producto::create([
            'codigo_barra' => 'STOCK-OK-001',
            'nombre' => 'Producto con stock normal',
            'precio' => 1000,
            'stock' => 4,
            'stock_critico' => 3,
            'id_categoria' => $categoria->id_categoria,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('7 productos con stock bajo')
            ->assertSee('stock=critico')
            ->assertViewHas('cantidadStockCritico', 7);
    }
}
