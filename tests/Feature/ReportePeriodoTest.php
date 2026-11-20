<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportePeriodoTest extends TestCase
{
    use RefreshDatabase;

    public function test_periods_and_exports_use_the_same_filtered_sales(): void
    {
        $this->travelTo(now()->setDate(2025, 6, 15)->startOfDay());
        $admin = User::factory()->create(['rol' => 'Administrador']);
        foreach ([
            ['2024-02-10', 1000],
            ['2025-02-05', 2000],
            ['2025-02-15', 3000],
            ['2025-02-20', 5000],
            ['2025-03-01', 4000],
        ] as [$fecha, $total]) {
            Venta::create([
                'fecha' => $fecha.' 12:00:00',
                'tipo_documento' => 'Boleta Electrónica',
                'neto' => $total,
                'iva' => 0,
                'total' => $total,
                'medio_pago' => $total === 3000 ? 'Tarjeta' : 'Efectivo',
                'user_id' => $admin->id,
            ]);
        }

        $this->actingAs($admin)->get(route('reportes.index'))
            ->assertOk()
            ->assertViewHas('resumen', fn ($r): bool => (int) $r->cantidad === 4 && (float) $r->total === 14000.0)
            ->assertViewHas('aniosDisponibles', fn (array $a): bool => in_array(2024, $a, true));

        $this->get(route('reportes.index', ['anio' => 2024]))
            ->assertViewHas('resumen', fn ($r): bool => (int) $r->cantidad === 1 && (float) $r->total === 1000.0);

        $filtros = ['anio' => 2025, 'mes' => 2, 'desde' => '2025-02-10', 'hasta' => '2025-03-10', 'medio_pago' => 'Tarjeta'];
        $this->get(route('reportes.index', $filtros))
            ->assertOk()
            ->assertSee('10/02/2025 - 28/02/2025')
            ->assertViewHas('resumen', fn ($r): bool => (int) $r->cantidad === 1 && (float) $r->total === 3000.0);

        $csv = $this->get(route('reportes.exportar', ['formato' => 'csv', ...$filtros]))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('15/02/2025 12:00', $csv);
        $this->assertStringNotContainsString('05/02/2025 12:00', $csv);
        $this->assertStringNotContainsString('20/02/2025 12:00', $csv);
        $this->assertStringNotContainsString('01/03/2025 12:00', $csv);
        $this->assertStringContainsString('10/02/2025 - 28/02/2025', $csv);

        $this->get(route('reportes.exportar', ['formato' => 'pdf', ...$filtros]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $documento = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $documento->shouldReceive('download')->once()->andReturn(response('PDF'));
        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $vista, array $datos): bool {
            return $vista === 'reportes.export-pdf'
                && $datos['ventas']->count() === 1
                && (float) $datos['ventas']->first()->total === 3000.0
                && $datos['filtros']['Período aplicado'] === '10/02/2025 - 28/02/2025';
        })->andReturn($documento);
        $this->get(route('reportes.exportar', ['formato' => 'pdf', ...$filtros]))->assertOk();
    }

    public function test_empty_and_invalid_ranges_are_handled_consistently(): void
    {
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $this->actingAs($admin);

        $filtros = ['anio' => 2025, 'mes' => 2, 'desde' => '2025-03-01'];
        $this->get(route('reportes.index', $filtros))
            ->assertOk()
            ->assertSee('sin coincidencia entre el período y el rango de fechas')
            ->assertSee('No hay ventas para los filtros seleccionados.');
        $this->get(route('reportes.exportar', ['formato' => 'csv', ...$filtros]))
            ->assertOk();

        $this->get(route('reportes.index', ['anio' => 2025, 'hasta' => '2025-02-01']))->assertOk();

        $filtros = ['anio' => 2025, 'desde' => '2025-03-01', 'hasta' => '2025-02-01'];
        $this->getJson(route('reportes.index', $filtros))->assertUnprocessable();
        $this->getJson(route('reportes.exportar', ['formato' => 'csv', ...$filtros]))->assertUnprocessable();
    }
}
