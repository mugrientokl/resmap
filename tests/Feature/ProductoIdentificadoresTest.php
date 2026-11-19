<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Database\Seeders\ProductoRealSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductoIdentificadoresTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertSame(sprintf('RESMAP-%06d', $producto->id_producto), $producto->codigo_barra);
        $this->assertSame($producto->codigo_barra, $producto->codigo_origen);

        $this->post(route('productos.store'), [...$datos, 'nombre' => '  filtro nuevo  '])->assertSessionHasErrors('nombre');
        $this->post(route('productos.store'), [...$datos, 'nombre' => 'Otro filtro', 'codigo_barra' => $producto->codigo_barra])
            ->assertSessionHasErrors('codigo_barra');
        $this->put(route('productos.update', $producto->id_producto), [...$datos, 'codigo_barra' => 'OTRO'])->assertSessionHasErrors('codigo_barra');
        $this->put(route('productos.update', $producto->id_producto), [...$datos, 'codigo_barra' => $producto->codigo_barra])
            ->assertSessionHasNoErrors();
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

    public function test_import_can_run_twice_without_creating_duplicate_products(): void
    {
        $this->seed(ProductoRealSeeder::class);
        $cantidad = Producto::count();
        $codigos = Producto::orderBy('id_producto')->pluck('codigo_barra', 'id_producto')->all();
        $this->assertGreaterThan(0, $cantidad);

        $this->seed(ProductoRealSeeder::class);
        $this->assertSame($cantidad, Producto::count());
        $this->assertSame($codigos, Producto::orderBy('id_producto')->pluck('codigo_barra', 'id_producto')->all());
    }
}
