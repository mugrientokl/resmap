<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\NuevaCotizacionPendiente;
use App\Notifications\ProductoStockCritico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_force_internal_urls(): void
    {
        foreach (['/productos', '/pos', '/categorias', '/clientes', '/solicitudes-web', '/usuarios'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_protected_pages_are_not_cached_and_logout_removes_access(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $response = $this->actingAs($admin)->get('/productos');

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->actingAs($admin)->post('/logout')->assertRedirect('/');
        $this->get('/productos')->assertRedirect('/login');
    }

    public function test_seller_cannot_create_or_delete_products_or_categories(): void
    {
        $seller = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($seller)->get('/productos/crear')->assertForbidden();
        $this->actingAs($seller)->post('/productos', [])->assertForbidden();
        $this->actingAs($seller)->get('/categorias/crear')->assertForbidden();
        $this->actingAs($seller)->post('/categorias', [])->assertForbidden();
    }

    public function test_seller_can_read_clients_but_not_reports_or_system_reports(): void
    {
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        Cliente::create([
            'rut' => '12.345.678-5',
            'nombre' => 'Cliente de prueba',
            'correo' => 'cliente@example.com',
        ]);

        $this->actingAs($seller)->get('/clientes?buscar=prueba')
            ->assertOk()
            ->assertSee('Cliente de prueba')
            ->assertDontSee('Editar cliente');
        $this->actingAs($seller)->get('/reportes')->assertForbidden();
        $this->actingAs($seller)->get('/auditoria')->assertForbidden();
        $this->actingAs($seller)->get('/backups')->assertForbidden();
        $this->actingAs($seller)->get('/usuarios')->assertForbidden();
    }

    public function test_administrator_can_view_sales_reports(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->get('/reportes')
            ->assertOk()
            ->assertSee('Reporte de ventas')
            ->assertSee('Aplicar filtros');
    }

    public function test_individual_permissions_control_reports_and_take_effect_on_the_next_request(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $first = User::factory()->create(['rol' => 'Vendedor']);
        $second = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($admin)->putJson(route('usuarios.permisos.update', $first), [
            'permisos' => ['reportes.ver'],
        ])->assertOk();

        $this->actingAs($first->fresh())->get(route('reportes.index'))->assertOk()
            ->assertSee('Reporte de ventas')
            ->assertSee('href="'.route('reportes.index').'"', false)
            ->assertDontSee('href="'.route('productos.index').'"', false);
        $this->get(route('productos.index'))->assertForbidden();
        $this->actingAs($second)->get(route('reportes.index'))->assertForbidden();
        $this->get(route('productos.index'))->assertOk();

        $this->actingAs($admin)->putJson(route('usuarios.permisos.update', $first), [])
            ->assertOk();
        $this->actingAs($first->fresh())->get(route('reportes.index'))->assertForbidden();
        $this->get(route('reportes.exportar', 'csv'))->assertForbidden();
        $this->get(route('perfil.show'))->assertOk()
            ->assertDontSee('href="'.route('reportes.index').'"', false);
        $this->actingAs($admin)->get(route('reportes.index'))->assertOk();
    }

    public function test_permissions_separate_view_and_write_and_are_validated(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $seller = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($admin)->putJson(route('usuarios.permisos.update', $seller), [
            'permisos' => ['productos.crear', 'clientes.ver'],
        ])->assertOk();
        $seller = $seller->fresh();
        $this->assertTrue($seller->tienePermiso('productos.ver'));
        $this->actingAs($seller)->get(route('productos.create'))->assertOk();
        $this->get(route('productos.index'))->assertSee('+ Nuevo Repuesto')->assertDontSee('Editar producto');
        $this->postJson('/productos', [])->assertRedirect()->assertSessionHasErrors('nombre');
        $this->putJson('/productos/1', [])->assertForbidden();
        $this->deleteJson('/productos/1')->assertForbidden();
        $this->postJson('/ventas', [])->assertForbidden();
        $this->get(route('clientes.index'))->assertOk();
        $this->putJson('/clientes/1', [])->assertForbidden();

        $this->actingAs($admin)->putJson(route('usuarios.permisos.update', $seller), [
            'permisos' => ['usuarios.editar'],
        ])->assertUnprocessable();
        $this->assertTrue($seller->fresh()->tienePermiso('productos.crear'));
        $this->actingAs($seller)->putJson(route('usuarios.permisos.update', $seller), [
            'permisos' => ['reportes.ver'],
        ])->assertForbidden();
        $this->actingAs($admin)->putJson(route('usuarios.permisos.update', $admin), [
            'permisos' => [],
        ])->assertForbidden();
    }

    public function test_seller_creation_saves_the_selected_permissions_without_accepting_unknown_ones(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $datos = [
            'username' => 'vendedor-permisos',
            'name' => 'Vendedor',
            'email' => 'vendedor-permisos@example.com',
            'rol' => 'Vendedor',
            'permisos_configurados' => true,
        ];

        $this->actingAs($admin)->postJson(route('usuarios.store'), [
            ...$datos, 'permisos' => ['reportes.ver', 'usuarios.editar'],
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['username' => $datos['username']]);

        $this->postJson(route('usuarios.store'), [...$datos, 'permisos' => ['reportes.ver']])->assertCreated();
        $seller = User::where('username', $datos['username'])->firstOrFail();
        $this->assertSame(['reportes.ver'], $seller->permisos);
        $this->actingAs($seller)->get(route('reportes.index'))->assertOk();
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_category_create_permission_does_not_grant_edit_or_delete(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $this->actingAs($admin)->putJson(route('usuarios.permisos.update', $seller), [
            'permisos' => ['categorias.crear'],
        ])->assertOk();

        $this->actingAs($seller->fresh())->post(route('categorias.store'), [
            'nombre_categoria' => 'Repuestos nuevos',
        ])->assertRedirect(route('categorias.index'));
        $categoryId = DB::table('categorias')->where('nombre_categoria', 'Repuestos nuevos')->value('id_categoria');
        $this->get(route('categorias.index'))->assertOk()
            ->assertSee('+ Nueva Categoría')->assertDontSee('title="Editar categoría"', false)
            ->assertDontSee('title="Eliminar categoría"', false);
        $this->put(route('categorias.update', $categoryId), ['nombre_categoria' => 'Cambio'])->assertForbidden();
        $this->delete(route('categorias.destroy', $categoryId))->assertForbidden();
        $this->assertDatabaseHas('categorias', ['id_categoria' => $categoryId, 'nombre_categoria' => 'Repuestos nuevos']);
    }

    public function test_seller_without_dashboard_permission_can_sign_in_to_their_profile(): void
    {
        $seller = User::factory()->create(['rol' => 'Vendedor', 'password' => 'contraseña-segura-123']);
        $seller->forceFill(['permisos' => []])->save();

        $this->post(route('login.store'), [
            'login' => $seller->username,
            'password' => 'contraseña-segura-123',
        ])->assertRedirect(route('perfil.show'));
        $this->get(route('perfil.show'))->assertOk()
            ->assertDontSee('href="'.route('dashboard').'"', false);
        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_revoked_modules_hide_existing_notifications(): void
    {
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        foreach ([
            ['type' => ProductoStockCritico::class, 'titulo' => 'Producto privado'],
            ['type' => NuevaCotizacionPendiente::class, 'titulo' => 'Cotización privada'],
        ] as $aviso) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => $aviso['type'],
                'notifiable_type' => User::class,
                'notifiable_id' => $seller->id,
                'data' => json_encode(['tipo' => 'solicitud', 'titulo' => $aviso['titulo'], 'mensaje' => 'Detalle']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $seller->forceFill(['permisos' => []])->save();
        $this->actingAs($seller->fresh())->get(route('notificaciones.index'))->assertOk()
            ->assertDontSee('Producto privado')->assertDontSee('Cotización privada');
    }

    public function test_administrator_can_create_a_seller_account(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $response = $this->actingAs($admin)->postJson('/usuarios', [
            'username' => 'nuevo-vendedor',
            'name' => 'Nuevo Vendedor',
            'email' => 'nuevo-vendedor@example.com',
            'rol' => 'Vendedor',
        ]);

        $response->assertCreated()
            ->assertJsonMissingPath('user.password');
        $vendedor = User::query()->where('username', 'nuevo-vendedor')->firstOrFail();
        $contrasena = $response->json('initial_password');

        $this->assertTrue($vendedor->activo);
        $this->assertIsString($contrasena);
        $this->assertSame(32, strlen($contrasena));
        $this->assertTrue(Hash::check($contrasena, $vendedor->password));
        $this->assertStringNotContainsString($contrasena, json_encode(DB::table('auditorias')->get()));
        $this->assertDatabaseHas('users', [
            'username' => 'nuevo-vendedor',
            'rol' => 'Vendedor',
        ]);

        auth()->logout();
        $this->post(route('login.store'), [
            'login' => $vendedor->username,
            'password' => $contrasena,
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($vendedor);
    }

    public function test_initial_password_is_shown_only_in_the_creation_response(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $response = $this->actingAs($admin)->post(route('usuarios.store'), [
            'username' => 'vendedor-nuevo',
            'name' => 'Nuevo Vendedor',
            'email' => 'vendedor-nuevo@example.com',
            'rol' => 'Vendedor',
        ]);

        $response->assertOk()->assertViewIs('usuarios.contrasena-inicial')
            ->assertSee('Copiar contraseña');
        $contrasena = $response->viewData('contrasenaInicial');
        $this->assertTrue(Hash::check($contrasena, User::where('username', 'vendedor-nuevo')->firstOrFail()->password));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString($contrasena, json_encode(session()->all()));
        $this->get(route('usuarios.index'))->assertOk()->assertDontSee($contrasena);
    }

    public function test_profile_password_change_requires_current_password_and_updates_login_credentials(): void
    {
        $seller = User::factory()->create(['rol' => 'Vendedor', 'password' => 'contraseña-anterior-segura']);

        $this->actingAs($seller)->from(route('perfil.show'))->put(route('perfil.contrasena.update'), [
            'current_password' => 'incorrecta',
            'password' => 'nueva-contraseña-segura',
            'password_confirmation' => 'nueva-contraseña-segura',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('contraseña-anterior-segura', $seller->fresh()->password));

        $this->put(route('perfil.contrasena.update'), [
            'current_password' => 'contraseña-anterior-segura',
            'password' => 'nueva-contraseña-segura',
            'password_confirmation' => 'no-coincide',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('contraseña-anterior-segura', $seller->fresh()->password));

        $this->put(route('perfil.contrasena.update'), [
            'current_password' => 'contraseña-anterior-segura',
            'password' => 'nueva-contraseña-segura',
            'password_confirmation' => 'nueva-contraseña-segura',
        ])->assertRedirect(route('perfil.show'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('nueva-contraseña-segura', $seller->fresh()->password));
        $this->assertFalse(Hash::check('contraseña-anterior-segura', $seller->fresh()->password));
    }

    public function test_administrator_can_disable_and_reactivate_seller_accounts(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $seller = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($admin)
            ->patchJson(route('usuarios.estado', $seller), ['activo' => false])
            ->assertOk()
            ->assertJsonPath('user.activo', false);

        $this->assertDatabaseHas('users', ['id' => $seller->id, 'activo' => false]);

        $this->actingAs($admin)
            ->patchJson(route('usuarios.estado', $seller), ['activo' => true])
            ->assertOk()
            ->assertJsonPath('user.activo', true);

        $this->assertDatabaseHas('users', ['id' => $seller->id, 'activo' => true]);
    }

    public function test_administrator_disabling_a_seller_revokes_their_sessions_and_password_reset_tokens(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        DB::table('sessions')->insert([
            'id' => 'seller-active-session',
            'user_id' => $seller->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
        Password::createToken($seller);

        $this->actingAs($admin)
            ->patchJson(route('usuarios.estado', $seller), ['activo' => false])
            ->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'seller-active-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $seller->email]);

        $this->actingAs($seller->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_only_administrators_can_change_seller_status_and_administrator_accounts_cannot_be_disabled(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $seller = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($seller)
            ->patchJson(route('usuarios.estado', $seller), ['activo' => false])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson(route('usuarios.estado', $admin), ['activo' => false])
            ->assertForbidden();
    }

    public function test_administrator_can_archive_and_restore_a_seller_without_losing_sales_history(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $seller = User::factory()->create([
            'rol' => 'Vendedor',
            'password' => 'Vendedor-Seguro-2026-Frase',
        ]);
        $sale = Venta::create([
            'fecha' => now(),
            'tipo_documento' => 'Boleta Electrónica',
            'neto' => 1000,
            'iva' => 190,
            'total' => 1190,
            'medio_pago' => 'Efectivo',
            'user_id' => $seller->id,
        ]);
        DB::table(config('session.table'))->insert([
            'id' => 'seller-archived-session',
            'user_id' => $seller->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
        Password::createToken($seller);

        $this->actingAs($admin)
            ->deleteJson(route('usuarios.destroy', $seller))
            ->assertOk()
            ->assertJsonPath('message', 'La cuenta fue archivada. Se conservaron las ventas y el historial de auditoría.');

        $this->assertSoftDeleted('users', ['id' => $seller->id]);
        $this->assertDatabaseHas('users', ['id' => $seller->id, 'activo' => false]);
        $this->assertDatabaseHas('ventas', ['id_venta' => $sale->id_venta, 'user_id' => $seller->id]);
        $this->assertDatabaseMissing(config('session.table'), ['id' => 'seller-archived-session']);
        $this->assertDatabaseMissing(config('auth.passwords.users.table'), ['email' => $seller->email]);

        $this->actingAs($admin)
            ->get(route('usuarios.index'))
            ->assertOk()
            ->assertDontSee($seller->username);

        $this->actingAs($admin)
            ->get(route('usuarios.index', ['archivados' => 1]))
            ->assertOk()
            ->assertSee($seller->username)
            ->assertSee('Archivada');

        auth()->logout();
        $this->post(route('login.store'), [
            'login' => $seller->username,
            'password' => 'Vendedor-Seguro-2026-Frase',
        ])->assertRedirect()->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->actingAs($admin)
            ->postJson(route('usuarios.restaurar', $seller->id))
            ->assertOk();

        $this->assertNotSoftDeleted('users', ['id' => $seller->id]);
        $this->assertDatabaseHas('users', ['id' => $seller->id, 'activo' => true]);
        $this->assertDatabaseHas('ventas', ['id_venta' => $sale->id_venta, 'user_id' => $seller->id]);
    }

    public function test_seller_cannot_archive_accounts_and_administrator_accounts_cannot_be_archived(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $seller = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($seller)
            ->deleteJson(route('usuarios.destroy', $seller))
            ->assertForbidden();

        $this->actingAs($admin)
            ->deleteJson(route('usuarios.destroy', $admin))
            ->assertForbidden();
    }
}
