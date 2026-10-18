<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_sales_count_as_a_quantity(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);

        foreach (range(1, 2) as $saleNumber) {
            Venta::create([
                'fecha' => now(),
                'tipo_documento' => 'Boleta Electrónica',
                'neto' => 1000,
                'iva' => 190,
                'total' => 1190,
                'medio_pago' => 'Efectivo',
                'user_id' => $admin->id,
            ]);
        }

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<p class="stat-value">2</p>', false)
            ->assertSee('Ventas registradas')
            ->assertDontSee('<p class="stat-value">$2</p>', false);
    }
}
