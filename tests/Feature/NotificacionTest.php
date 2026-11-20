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
        $enPapelera = $this->crearAviso($usuario, 'En papelera', deleted: true);
        $deOtroUsuario = $this->crearAviso($otroUsuario, 'Otro usuario');

        $this->actingAs($usuario)->post(route('notificaciones.read-all'))
            ->assertRedirect(route('notificaciones.index'));

        $this->assertSame(0, $usuario->unreadNotifications()->whereNull('deleted_at')->count());
        $this->assertDatabaseHas('notifications', ['id' => $enPapelera, 'read_at' => null]);
        $this->assertDatabaseHas('notifications', ['id' => $deOtroUsuario, 'read_at' => null]);
    }

    public function test_notifications_can_be_trashed_restored_and_permanently_deleted(): void
    {
        $usuario = User::factory()->create();
        $aviso = $this->crearAviso($usuario, 'Aviso de prueba');
        $this->actingAs($usuario);

        $this->get(route('notificaciones.index'))->assertOk()->assertSee('Aviso de prueba')->assertSee('Marcar todo como leído');
        $this->post(route('notificaciones.move-to-trash', $aviso))->assertRedirect();
        $this->assertNotNull(DB::table('notifications')->where('id', $aviso)->value('deleted_at'));
        $this->get(route('notificaciones.index'))->assertDontSee('Aviso de prueba')
            ->assertDontSee('Avisos <span class="ml-1', false);
        $this->get(route('notificaciones.trash'))->assertSee('Aviso de prueba')->assertSee('Restaurar')->assertSee('Eliminar definitivamente')
            ->assertSee('data-confirm-message="¿Eliminar este aviso definitivamente? Esta acción no se puede deshacer."', false)
            ->assertSee('data-confirm-title="Eliminar aviso"', false)
            ->assertDontSee('onsubmit="return confirm(', false);

        $this->post(route('notificaciones.restore', $aviso))->assertRedirect();
        $this->assertDatabaseHas('notifications', ['id' => $aviso, 'deleted_at' => null, 'read_at' => null]);
        $this->get(route('notificaciones.index'))->assertSee('Aviso de prueba')
            ->assertSee('Avisos <span class="ml-1', false);
        $this->get(route('notificaciones.trash'))->assertDontSee('Aviso de prueba');

        $this->post(route('notificaciones.move-to-trash', $aviso))->assertRedirect();
        $this->delete(route('notificaciones.destroy', $aviso))->assertRedirect();
        $this->assertDatabaseMissing('notifications', ['id' => $aviso]);
        $this->get(route('notificaciones.trash'))->assertSee('La papelera está vacía.');
    }

    public function test_moving_read_alerts_to_trash_only_affects_the_users_read_inbox_across_pages(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();
        $leidos = [];
        for ($i = 0; $i < 25; $i++) {
            $leidos[] = $this->crearAviso($usuario, 'Leído '.$i);
        }
        $sinLeer = $this->crearAviso($usuario, 'Sin leer');
        $enPapelera = $this->crearAviso($usuario, 'Ya en papelera', deleted: true);
        $ajeno = $this->crearAviso($otroUsuario, 'Leído de otro usuario');
        $leidoEn = now()->subDay();
        $eliminadoEn = now()->subHours(2);
        DB::table('notifications')->whereIn('id', [...$leidos, $enPapelera, $ajeno])->update(['read_at' => $leidoEn]);
        DB::table('notifications')->where('id', $enPapelera)->update(['deleted_at' => $eliminadoEn]);

        $this->actingAs($usuario)->get(route('notificaciones.index'))->assertSee('Mover leídos a la papelera');
        $this->post(route('notificaciones.move-read-to-trash'))->assertRedirect(route('notificaciones.index'));

        $this->assertSame(25, DB::table('notifications')->whereIn('id', $leidos)->whereNotNull('deleted_at')->count());
        $this->assertDatabaseHas('notifications', ['id' => $sinLeer, 'read_at' => null, 'deleted_at' => null]);
        $this->assertDatabaseHas('notifications', ['id' => $ajeno, 'deleted_at' => null]);
        $this->assertDatabaseHas('notifications', ['id' => $enPapelera, 'deleted_at' => $eliminadoEn->toDateTimeString()]);
        $this->get(route('notificaciones.trash'))->assertDontSee('Mover leídos a la papelera');
        $this->post(route('notificaciones.restore', $leidos[0]))->assertRedirect();
        $this->assertDatabaseHas('notifications', ['id' => $leidos[0], 'read_at' => $leidoEn->toDateTimeString(), 'deleted_at' => null]);
    }

    public function test_actions_are_scoped_to_the_owner_and_correct_folder(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();
        $activo = $this->crearAviso($usuario, 'Activo');
        $enPapelera = $this->crearAviso($usuario, 'En papelera', deleted: true);
        $ajeno = $this->crearAviso($otroUsuario, 'Ajeno');
        $ajenoEnPapelera = $this->crearAviso($otroUsuario, 'Ajeno en papelera', deleted: true);
        $this->actingAs($usuario);

        $this->post(route('notificaciones.read', $ajeno))->assertNotFound();
        $this->post(route('notificaciones.move-to-trash', $ajeno))->assertNotFound();
        $this->post(route('notificaciones.restore', $ajenoEnPapelera))->assertNotFound();
        $this->delete(route('notificaciones.destroy', $ajenoEnPapelera))->assertNotFound();
        $this->delete(route('notificaciones.destroy', $activo))->assertNotFound();
        $this->post(route('notificaciones.restore', $activo))->assertNotFound();
        $this->post(route('notificaciones.read', $enPapelera))->assertNotFound();
        $this->post(route('notificaciones.move-to-trash', $enPapelera))->assertNotFound();
        $this->assertDatabaseHas('notifications', ['id' => $ajeno, 'read_at' => null, 'deleted_at' => null]);
        $this->assertDatabaseHas('notifications', ['id' => $ajenoEnPapelera]);
    }

    public function test_restoring_all_only_restores_the_users_trashed_alerts_across_pages(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();
        $enPapelera = [];
        for ($i = 0; $i < 25; $i++) {
            $enPapelera[] = $this->crearAviso($usuario, 'Aviso '.$i, deleted: true);
        }
        $leidoEn = now()->subDay()->toDateTimeString();
        DB::table('notifications')->where('id', $enPapelera[0])->update(['read_at' => $leidoEn]);
        $activo = $this->crearAviso($usuario, 'Activo');
        $ajeno = $this->crearAviso($otroUsuario, 'Ajeno', deleted: true);
        $fechaAjena = DB::table('notifications')->where('id', $ajeno)->value('deleted_at');

        $this->post(route('notificaciones.restore-all'))->assertRedirect(route('login'));
        $this->actingAs($usuario)->get(route('notificaciones.index'))->assertDontSee('Restaurar todos');
        $this->get(route('notificaciones.trash'))->assertSee('Restaurar todos');
        $this->post(route('notificaciones.restore-all'))->assertRedirect(route('notificaciones.trash'));

        $this->assertSame(25, DB::table('notifications')->whereIn('id', $enPapelera)->whereNull('deleted_at')->count());
        $this->assertDatabaseHas('notifications', ['id' => $enPapelera[0], 'deleted_at' => null, 'read_at' => $leidoEn]);
        $this->assertDatabaseHas('notifications', ['id' => $enPapelera[1], 'deleted_at' => null, 'read_at' => null]);
        $this->assertDatabaseHas('notifications', ['id' => $activo, 'deleted_at' => null, 'read_at' => null]);
        $this->assertDatabaseHas('notifications', ['id' => $ajeno, 'deleted_at' => $fechaAjena]);
        $this->get(route('notificaciones.trash'))->assertSee('La papelera está vacía.');
    }

    public function test_read_status_is_preserved_when_restoring_an_alert(): void
    {
        $usuario = User::factory()->create();
        $aviso = $this->crearAviso($usuario, 'Aviso leído');
        $this->actingAs($usuario)->post(route('notificaciones.read', $aviso))->assertRedirect();
        $leidoEn = DB::table('notifications')->where('id', $aviso)->value('read_at');
        $this->assertNotNull($leidoEn);

        $this->post(route('notificaciones.move-to-trash', $aviso))->assertRedirect();
        $this->post(route('notificaciones.restore', $aviso))->assertRedirect();
        $this->assertDatabaseHas('notifications', ['id' => $aviso, 'read_at' => $leidoEn, 'deleted_at' => null]);
    }

    public function test_emptying_trash_only_deletes_the_users_trashed_alerts_across_pages(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();
        $enPapelera = [];
        for ($i = 0; $i < 25; $i++) {
            $enPapelera[] = $this->crearAviso($usuario, 'Aviso '.$i, deleted: true);
        }
        DB::table('notifications')->where('id', $enPapelera[0])->update(['read_at' => now()]);
        $activo = $this->crearAviso($usuario, 'Activo');
        $ajeno = $this->crearAviso($otroUsuario, 'Ajeno', deleted: true);

        $this->delete(route('notificaciones.destroy-all'))->assertRedirect(route('login'));
        $this->actingAs($usuario)->get(route('notificaciones.index'))->assertDontSee('Eliminar todos definitivamente');
        $this->get(route('notificaciones.trash'))
            ->assertSee('Eliminar todos definitivamente')
            ->assertSee('data-confirm-message="¿Eliminar definitivamente todos los avisos de la papelera? Esta acción no se puede deshacer."', false)
            ->assertSee('action="'.route('notificaciones.destroy-all').'"', false);
        $this->delete(route('notificaciones.destroy-all'))
            ->assertRedirect(route('notificaciones.trash'))
            ->assertSessionHas('success', 'Los avisos de la papelera se eliminaron definitivamente.');

        $this->assertSame(0, DB::table('notifications')->whereIn('id', $enPapelera)->count());
        $this->assertDatabaseHas('notifications', ['id' => $activo, 'deleted_at' => null]);
        $this->assertDatabaseHas('notifications', ['id' => $ajeno]);
        $this->get(route('notificaciones.trash'))->assertSee('La papelera está vacía.');
    }

    public function test_a_trashed_unread_stock_alert_is_not_immediately_recreated(): void
    {
        $usuario = User::factory()->create();
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Pruebas', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('productos')->insert([
            'codigo_barra' => 'TEST-STOCK',
            'nombre' => 'Producto crítico',
            'precio' => 100,
            'stock' => 0,
            'stock_critico' => 1,
            'id_categoria' => $categoria,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($usuario)->get(route('notificaciones.index'))->assertSee('Producto crítico');
        $aviso = $usuario->notifications()->firstOrFail();

        $this->post(route('notificaciones.move-to-trash', $aviso->id))->assertRedirect();
        $this->get(route('notificaciones.index'))->assertDontSee('Producto crítico');
        $this->assertSame(1, $usuario->notifications()->count());
    }

    public function test_reading_stock_alerts_does_not_create_new_unread_alerts_on_the_next_visit(): void
    {
        $usuario = User::factory()->create();
        $otroUsuario = User::factory()->create();
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Pruebas', 'created_at' => now(), 'updated_at' => now()]);
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

        $this->post(route('notificaciones.move-read-to-trash'))->assertRedirect();
        $this->get(route('notificaciones.index'))->assertDontSee('Producto crítico');
        $this->assertSame(1, $usuario->notifications()->count());
        $this->actingAs($otroUsuario)->get(route('notificaciones.index'))->assertSee('Producto crítico')->assertSee('No leído');
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
