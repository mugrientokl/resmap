<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_all_as_read_only_affects_the_users_inbox_across_pages(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();
        for ($i = 0; $i < 25; $i++) {
            $this->crearAviso($usuario, 'Aviso '.$i);
        }
        $oculto = $this->crearAviso($usuario, 'Oculto', deleted: true);
        $deOtroUsuario = $this->crearAviso($otroUsuario, 'Otro usuario');

        $this->actingAs($usuario)->post(route('notificaciones.read-all'))
            ->assertRedirect(route('notificaciones.index'));

        $this->assertSame(0, $usuario->unreadNotifications()->whereNull('deleted_at')->count());
        $this->assertDatabaseHas('notifications', ['id' => $oculto, 'read_at' => null]);
        $this->assertDatabaseHas('notifications', ['id' => $deOtroUsuario, 'read_at' => null]);
    }

    public function test_marking_one_notification_as_read_is_scoped_to_the_owner(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();
        $propio = $this->crearAviso($usuario, 'Propio');
        $ajeno = $this->crearAviso($otroUsuario, 'Ajeno');

        $this->actingAs($usuario)
            ->post(route('notificaciones.read', $propio))
            ->assertRedirect();
        $this->post(route('notificaciones.read', $ajeno))->assertNotFound();

        $this->assertNotNull(DB::table('notifications')->where('id', $propio)->value('read_at'));
        $this->assertDatabaseHas('notifications', ['id' => $ajeno, 'read_at' => null]);
    }

    public function test_reading_stock_alerts_does_not_create_new_unread_alerts_on_the_next_visit(): void
    {
        $usuario = User::factory()->create();
        $categoria = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Pruebas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('productos')->insert([
            'codigo_barra' => 'STOCK-LEIDO',
            'nombre' => 'Producto crítico',
            'precio' => 100,
            'stock' => 0,
            'stock_critico' => 1,
            'id_categoria' => $categoria,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($usuario)->get(route('notificaciones.index'))->assertSee('No leído');
        $this->post(route('notificaciones.read-all'))->assertRedirect(route('notificaciones.index'));
        $this->get(route('notificaciones.index'))->assertSee('Leído')->assertDontSee('No leído');

        $this->assertSame(1, $usuario->notifications()->count());
        $this->assertSame(0, $usuario->unreadNotifications()->count());
    }

    private function crearAviso(User $usuario, string $titulo, bool $deleted = false): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'Test',
            'notifiable_type' => User::class,
            'notifiable_id' => $usuario->id,
            'data' => json_encode(['tipo' => 'cotizacion', 'titulo' => $titulo, 'mensaje' => 'Mensaje de prueba']),
            'read_at' => null,
            'deleted_at' => $deleted ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
