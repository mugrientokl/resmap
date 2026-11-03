<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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

    public function test_administrator_can_create_a_seller_account(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $response = $this->actingAs($admin)->postJson('/usuarios', [
            'username' => 'nuevo-vendedor',
            'name' => 'Nuevo Vendedor',
            'email' => 'nuevo-vendedor@example.com',
            'rol' => 'Vendedor',
        ]);

        $response->assertCreated()
            ->assertJsonPath('password_setup_link_sent', true)
            ->assertJsonMissingPath('user.password');
        $vendedor = User::query()->where('username', 'nuevo-vendedor')->firstOrFail();

        $this->assertTrue($vendedor->activo);
        $this->assertNotSame('Vendedor-Seguro-2026-Frase', $vendedor->password);
        $this->assertFalse(Hash::check('Vendedor-Seguro-2026-Frase', $vendedor->password));
        Notification::assertSentTo($vendedor, ResetPasswordNotification::class);
        $this->assertDatabaseHas('users', [
            'username' => 'nuevo-vendedor',
            'rol' => 'Vendedor',
        ]);
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
