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

    public function test_user_creation_generates_unique_username_and_stores_personal_data(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $datos = [
            'nombre' => 'juan',
            'apellido_paterno' => 'pÉREZ',
            'apellido_materno' => 'SOTO',
            'run' => '11.111.111-1',
            'correo' => 'juan@example.cl',
            'telefono' => '212345678',
            'rol' => 'Vendedor',
        ];

        $this->actingAs($admin)->post(route('usuarios.store'), $datos)
            ->assertStatus(200)
            ->assertSessionHasNoErrors()
            ->assertSee('initial-username', false)
            ->assertSee('Copiar credenciales', false);

        $this->assertDatabaseHas('users', [
            'username' => 'juan.soto',
            'name' => 'Juan Pérez Soto',
            'run' => '11111111-1',
            'email' => 'juan@example.cl',
            'telefono' => '+56212345678',
        ]);

        $datos['run'] = '12.345.678-5';
        $datos['correo'] = 'juan2@example.cl';
        $this->actingAs($admin)->post(route('usuarios.store'), $datos)
            ->assertStatus(200)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'juan.soto2']);

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

    public function test_permission_panels_start_collapsed_and_preserve_each_users_selections(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $seller->forceFill(['permisos' => ['reportes.ver']])->save();

        $response = $this->actingAs($admin)->get(route('usuarios.index'))->assertOk()
            ->assertSee('Permisos del vendedor')
            ->assertSee('Configurar permisos')
            ->assertSee('1 seleccionado');

        $content = $response->getContent();
        $this->assertMatchesRegularExpression('/<details data-permissions class="[^"]+"\\s*>/', $content);
        $this->assertStringContainsString('name="permisos[]" value="productos.ver" checked', $content);
        $this->assertStringContainsString('name="permisos[]" value="reportes.ver" checked', $content);
        foreach (array_keys(config('permisos.opciones')) as $permiso) {
            $this->assertSame(2, substr_count($content, 'name="permisos[]" value="'.$permiso.'"'));
        }
    }
}
