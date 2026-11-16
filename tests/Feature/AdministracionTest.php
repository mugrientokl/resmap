<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_access_the_panel_and_change_navigation(): void
    {
        $this->get(route('administracion.index'))->assertRedirect(route('login'));
        $this->put(route('administracion.navegacion'), ['posicion_navegacion' => 'izquierda'])->assertRedirect(route('login'));

        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $this->actingAs($seller)->get(route('administracion.index'))->assertForbidden();
        $this->actingAs($seller)->put(route('administracion.navegacion'), ['posicion_navegacion' => 'izquierda'])->assertForbidden();
        $this->assertDatabaseMissing('configuraciones', ['clave' => 'posicion_navegacion']);
    }

    public function test_administration_panel_links_to_existing_management_pages(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $response = $this->actingAs($admin)->get(route('administracion.index'))->assertOk();
        foreach (['categorias.index', 'proveedores.index', 'usuarios.index', 'backups.index'] as $route) {
            $response->assertSee(route($route));
        }
        $response->assertSee('value="superior" checked', false);
        $response->assertSeeInOrder(['id="main-menu"', route('administracion.index'), 'id="profile-menu-button"'], false);
        $response->assertDontSee('id="public-navigation-position"', false);
    }

    public function test_user_management_displays_each_flash_notification_only_once(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $response = $this->actingAs($admin)->withSession([
            'success' => 'Cuenta creada correctamente.',
            'warning' => 'Revisa el correo enviado.',
        ])->get(route('usuarios.index'))->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), 'Cuenta creada correctamente.'));
        $this->assertSame(1, substr_count($response->getContent(), 'Revisa el correo enviado.'));
    }

    public function test_navigation_setting_persists_and_applies_to_other_authenticated_users(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $seller = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($admin)->put(route('administracion.navegacion'), [
            'posicion_navegacion' => 'izquierda',
        ])->assertRedirect(route('administracion.index'));

        $this->assertDatabaseHas('configuraciones', ['clave' => 'posicion_navegacion', 'valor' => 'izquierda']);
        $this->actingAs($admin)->get(route('administracion.index'))->assertOk()->assertSee('value="izquierda" checked', false);
        $this->actingAs($seller)->get(route('dashboard'))->assertOk()
            ->assertSee('antialiased text-[#241817]  navegacion-izquierda', false)
            ->assertDontSee(route('administracion.index'));

        $this->actingAs($admin)->put(route('administracion.navegacion'), [
            'posicion_navegacion' => 'superior',
        ])->assertRedirect(route('administracion.index'));

        $this->assertDatabaseHas('configuraciones', ['clave' => 'posicion_navegacion', 'valor' => 'superior']);
        $this->actingAs($seller)->get(route('dashboard'))->assertOk()
            ->assertDontSee('antialiased text-[#241817]  navegacion-izquierda', false);
    }

    public function test_navigation_marks_the_current_section_including_administration_pages(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        foreach ([
            'dashboard' => 'dashboard',
            'categorias.index' => 'administracion.index',
        ] as $page => $navigationRoute) {
            $response = $this->actingAs($admin)->get(route($page))->assertOk();
            $this->assertMatchesRegularExpression(
                '/<a href="'.preg_quote(route($navigationRoute), '/').'"[^>]*aria-current="page"/',
                $response->getContent(),
            );
        }
    }

    public function test_staff_navigation_omits_public_catalog_and_services_links(): void
    {
        foreach (['Administrador', 'Vendedor'] as $role) {
            $user = User::factory()->create(['rol' => $role]);

            $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertDontSee('href="'.route('catalogo.index').'"', false)
                ->assertDontSee('href="'.route('servicios.index').'"', false)
                ->assertSee(route('solicitudes.index'));
        }
    }

    public function test_invalid_navigation_setting_is_rejected_without_changing_the_previous_value(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $this->actingAs($admin)->put(route('administracion.navegacion'), ['posicion_navegacion' => 'izquierda']);

        $this->actingAs($admin)->from(route('administracion.index'))
            ->put(route('administracion.navegacion'), ['posicion_navegacion' => 'otra'])
            ->assertRedirect(route('administracion.index'))
            ->assertSessionHasErrors('posicion_navegacion');

        $this->assertDatabaseHas('configuraciones', ['clave' => 'posicion_navegacion', 'valor' => 'izquierda']);
    }
}
