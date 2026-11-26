<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductoIdentificadoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_product_keeps_the_manual_price_and_persists_its_recommendation_inputs(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);

        $this->actingAs($admin)->post(route('productos.store'), [
            'nombre' => 'Filtro automático',
            'precio' => '999',
            'stock' => 5,
            'stock_critico' => 1,
            'id_categoria' => $categoria,
            'unidad' => 'unidad',
            'activo' => '1',
            'costo_neto_unitario' => '1000',
            'margen_objetivo' => '30',
        ])->assertSessionHasNoErrors();

        $producto = Producto::where('nombre', 'Filtro automático')->firstOrFail();
        $this->assertSame(999.0, (float) $producto->precio);
        $this->assertSame(1000.0, (float) $producto->costo_promedio_neto);
        $this->assertSame(30.0, (float) $producto->margen_objetivo);
        $this->assertSame('manual', $producto->modo_precio);
        $this->assertSame('FIL001', $producto->codigo_barra);
        $this->assertDatabaseHas('inventario_movimientos', [
            'id_producto' => $producto->id_producto,
            'motivo' => 'Stock inicial',
            'costo_unitario_neto' => 1000,
            'costo_total_neto' => 5000,
        ]);
    }

    public function test_existing_identifiers_are_preserved_and_duplicates_are_renamed(): void
    {
        $migration = require database_path('migrations/2026_11_19_004454_normalize_product_identifiers.php');
        $migration->down();
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $datos = ['nombre' => 'Filtro', 'precio' => 100, 'stock' => 1, 'stock_critico' => 1, 'id_categoria' => $categoria];
        $primero = DB::table('productos')->insertGetId([...$datos, 'codigo_barra' => 'RESMAP-000002', 'codigo_origen' => 'DUPLICADO']);
        $segundo = DB::table('productos')->insertGetId([...$datos, 'codigo_barra' => 'ORIGINAL-2', 'codigo_origen' => 'DUPLICADO']);

        $migration->up();

        $productos = DB::table('productos')->orderBy('id_producto')->get()->keyBy('id_producto');
        $this->assertSame(sprintf('RESMAP-%06d', $primero), $productos[$primero]->codigo_barra);
        $this->assertSame(sprintf('RESMAP-%06d', $segundo), $productos[$segundo]->codigo_origen);
        $this->assertNotSame($productos[$primero]->nombre, $productos[$segundo]->nombre);
        $this->assertDatabaseHas('producto_identificadores_anteriores', [
            'id_producto' => $segundo, 'codigo_barra' => 'ORIGINAL-2', 'codigo_origen' => 'DUPLICADO', 'nombre' => 'Filtro',
        ]);
    }

    public function test_new_products_get_codes_and_duplicate_names_and_code_changes_are_rejected(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $datos = ['nombre' => 'Filtro nuevo', 'precio' => 100, 'stock' => 1, 'stock_critico' => 1, 'id_categoria' => $categoria, 'unidad' => 'unidad', 'activo' => '1'];

        $this->actingAs($admin)->post(route('productos.store'), $datos)->assertSessionHasNoErrors();
        $producto = Producto::where('nombre', 'Filtro nuevo')->firstOrFail();
        $this->assertSame('FIL001', $producto->codigo_barra);
        $this->assertSame($producto->codigo_barra, $producto->codigo_origen);

        $this->post(route('productos.store'), [...$datos, 'nombre' => '  filtro nuevo  '])->assertSessionHasErrors('nombre');
        $this->post(route('productos.store'), [...$datos, 'nombre' => 'Otro filtro', 'codigo_barra' => $producto->codigo_barra])
            ->assertSessionHasErrors(['codigo_barra' => 'El código del repuesto se genera automáticamente.']);
        $this->put(route('productos.update', $producto->id_producto), [...$datos, 'codigo_barra' => 'OTRO'])->assertSessionHasErrors('codigo_barra');
        $this->put(route('productos.update', $producto->id_producto), [...$datos, 'codigo_barra' => $producto->codigo_barra])
            ->assertSessionHasNoErrors();
    }

    public function test_product_model_rejects_code_changes_and_forms_do_not_offer_editable_codes(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $producto = Producto::crearConCodigo([
            'nombre' => 'Filtro nuevo',
            'precio' => 100,
            'stock' => 1,
            'stock_critico' => 1,
            'id_categoria' => $categoria,
        ]);

        $this->actingAs($admin)->get(route('productos.create'))
            ->assertOk()
            ->assertSee('Se asignará automáticamente')
            ->assertDontSee('name="codigo_barra"', false)
            ->assertDontSee('name="modo_precio"', false);
        $this->get(route('productos.edit', $producto->id_producto))
            ->assertOk()
            ->assertSee('name="codigo_barra"', false)
            ->assertSee('readonly', false)
            ->assertDontSee('name="modo_precio"', false);

        $producto->codigo_barra = 'FIL002';
        $this->expectException(ValidationException::class);
        $producto->save();
    }

    public function test_manual_codes_are_rejected_and_server_generates_the_identifier(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $datos = [
            'nombre' => 'Filtro hidráulico',
            'codigo_barra' => 'fil245',
            'precio' => 100,
            'stock' => 1,
            'stock_critico' => 1,
            'id_categoria' => $categoria,
            'unidad' => 'unidad',
            'activo' => '1',
        ];

        $this->actingAs($admin)
            ->post(route('productos.store'), $datos)
            ->assertSessionHasErrors('codigo_barra');
        $this->assertDatabaseMissing('productos', ['nombre' => 'Filtro hidráulico']);

        unset($datos['codigo_barra']);
        $this->post(route('productos.store'), $datos)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', [
            'nombre' => 'Filtro hidráulico',
            'codigo_barra' => 'FIL001',
            'codigo_origen' => 'FIL001',
        ]);

        $this->post(route('productos.store'), [
            ...$datos,
            'nombre' => 'Filtro especial',
            'codigo_barra' => 'FIL001',
        ])->assertSessionHasErrors(['codigo_barra' => 'El código del repuesto se genera automáticamente.']);

    }

    public function test_names_are_unique_ignoring_unicode_case_on_create_and_update(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $datos = ['nombre' => 'Árbol', 'precio' => 100, 'stock' => 1, 'stock_critico' => 1, 'id_categoria' => $categoria, 'unidad' => 'unidad', 'activo' => '1'];

        $this->actingAs($admin)->post(route('productos.store'), $datos)->assertSessionHasNoErrors();
        $this->post(route('productos.store'), [...$datos, 'nombre' => 'árbol'])->assertSessionHasErrors('nombre');
        $this->post(route('productos.store'), [...$datos, 'nombre' => 'Otro'])->assertSessionHasNoErrors();
        $otro = Producto::where('nombre', 'Otro')->firstOrFail();
        $this->put(route('productos.update', $otro->id_producto), [...$datos, 'nombre' => 'árbol', 'codigo_barra' => $otro->codigo_barra])
            ->assertSessionHasErrors('nombre');
    }

    public function test_codes_continue_after_existing_matching_codes_and_leave_existing_identifiers_unchanged(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $datos = ['nombre' => 'Martillo', 'precio' => 100, 'stock' => 1, 'stock_critico' => 1, 'id_categoria' => $categoria, 'unidad' => 'unidad', 'activo' => '1'];
        $legacy = DB::table('productos')->insertGetId([
            ...$datos, 'nombre' => 'Producto anterior', 'codigo_barra' => 'MAR002', 'codigo_origen' => 'mar010',
        ]);
        DB::table('productos')->insert([
            ...$datos, 'nombre' => 'Código distinto', 'codigo_barra' => 'MARXYZ999', 'codigo_origen' => 'OTRO',
        ]);
        DB::table('productos')->insert([
            ...$datos, 'nombre' => 'Código alto', 'codigo_barra' => 'mar009', 'codigo_origen' => null,
        ]);

        $this->actingAs($admin)->post(route('productos.store'), $datos)->assertSessionHasNoErrors();
        $this->post(route('productos.store'), [...$datos, 'nombre' => 'Marca'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['nombre' => 'Martillo', 'codigo_barra' => 'MAR011', 'codigo_origen' => 'MAR011']);
        $this->assertDatabaseHas('productos', ['nombre' => 'Marca', 'codigo_barra' => 'MAR012']);
        $this->assertDatabaseHas('productos', ['id_producto' => $legacy, 'codigo_barra' => 'MAR002', 'codigo_origen' => 'mar010']);
        $this->post(route('productos.store'), [...$datos, 'nombre' => 'A2'])->assertSessionHasErrors('nombre');
    }

    public function test_code_generation_stops_when_the_three_digit_sequence_is_exhausted(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        DB::table('productos')->insert([
            'codigo_barra' => 'FIL999',
            'codigo_origen' => 'FIL999',
            'nombre' => 'Filtro anterior',
            'precio' => 100,
            'stock' => 1,
            'stock_critico' => 1,
            'id_categoria' => $categoria,
        ]);

        $this->actingAs($admin)->post(route('productos.store'), [
            'nombre' => 'Filtro nuevo',
            'precio' => 100,
            'stock' => 1,
            'stock_critico' => 1,
            'id_categoria' => $categoria,
            'unidad' => 'unidad',
            'activo' => '1',
        ])->assertSessionHasErrors([
            'codigo_barra' => 'Se agotaron los códigos disponibles para el prefijo FIL.',
        ]);
    }

    public function test_duplicate_names_ignore_accents_and_extra_spaces_even_for_legacy_rows(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $datos = ['nombre' => 'Martillo grande', 'precio' => 100, 'stock' => 1, 'stock_critico' => 1, 'id_categoria' => $categoria, 'unidad' => 'unidad', 'activo' => '1'];
        DB::table('productos')->insert([...$datos, 'codigo_barra' => 'ANT001', 'nombre_normalizado' => null]);

        $this->actingAs($admin)->post(route('productos.store'), [...$datos, 'nombre' => '  MARTÍLLO   grande  '])
            ->assertSessionHasErrors(['nombre' => 'Ya existe un repuesto con ese nombre.']);
        $this->post(route('productos.store'), [...$datos, 'nombre' => "\u{00a0}MARTILLO grande\u{00a0}"])
            ->assertSessionHasErrors(['nombre' => 'Ya existe un repuesto con ese nombre.']);
        $this->post(route('productos.store'), [...$datos, 'nombre' => 'Otro producto'])->assertSessionHasNoErrors();
        $otro = Producto::where('nombre', 'Otro producto')->firstOrFail();
        $this->put(route('productos.update', $otro->id_producto), [
            ...$datos, 'nombre' => 'martillo  GRÁNDE', 'codigo_barra' => $otro->codigo_barra,
        ])->assertSessionHasErrors(['nombre' => 'Ya existe un repuesto con ese nombre.']);
    }

    public function test_normalization_migration_preserves_existing_accent_duplicates(): void
    {
        $categoria = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Prueba']);
        $datos = ['precio' => 100, 'stock' => 1, 'stock_critico' => 1, 'id_categoria' => $categoria];
        DB::table('productos')->insert([
            ...$datos, 'nombre' => 'Martillo', 'codigo_barra' => 'ANT001',
            'nombre_normalizado' => hash('sha256', 'martillo'),
        ]);
        DB::table('productos')->insert([
            ...$datos, 'nombre' => 'Martíllo', 'codigo_barra' => 'ANT002',
            'nombre_normalizado' => hash('sha256', 'martíllo'),
        ]);

        $migration = require database_path('migrations/2026_11_19_235153_normalizar_nombres_productos_sin_acentos.php');
        $migration->up();

        $this->assertDatabaseHas('productos', ['nombre' => 'Martillo', 'codigo_barra' => 'ANT001']);
        $this->assertDatabaseHas('productos', ['nombre' => 'Martíllo', 'codigo_barra' => 'ANT002', 'nombre_normalizado' => null]);
        $this->assertTrue(Producto::nombreExiste('MARTILLO'));
        $migration->down();
        $this->assertDatabaseHas('productos', ['nombre' => 'Martíllo', 'codigo_barra' => 'ANT002']);
    }
}
