<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ClienteVentasPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_name_is_not_a_history_link_but_history_button_remains(): void
    {
        $cliente = Cliente::create([
            'rut' => '12.345.678-5',
            'nombre' => 'Cliente de prueba',
            'correo' => 'cliente@example.com',
        ]);
        $admin = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($admin)
            ->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('Cliente de prueba')
            ->assertSee('Ver historial')
            ->assertSee(route('clientes.historial', $cliente->id_cliente), false)
            ->assertDontSee(
                'href="'.route('clientes.historial', $cliente->id_cliente).'" class="font-bold text-[#9f2f25] hover:underline"',
                false
            );
    }

    public function test_client_sales_pdf_includes_all_sales_for_only_that_client(): void
    {
        $cliente = Cliente::create([
            'rut' => '12.345.678-5',
            'nombre' => 'Cliente de prueba',
            'correo' => 'cliente@example.com',
        ]);
        $otroCliente = Cliente::create([
            'rut' => '76.123.456-K',
            'nombre' => 'Otro cliente',
            'correo' => 'otro@example.com',
        ]);
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $primeraVentaId = $this->createSale($cliente, $admin, now()->subDay());
        $segundaVentaId = $this->createSale($cliente, $admin, now());
        $this->createSale($otroCliente, $admin, now());

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('download')
            ->once()
            ->with('ventas-cliente-'.$cliente->id_cliente.'.pdf')
            ->andReturn(response('pdf')->header(
                'Content-Disposition',
                'attachment; filename=ventas-cliente-'.$cliente->id_cliente.'.pdf'
            ));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('clientes.ventas-pdf', Mockery::on(
                fn (array $data): bool => $data['cliente']->is($cliente)
                    && $data['ventas']->pluck('id_venta')->all() === [$segundaVentaId, $primeraVentaId]
            ))
            ->andReturn($pdf);

        $this->actingAs($admin)
            ->get(route('clientes.ventas.pdf', $cliente->id_cliente))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=ventas-cliente-'.$cliente->id_cliente.'.pdf');
    }

    public function test_client_sales_pdf_is_downloaded_as_a_pdf(): void
    {
        $cliente = Cliente::create([
            'rut' => '12.345.678-5',
            'nombre' => 'Cliente de prueba',
            'correo' => 'cliente@example.com',
        ]);
        $admin = User::factory()->create(['rol' => 'Administrador']);
        $this->createSale($cliente, $admin, now());

        $this->actingAs($admin)
            ->get(route('clientes.ventas.pdf', $cliente->id_cliente))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename=ventas-cliente-'.$cliente->id_cliente.'.pdf'
            );
    }

    private function createSale(Cliente $cliente, User $usuario, Carbon $fecha): int
    {
        return DB::table('ventas')->insertGetId([
            'fecha' => $fecha,
            'tipo_documento' => 'Boleta Electrónica',
            'neto' => 1000,
            'iva' => 190,
            'total' => 1190,
            'medio_pago' => 'Efectivo',
            'user_id' => $usuario->id,
            'id_cliente' => $cliente->id_cliente,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
