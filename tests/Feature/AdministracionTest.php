<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdministracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_navigation_is_lateral_and_respects_existing_permissions(): void
    {
        DB::table('configuraciones')->insert(['clave' => 'posicion_navegacion', 'valor' => 'superior']);

        foreach (['Administrador', 'Vendedor'] as $role) {
            $user = User::factory()->create(['rol' => $role]);
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertSee('navegacion-izquierda')
                ->assertDontSee('navegacion-superior public-navigation')
                ->assertDontSee('href="'.url('/administracion').'"', false)
                ->assertDontSee('href="'.route('catalogo.index').'"', false)
                ->assertDontSee('href="'.route('servicios.index').'"', false);

            $content = $response->getContent();
            $navigationLinks = explode('</nav>', explode('id="main-menu"', $content, 2)[1], 2)[0];
            $profileMenu = explode('</div>', explode('id="profile-menu"', $content, 2)[1], 2)[0];
            $response->assertSeeInOrder(['id="profile-menu-button"', 'href="'.route('dashboard').'"'], false);
            $this->assertStringContainsString(route('perfil.show'), $profileMenu);
            $this->assertStringContainsString(route('logout'), $profileMenu);

            foreach (['notificaciones.index', 'reportes.index', 'inventario.movimientos', 'actividad.index'] as $route) {
                $this->assertStringNotContainsString(route($route), $profileMenu);
                if ($route === 'notificaciones.index' || $role === 'Administrador') {
                    $this->assertStringContainsString('href="'.route($route).'"', $navigationLinks);
                } else {
                    $response->assertDontSee('href="'.route($route).'"', false);
                }
            }

            foreach (['categorias.index', 'proveedores.index', 'usuarios.index', 'backups.index'] as $route) {
                if ($role === 'Administrador') {
                    $this->assertStringContainsString('href="'.route($route).'"', $navigationLinks);
                } else {
                    $response->assertDontSee('href="'.route($route).'"', false);
                }
            }
        }
    }

    public function test_navigation_marks_the_current_management_section(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        foreach (['dashboard', 'categorias.index', 'proveedores.index', 'usuarios.index', 'backups.index', 'notificaciones.index'] as $page) {
            $response = $this->actingAs($admin)->get(route($page))->assertOk();
            $this->assertMatchesRegularExpression(
                '/<a href="'.preg_quote(route($page), '/').'"[^>]*aria-current="page"/',
                $response->getContent(),
            );
        }
    }

    public function test_navigation_configuration_is_no_longer_available(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)->get('/administracion')->assertNotFound();
        $this->actingAs($admin)->put('/administracion/navegacion', ['posicion_navegacion' => 'superior'])->assertNotFound();
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
}
