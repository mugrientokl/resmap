<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogoStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_rejected_when_requested_quantity_exceeds_available_stock(): void
    {
        $productId = $this->createProductWithStock(3);

        $response = $this->from('/catalogo')->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 4],
            ],
        ]);

        $response->assertRedirect('/catalogo')
            ->assertSessionHasErrors([
                'detalles_productos' => 'Stock insuficiente para Producto de prueba: solicitaste 4 y quedan 3.',
            ]);
        $this->assertDatabaseCount('solicitud_webs', 0);
        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_duplicate_product_lines_are_combined_before_checking_stock(): void
    {
        $productId = $this->createProductWithStock(3);

        $this->from('/catalogo')->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 2],
                ['id_producto' => $productId, 'cantidad' => 2],
            ],
        ])->assertRedirect('/catalogo')
            ->assertSessionHasErrors([
                'detalles_productos' => 'Stock insuficiente para Producto de prueba: solicitaste 4 y quedan 3.',
            ]);

        $this->assertDatabaseCount('solicitud_webs', 0);
    }

    public function test_request_is_accepted_when_requested_quantity_matches_available_stock(): void
    {
        $productId = $this->createProductWithStock(3);

        $this->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 3],
            ],
        ])->assertRedirect(route('catalogo.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('solicitud_webs', 1);
    }

    public function test_inactive_product_is_hidden_and_cannot_be_requested(): void
    {
        $productId = $this->createProductWithStock(3);
        DB::table('productos')->where('id_producto', $productId)->update(['activo' => false]);

        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertDontSee('Producto de prueba');

        $this->from('/catalogo')->post(route('catalogo.solicitudes.store'), [
            ...$this->validCustomerData(),
            'detalles_productos' => [
                ['id_producto' => $productId, 'cantidad' => 1],
            ],
        ])->assertRedirect('/catalogo')
            ->assertSessionHasErrors([
                'detalles_productos' => 'No se pueden solicitar productos inactivos: Producto de prueba.',
            ]);

        $this->assertDatabaseCount('solicitud_webs', 0);
    }

    public function test_new_products_default_to_unit_and_active(): void
    {
        $productId = $this->createProductWithStock(3);

        $this->assertDatabaseHas('productos', [
            'id_producto' => $productId,
            'unidad' => 'unidad',
            'activo' => true,
        ]);
    }

    public function test_administrator_can_set_product_unit_and_catalog_visibility(): void
    {
        $productId = $this->createProductWithStock(3);
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $product = DB::table('productos')->where('id_producto', $productId)->first();

        $this->actingAs($admin)->put(route('productos.update', $productId), [
            'codigo_barra' => $product->codigo_barra,
            'nombre' => $product->nombre,
            'precio' => $product->precio,
            'stock' => $product->stock,
            'stock_critico' => $product->stock_critico,
            'id_categoria' => $product->id_categoria,
            'unidad' => 'metro',
            'activo' => '0',
        ])->assertRedirect(route('productos.index'));

        $this->assertDatabaseHas('productos', [
            'id_producto' => $productId,
            'unidad' => 'metro',
            'activo' => false,
        ]);

        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertDontSee($product->nombre);
    }

    private function createProductWithStock(int $stock): int
    {
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Repuestos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('productos')->insertGetId([
            'codigo_barra' => 'TEST-'.uniqid(),
            'nombre' => 'Producto de prueba',
            'precio' => 1000,
            'stock' => $stock,
            'stock_critico' => 1,
            'id_categoria' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function validCustomerData(): array
    {
        return [
            'rut' => '12345678-5',
            'nombre' => 'Cliente de prueba',
            'prefijo_telefono' => '+56',
            'telefono' => '91234567',
            'region' => 'Metropolitana',
            'comuna' => 'Santiago',
        ];
    }
}
