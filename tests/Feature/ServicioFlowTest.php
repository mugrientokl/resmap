<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\SolicitudWebRecibida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class ServicioFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_request_notifies_staff_by_email_and_database(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $vendedor = User::factory()->create(['rol' => 'Vendedor']);
        $this->solicitarServicio();

        foreach ([$admin, $vendedor] as $usuario) {
            Notification::assertSentTo($usuario, SolicitudWebRecibida::class, fn ($notificacion, $canales): bool => $canales === ['database', 'mail']
                && $notificacion->solicitud->tipo_solicitud === 'servicio');
        }
    }

    public function test_mail_transport_failure_keeps_request_and_warns_customer(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $vendedor = User::factory()->create(['rol' => 'Vendedor']);
        Event::listen(MessageSending::class, fn () => throw new TransportException('Fallo de prueba'));
        $this->post(route('servicios.store'), [
            'rut' => '12345678-5', 'nombre' => 'Empresa', 'contacto' => 'Ana',
            'correo' => 'cliente@example.test',
            'prefijo_telefono' => '+569', 'telefono' => '12345678',
            'direccion' => 'Calle 123', 'region' => 'Metropolitana', 'comuna' => 'Santiago',
            'tipo_servicio' => 'Mantención', 'descripcion_servicio' => 'Revisión preventiva del equipo',
        ])->assertRedirect(route('servicios.index'))
            ->assertSessionHas('warning', 'Tu solicitud quedó registrada y el equipo recibió un aviso en el sistema, pero no se pudo enviar algún correo interno.');
        $this->assertDatabaseHas('solicitud_webs', ['tipo_solicitud' => 'servicio', 'estado' => 'Pendiente']);
        foreach ([$admin, $vendedor] as $usuario) {
            $aviso = $usuario->notifications()->firstOrFail();
            $this->assertSame('Nuevo servicio solicitado', $aviso->data['titulo']);
            $this->assertSame(route('solicitudes.show', SolicitudWeb::firstOrFail()), $aviso->data['url']);
        }
    }

    public function test_service_requires_address_without_public_rut_lookup(): void
    {
        $this->get(route('servicios.index'))->assertOk()
            ->assertDontSee(asset('js/cliente-por-rut.js'), false)
            ->assertDontSee('/catalogo/cliente-por-rut', false)
            ->assertSee('name="direccion" value="" required', false);

        $this->post(route('servicios.store'), [
            'rut' => '12345678-5', 'nombre' => 'Empresa', 'contacto' => 'Ana',
            'correo' => 'cliente@example.test',
            'prefijo_telefono' => '+569', 'telefono' => '12345678',
            'region' => 'Metropolitana', 'comuna' => 'Santiago',
            'tipo_servicio' => 'Mantención', 'descripcion_servicio' => 'Revisión preventiva del equipo',
        ])->assertSessionHasErrors('direccion');
        $this->assertDatabaseCount('solicitud_webs', 0);
    }

    public function test_service_requires_a_valid_email(): void
    {
        $this->get(route('servicios.index'))->assertOk()
            ->assertSee('name="correo" type="email" value="" required', false);

        $datos = [
            'rut' => '12345678-5', 'nombre' => 'Empresa', 'contacto' => 'Ana',
            'prefijo_telefono' => '+569', 'telefono' => '12345678',
            'direccion' => 'Calle 123', 'region' => 'Metropolitana', 'comuna' => 'Santiago',
            'tipo_servicio' => 'Mantención', 'descripcion_servicio' => 'Revisión preventiva del equipo',
        ];

        $this->post(route('servicios.store'), $datos)->assertSessionHasErrors('correo');
        $this->post(route('servicios.store'), [...$datos, 'correo' => 'correo-invalido'])
            ->assertSessionHasErrors('correo');
        $this->assertDatabaseCount('solicitud_webs', 0);
    }

    public function test_service_accepts_chilean_national_numbers_and_rejects_foreign_or_duplicate_prefixes(): void
    {
        Notification::fake();
        $datos = [
            'rut' => '12345678-5', 'nombre' => 'Empresa', 'contacto' => 'Ana',
            'correo' => 'cliente@example.test', 'prefijo_telefono' => '+56', 'telefono' => '223456789',
            'direccion' => 'Calle 123', 'region' => 'Metropolitana', 'comuna' => 'Santiago',
            'tipo_servicio' => 'Mantención', 'descripcion_servicio' => 'Revisión preventiva del equipo',
        ];

        $this->post(route('servicios.store'), [...$datos, 'prefijo_telefono' => '+54'])
            ->assertSessionHasErrors('prefijo_telefono');
        $this->post(route('servicios.store'), [...$datos, 'telefono' => '56912345678'])
            ->assertSessionHasErrors('telefono');
        $this->assertDatabaseCount('solicitud_webs', 0);

        $this->post(route('servicios.store'), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', ['rut' => '12345678-5', 'telefono' => '+56223456789']);
    }

    public function test_service_without_staff_recipients_is_saved_with_a_warning(): void
    {
        Notification::fake();
        $solicitud = $this->solicitarServicio();
        $this->assertNotNull($solicitud->id_solicitud);
        $this->assertSame(
            'Tu solicitud quedó registrada, pero no hay destinatarios disponibles para avisar al equipo. Contacta a RESMAP.',
            session('warning')
        );
        Notification::assertNothingSent();
    }

    public function test_paid_service_with_parts_is_registered_once_and_uses_shared_summary(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $producto = $this->crearProducto();
        $solicitud = $this->solicitarServicio();
        $this->actingAs($admin)->get(route('solicitudes.show', $solicitud))
            ->assertOk()->assertSee('Registrar servicio completado')->assertSee('Revisión preventiva del equipo');

        $datos = $this->datosCierre([
            'repuestos' => [['id_producto' => $producto, 'cantidad' => 2]],
            'observaciones' => 'Trabajo finalizado correctamente.',
        ]);
        $this->post(route('solicitudes.registrar-servicio', $solicitud), $datos)->assertSessionHasNoErrors();
        $solicitud->refresh();
        $venta = $solicitud->venta;
        $this->assertSame('Entregado y completado', $solicitud->estado);
        $this->assertSame('confirmada', $venta->estado_venta);
        $this->assertSame('pagado', $venta->estado_pago);
        $this->assertSame(7380.0, (float) $venta->total);
        $this->assertSame(6201.68, (float) $venta->neto);
        $this->assertSame(1178.32, (float) $venta->iva);
        $this->assertSame(7380.0, (float) $venta->monto_pagado);
        $this->assertDatabaseHas('detalle_ventas', ['id_venta' => $venta->id_venta, 'id_producto' => null, 'descripcion' => 'Mantención preventiva', 'subtotal' => 5000]);
        $this->assertDatabaseHas('detalle_ventas', ['id_venta' => $venta->id_venta, 'id_producto' => $producto, 'cantidad' => 2, 'subtotal' => 2380]);
        $this->assertDatabaseHas('productos', ['id_producto' => $producto, 'stock' => 3]);
        $this->assertDatabaseHas('inventario_movimientos', ['id_venta' => $venta->id_venta, 'id_producto' => $producto, 'cantidad' => -2, 'stock_anterior' => 5, 'stock_nuevo' => 3]);
        $solicitud->cliente->update(['nombre' => 'Empresa actualizada']);
        $movimiento = DB::table('inventario_movimientos')->where('id_venta', $venta->id_venta)->value('id_movimiento');
        $this->getJson(route('inventario.movimientos.show', $movimiento))
            ->assertOk()->assertJsonPath('venta.detalles.0.producto', 'Mantención preventiva')
            ->assertJsonPath('venta.cliente', 'Empresa');

        $this->get(route('solicitudes.show', $solicitud))->assertOk()
            ->assertSee(route('ventas.generar-resumen', $venta), false)
            ->assertDontSee('Registrar servicio completado');
        $this->getJson(route('reportes.ventas.detalle', $venta->id_venta))
            ->assertOk()->assertJsonPath('detalles.0.producto', 'Mantención preventiva');
        $venta->load(['solicitudServicio', 'detalles.producto', 'user', 'cotizacionVersion', 'ordenesCompra', 'creditoAutorizadoPor']);
        $this->view('reportes.venta-pdf', [
            'venta' => $venta,
            'cliente' => Cliente::desdeSnapshot($venta->snapshotDatosCliente()),
            'despacho' => 0,
            'logoDataUri' => '',
        ])->assertSee('Origen: Servicio #'.$solicitud->id_solicitud)
            ->assertSee('Revisión preventiva del equipo')
            ->assertSee('Trabajo finalizado correctamente.')
            ->assertSee('Mantención preventiva');

        $this->post(route('ventas.generar-resumen', $venta), [
            'tipo_documento' => 'Boleta Electrónica',
            'acteco_emisor' => array_key_first(config('services.cotizaciones.actividades_emisor')),
            'tipo_venta' => 'del_giro',
            'rut' => '12345678-5',
            'nombre' => 'Empresa',
            'giro' => 'Agricultura',
            'direccion' => 'Calle 123',
            'comuna' => 'Santiago',
            'ciudad' => 'Santiago',
            'contacto' => 'Ana',
            'tipo_compra' => 'del_giro',
            'correo' => 'cliente@example.test',
        ])->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame('Agricultura', $venta->fresh()->datos_resumen['giro']);

        $this->post(route('solicitudes.registrar-servicio', $solicitud), $datos)->assertSessionHasErrors('servicio');
        $this->patch(route('solicitudes.estado', $solicitud), ['estado' => 'Rechazado']);
        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseCount('inventario_movimientos', 1);
        $this->assertDatabaseHas('solicitud_webs', ['id_solicitud' => $solicitud->id_solicitud, 'estado' => 'Entregado y completado']);
    }

    public function test_service_without_parts_and_old_completion_endpoint(): void
    {
        Notification::fake();
        $solicitud = $this->solicitarServicio();
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']));
        $this->patch(route('solicitudes.estado', $solicitud), ['estado' => 'En proceso'])
            ->assertSessionHasNoErrors();
        $this->get(route('solicitudes.show', $solicitud))->assertSee('Servicio: En proceso');
        $this->patch(route('solicitudes.estado', $solicitud), ['estado' => 'Entregado y completado'])
            ->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('ventas', 0);
        $this->post(route('solicitudes.registrar-servicio', $solicitud), $this->datosCierre())
            ->assertRedirect(route('solicitudes.show', $solicitud));
        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseCount('detalle_ventas', 1);
        $this->assertDatabaseCount('inventario_movimientos', 0);
    }

    public function test_reserved_stock_and_invalid_prices_do_not_create_a_sale(): void
    {
        Notification::fake();
        $producto = $this->crearProducto();
        $this->post(route('catalogo.solicitudes.store'), [
            'rut' => '12345678-5', 'nombre' => 'Empresa', 'contacto' => 'Ana',
            'correo' => 'cliente@example.test', 'prefijo_telefono' => '+569',
            'telefono' => '12345678', 'region' => 'Metropolitana', 'comuna' => 'Santiago',
            'detalles_productos' => [['id_producto' => $producto, 'cantidad' => 4]],
        ])->assertSessionHasNoErrors();
        $solicitud = $this->solicitarServicio();
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']));
        $this->post(route('solicitudes.registrar-servicio', $solicitud), $this->datosCierre([
            'repuestos' => [['id_producto' => $producto, 'cantidad' => 2]],
        ]))->assertSessionHasErrors('repuestos');
        $this->post(route('solicitudes.registrar-servicio', $solicitud), $this->datosCierre(['precio_servicio' => '-1']))
            ->assertSessionHasErrors('precio_servicio');
        $this->post(route('solicitudes.registrar-servicio', $solicitud), $this->datosCierre([
            'precio_servicio' => '99999999.99',
            'repuestos' => [['id_producto' => $producto, 'cantidad' => 1]],
        ]))->assertSessionHasErrors('precio_servicio');
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('inventario_movimientos', 0);
        $this->assertDatabaseHas('productos', ['id_producto' => $producto, 'stock' => 5]);
        $this->assertDatabaseHas('solicitud_webs', ['id_solicitud' => $solicitud->id_solicitud, 'estado' => 'Pendiente', 'venta_id' => null]);
    }

    public function test_service_registration_requires_staff_and_rejects_closed_requests(): void
    {
        Notification::fake();
        $solicitud = $this->solicitarServicio();
        $this->post(route('solicitudes.registrar-servicio', $solicitud), $this->datosCierre())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']))
            ->patch(route('solicitudes.estado', $solicitud), ['estado' => 'Rechazado'])->assertSessionHasNoErrors();
        $this->post(route('solicitudes.registrar-servicio', $solicitud), $this->datosCierre())->assertSessionHasErrors('servicio');
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_previously_completed_service_keeps_its_legacy_summary(): void
    {
        Notification::fake();
        $solicitud = $this->solicitarServicio();
        $usuario = User::factory()->create(['rol' => 'Administrador']);
        $venta = Venta::create([
            'fecha' => now(), 'tipo_documento' => 'Boleta Electrónica',
            'neto' => 0, 'iva' => 0, 'total' => 0, 'medio_pago' => 'Pendiente de emisión',
            'estado_sii' => 'Pendiente', 'user_id' => $usuario->id, 'id_cliente' => $solicitud->id_cliente,
        ]);
        $solicitud->update(['estado' => 'Entregado y completado', 'venta_id' => $venta->id_venta]);
        $this->actingAs($usuario)->get(route('solicitudes.show', $solicitud))
            ->assertOk()->assertSee(route('solicitudes.pdf', $solicitud), false)
            ->assertDontSee('Registrar servicio completado');
        $this->get(route('solicitudes.pdf', $solicitud))
            ->assertOk()->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=resumen-servicio-'.$solicitud->id_solicitud.'.pdf');
        $this->post(route('solicitudes.registrar-servicio', $solicitud), $this->datosCierre())
            ->assertSessionHasErrors('servicio');
        $this->assertDatabaseCount('ventas', 1);
    }

    private function solicitarServicio(): SolicitudWeb
    {
        $this->post(route('servicios.store'), [
            'rut' => '12345678-5', 'nombre' => 'Empresa', 'contacto' => 'Ana',
            'correo' => 'cliente@example.test', 'prefijo_telefono' => '+569',
            'telefono' => '12345678', 'direccion' => 'Calle 123', 'region' => 'Metropolitana', 'comuna' => 'Santiago',
            'tipo_servicio' => 'Mantención', 'descripcion_servicio' => 'Revisión preventiva del equipo',
        ])->assertRedirect(route('servicios.index'));

        return SolicitudWeb::where('tipo_solicitud', 'servicio')->latest('id_solicitud')->firstOrFail();
    }

    private function crearProducto(): int
    {
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Repuestos', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('productos')->insertGetId([
            'codigo_barra' => 'SERVICIO-001', 'nombre' => 'Filtro', 'precio' => 1190,
            'stock' => 5, 'stock_critico' => 1, 'id_categoria' => $categoria,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function datosCierre(array $cambios = []): array
    {
        return array_replace([
            'detalle_servicio' => 'Mantención preventiva',
            'precio_servicio' => '5000',
            'medio_pago' => 'Transferencia',
        ], $cambios);
    }
}
