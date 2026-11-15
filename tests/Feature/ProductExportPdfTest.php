<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductExportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_a_complete_inventory_with_many_products_as_pdf(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $categoria = Categoria::create([
            'nombre_categoria' => 'Inventario',
            'descripcion' => 'Productos para validar la exportación completa.',
        ]);
        $fecha = now();
        $productos = [];

        for ($numero = 1; $numero <= 1000; $numero++) {
            $productos[] = [
                'codigo_barra' => sprintf('INV-%04d', $numero),
                'nombre' => sprintf('Producto de inventario %04d', $numero),
                'precio' => 1000,
                'stock' => 10,
                'stock_critico' => 2,
                'id_categoria' => $categoria->id_categoria,
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ];
        }

        foreach (array_chunk($productos, 100) as $lote) {
            DB::table('productos')->insert($lote);
        }

        $response = $this->actingAs($admin)->get(route('productos.exportar.pdf'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename=inventario-'.$fecha->format('Y-m-d').'.pdf'
            );

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
