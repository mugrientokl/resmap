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

    public function test_dashboard_displays_sales_count_as_a_quantity(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        foreach (range(1, 2) as $saleNumber) {
            Venta::create([
                'fecha' => now(),
                'tipo_documento' => 'Boleta Electrónica',
                'neto' => 1000,
                'iva' => 190,
                'total' => 1190,
                'medio_pago' => 'Efectivo',
                'user_id' => $admin->id,
            ]);
        }

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Cantidad de ventas esta semana')
            ->assertSee('Ventas completadas')
            ->assertSee('Monto vendido hoy')
            ->assertSee('$2.380,00')
            ->assertSee('Monto de ventas completadas hoy')
            ->assertSee('Cantidad de ventas hoy')
            ->assertSee('Monto vendido esta semana')
            ->assertSee('Monto vendido este mes')
            ->assertSee('Monto total vendido')
            ->assertSee('Total de cotizaciones')
            ->assertSee('Ventas completadas en POS')
            ->assertSee('$2.380,00')
            ->assertDontSee('<h3>Productos</h3>', false)
            ->assertDontSee('<h3>Clientes</h3>', false)
            ->assertDontSee('Ticket promedio del mes')
            ->assertDontSee('Unidades vendidas este mes');
    }

    public function test_dashboard_summarizes_weekly_and_monthly_sales_and_top_products(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = Categoria::create([
            'nombre_categoria' => 'Accesorios',
            'descripcion' => 'Accesorios para pruebas',
        ]);
        $producto = Producto::create([
            'codigo_barra' => 'DASH-001',
            'nombre' => 'Producto destacado',
            'precio' => 1000,
            'stock' => 10,
            'stock_critico' => 2,
            'id_categoria' => $categoria->id_categoria,
        ]);
        $productoMesAnterior = Producto::create([
            'codigo_barra' => 'DASH-002',
            'nombre' => 'Producto solo del mes anterior',
            'precio' => 1000,
            'stock' => 30,
            'stock_critico' => 2,
            'id_categoria' => $categoria->id_categoria,
        ]);
        $ventaEstaSemana = Venta::create([
            'fecha' => now()->startOfWeek(),
            'tipo_documento' => 'Boleta Electrónica',
            'neto' => 2000,
            'iva' => 380,
            'total' => 2380,
            'medio_pago' => 'Efectivo',
            'user_id' => $admin->id,
        ]);
        $ventaMesAnterior = Venta::create([
            'fecha' => now()->subMonth()->startOfMonth(),
            'tipo_documento' => 'Boleta Electrónica',
            'neto' => 1000,
            'iva' => 190,
            'total' => 1190,
            'medio_pago' => 'Efectivo',
            'user_id' => $admin->id,
        ]);

        DetalleVenta::create([
            'id_venta' => $ventaEstaSemana->id_venta,
            'id_producto' => $producto->id_producto,
            'cantidad' => 3,
            'precio_unitario' => 1000,
            'subtotal' => 3000,
        ]);
        DetalleVenta::create([
            'id_venta' => $ventaMesAnterior->id_venta,
            'id_producto' => $producto->id_producto,
            'cantidad' => 8,
            'precio_unitario' => 1000,
            'subtotal' => 8000,
        ]);
        DetalleVenta::create([
            'id_venta' => $ventaMesAnterior->id_venta,
            'id_producto' => $productoMesAnterior->id_producto,
            'cantidad' => 20,
            'precio_unitario' => 1000,
            'subtotal' => 20000,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Cantidad de ventas esta semana')
            ->assertSee('$2.380,00')
            ->assertSee('Monto vendido este mes')
            ->assertSee('Monto vendido esta semana')
            ->assertSee('Cantidad de ventas esta semana')
            ->assertSee('Producto destacado')
            ->assertDontSee('Producto solo del mes anterior')
            ->assertSee('Ventas por día')
            ->assertSee('Monto total vendido en cada día. Las fechas incluyen mes y año.')
            ->assertSee('Ventas por período')
            ->assertSee('Monto total vendido por mes durante los últimos 6 meses.')
            ->assertSee('Productos más vendidos esta semana')
            ->assertSee('Los 5 productos con más unidades vendidas esta semana.')
            ->assertSee('Productos más vendidos este mes')
            ->assertSee('Los 5 productos con más unidades vendidas este mes.')
            ->assertSee(now()->locale('es')->isoFormat('MMMM YYYY'))
            ->assertSee('value="week"', false)
            ->assertViewHas('ventasMensuales', function (array $ventasMensuales): bool {
                return count($ventasMensuales) === 6
                    && $ventasMensuales[5]['etiqueta'] === now()->locale('es')->isoFormat('MMMM YYYY')
                    && $ventasMensuales[5]['periodo'] === now()->format('Y-m')
                    && array_sum(array_column($ventasMensuales, 'total')) === 3570.0;
            })
            ->assertViewHas('ventasSemanalesPorMes', function (array $ventasSemanalesPorMes): bool {
                $semanasMesActual = $ventasSemanalesPorMes[now()->format('Y-m')];

                return count($semanasMesActual) >= 1
                    && count($semanasMesActual) <= 6
                    && array_sum(array_column($semanasMesActual, 'total')) === 2380.0;
            })
            ->assertViewHas('ventasDiarias', function (array $ventasDiarias): bool {
                return count($ventasDiarias) === 7
                    && $ventasDiarias[6]['etiqueta'] === now()->locale('es')->isoFormat('D [de] MMMM YYYY');
            });
    }

    public function test_dashboard_separates_completed_quotation_sales_and_pos_sales_from_all_quotations(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $cliente = Cliente::create([
            'rut' => '11.111.111-1',
            'nombre' => 'Cliente de prueba',
        ]);
        $solicitud = SolicitudWeb::create([
            'fecha' => now(),
            'id_cliente' => $cliente->id_cliente,
            'detalles_productos' => [],
        ]);
        $cotizacion = Cotizacion::create([
            'solicitud_id' => $solicitud->id_solicitud,
            'creada_por' => $admin->id,
            'estado' => 'rechazada',
        ]);
        $solicitudPendiente = SolicitudWeb::create([
            'fecha' => now(),
            'id_cliente' => $cliente->id_cliente,
            'detalles_productos' => [],
        ]);
        Cotizacion::create([
            'solicitud_id' => $solicitudPendiente->id_solicitud,
            'creada_por' => $admin->id,
            'estado' => 'pendiente_revision',
        ]);
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
        Venta::create([
            'fecha' => now(),
            'tipo_documento' => 'Factura Electrónica',
            'neto' => 1000,
            'iva' => 190,
            'total' => 1190,
            'medio_pago' => 'Transferencia',
            'estado_venta' => 'confirmada',
            'estado_facturacion' => 'simulada_exitosa',
            'estado_entrega' => 'entregada',
            'user_id' => $admin->id,
            'id_cliente' => $cliente->id_cliente,
            'cotizacion_version_id' => $version->id_version,
        ]);
        $ventaPos = Venta::create([
            'fecha' => now(),
            'tipo_documento' => 'Boleta Electrónica',
            'neto' => 2000,
            'iva' => 380,
            'total' => 2380,
            'medio_pago' => 'Efectivo',
            'user_id' => $admin->id,
        ]);
        $solicitudWeb = SolicitudWeb::create([
            'fecha' => now(),
            'id_cliente' => $cliente->id_cliente,
            'detalles_productos' => [],
        ]);
        $ventaWeb = Venta::create([
            'fecha' => now(),
            'tipo_documento' => 'Boleta Electrónica',
            'neto' => 4000,
            'iva' => 760,
            'total' => 4760,
            'medio_pago' => 'Pendiente de emisión',
            'user_id' => $admin->id,
            'id_cliente' => $cliente->id_cliente,
        ]);
        $solicitudWeb->update(['venta_id' => $ventaWeb->id_venta]);
        Venta::create([
            'fecha' => now(),
            'tipo_documento' => 'Boleta Electrónica',
            'neto' => 3000,
            'iva' => 570,
            'total' => 3570,
            'medio_pago' => 'Efectivo',
            'estado_venta' => 'pendiente_confirmacion',
            'estado_facturacion' => 'pendiente',
            'estado_entrega' => 'pendiente',
            'user_id' => $admin->id,
            'id_cliente' => $cliente->id_cliente,
            'cotizacion_version_id' => $version->id_version,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Ventas completadas desde cotizaciones')
            ->assertSee('Total de cotizaciones')
            ->assertViewHas('ventasCotizacionesCompletadas', 1)
            ->assertViewHas('totalCotizaciones', 2)
            ->assertViewHas('ventasPos', [
                'cantidad' => 1,
                'monto' => 2380.0,
            ]);
    }

    public function test_dashboard_counts_all_products_with_critical_stock_including_zero_stock(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = Categoria::create([
            'nombre_categoria' => 'Stock de prueba',
            'descripcion' => 'Productos para validar el conteo del dashboard',
        ]);

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
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('7 productos con stock bajo')
            ->assertSee('stock=critico')
            ->assertViewHas('cantidadStockCritico', 7);
    }
}
