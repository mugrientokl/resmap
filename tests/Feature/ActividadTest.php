<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Categoria;
use App\Models\User;
use App\Services\RegistroActividad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class ActividadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['rol' => 'Administrador']);
    }

    public function test_only_administrators_can_see_the_activity_log(): void
    {
        $this->get('/actividad')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']))->get('/actividad')->assertForbidden();
        $this->actingAs($this->admin())->get('/actividad')->assertOk()->assertSee('Registro de Actividad');
    }

    public function test_menu_shows_the_activity_link_to_administrators(): void
    {
        $this->actingAs($this->admin())->get('/productos')->assertSee(route('actividad.index'), false);
    }

    public function test_legacy_audit_urls_redirect_to_the_new_module(): void
    {
        $this->actingAs($this->admin())->get('/auditoria')->assertRedirect(route('actividad.index'));
        $this->get('/reportes/auditoria/exportar/csv')->assertRedirect('/actividad');
    }

    public function test_model_changes_are_logged_with_only_the_changed_fields(): void
    {
        $this->actingAs($this->admin());
        $categoria = Categoria::create(['nombre_categoria' => 'Tornillos', 'descripcion' => 'A']);
        $categoria->update(['descripcion' => 'B']);

        $edicion = Auditoria::where('accion', 'editado')->firstOrFail();

        $this->assertSame(['descripcion' => 'A'], $edicion->datos_anteriores);
        $this->assertSame(['descripcion' => 'B'], $edicion->datos_nuevos);
        $this->assertNotNull($edicion->hash);
        $this->assertSame(1, Auditoria::where('accion', 'creado')->where('modelo', Categoria::class)->count());
    }

    public function test_passwords_are_never_stored_and_tokens_do_not_create_noise(): void
    {
        $usuario = User::factory()->create(['rol' => 'Vendedor']);
        $usuario->update(['password' => 'nueva-clave-segura-123']);
        $usuario->forceFill(['remember_token' => 'abc'])->save();

        $this->assertSame(1, Auditoria::where('accion', 'editado')->count());
        $this->assertStringNotContainsString('nueva-clave', json_encode(Auditoria::all()->toArray()));
    }

    public function test_bulk_operations_are_recorded_as_a_single_event(): void
    {
        app(RegistroActividad::class)->enLote('Importación de prueba', function (): array {
            foreach (range(1, 10) as $i) {
                Categoria::create(['nombre_categoria' => "Cat {$i}"]);
            }

            return ['filas' => 10];
        });

        $this->assertSame(0, Auditoria::where('modelo', Categoria::class)->count());
        $evento = Auditoria::where('categoria', 'importacion')->sole();
        $this->assertNotNull($evento->lote_id);
        $this->assertSame(10, $evento->datos_nuevos['filas']);
    }

    public function test_authentication_events_are_logged(): void
    {
        $usuario = User::factory()->create(['rol' => 'Administrador', 'password' => 'clave-muy-segura-123']);

        $this->post('/login', ['login' => $usuario->email, 'password' => 'incorrecta'])->assertSessionHasErrors();
        $this->post('/login', ['login' => $usuario->email, 'password' => 'clave-muy-segura-123']);
        $this->post('/logout');

        $this->assertDatabaseHas('auditorias', ['accion' => 'inicio_sesion_fallido', 'resultado' => 'fallo']);
        $this->assertDatabaseHas('auditorias', ['accion' => 'inicio_sesion', 'user_id' => $usuario->id]);
        $this->assertDatabaseHas('auditorias', ['accion' => 'cierre_sesion', 'user_id' => $usuario->id]);
    }

    public function test_denied_access_is_logged(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']))->get('/actividad')->assertForbidden();

        $this->assertDatabaseHas('auditorias', ['accion' => 'acceso_denegado', 'resultado' => 'fallo']);
    }

    public function test_filters_are_applied_in_the_database_and_validated(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        Categoria::create(['nombre_categoria' => 'Filtrable']);

        $this->get('/actividad?categoria=datos&accion=creado')->assertOk()->assertSee('Filtrable');
        $this->get('/actividad?categoria=seguridad&accion=creado')->assertOk()->assertDontSee('Filtrable');
        $this->get('/actividad?categoria=invalida')->assertSessionHasErrors('categoria');
    }

    public function test_detail_view_shows_changes_and_logs_the_consultation(): void
    {
        $this->actingAs($this->admin());
        $categoria = Categoria::create(['nombre_categoria' => 'Detalle', 'descripcion' => 'x']);
        $categoria->update(['descripcion' => 'y']);
        $registro = Auditoria::where('accion', 'editado')->firstOrFail();

        $this->get(route('actividad.show', $registro))->assertOk()->assertSee('descripcion');
        $this->assertDatabaseHas('auditorias', ['accion' => 'consulta_detalle', 'modelo_id' => $registro->getKey()]);
    }

    public function test_exports_are_limited_to_administrators_and_audited(): void
    {
        $this->actingAs($this->admin());
        Categoria::create(['nombre_categoria' => 'Exportable']);

        $respuesta = $this->get('/actividad/exportar/csv');
        $respuesta->assertOk();
        $this->assertStringContainsString('Exportable', $respuesta->streamedContent());
        $this->assertDatabaseHas('auditorias', ['accion' => 'exportado', 'categoria' => 'exportacion']);
        $this->get('/actividad/exportar/pdf')->assertOk();
    }

    public function test_activity_records_are_immutable(): void
    {
        $registro = app(RegistroActividad::class)->evento('sistema', 'prueba', 'Evento');

        $this->expectException(LogicException::class);
        $registro->update(['descripcion' => 'Alterado']);
    }

    public function test_integrity_verification_detects_tampering(): void
    {
        $actividad = app(RegistroActividad::class);
        $actividad->evento('sistema', 'uno', 'Uno');
        $actividad->evento('sistema', 'dos', 'Dos');

        $this->artisan('actividad:verificar')->assertSuccessful();

        DB::table('auditorias')->where('accion', 'uno')->update(['descripcion' => 'Manipulado']);

        $this->artisan('actividad:verificar')->assertFailed();
    }

    public function test_integrity_verification_detects_deleted_rows(): void
    {
        $actividad = app(RegistroActividad::class);
        $actividad->evento('sistema', 'uno', 'Uno');
        $actividad->evento('sistema', 'dos', 'Dos');
        $actividad->evento('sistema', 'tres', 'Tres');

        DB::table('auditorias')->where('accion', 'dos')->delete();

        $this->artisan('actividad:verificar')->assertFailed();
    }

    public function test_purge_archives_and_keeps_the_chain_verifiable(): void
    {
        Storage::fake('local');
        $actividad = app(RegistroActividad::class);
        $actividad->evento('sistema', 'antiguo', 'Antiguo');
        DB::table('auditorias')->where('accion', 'antiguo')->update(['created_at' => now()->subMonths(13)]);
        $actividad->evento('datos', 'creado', 'Datos recientes');

        $this->artisan('actividad:depurar')->assertSuccessful();

        $this->assertDatabaseMissing('auditorias', ['accion' => 'antiguo']);
        $this->assertDatabaseHas('auditorias', ['accion' => 'depurado']);
        $this->assertNotEmpty(Storage::disk('local')->files('actividad-archivo'));
        $this->artisan('actividad:verificar')->assertSuccessful();
    }

    public function test_historical_import_rows_are_grouped_without_deleting_anything(): void
    {
        $momento = now()->toDateTimeString();
        foreach (range(1, 8) as $i) {
            DB::table('auditorias')->insert(['modelo' => 'App\\Models\\Producto', 'accion' => 'creado', 'created_at' => $momento, 'updated_at' => $momento]);
        }

        $this->artisan('actividad:agrupar-historico')->assertSuccessful();

        $this->assertSame(8, Auditoria::count());
        $this->assertSame(1, Auditoria::distinct()->pluck('lote_id')->count());

        $this->actingAs($this->admin())->get('/actividad?modelo='.urlencode('App\\Models\\Producto'))
            ->assertSee('Operación masiva histórica: 8 registros');
    }
}
