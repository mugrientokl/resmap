<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionVersion;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResumenInternoVentaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_sale_offers_internal_summary_download_as_pdf(): void
    {
        $vendedor = User::factory()->create(['rol' => 'Vendedor']);
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Pruebas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productoId = DB::table('productos')->insertGetId([
            'codigo_barra' => 'RES-001',
            'nombre' => 'Producto resumen',
            'precio' => 1190,
            'stock' => 5,
            'stock_critico' => 1,
            'id_categoria' => $categoriaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($vendedor)->postJson('/ventas', [
            'tipo_documento' => 'Boleta Electrónica',
            'medio_pago' => 'Efectivo',
            'rut' => '12.345.678-5',
            'nombre_cliente' => 'Cliente POS',
            'detalles' => [['id_producto' => $productoId, 'cantidad' => 1]],
        ])->assertCreated();

        $venta = Venta::firstOrFail();

        $this->get('/pos')
            ->assertOk()
            ->assertSee('Registrar venta')
            ->assertDontSee('Registrar venta e imprimir')
            ->assertSee(route('ventas.resumen-interno', $venta->id_venta), false);

        $respuesta = $this->get(route('ventas.resumen-interno', $venta->id_venta));
        $respuesta->assertOk();
        $this->assertStringContainsString('application/pdf', $respuesta->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_quote_summary_is_only_available_when_the_sale_is_completed(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        [$solicitud, $venta] = $this->crearCotizacionConVenta($admin, [
            'estado_venta' => 'confirmada',
            'estado_facturacion' => 'pendiente',
            'estado_entrega' => 'pendiente',
        ]);

        $this->actingAs($admin)
            ->get(route('solicitudes.show', $solicitud->id_solicitud))
            ->assertOk()
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee(route('solicitudes.pdf', $solicitud->id_solicitud), false);

        $this->get(route('solicitudes.pdf', $solicitud->id_solicitud))->assertForbidden();
        $this->get(route('ventas.resumen-interno', $venta->id_venta))->assertNotFound();

        $venta->update(['estado_facturacion' => 'simulada_exitosa', 'estado_entrega' => 'entregada']);

        $this->get(route('solicitudes.show', $solicitud->id_solicitud))
            ->assertSee(route('solicitudes.pdf', $solicitud->id_solicitud), false);

        $respuesta = $this->get(route('solicitudes.pdf', $solicitud->id_solicitud));
        $respuesta->assertOk();
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->get(route('ventas.resumen-interno', $venta->id_venta))->assertOk();
    }

    /**
     * @param  array<string, string>  $estados
     * @return array{0: SolicitudWeb, 1: Venta}
     */
    private function crearCotizacionConVenta(User $usuario, array $estados): array
    {
        $cliente = Cliente::create(['rut' => '11.111.111-1', 'nombre' => 'Cliente B2B']);
        $solicitud = SolicitudWeb::create([
            'fecha' => now(),
            'estado' => 'Pendiente',
            'tipo_solicitud' => 'productos',
            'id_cliente' => $cliente->id_cliente,
            'detalles_productos' => [],
        ]);
        $cotizacion = Cotizacion::create([
            'solicitud_id' => $solicitud->id_solicitud,
            'creada_por' => $usuario->id,
            'estado' => 'aceptada',
            'ultima_version' => 1,
        ]);
        $version = CotizacionVersion::create([
            'cotizacion_id' => $cotizacion->id_cotizacion,
            'numero_version' => 1,
            'estado' => 'aceptada',
            'neto' => 1000,
            'iva' => 190,
            'costo_despacho' => 0,
            'total' => 1190,
            'metodo_entrega' => 'retiro',
            'vigente_hasta' => now()->addDays(7),
        ]);
        $venta = Venta::create([
            'fecha' => now(),
            'tipo_documento' => 'Factura Electrónica',
            'neto' => 1000,
            'iva' => 190,
            'total' => 1190,
            'medio_pago' => 'Transferencia',
            'estado_pago' => 'pagado',
            'cotizacion_version_id' => $version->id_version,
            'user_id' => $usuario->id,
            'id_cliente' => $cliente->id_cliente,
        ] + $estados);
        $solicitud->update(['venta_id' => $venta->id_venta]);

        return [$solicitud, $venta];
    }
}
