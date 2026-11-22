<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductAndCustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_search_uses_names_and_codes_in_catalog_pos_and_inventory(): void
    {
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Filtros',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = $this->createProduct($categoryId, [
            'codigo_barra' => 'BAR-100',
            'codigo_origen' => 'ORG-7788',
            'nombre' => 'Filtro de aceite',
            'stock' => 2,
            'stock_critico' => 3,
        ]);
        $this->createProduct($categoryId, [
            'codigo_barra' => 'BAR-200',
            'codigo_origen' => 'ORG-8899',
            'nombre' => 'Bujía estándar',
            'stock' => 20,
            'stock_critico' => 3,
        ]);

        $catalogo = $this->get(route('catalogo.index', ['nombre' => 'filtro ORG-7788']));
        $catalogo->assertOk();
        $this->assertSame(
            [$productId],
            $catalogo->viewData('productos')->pluck('id_producto')->all()
        );

        $admin = User::factory()->create(['rol' => 'Administrador']);
        $this->actingAs($admin)->get(route('pos.index'))
            ->assertOk()->assertDontSee('id="tabla-productos"', false)
            ->assertDontSee('id="datos-productos"', false);
        $this->getJson(route('pos.productos.buscar', ['buscar' => 'iltro ORG-7788']))
            ->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.id_producto', $productId)
            ->assertJsonPath('0.stock', 2);

        $inventario = $this->get(route('productos.index', [
            'nombre' => 'filtro ORG-7788',
            'categoria' => $categoryId,
            'stock' => 'critico',
        ]));
        $inventario->assertOk();
        $this->assertSame(
            [$productId],
            $inventario->viewData('productos')->pluck('id_producto')->all()
        );
    }

    public function test_inventory_can_filter_products_by_catalog_status(): void
    {
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Filtros',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $activeProductId = $this->createProduct($categoryId, [
            'codigo_barra' => 'BAR-ACTIVE',
            'codigo_origen' => 'ORG-ACTIVE',
            'nombre' => 'Repuesto activo',
            'stock' => 3,
            'stock_critico' => 1,
        ]);
        $inactiveProductId = $this->createProduct($categoryId, [
            'codigo_barra' => 'BAR-INACTIVE',
            'codigo_origen' => 'ORG-INACTIVE',
            'nombre' => 'Repuesto inactivo',
            'stock' => 3,
            'stock_critico' => 1,
        ]);
        DB::table('productos')->where('id_producto', $inactiveProductId)->update(['activo' => false]);
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $activos = $this->actingAs($admin)->get(route('productos.index', ['activo' => '1']));
        $activos->assertOk()
            ->assertSee('value="1" selected', false);
        $this->assertSame([$activeProductId], $activos->viewData('productos')->pluck('id_producto')->all());

        $inactivos = $this->get(route('productos.index', ['activo' => '0']));
        $inactivos->assertOk()
            ->assertSee('value="0" selected', false)
            ->assertSee('Motivo: Sin motivo registrado');
        $this->assertSame([$inactiveProductId], $inactivos->viewData('productos')->pluck('id_producto')->all());
    }

    public function test_pos_search_is_authenticated_bounded_and_excludes_inactive_products(): void
    {
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Repuestos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        for ($i = 0; $i < 10; $i++) {
            $this->createProduct($categoryId, [
                'codigo_barra' => 'BAR-'.$i,
                'codigo_origen' => 'ORG-'.$i,
                'nombre' => 'Motor '.$i,
                'stock' => 3,
                'stock_critico' => 1,
            ]);
        }
        $inactiveId = $this->createProduct($categoryId, [
            'codigo_barra' => 'INACTIVO',
            'codigo_origen' => 'INACTIVO',
            'nombre' => 'Motor inactivo',
            'stock' => 3,
            'stock_critico' => 1,
        ]);
        DB::table('productos')->where('id_producto', $inactiveId)->update(['activo' => false]);

        $this->getJson(route('pos.productos.buscar', ['buscar' => 'motor']))->assertUnauthorized();
        $this->actingAs(User::factory()->create(['rol' => 'Vendedor']));
        $this->getJson(route('pos.productos.buscar', ['buscar' => 'otor']))
            ->assertOk()->assertJsonCount(8)->assertJsonMissing(['id_producto' => $inactiveId]);
        $this->getJson(route('pos.productos.buscar', ['buscar' => 'inactivo']))
            ->assertOk()->assertExactJson([]);
        $this->getJson(route('pos.productos.buscar'))->assertUnprocessable();
    }

    public function test_customer_search_matches_normalized_rut_and_suggests_rut_and_email(): void
    {
        $clienteId = DB::table('clientes')->insertGetId([
            'rut' => '76123456-K',
            'nombre' => 'Repuestos del Sur',
            'correo' => 'compras@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $vendedor = User::factory()->create(['rol' => 'Vendedor']);

        $response = $this->actingAs($vendedor)
            ->get(route('clientes.index', ['buscar' => '76123456k']));
        $response->assertOk();
        $this->assertSame(
            [$clienteId],
            $response->viewData('clientes')->pluck('id_cliente')->all()
        );

        $this->getJson(route('clientes.sugerencias', ['buscar' => 'compras@example.com']))
            ->assertOk()
            ->assertJsonFragment([
                'id_cliente' => $clienteId,
                'nombre' => 'Repuestos del Sur',
                'rut' => '76.123.456-K',
                'correo' => 'compras@example.com',
            ]);
    }

    public function test_public_catalog_does_not_expose_customer_data_by_rut(): void
    {
        DB::table('clientes')->insert([
            'rut' => '76123456-0',
            'nombre' => 'Repuestos del Sur',
            'contacto' => 'Contacto de prueba',
            'giro' => 'Venta de repuestos',
            'correo' => 'compras@example.com',
            'telefono' => '+56987654321',
            'direccion' => 'Av. Central 123',
            'region' => 'Metropolitana de Santiago',
            'comuna' => 'Santiago',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/catalogo/cliente-por-rut?rut=761234560')->assertNotFound();
    }

    public function test_catalog_quote_form_does_not_include_public_rut_autocomplete(): void
    {
        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('id="rut_solicitud"', false)
            ->assertDontSee('/catalogo/cliente-por-rut', false)
            ->assertDontSee(asset('js/cliente-por-rut.js'), false);
    }

    /**
     * @param  array{codigo_barra: string, codigo_origen: string, nombre: string, stock: int, stock_critico: int}  $attributes
     */
    private function createProduct(int $categoryId, array $attributes): int
    {
        return DB::table('productos')->insertGetId([
            ...$attributes,
            'precio' => 1000,
            'id_categoria' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
