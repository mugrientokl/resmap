<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SalesFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_is_registered_without_external_billing_integration(): void
    {
        Http::fake();
        $seller = User::factory()->create(['rol' => 'Vendedor']);
        $categoryId = DB::table('categorias')->insertGetId([
            'nombre_categoria' => 'Pruebas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('productos')->insertGetId([
            'codigo_barra' => 'SALE-TEST-001',
            'nombre' => 'Producto de prueba',
            'precio' => 1190,
            'stock' => 5,
            'stock_critico' => 1,
            'id_categoria' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($seller)->postJson('/ventas', [
            'tipo_documento' => 'Boleta Electrónica',
            'medio_pago' => 'Efectivo',
            'rut' => '66666666-6',
            'nombre_cliente' => 'Cliente de prueba',
            'detalles' => [
                ['id_producto' => $productId, 'cantidad' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Venta registrada con éxito, stock actualizado y cliente sincronizado.')
            ->assertJsonMissingPath('resultado.integracion');

        $this->assertDatabaseHas('ventas', ['total' => 1190]);
        $this->assertDatabaseHas('productos', ['id_producto' => $productId, 'stock' => 4]);
        Http::assertNothingSent();
    }
}
