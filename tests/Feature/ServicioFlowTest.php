<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\SolicitudWebRecibida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class ServicioFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_request_notifies_staff_only_by_email(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $vendedor = User::factory()->create(['rol' => 'Vendedor']);
        $this->solicitarServicio();

        foreach ([$admin, $vendedor] as $usuario) {
            Notification::assertSentTo($usuario, SolicitudWebRecibida::class, fn ($notificacion, $canales): bool => $canales === ['mail']
                && $notificacion->solicitud->tipo_solicitud === 'servicio');
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_mail_transport_failure_keeps_request_and_warns_customer(): void
    {
        User::factory()->create(['rol' => 'Administrador']);
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('Fallo de prueba'));
        $this->post(route('servicios.store'), [
            'rut' => '12345678-5', 'nombre' => 'Empresa', 'contacto' => 'Ana',
            'prefijo_telefono' => '+569', 'telefono' => '12345678',
            'region' => 'Metropolitana', 'comuna' => 'Santiago',
            'tipo_servicio' => 'Mantención', 'descripcion_servicio' => 'Revisión preventiva del equipo',
        ])->assertRedirect(route('servicios.index'))->assertSessionHas('warning');
        $this->assertDatabaseHas('solicitud_webs', ['tipo_solicitud' => 'servicio', 'estado' => 'Pendiente']);
    }

    public function test_service_without_staff_recipients_is_saved_with_a_warning(): void
    {
        Notification::fake();
        $solicitud = $this->solicitarServicio();
        $this->assertNotNull($solicitud->id_solicitud);
        $this->assertNotNull(session('warning'));
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
            'acteco_emisor' => array_key_first(config('services.cotizaciones.actividades_emisor_prueba')),
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
            'telefono' => '12345678', 'region' => 'Metropolitana', 'comuna' => 'Santiago',
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
