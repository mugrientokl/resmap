<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Rules\CorreoConDominio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
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
        $this->assertDatabaseCount('cliente_contactos_historial', 0);
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
        $this->assertDatabaseCount('cliente_contactos_historial', 0);

        $this->getJson(route('pos.cliente.rut', ['rut' => $datos['rut']]))
            ->assertOk()->assertJsonPath('cliente.contacto', 'Ana');
        $this->getJson('/catalogo/cliente-por-rut?rut='.$datos['rut'])->assertNotFound();

        $this->put(route('clientes.update', $cliente->id_cliente), [
            'rut' => $datos['rut'],
            'nombre' => $datos['nombre'],
            'contacto' => 'Camila',
        ])->assertRedirect(route('clientes.index'))->assertSessionHasNoErrors();
        $this->assertSame(['Ana'], DB::table('cliente_contactos_historial')->orderBy('id')->pluck('contacto')->all());

        $this->get(route('clientes.historial', $cliente->id_cliente))
            ->assertOk()
            ->assertSee('Contacto actual:')
            ->assertSee('Camila')
            ->assertDontSee('Beatriz')
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

    public function test_customer_edit_preserves_chilean_numbers_and_rejects_foreign_numbers(): void
    {
        $cliente = Cliente::create([
            'rut' => '12345678-5',
            'nombre' => 'Empresa',
            'telefono' => '+56223456789',
        ]);
        $this->actingAs(User::factory()->create(['rol' => 'Administrador']));

        $this->put(route('clientes.update', $cliente->id_cliente), [
            'rut' => $cliente->rut, 'nombre' => $cliente->nombre, 'telefono' => '223456789',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('clientes', ['id_cliente' => $cliente->id_cliente, 'telefono' => '+56223456789']);

        $this->put(route('clientes.update', $cliente->id_cliente), [
            'rut' => $cliente->rut, 'nombre' => $cliente->nombre, 'telefono' => '+541123456789',
        ])->assertSessionHasErrors('telefono');
        $this->assertDatabaseHas('clientes', ['id_cliente' => $cliente->id_cliente, 'telefono' => '+56223456789']);
    }

    public function test_sale_normalizes_chilean_phone_and_rejects_duplicated_prefix(): void
    {
        $producto = $this->crearProducto();
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']));
        $venta = [
            'rut' => '12345678-5', 'nombre_cliente' => 'Empresa', 'contacto' => 'Ana',
            'correo_cliente' => 'cliente@example.test', 'direccion_cliente' => 'Calle 123',
            'region_cliente' => 'Metropolitana', 'comuna_cliente' => 'Santiago', 'ciudad_cliente' => 'Santiago',
            'acteco_emisor' => '475201', 'tipo_venta' => 'del_giro', 'tipo_compra' => 'del_giro',
            'tipo_documento' => 'Boleta Electrónica', 'medio_pago' => 'Efectivo',
            'detalles' => [['id_producto' => $producto, 'cantidad' => 1]],
        ];

        $this->postJson('/ventas', [...$venta, 'telefono_cliente' => '+5656912345678'])
            ->assertUnprocessable()->assertJsonValidationErrors('telefono_cliente');
        $this->assertDatabaseCount('ventas', 0);

        $this->postJson('/ventas', [...$venta, 'telefono_cliente' => '912345678'])->assertCreated();
        $this->assertDatabaseHas('clientes', ['rut' => '12345678-5', 'telefono' => '+56912345678']);
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

    public function test_public_requests_keep_their_snapshot_without_rewriting_master_customer_data(): void
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
        $this->assertSame('cliente@example.test', $cliente->correo);
        $this->assertSame('Ana', $cliente->contacto);
    }

    public function test_quote_detail_displays_all_customer_data_from_the_request_snapshot(): void
    {
        Notification::fake();
        $producto = $this->crearProducto();

        $this->post(route('catalogo.solicitudes.store'), [
            ...$this->datosCliente(),
            'tipo_cliente' => 'Empresa',
            'giro' => 'Repuestos industriales',
            'contacto' => 'Ana solicitud',
            'cargo' => 'Compras solicitud',
            'direccion' => 'Calle solicitud 123',
            'detalles_productos' => [['id_producto' => $producto, 'cantidad' => 1]],
        ])->assertSessionHasNoErrors();

        $solicitud = SolicitudWeb::firstOrFail();
        $solicitud->cliente->update(['nombre' => 'Nombre actualizado', 'comuna' => 'Comuna actualizada']);

        $this->actingAs(User::factory()->create(['rol' => 'Administrador']))
            ->get(route('solicitudes.show', $solicitud->id_solicitud))
            ->assertOk()
            ->assertSeeInOrder(['Tipo de cliente', 'Empresa', 'Razón social', 'Empresa de prueba', 'Giro', 'Repuestos industriales'])
            ->assertSee('12.345.678-5')
            ->assertSee('Ana solicitud')
            ->assertSee('Compras solicitud')
            ->assertSee('cliente@example.test')
            ->assertSee('+56912345678')
            ->assertSee('Calle solicitud 123')
            ->assertSee('Biobío')
            ->assertSee('Los Ángeles')
            ->assertDontSee('Método de entrega')
            ->assertDontSee('Comuna actualizada');
    }

    public function test_email_requires_a_complete_domain_without_restricting_common_extensions(): void
    {
        foreach (['cliente@empresa', 'cliente@empresa.c', 'cliente@empresa..cl', 'cliente@empresa.'] as $correo) {
            $this->assertTrue(Validator::make(['correo' => $correo], [
                'correo' => ['required', 'email', new CorreoConDominio],
            ])->fails(), $correo);
        }

        foreach (['cliente@empresa.cl', 'cliente@empresa.com', 'cliente@empresa.co.uk', 'cliente@empresa.technology'] as $correo) {
            $this->assertFalse(Validator::make(['correo' => $correo], [
                'correo' => ['required', 'email', new CorreoConDominio],
            ])->fails(), $correo);
        }
    }

    public function test_customer_facing_and_internal_forms_reject_emails_without_domain_suffix(): void
    {
        $correo = 'cliente@empresa';
        $producto = $this->crearProducto();
        $cliente = Cliente::create(['rut' => '12345678-5', 'nombre' => 'Cliente existente']);
        $datos = [...$this->datosCliente(), 'contacto' => 'Ana', 'correo' => $correo];

        $this->postJson(route('catalogo.solicitudes.store'), [
            ...$datos, 'detalles_productos' => [['id_producto' => $producto, 'cantidad' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('correo');

        $this->postJson(route('servicios.store'), [
            ...$datos, 'direccion' => 'Calle 123', 'tipo_servicio' => 'Mantención',
            'descripcion_servicio' => 'Revisión preventiva del equipo.',
        ])->assertUnprocessable()->assertJsonValidationErrors('correo');

        $this->postJson(route('password.email'), ['email' => $correo])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson(route('password.update'), [
            'email' => $correo, 'token' => 'token', 'password' => 'Una-clave-segura-123',
            'password_confirmation' => 'Una-clave-segura-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->actingAs(User::factory()->create(['rol' => 'Administrador']));
        $this->putJson(route('clientes.update', $cliente->id_cliente), [
            'rut' => $cliente->rut, 'nombre' => $cliente->nombre, 'correo' => $correo,
        ])->assertUnprocessable()->assertJsonValidationErrors('correo');
        $this->postJson(route('proveedores.store'), [
            'nombre' => 'Proveedor', 'contacto' => 'Ana', 'telefono' => '912345678', 'correo' => $correo,
        ])->assertUnprocessable()->assertJsonValidationErrors('correo');
        $this->postJson(route('usuarios.store'), [
            'username' => 'usuario-prueba', 'name' => 'Usuario', 'email' => $correo, 'rol' => 'Vendedor',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/ventas', [
            'rut' => '12345678-5', 'nombre_cliente' => 'Cliente', 'contacto' => 'Ana',
            'correo_cliente' => $correo, 'tipo_documento' => 'Boleta Electrónica', 'medio_pago' => 'Efectivo',
            'acteco_emisor' => '475201', 'tipo_venta' => 'del_giro', 'tipo_compra' => 'del_giro',
            'detalles' => [['id_producto' => $producto, 'cantidad' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('correo_cliente');

        $this->assertDatabaseCount('solicitud_webs', 0);
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_old_quote_without_a_snapshot_shows_current_customer_data_with_a_notice(): void
    {
        $cliente = Cliente::create([
            'rut' => '12345678-5',
            'nombre' => 'Cliente actual',
            'contacto' => 'Contacto actual',
            'correo' => 'cliente@example.test',
            'region' => 'Biobío',
            'comuna' => 'Los Ángeles',
        ]);
        $solicitud = SolicitudWeb::create([
            'fecha' => now(),
            'estado' => 'Pendiente',
            'id_cliente' => $cliente->id_cliente,
            'detalles_productos' => [],
            'direccion_referencias' => 'Portería',
        ]);

        $this->actingAs(User::factory()->create(['rol' => 'Administrador']))
            ->get(route('solicitudes.show', $solicitud->id_solicitud))
            ->assertOk()
            ->assertSee('se muestran sus datos actuales')
            ->assertSee('Cliente actual')
            ->assertSee('Contacto actual')
            ->assertSee('cliente@example.test')
            ->assertSee('Biobío')
            ->assertSee('Los Ángeles')
            ->assertSee('Portería');
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
