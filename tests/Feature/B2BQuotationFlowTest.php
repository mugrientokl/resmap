<?php

namespace Tests\Feature;

use App\Mail\CotizacionCliente;
use App\Models\Cliente;
use App\Models\CotizacionVersion;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\NuevaCotizacionPendiente;
use App\Notifications\SolicitudWebRecibida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class B2BQuotationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_quote_marks_only_its_pending_notification_as_read(): void
    {
        Notification::fake();
        $this->createProductRequest($this->createProductWithStock(5), 1);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $cotizacionId = (int) DB::table('cotizaciones')->where('solicitud_id', $solicitudId)->value('id_cotizacion');
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $notificationIds = [(string) Str::uuid(), (string) Str::uuid()];

        foreach ([$cotizacionId, $cotizacionId + 1] as $index => $idCotizacion) {
            DB::table('notifications')->insert([
                'id' => $notificationIds[$index],
                'type' => NuevaCotizacionPendiente::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $seller->id,
                'data' => json_encode([
                    'tipo' => 'cotizacion_pendiente',
                    'cotizacion_id' => $idCotizacion,
                    'titulo' => 'Nueva cotización B2B',
                    'mensaje' => 'Cotización pendiente de revisión.',
                    'url' => route('solicitudes.show', $solicitudId),
                ]),
                'read_at' => null,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($seller)
            ->get(route('notificaciones.index'))
            ->assertOk();
        $this->assertDatabaseHas('notifications', ['id' => $notificationIds[0], 'read_at' => null]);

        $this->get(route('solicitudes.show', $solicitudId))->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notificationIds[0], 'read_at' => null]);
        $this->assertDatabaseHas('notifications', ['id' => $notificationIds[1], 'read_at' => null]);
    }

    public function test_opening_a_quote_reads_its_own_new_and_legacy_request_alerts_only(): void
    {
        Notification::fake();
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2);
        $firstId = (int) DB::table('solicitud_webs')->max('id_solicitud');
        $this->createProductRequest($productId, 1);
        $secondId = (int) DB::table('solicitud_webs')->max('id_solicitud');
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $otherSeller = User::factory()->create(['rol' => 'Vendedor']);
        $alertData = (new SolicitudWebRecibida(SolicitudWeb::findOrFail($firstId)))->toArray($seller);
        $this->assertSame($firstId, $alertData['solicitud_id']);
        $legacyData = $alertData;
        unset($legacyData['solicitud_id']);
        $legacyData['url'] = 'https://antiguo.example'.route('solicitudes.show', $firstId, false);
        $otherData = (new SolicitudWebRecibida(SolicitudWeb::findOrFail($secondId)))->toArray($seller);
        $inconsistentData = $alertData;
        $inconsistentData['solicitud_id'] = $secondId;
        $ids = [];

        foreach ([[$seller, $alertData, false], [$seller, $legacyData, false], [$seller, $otherData, false], [$otherSeller, $alertData, false], [$seller, $alertData, true], [$seller, $inconsistentData, false]] as [$owner, $data, $trashed]) {
            $id = (string) Str::uuid();
            $ids[] = $id;
            DB::table('notifications')->insert([
                'id' => $id,
                'type' => SolicitudWebRecibida::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $owner->id,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'read_at' => null,
                'deleted_at' => $trashed ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->get(route('solicitudes.show', $firstId))->assertRedirect(route('login'));
        $this->actingAs($seller)->get(route('solicitudes.index'))->assertOk();
        $this->assertSame(4, $seller->unreadNotifications()->whereNull('deleted_at')->count());
        $this->get(route('solicitudes.show', $firstId))->assertOk()
            ->assertSee('>2</span>', false);

        $this->assertDatabaseMissing('notifications', ['id' => $ids[0], 'read_at' => null]);
        $this->assertDatabaseMissing('notifications', ['id' => $ids[1], 'read_at' => null]);
        foreach (array_slice($ids, 2) as $id) {
            $this->assertDatabaseHas('notifications', ['id' => $id, 'read_at' => null]);
        }
        $this->assertSame(2, $seller->unreadNotifications()->whereNull('deleted_at')->count());
        $this->assertSame(5, $seller->notifications()->count());
    }

    public function test_quote_pdf_shows_the_request_contact_and_optional_cargo_with_historical_customer_data(): void
    {
        Notification::fake();
        $this->createProductRequest($this->createProductWithStock(5), 1);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']))
            ->post(route('solicitudes.cotizacion.crear', $solicitudId))
            ->assertSessionHasNoErrors();
        $version = CotizacionVersion::with(['cotizacion.solicitud', 'detalles'])->firstOrFail();
        Cliente::where('rut', '12345678-5')->firstOrFail()->update([
            'nombre' => 'Empresa nueva',
            'contacto' => 'Contacto nuevo',
            'telefono' => '+56987654321',
        ]);

        $datos = [
            'version' => $version,
            'cliente' => Cliente::desdeSnapshot($version->snapshotDatosCliente()),
            'logoDataUri' => '',
        ];
        $this->view('cotizaciones.pdf', $datos)
            ->assertSee('Razón social: Cliente de prueba')
            ->assertSee('Atención / contacto: Contacto B2B')
            ->assertSee('Cargo: OT-123')
            ->assertSee('Teléfono: +56912345678')
            ->assertSee('Región: Metropolitana')
            ->assertSee('Ciudad: Santiago')
            ->assertSee('Esperando tener una buena acogida por parte de ustedes')
            ->assertDontSee('Empresa nueva')
            ->assertDontSee('Contacto nuevo')
            ->assertDontSee('Entrega:')
            ->assertDontSee('Esta cotización no es una factura');

        $this->get(route('cotizaciones.pdf', $version->id_version))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $version->cotizacion->solicitud->cargo = null;
        $this->view('cotizaciones.pdf', $datos)->assertDontSee('Cargo:');
    }

    public function test_team_records_acceptance_and_converts_quote_to_sale_once(): void
    {
        Mail::fake();
        Notification::fake();
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $this->actingAs($seller)->post(route('solicitudes.cotizacion.crear', $solicitudId))->assertSessionHasNoErrors();
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('catalogo.solicitudes.store'), [
            'rut' => '12345678-5',
            'nombre' => 'Cliente actualizado',
            'contacto' => 'Contacto nuevo',
            'correo' => 'nuevo@example.test',
            'prefijo_telefono' => '+569',
            'telefono' => '87654321',
            'region' => 'Metropolitana',
            'comuna' => 'Santiago',
            'detalles_productos' => [['id_producto' => $productId, 'cantidad' => 1]],
        ])->assertRedirect(route('catalogo.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', ['rut' => '12345678-5', 'nombre' => 'Cliente actualizado']);
        $this->assertDatabaseHas('cotizacion_versiones', [
            'id_version' => $versionId,
            'datos_cliente->nombre' => 'Cliente de prueba',
            'datos_cliente->contacto' => 'Contacto B2B',
        ]);

        $this->post(route('cotizaciones.enviar', $versionId))->assertRedirect();
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);

        $this->post(route('cotizaciones.respuesta-interna', $versionId), ['decision' => 'aceptada'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Se registró la aceptación de la cotización.');
        $this->assertDatabaseCount('ventas', 0);
        $this->get(route('solicitudes.show', $solicitudId))
            ->assertOk()
            ->assertSee('sin adjuntar una orden de compra')
            ->assertDontSee('Confirmar pago');
        $this->post(route('cotizaciones.convertir-venta', $versionId))->assertSessionHasNoErrors();
        $this->post(route('cotizaciones.convertir-venta', $versionId))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 3]);
        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseHas('ventas', ['contacto' => 'Contacto B2B', 'cargo' => 'OT-123']);
        $this->assertDatabaseHas('ventas', [
            'id_cliente' => DB::table('clientes')->value('id_cliente'),
            'datos_cliente->nombre' => 'Cliente de prueba',
            'datos_cliente->contacto' => 'Contacto B2B',
        ]);
        $this->assertDatabaseCount('inventario_movimientos', 1);
        $this->assertDatabaseHas('cotizacion_versiones', ['id_version' => $versionId, 'respuesta_registrada_por' => $seller->id]);
        $this->assertDatabaseHas('reservas_inventario', ['producto_id' => $productId, 'estado' => 'consumida', 'cantidad' => 2]);
        $this->assertDatabaseHas('cotizacion_versiones', ['id_version' => $versionId, 'metodo_entrega' => 'por_coordinar']);
        $this->assertDatabaseCount('ordenes_compra', 0);
    }

    public function test_changes_create_new_version_and_release_removed_reservations(): void
    {
        Mail::fake();
        Notification::fake();
        $first = $this->createProductWithStock(5);
        $second = $this->createProductWithStock(5);
        $this->createProductRequest($first, 3);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $this->actingAs($seller)->post(route('solicitudes.cotizacion.crear', $solicitudId))->assertSessionHasNoErrors();
        $firstVersion = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $firstVersion))->assertRedirect();
        $this->post(route('cotizaciones.respuesta-interna', $firstVersion), [
            'decision' => 'cambios_solicitados',
            'comentarios' => 'Cambiar cantidades',
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('warning', 'Se registraron los cambios solicitados para la cotización.');
        $this->post(route('solicitudes.cotizacion.crear', $solicitudId), [
            'items_edicion' => '1',
            'items' => [
                ['id_producto' => $first, 'cantidad' => 1],
                ['id_producto' => $second, 'cantidad' => 2],
            ],
        ])->assertSessionHasNoErrors();
        $secondVersion = (int) DB::table('cotizacion_versiones')->where('numero_version', 2)->value('id_version');
        $this->assertNotSame(0, $secondVersion);
        $this->assertSame(1, (int) DB::table('reservas_inventario')->where('producto_id', $first)->where('estado', 'activa')->sum('cantidad'));
        $this->assertSame(2, (int) DB::table('reservas_inventario')->where('producto_id', $second)->where('estado', 'activa')->sum('cantidad'));
        $this->assertDatabaseHas('productos', ['id_producto' => $first, 'stock' => 5]);
        $this->post(route('cotizaciones.enviar', $secondVersion))->assertRedirect();
        $this->post(route('cotizaciones.respuesta-interna', $secondVersion), ['decision' => 'rechazada'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('rejected', 'Se registró el rechazo de la cotización.');
        $this->assertSame(0, DB::table('reservas_inventario')->where('estado', 'activa')->count());
    }

    public function test_sale_with_insufficient_stock_rolls_back_without_creating_a_sale(): void
    {
        Mail::fake();
        Notification::fake();
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $this->actingAs($seller)->post(route('solicitudes.cotizacion.crear', $solicitudId));
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId));
        $this->post(route('cotizaciones.respuesta-interna', $versionId), ['decision' => 'aceptada']);
        DB::table('productos')->where('id_producto', $productId)->update(['stock' => 1]);

        $this->post(route('cotizaciones.convertir-venta', $versionId))->assertSessionHasErrors('stock');
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('inventario_movimientos', 0);
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 1]);
    }

    public function test_existing_matching_inventory_movements_are_not_deducted_again(): void
    {
        Mail::fake();
        Notification::fake();
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $this->actingAs($seller)->post(route('solicitudes.cotizacion.crear', $solicitudId));
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId));
        $this->post(route('cotizaciones.respuesta-interna', $versionId), ['decision' => 'aceptada']);
        $this->post(route('cotizaciones.convertir-venta', $versionId))->assertSessionHasNoErrors();
        DB::table('ventas')->update(['stock_descontado_at' => null, 'estado_venta' => 'pendiente_confirmacion']);

        $migration = require database_path('migrations/2026_11_13_224156_migrate_pending_b2b_sales_to_new_flow.php');
        $migration->up();
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 3]);
        $this->assertDatabaseCount('inventario_movimientos', 1);
        $this->assertDatabaseHas('ventas', ['estado_venta' => 'confirmada']);
        $this->assertNotNull(DB::table('ventas')->value('stock_descontado_at'));
    }

    public function test_migration_rolls_back_all_sales_when_historical_movements_do_not_match(): void
    {
        Mail::fake();
        Notification::fake();
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $this->actingAs($seller);

        foreach ([1, 2] as $index) {
            $productId = $this->createProductWithStock(10);
            $this->createProductRequest($productId, 2);
            $solicitudId = (int) DB::table('solicitud_webs')->max('id_solicitud');
            $this->post(route('solicitudes.cotizacion.crear', $solicitudId))->assertSessionHasNoErrors();
            $versionId = (int) DB::table('cotizacion_versiones')->max('id_version');
            $this->post(route('cotizaciones.enviar', $versionId));
            $this->post(route('cotizaciones.respuesta-interna', $versionId), ['decision' => 'aceptada']);
            $this->post(route('cotizaciones.convertir-venta', $versionId))->assertSessionHasNoErrors();
            $products[$index] = $productId;
            $sales[$index] = (int) DB::table('ventas')->max('id_venta');
        }

        DB::table('ventas')->update(['stock_descontado_at' => null, 'estado_venta' => 'pendiente_confirmacion']);
        DB::table('inventario_movimientos')->where('id_venta', $sales[1])->delete();
        DB::table('inventario_movimientos')->where('id_venta', $sales[2])->update(['cantidad' => -1]);

        try {
            $migration = require database_path('migrations/2026_11_13_224156_migrate_pending_b2b_sales_to_new_flow.php');
            $migration->up();
            $this->fail('Se esperaba un error por movimientos históricos inconsistentes.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('venta', $exception->errors());
        }

        $this->assertDatabaseHas('productos', ['id_producto' => $products[1], 'stock' => 8]);
        $this->assertDatabaseHas('productos', ['id_producto' => $products[2], 'stock' => 8]);
        $this->assertDatabaseMissing('inventario_movimientos', ['id_venta' => $sales[1]]);
        $this->assertSame(2, DB::table('ventas')->whereNull('stock_descontado_at')->count());
    }

    public function test_migration_deducts_stock_for_historical_sales_without_movements(): void
    {
        Mail::fake();
        Notification::fake();
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']))
            ->post(route('solicitudes.cotizacion.crear', $solicitudId));
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId));
        $this->post(route('cotizaciones.respuesta-interna', $versionId), ['decision' => 'aceptada']);
        $this->post(route('cotizaciones.convertir-venta', $versionId))->assertSessionHasNoErrors();
        DB::table('inventario_movimientos')->delete();
        DB::table('productos')->where('id_producto', $productId)->update(['stock' => 5]);
        DB::table('ventas')->update(['stock_descontado_at' => null, 'estado_venta' => 'pendiente_confirmacion']);

        $migration = require database_path('migrations/2026_11_13_224156_migrate_pending_b2b_sales_to_new_flow.php');
        $migration->up();
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 3]);
        $this->assertDatabaseCount('inventario_movimientos', 1);
        $this->assertDatabaseHas('ventas', ['estado_venta' => 'confirmada']);
        $this->assertNotNull(DB::table('ventas')->value('stock_descontado_at'));
    }

    public function test_sale_does_not_consume_stock_reserved_for_another_quote(): void
    {
        Mail::fake();
        Notification::fake();
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2);
        $firstRequest = (int) DB::table('solicitud_webs')->max('id_solicitud');
        $this->createProductRequest($productId, 3);
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $this->actingAs($seller)->post(route('solicitudes.cotizacion.crear', $firstRequest));
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId));
        $this->post(route('cotizaciones.respuesta-interna', $versionId), ['decision' => 'aceptada']);
        DB::table('productos')->where('id_producto', $productId)->update(['stock' => 4]);

        $this->post(route('cotizaciones.convertir-venta', $versionId))->assertSessionHasErrors('stock');
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 4]);
        $this->assertSame(5, (int) DB::table('reservas_inventario')->where('estado', 'activa')->sum('cantidad'));
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_internal_summary_records_customer_data_and_purchase_order_without_invoicing(): void
    {
        Mail::fake();
        Notification::fake();
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 1);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $this->actingAs($seller)->post(route('solicitudes.cotizacion.crear', $solicitudId));
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId));
        $this->post(route('cotizaciones.respuesta-interna', $versionId), ['decision' => 'aceptada']);
        $this->post(route('cotizaciones.convertir-venta', $versionId));
        $ventaId = (int) DB::table('ventas')->value('id_venta');
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $this->actingAs($admin)->get(route('reportes.ventas.resumen', $ventaId))->assertNotFound();
        $this->actingAs($seller);
        $this->get(route('solicitudes.show', $solicitudId))
            ->assertOk()
            ->assertSee('Datos del emisor')
            ->assertSee('Datos del receptor')
            ->assertSee('Número de orden de compra')
            ->assertSee('Cliente de prueba')
            ->assertSee('El resumen no podrá modificarse ni generarse nuevamente');
        $datos = [
            'acteco_emisor' => '453000',
            'tipo_venta' => 'del_giro',
            'nombre' => 'Empresa de prueba',
            'rut' => '12.345.678-5',
            'giro' => 'Comercio',
            'direccion' => 'Calle 123',
            'comuna' => 'Santiago',
            'ciudad' => 'Santiago',
            'contacto' => 'Responsable receptor',
            'tipo_compra' => 'del_giro',
            'correo' => 'cliente@example.test',
            'numero_oc' => 'OC-123',
        ];
        $this->post(route('ventas.generar-resumen', $ventaId), [
            ...$datos,
            'acteco_emisor' => 'CODIGO-REAL',
            'contacto' => '',
            'ciudad' => '',
            'tipo_venta' => 'otro',
            'tipo_compra' => 'otro',
        ])->assertSessionHasErrors(['acteco_emisor', 'contacto', 'ciudad', 'tipo_venta', 'tipo_compra']);
        $this->assertNull(DB::table('ventas')->value('datos_resumen'));

        $response = $this->post(route('ventas.generar-resumen', $ventaId), $datos);
        $response->assertRedirect()->assertSessionHasNoErrors();
        $guardados = json_decode(DB::table('ventas')->value('datos_resumen'), true);
        $this->assertSame('OC-123', $guardados['numero_oc']);
        $this->assertSame('Responsable receptor', $guardados['contacto']);
        $this->assertSame('453000', $guardados['acteco_emisor']);
        $this->assertSame('Venta de partes, piezas y accesorios para vehículos automotores', $guardados['giro_emisor']);
        $this->assertSame('del_giro', $guardados['tipo_venta']);
        $this->assertSame('del_giro', $guardados['tipo_compra']);
        $venta = Venta::findOrFail($ventaId);
        $this->view('reportes.venta-pdf', [
            'venta' => $venta,
            'cliente' => Cliente::desdeSnapshot($venta->snapshotDatosCliente()),
            'despacho' => 0,
            'logoDataUri' => '',
        ])->assertSee('EMISOR')
            ->assertSee('RECEPTOR')
            ->assertSee('453000')
            ->assertSee('Responsable receptor')
            ->assertSee('Ciudad: Santiago')
            ->assertSee('Tipo de venta: Del giro')
            ->assertSee('Tipo de compra: Del giro')
            ->assertDontSee('OT-123');
        $this->get(route('solicitudes.show', $solicitudId))
            ->assertOk()
            ->assertSee('El resumen interno ya fue generado')
            ->assertDontSee('Generar resumen interno');
        $this->post(route('ventas.generar-resumen', $ventaId), [...$datos, 'nombre' => 'Otro cliente'])
            ->assertSessionHasErrors('resumen');
        $this->assertSame('Empresa de prueba', Venta::findOrFail($ventaId)->datos_resumen['nombre']);
        $this->getJson(route('pos.cliente.rut', ['rut' => '12.345.678-5']))
            ->assertOk()->assertJsonPath('cliente.contacto', 'Contacto B2B')
            ->assertJsonStructure(['cliente' => ['razon_social', 'ciudad']]);
        $this->assertDatabaseCount('eventos_facturacion', 0);
        $original = base64_decode(Venta::findOrFail($ventaId)->resumen_interno_pdf, true);
        $this->assertStringStartsWith('%PDF', $original);
        DB::table('ventas')->where('id_venta', $ventaId)->update(['total' => 99999]);
        $descarga = $this->actingAs($admin)->get(route('reportes.ventas.resumen', $ventaId))->assertOk();
        $this->assertSame($original, $descarga->getContent());
    }

    public function test_new_quote_notifies_admin_and_seller_by_email(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $this->createProductRequest($this->createProductWithStock(5), 1);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');

        foreach ([$admin, $seller] as $user) {
            Notification::assertSentTo($user, SolicitudWebRecibida::class, function ($notification, $channels) use ($user, $solicitudId): bool {
                return in_array('mail', $channels, true)
                    && $notification->toMail($user)->viewData['urlSolicitud'] === route('solicitudes.show', $solicitudId);
            });
        }
    }

    public function test_http_errors_use_resmap_branded_pages(): void
    {
        $admin = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($admin)->get(route('reportes.index'))
            ->assertForbidden()
            ->assertSee('No tienes permiso para ingresar')
            ->assertSee('ERROR 403')
            ->assertDontSee('Symfony\\Component\\HttpKernel\\Exception');

        $this->get('/ruta-que-no-existe')
            ->assertNotFound()
            ->assertSee('No encontramos esta página')
            ->assertSee('RESMAP');
    }

    public function test_product_request_reserves_stock_without_decrementing_physical_inventory(): void
    {
        $productId = $this->createProductWithStock(5);

        $this->createProductRequest($productId, 3);

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
        $this->assertDatabaseHas('reservas_inventario', [
            'producto_id' => $productId,
            'cantidad' => 3,
            'estado' => 'activa',
        ]);
    }

    public function test_quote_editor_availability_excludes_other_reservations_but_includes_its_own(): void
    {
        Notification::fake();
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2);
        $firstId = (int) DB::table('solicitud_webs')->max('id_solicitud');
        $this->createProductRequest($productId, 3);

        $zeroStockId = $this->createProductWithStock(0);
        $response = $this->actingAs(User::factory()->create(['rol' => 'Vendedor']))
            ->get(route('solicitudes.show', $firstId))
            ->assertOk()
            ->assertViewHas('productosDisponibles', fn ($productos) => $productos->firstWhere('id_producto', $productId)->stock_disponible === 2)
            ->assertSee('data-buscar-producto', false);

        preg_match('/<script type="application\/json" id="productos-cotizacion">(.*?)<\/script>/s', $response->getContent(), $matches);
        $products = collect(json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR))->keyBy('id');
        $this->assertSame(5, $products[$productId]['stock']);
        $this->assertSame(2, $products[$productId]['disponible']);
        $this->assertSame(0, $products[$zeroStockId]['stock']);
        $this->assertSame(0, $products[$zeroStockId]['disponible']);

        $this->post(route('solicitudes.cotizacion.crear', $firstId), [
            'items_edicion' => 1,
            'items' => [
                ['id_producto' => $productId, 'cantidad' => 3],
                ['id_producto' => $zeroStockId, 'cantidad' => 1],
            ],
        ])->assertSessionHasErrors('detalles_productos')->assertSessionHasInput('items.0.cantidad', 3);
        $this->assertDatabaseHas('reservas_inventario', ['producto_id' => $productId, 'cantidad' => 2, 'estado' => 'activa']);
        $this->get(route('solicitudes.show', $firstId))
            ->assertSee('name="items[0][cantidad]" value="3"', false)
            ->assertSee('name="items[1][id_producto]" value="'.$zeroStockId.'"', false);
    }

    public function test_quote_stock_error_uses_the_dismissible_notification_without_hiding_other_errors(): void
    {
        Notification::fake();
        $this->createProductRequest($this->createProductWithStock(5), 1);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $response = $this->actingAs(User::factory()->create(['rol' => 'Vendedor']))
            ->get(route('solicitudes.show', $solicitudId))->assertOk();

        $this->withViewErrors([
            'detalles_productos' => 'Stock insuficiente para el repuesto.',
            'cotizacion' => 'Otro error de la cotización.',
        ])->view('solicitudes.show', $response->original->getData())
            ->assertSee('Stock insuficiente para el repuesto.')
            ->assertSee('role="alert" data-auto-dismiss', false)
            ->assertSee('data-dismiss-notification', false)
            ->assertSee('No se pudo completar la acción:')
            ->assertSee('Otro error de la cotización.');
    }

    public function test_legacy_request_completion_cannot_bypass_b2b_quote_flow(): void
    {
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 2);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->patch(route('solicitudes.estado', $solicitudId), ['estado' => 'Entregado y completado'])
            ->assertRedirect(route('solicitudes.show', $solicitudId))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_legacy_completion_cannot_consume_stock_reserved_for_another_quote(): void
    {
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 3);
        $clienteId = (int) DB::table('clientes')->value('id_cliente');
        $solicitudId = DB::table('solicitud_webs')->insertGetId([
            'fecha' => now(),
            'estado' => 'Pendiente',
            'tipo_solicitud' => 'pedido',
            'id_cliente' => $clienteId,
            'detalles_productos' => json_encode([
                ['id_producto' => $productId, 'cantidad' => 3],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->patch(route('solicitudes.estado', $solicitudId), ['estado' => 'Entregado y completado'])
            ->assertRedirect(route('solicitudes.show', $solicitudId))
            ->assertSessionHas('error', 'Stock disponible insuficiente para Producto B2B de prueba; hay 2 unidades libres.');

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_manual_product_stock_edit_cannot_reduce_stock_below_active_reservations(): void
    {
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 3);
        $producto = DB::table('productos')->where('id_producto', $productId)->first();
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->put(route('productos.update', $productId), [
                'codigo_barra' => $producto->codigo_barra,
                'nombre' => $producto->nombre,
                'precio' => $producto->precio,
                'stock' => 2,
                'stock_critico' => $producto->stock_critico,
                'unidad' => $producto->unidad,
                'activo' => $producto->activo,
                'id_categoria' => $producto->id_categoria,
            ])
            ->assertSessionHasErrors('stock');

        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
    }

    public function test_consented_customer_receives_an_approved_whatsapp_template_through_the_test_allowlist(): void
    {
        Mail::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test-message']], 'contacts' => []]),
        ]);
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.api_version' => 'v25.0',
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.test_mode' => true,
            'services.whatsapp.test_recipients' => ['+56987654321'],
            'services.whatsapp.quote_template' => 'resmap_quote_es_cl',
            'services.whatsapp.quote_url_button' => true,
            'services.whatsapp.quote_url_button_index' => 0,
        ]);
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 1);
        DB::table('clientes')->update([
            'consentimiento_whatsapp_at' => now(),
            'consentimiento_whatsapp_origen' => 'prueba',
            'telefono_whatsapp' => '+56987654321',
        ]);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->post(route('solicitudes.cotizacion.crear', $solicitudId))
            ->assertRedirect(route('solicitudes.show', $solicitudId));

        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId))
            ->assertRedirect(route('solicitudes.show', $solicitudId));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/test-phone-number-id/messages')
            && $request->data()['type'] === 'template'
            && $request->data()['to'] === '56987654321'
            && $request->data()['template']['name'] === 'resmap_quote_es_cl');
        $this->assertDatabaseHas('mensajes_whatsapp', [
            'version_id' => $versionId,
            'estado' => 'aceptado_por_meta',
            'id_proveedor' => 'wamid.test-message',
        ]);

        $datosRegistrados = (string) DB::table('mensajes_whatsapp')->where('version_id', $versionId)->value('datos');
        $this->assertStringContainsString('[LINK DE COTIZACION]', $datosRegistrados);
        $this->assertSame(0, preg_match('/quote:\d+:aceptar:[A-Za-z0-9]{48}/', $datosRegistrados));
    }

    public function test_whatsapp_template_can_send_the_quote_link_as_a_url_button(): void
    {
        Mail::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.url-button']], 'contacts' => []]),
        ]);
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.api_version' => 'v25.0',
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.test_mode' => true,
            'services.whatsapp.test_recipients' => ['+56912345678'],
            'services.whatsapp.quote_template' => 'resmap_cotizacion_v2',
            'services.whatsapp.quote_quick_replies' => true,
            'services.whatsapp.quote_url_button' => true,
            'services.whatsapp.quote_url_button_index' => 3,
        ]);
        $this->createProductRequest($this->createProductWithStock(5), 1);
        DB::table('clientes')->update([
            'consentimiento_whatsapp_at' => now(),
            'consentimiento_whatsapp_origen' => 'prueba',
        ]);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)->post(route('solicitudes.cotizacion.crear', $solicitudId));
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId));

        Http::assertSent(function ($request) use ($versionId): bool {
            $componentes = $request->data()['template']['components'];
            $boton = collect($componentes)->firstWhere('sub_type', 'url');

            return count($componentes[0]['parameters']) === 3
                && collect($componentes)->where('sub_type', 'quick_reply')->count() === 0
                && $boton['index'] === '3'
                && str_starts_with($boton['parameters'][0]['text'], $versionId.'/documento-externo?');
        });
    }

    public function test_whatsapp_template_can_send_only_a_url_button(): void
    {
        Mail::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.url-only']], 'contacts' => []]),
        ]);
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.api_version' => 'v25.0',
            'services.whatsapp.phone_number_id' => 'test-phone-number-id',
            'services.whatsapp.access_token' => 'test-access-token',
            'services.whatsapp.test_mode' => true,
            'services.whatsapp.test_recipients' => ['+56912345678'],
            'services.whatsapp.quote_template' => 'resmap_cotizacion_v3',
            'services.whatsapp.quote_quick_replies' => false,
            'services.whatsapp.quote_url_button' => true,
            'services.whatsapp.quote_url_button_index' => 0,
        ]);
        $this->createProductRequest($this->createProductWithStock(5), 1);
        DB::table('clientes')->update([
            'consentimiento_whatsapp_at' => now(),
            'consentimiento_whatsapp_origen' => 'prueba',
        ]);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)->post(route('solicitudes.cotizacion.crear', $solicitudId));
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId))->assertRedirect();

        $mensaje = Mail::sent(CotizacionCliente::class)->first();
        $this->assertNotNull($mensaje);
        $rutaPdf = (string) parse_url($mensaje->urlPdf, PHP_URL_PATH).'?'.(string) parse_url($mensaje->urlPdf, PHP_URL_QUERY);
        $this->get($rutaPdf)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(str_replace('signature=', 'signature=incorrecta', $rutaPdf))->assertForbidden();
        Http::assertSent(function ($request) use ($rutaPdf): bool {
            $plantilla = $request->data()['template'];
            $componentes = $plantilla['components'];

            return $plantilla['name'] === 'resmap_cotizacion_v3'
                && count($componentes) === 2
                && $componentes[0]['type'] === 'body'
                && count($componentes[0]['parameters']) === 3
                && $componentes[1]['sub_type'] === 'url'
                && $componentes[1]['index'] === '0'
                && $componentes[1]['parameters'][0]['text'] === substr($rutaPdf, strlen('/cotizaciones/'));
        });
        $this->assertDatabaseHas('mensajes_whatsapp', [
            'version_id' => $versionId,
            'estado' => 'aceptado_por_meta',
        ]);
        $datosRegistrados = (string) DB::table('mensajes_whatsapp')->where('version_id', $versionId)->value('datos');
        $this->assertStringNotContainsString((string) parse_url($mensaje->urlPdf, PHP_URL_QUERY), $datosRegistrados);
    }

    public function test_old_response_links_and_buttons_cannot_change_a_quote(): void
    {
        Mail::fake();
        Notification::fake();
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 1);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']))
            ->post(route('solicitudes.cotizacion.crear', $solicitudId));
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId));
        auth()->logout();

        $this->get(route('cotizaciones.responder.form', ['version' => $versionId, 'token' => 'antiguo']))->assertStatus(410);
        $this->post(route('cotizaciones.responder', ['version' => $versionId, 'token' => 'antiguo']), ['decision' => 'aceptar'])->assertStatus(410);
        config(['services.whatsapp.app_secret' => 'test-app-secret']);
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [['changes' => [['value' => ['messages' => [[
                'from' => '56912345678',
                'id' => 'wamid.old-button',
                'type' => 'button',
                'button' => ['text' => 'Aceptar', 'payload' => "quote:{$versionId}:aceptar:".str_repeat('a', 48)],
            ]]]]]]],
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->call('POST', route('whatsapp.webhook.recibir'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'test-app-secret'),
        ], $body)->assertOk();

        $this->assertDatabaseHas('cotizacion_versiones', ['id_version' => $versionId, 'estado' => 'enviada']);
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 5]);
    }

    public function test_quote_pdf_link_expires_and_internal_actions_require_authentication(): void
    {
        Mail::fake();
        Notification::fake();
        $this->createProductRequest($this->createProductWithStock(5), 1);
        $solicitudId = (int) DB::table('solicitud_webs')->value('id_solicitud');
        $this->actingAs(User::factory()->create(['rol' => 'Administrador']))
            ->post(route('solicitudes.cotizacion.crear', $solicitudId));
        $versionId = (int) DB::table('cotizacion_versiones')->value('id_version');
        $this->post(route('cotizaciones.enviar', $versionId));
        $mail = Mail::sent(CotizacionCliente::class)->first();
        $url = (string) parse_url($mail->urlPdf, PHP_URL_PATH).'?'.(string) parse_url($mail->urlPdf, PHP_URL_QUERY);
        auth()->logout();
        $this->post(route('cotizaciones.respuesta-interna', $versionId), ['decision' => 'aceptada'])->assertRedirect(route('login'));
        $this->post(route('cotizaciones.convertir-venta', $versionId))->assertRedirect(route('login'));
        $this->get($url)->assertOk();
        $this->travel(8)->days();
        $this->get($url)->assertForbidden();
    }

    public function test_whatsapp_webhook_verification_accepts_meta_query_parameters(): void
    {
        config(['services.whatsapp.verify_token' => 'token-secreto']);

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=token-secreto&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345');
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=otro&hub.challenge=12345')
            ->assertForbidden();
    }

    public function test_signed_whatsapp_stop_message_revokes_customer_consent(): void
    {
        $productId = $this->createProductWithStock(5);
        $this->createProductRequest($productId, 1);
        DB::table('clientes')->update([
            'consentimiento_whatsapp_at' => now(),
            'consentimiento_whatsapp_origen' => 'catalogo',
            'telefono_whatsapp' => '+56987654321',
        ]);
        config(['services.whatsapp.app_secret' => 'test-app-secret']);
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '56987654321',
                            'id' => 'wamid.inbound-stop',
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'STOP'],
                        ]],
                    ],
                ]],
            ]],
        ];
        $cuerpo = json_encode($payload, JSON_THROW_ON_ERROR);
        $firma = 'sha256='.hash_hmac('sha256', $cuerpo, 'test-app-secret');

        $this->call('POST', route('whatsapp.webhook.recibir'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $firma,
        ], $cuerpo)->assertOk();

        $this->assertDatabaseHas('clientes', [
            'telefono' => '+56912345678',
            'consentimiento_whatsapp_at' => null,
            'consentimiento_whatsapp_origen' => 'whatsapp_opt_out',
        ]);
    }

    private function createProductRequest(int $productId, int $cantidad, ?string $correo = null): void
    {
        $datos = [
            'rut' => '12345678-5',
            'nombre' => 'Cliente de prueba',
            'contacto' => 'Contacto B2B',
            'cargo' => 'OT-123',
            'correo' => $correo ?? 'cliente@example.test',
            'prefijo_telefono' => '+569',
            'telefono' => '12345678',
            'preferencia_cotizacion' => 'solo_correo',
            'region' => 'Metropolitana',
            'comuna' => 'Santiago',
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => $cantidad],
            ],
        ];
        $this->post(route('catalogo.solicitudes.store'), $datos)
            ->assertRedirect(route('catalogo.index'));
    }

    private function createProductWithStock(int $stock): int
    {
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Repuestos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('productos')->insertGetId([
            'codigo_barra' => 'B2B-'.uniqid(),
            'nombre' => DB::table('productos')->where('nombre', 'Producto B2B de prueba')->exists()
                ? 'Producto B2B de prueba '.uniqid()
                : 'Producto B2B de prueba',
            'precio' => 1190,
            'stock' => $stock,
            'stock_critico' => 1,
            'id_categoria' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
