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

        $datosVenta = [
            'tipo_documento' => 'Boleta Electrónica',
            'medio_pago' => 'Efectivo',
            'forma_pago' => 'Contado',
            'rut' => '12.345.678-5',
            'nombre_cliente' => 'Cliente POS',
            'contacto' => 'Contacto POS',
            'giro_cliente' => 'Comercio de repuestos',
            'correo_cliente' => 'cliente@example.test',
            'direccion_cliente' => 'Av. Norte 123',
            'region_cliente' => 'Biobío',
            'comuna_cliente' => 'Los Ángeles',
            'ciudad_cliente' => 'Concepción',
            'acteco_emisor' => '453000',
            'tipo_venta' => 'del_giro',
            'tipo_compra' => 'del_giro',
            'detalles' => [['id_producto' => $productoId, 'cantidad' => 1]],
        ];
        $datosIncompletos = $datosVenta;
        unset($datosIncompletos['acteco_emisor']);

        $this->actingAs($vendedor)
            ->postJson('/ventas', $datosIncompletos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('acteco_emisor');
        $this->assertDatabaseCount('ventas', 0);

        $this->actingAs($vendedor)->postJson('/ventas', $datosVenta)->assertCreated();

        $venta = Venta::firstOrFail();
        $this->assertSame('Comercio de repuestos', $venta->datos_resumen['giro']);
        $this->assertSame('Los Ángeles', $venta->datos_resumen['comuna']);
        $this->assertSame('Contacto POS', $venta->datos_resumen['contacto']);
        $this->assertSame('Concepción', $venta->datos_resumen['ciudad']);
        $this->assertSame('del_giro', $venta->datos_resumen['tipo_venta']);
        $this->assertSame('del_giro', $venta->datos_resumen['tipo_compra']);
        $this->assertSame('Contado', $venta->datos_resumen['forma_pago']);
        $this->assertSame('Venta de partes, piezas y accesorios para vehículos automotores', $venta->datos_resumen['giro_emisor']);
        $this->assertDatabaseHas('clientes', [
            'rut' => '12345678-5',
            'comuna' => 'Los Ángeles',
            'ciudad' => 'Concepción',
        ]);

        $this->get('/pos')
            ->assertOk()
            ->assertSee('Registrar venta')
            ->assertSee('Giro del cliente (opcional para boleta)')
            ->assertSee('Datos de venta y facturación')
            ->assertSee('Tipo de documento *')
            ->assertSee('Datos del emisor')
            ->assertSee('Datos del cliente o receptor')
            ->assertDontSee('Datos para el resumen interno')
            ->assertSee('id="tipo_venta"', false)
            ->assertDontSee('id="tipo_compra"', false)
            ->assertDontSee('Registrar venta e imprimir')
            ->assertSee(route('ventas.resumen-interno', $venta->id_venta), false);

        $this->view('reportes.venta-pdf', [
            'venta' => $venta->load(['user', 'detalles.producto']),
            'cliente' => Cliente::desdeSnapshot($venta->snapshotDatosCliente()),
            'despacho' => 0,
            'logoDataUri' => '',
        ])
            ->assertSeeText('Giro: Comercio de repuestos')
            ->assertSeeText('Comuna: Los Ángeles')
            ->assertSeeText('Ciudad: Concepción')
            ->assertSeeText('Atención / contacto: Contacto POS')
            ->assertSeeText('Tipo de compra: Del giro')
            ->assertSeeInOrder(['Código', 'Producto'])
            ->assertDontSeeText('Modalidad de entrega');

        $respuesta = $this->get(route('ventas.resumen-interno', $venta->id_venta));
        $respuesta->assertOk();
        $this->assertStringContainsString('application/pdf', $respuesta->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_customer_giro_is_required_for_invoice_but_optional_for_boleta(): void
    {
        $vendedor = User::factory()->create(['rol' => 'Vendedor']);
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Pruebas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productoId = DB::table('productos')->insertGetId([
            'codigo_barra' => 'RES-002',
            'nombre' => 'Producto resumen opcional',
            'precio' => 1190,
            'stock' => 5,
            'stock_critico' => 1,
            'id_categoria' => $categoriaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $datos = [
            'tipo_documento' => 'Boleta Electrónica',
            'medio_pago' => 'Efectivo',
            'rut' => '12.345.678-5',
            'nombre_cliente' => 'Cliente POS',
            'contacto' => 'Contacto POS',
            'correo_cliente' => 'cliente@example.test',
            'direccion_cliente' => 'Av. Norte 123',
            'region_cliente' => 'Biobío',
            'comuna_cliente' => 'Los Ángeles',
            'ciudad_cliente' => 'Los Ángeles',
            'acteco_emisor' => '475201',
            'tipo_venta' => 'del_giro',
            'tipo_compra' => 'del_giro',
            'detalles' => [['id_producto' => $productoId, 'cantidad' => 1]],
        ];

        $this->actingAs($vendedor)->postJson('/ventas', $datos)->assertCreated();
        $this->assertNull(Venta::firstOrFail()->datos_resumen['giro']);

        $datos['tipo_documento'] = 'Factura Electrónica';
        $datos['rut'] = '11.111.111-1';
        $this->actingAs($vendedor)
            ->postJson('/ventas', $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('giro_cliente');
        $this->assertDatabaseCount('ventas', 1);
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
            ->assertDontSee(route('solicitudes.pdf', $solicitud->id_solicitud), false);

        $this->get(route('solicitudes.pdf', $solicitud->id_solicitud))->assertForbidden();
        $this->get(route('ventas.resumen-interno', $venta->id_venta))->assertNotFound();

        $venta->update(['stock_descontado_at' => now()]);

        $this->postJson(route('ventas.generar-resumen', $venta->id_venta), [
            'correo' => 'cliente@empresa',
        ])->assertUnprocessable()->assertJsonValidationErrors('correo');

        $this->get(route('solicitudes.show', $solicitud->id_solicitud))
            ->assertSee(route('ventas.generar-resumen', $venta->id_venta), false)
            ->assertSee('Generar resumen interno')
            ->assertDontSee(route('solicitudes.pdf', $solicitud->id_solicitud), false);

        $respuesta = $this->get(route('solicitudes.pdf', $solicitud->id_solicitud));
        $respuesta->assertNotFound();
        $this->get(route('ventas.resumen-interno', $venta->id_venta))->assertNotFound();
        $this->get(route('reportes.ventas.resumen', $venta->id_venta))->assertNotFound();
    }

    public function test_legacy_quote_summary_is_frozen_on_first_report_download(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        [$solicitud, $venta] = $this->crearCotizacionConVenta($admin, [
            'estado_venta' => 'confirmada',
            'estado_facturacion' => 'pendiente',
            'estado_entrega' => 'pendiente',
        ]);
        $venta->update([
            'stock_descontado_at' => now(),
            'datos_resumen' => ['nombre' => 'Cliente histórico', 'acteco_emisor' => 'PRUEBA-001'],
        ]);

        $this->actingAs($admin)->get(route('solicitudes.show', $solicitud->id_solicitud))
            ->assertDontSee('Generar resumen interno');
        $primeraDescarga = $this->get(route('reportes.ventas.resumen', $venta->id_venta))->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $primeraDescarga);
        $this->assertNotNull($venta->fresh()->resumen_interno_pdf);

        $venta->update(['total' => 99999]);
        $this->assertSame($primeraDescarga, $this->get(route('reportes.ventas.resumen', $venta->id_venta))->assertOk()->getContent());
    }

    public function test_seller_can_download_generated_quote_summary_from_its_detail_without_reports_access(): void
    {
        $vendedor = User::factory()->create(['rol' => 'Vendedor']);
        [$solicitud, $venta] = $this->crearCotizacionConVenta($vendedor, ['estado_venta' => 'confirmada']);
        $venta->update(['stock_descontado_at' => now(), 'estado_pago' => 'pagado']);
        $urlDescarga = route('ventas.resumen-interno', $venta->id_venta);

        $this->actingAs($vendedor)->get(route('solicitudes.show', $solicitud->id_solicitud))
            ->assertDontSee($urlDescarga, false);
        $this->get($urlDescarga)->assertNotFound();
        $this->get(route('reportes.ventas.resumen', $venta->id_venta))->assertForbidden();

        $this->post(route('ventas.generar-resumen', $venta->id_venta), [
            'tipo_documento' => 'Factura Electrónica',
            'medio_pago' => 'Transferencia',
            'forma_pago' => 'Contado',
            'acteco_emisor' => '453000',
            'tipo_venta' => 'del_giro',
            'tipo_compra' => 'del_giro',
            'nombre' => 'Empresa de prueba',
            'rut' => '12.345.678-5',
            'giro' => 'Comercio de repuestos',
            'direccion' => 'Calle 123',
            'region' => 'Biobío',
            'comuna' => 'Los Ángeles',
            'ciudad' => 'Los Ángeles',
            'contacto' => 'Contacto de prueba',
            'correo' => 'prueba@example.test',
            'numero_oc' => 'OC-PRUEBA',
        ])->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Resumen interno generado. Puedes descargarlo desde el detalle de la cotización.');
        $pdfGuardado = base64_decode($venta->fresh()->resumen_interno_pdf, true);
        $this->assertStringStartsWith('%PDF', $pdfGuardado);
        $this->get(route('solicitudes.show', $solicitud->id_solicitud))
            ->assertSeeInOrder(['Descargar cotización PDF', 'Descargar resumen interno PDF'])
            ->assertSee($urlDescarga, false);
        $this->get($urlDescarga)->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertContent($pdfGuardado);
        $venta->update(['total' => 99999]);
        $this->get($urlDescarga)->assertContent($pdfGuardado);

        $sinPermisos = User::factory()->create(['rol' => 'Vendedor']);
        $sinPermisos->forceFill(['permisos' => []])->save();
        $this->actingAs($sinPermisos)->get($urlDescarga)->assertForbidden();
        $sinPermisos->forceFill(['permisos' => ['ventas.ver']])->save();
        $this->actingAs($sinPermisos->fresh())->get($urlDescarga)->assertForbidden();
        $sinPermisos->forceFill(['permisos' => ['cotizaciones.ver']])->save();
        $this->actingAs($sinPermisos->fresh())->get($urlDescarga)->assertOk()->assertContent($pdfGuardado);
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
