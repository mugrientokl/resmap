<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_administrator_can_create_a_seller_account(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $response = $this->actingAs($admin)->postJson('/usuarios', [
            'username' => 'nuevo-vendedor',
            'name' => 'Nuevo Vendedor',
            'email' => 'nuevo-vendedor@example.com',
            'password' => 'password123',
            'rol' => 'Vendedor',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'username' => 'nuevo-vendedor',
            'rol' => 'Vendedor',
        ]);
    }
}
