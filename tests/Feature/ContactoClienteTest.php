<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContactoClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_changes_are_recorded_and_operations_keep_their_own_contact_and_cargo(): void
    {
        Notification::fake();
        $producto = $this->crearProducto();
        $datos = $this->datosCliente();

        $this->post(route('catalogo.solicitudes.store'), [
            ...$datos,
            'contacto' => 'Ana',
            'cargo' => 'OT-101',
            'detalles_productos' => [['id_producto' => $producto, 'cantidad' => 1]],
        ])->assertSessionHasNoErrors();

        $cliente = Cliente::where('rut', '12345678-5')->firstOrFail();
        $this->assertSame('Ana', $cliente->contacto);
        $this->assertDatabaseHas('solicitud_webs', [
            'id_cliente' => $cliente->id_cliente,
            'datos_cliente->contacto' => 'Ana',
        ]);
        $this->assertDatabaseHas('solicitud_webs', [
            'id_cliente' => $cliente->id_cliente,
            'contacto' => 'Ana',
            'cargo' => 'OT-101',
        ]);
        $this->assertDatabaseCount('cliente_contactos_historial', 0);

        $this->post(route('servicios.store'), [
            ...$datos,
            'contacto' => 'Beatriz',
            'cargo' => 'Equipo 12',
            'direccion' => 'Calle 123',
            'tipo_servicio' => 'Mantención',
            'descripcion_servicio' => 'Revisión preventiva del equipo.',
        ])->assertSessionHasNoErrors();

        $vendedor = User::factory()->create(['rol' => 'Vendedor']);
        $this->actingAs($vendedor)->postJson('/ventas', [
            'rut' => $datos['rut'],
            'nombre_cliente' => $datos['nombre'],
            'contacto' => 'Ana',
            'cargo' => 'Patente ABCD12',
            'giro_cliente' => 'Comercio',
            'correo_cliente' => 'cliente@example.test',
            'direccion_cliente' => 'Calle 123',
            'region_cliente' => 'Metropolitana',
            'comuna_cliente' => 'Santiago',
            'ciudad_cliente' => 'Santiago',
            'acteco_emisor' => '475201',
            'tipo_venta' => 'del_giro',
            'tipo_compra' => 'del_giro',
            'tipo_documento' => 'Boleta Electrónica',
            'medio_pago' => 'Efectivo',
            'detalles' => [['id_producto' => $producto, 'cantidad' => 1]],
        ])->assertCreated();

        $this->assertSame('Ana', $cliente->fresh()->contacto);
        $this->assertSame(['Ana', 'Beatriz'], DB::table('cliente_contactos_historial')->orderBy('id')->pluck('contacto')->all());
        $this->assertDatabaseHas('solicitud_webs', [
            'tipo_solicitud' => 'servicio',
            'contacto' => 'Beatriz',
            'cargo' => 'Equipo 12',
        ]);
        $this->assertDatabaseHas('ventas', ['contacto' => 'Ana', 'cargo' => 'Patente ABCD12']);

        $this->actingAs(User::factory()->create(['rol' => 'Administrador']));
        $this->put(route('clientes.update', $cliente->id_cliente), [
            'rut' => $datos['rut'],
            'nombre' => $datos['nombre'],
            'contacto' => 'Ana',
        ])->assertRedirect(route('clientes.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cliente_contactos_historial', 2);

        $this->getJson(route('pos.cliente.rut', ['rut' => $datos['rut']]))
            ->assertOk()->assertJsonPath('cliente.contacto', 'Ana');
        $this->getJson(route('catalogo.cliente.rut', ['rut' => $datos['rut']]))
            ->assertOk()->assertJsonPath('cliente.contacto', 'Ana');

        $this->put(route('clientes.update', $cliente->id_cliente), [
            'rut' => $datos['rut'],
            'nombre' => $datos['nombre'],
            'contacto' => 'Camila',
        ])->assertRedirect(route('clientes.index'))->assertSessionHasNoErrors();
        $this->assertSame(['Ana', 'Beatriz', 'Ana'], DB::table('cliente_contactos_historial')->orderBy('id')->pluck('contacto')->all());

        $this->get(route('clientes.historial', $cliente->id_cliente))
            ->assertOk()
            ->assertSee('Contacto actual:')
            ->assertSee('Camila')
            ->assertSee('Beatriz')
            ->assertDontSee('OT-101')
            ->assertDontSee('Patente ABCD12');
    }

    public function test_contact_is_required_and_cargo_is_optional_and_limited_in_each_flow(): void
    {
        $datos = $this->datosCliente();
        $producto = $this->crearProducto();

        $this->post(route('catalogo.solicitudes.store'), [
            ...$datos,
            'cargo' => str_repeat('a', 256),
            'detalles_productos' => [['id_producto' => $producto, 'cantidad' => 1]],
        ])->assertSessionHasErrors(['contacto', 'cargo']);

        $this->post(route('servicios.store'), [
            ...$datos,
            'tipo_servicio' => 'Mantención',
            'descripcion_servicio' => 'Revisión preventiva del equipo.',
        ])->assertSessionHasErrors('contacto');

        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']))
            ->postJson('/ventas', [
                'rut' => $datos['rut'],
                'nombre_cliente' => $datos['nombre'],
                'tipo_documento' => 'Boleta Electrónica',
                'medio_pago' => 'Efectivo',
                'detalles' => [['id_producto' => $producto, 'cantidad' => 1]],
            ])->assertUnprocessable()->assertJsonValidationErrors('contacto');

        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_failed_reservation_rolls_back_contact_change_and_history(): void
    {
        Notification::fake();
        $producto = $this->crearProducto();
        DB::table('productos')->where('id_producto', $producto)->update(['stock' => 1]);
        $solicitud = [
            ...$this->datosCliente(),
            'contacto' => 'Ana',
            'detalles_productos' => [['id_producto' => $producto, 'cantidad' => 1]],
        ];

        $this->post(route('catalogo.solicitudes.store'), $solicitud)->assertSessionHasNoErrors();
        $this->post(route('catalogo.solicitudes.store'), [...$solicitud, 'contacto' => 'Beatriz'])
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('clientes', ['rut' => '12345678-5', 'contacto' => 'Ana']);
        $this->assertDatabaseCount('cliente_contactos_historial', 0);
        $this->assertDatabaseCount('solicitud_webs', 1);
    }

    public function test_customer_changes_from_new_requests_are_audited_and_do_not_rewrite_old_snapshots(): void
    {
        Notification::fake();
        $producto = $this->crearProducto();
        $datos = $this->datosCliente();

        $this->post(route('catalogo.solicitudes.store'), [
            ...$datos,
            'contacto' => 'Ana',
            'detalles_productos' => [['id_producto' => $producto, 'cantidad' => 1]],
        ])->assertSessionHasNoErrors();

        $this->post(route('catalogo.solicitudes.store'), [
            ...$datos,
            'nombre' => 'Empresa actualizada',
            'correo' => 'nuevo@example.test',
            'telefono' => '87654321',
            'direccion' => 'Nueva dirección 123',
            'contacto' => 'Beatriz',
            'detalles_productos' => [['id_producto' => $producto, 'cantidad' => 1]],
        ])->assertSessionHasNoErrors();

        $cliente = Cliente::where('rut', '12345678-5')->firstOrFail();
        $solicitudes = DB::table('solicitud_webs')->orderBy('id_solicitud')->get();
        $snapshotAnterior = json_decode($solicitudes[0]->datos_cliente, true);
        $snapshotNuevo = json_decode($solicitudes[1]->datos_cliente, true);

        $this->assertSame('Empresa de prueba', $snapshotAnterior['nombre']);
        $this->assertSame('Ana', $snapshotAnterior['contacto']);
        $this->assertSame('Empresa actualizada', $snapshotNuevo['nombre']);
        $this->assertSame('Beatriz', $snapshotNuevo['contacto']);
        $this->assertSame('nuevo@example.test', $cliente->correo);
        $this->assertSame('Beatriz', $cliente->contacto);

        $auditoria = DB::table('auditorias')
            ->where('modelo', Cliente::class)
            ->where('modelo_id', $cliente->id_cliente)
            ->where('actor_nombre', 'Cliente')
            ->where('accion', 'editado')
            ->latest('id_auditoria')
            ->first();
        $this->assertNotNull($auditoria);
        $this->assertStringContainsString('solicitud de cotización web', $auditoria->descripcion);
        $this->assertSame('Empresa de prueba', json_decode($auditoria->datos_anteriores, true)['nombre']);
    }

    private function datosCliente(): array
    {
        return [
            'rut' => '12345678-5',
            'nombre' => 'Empresa de prueba',
            'correo' => 'cliente@example.test',
            'prefijo_telefono' => '+569',
            'telefono' => '12345678',
            'region' => 'Biobío',
            'comuna' => 'Los Ángeles',
        ];
    }

    private function crearProducto(): int
    {
        $categoria = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Pruebas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('productos')->insertGetId([
            'codigo_barra' => 'CONTACTO-001',
            'nombre' => 'Producto de prueba',
            'precio' => 1190,
            'stock' => 10,
            'stock_critico' => 1,
            'id_categoria' => $categoria,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
