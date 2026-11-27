<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\HistorialDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class HistorialFicticioTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_coherent_and_does_not_send_messages(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 27)->setTime(12, 0));
        Mail::fake();
        Notification::fake();
        Http::fake();
        $usuario = User::factory()->create(['rol' => 'Administrador']);
        $clienteId = DB::table('clientes')->insertGetId([
            'rut' => '12345678-5',
            'nombre' => 'Cliente existente',
            'correo' => 'existente@example.test',
        ]);
        $categoriaId = DB::table('categorias')->insertGetId(['nombre_categoria' => 'Repuestos']);
        foreach (range(1, 10) as $numero) {
            DB::table('productos')->insert([
                'codigo_barra' => 'REP'.str_pad((string) $numero, 3, '0', STR_PAD_LEFT),
                'nombre' => 'Repuesto '.$numero,
                'precio' => 10000 + $numero,
                'stock' => 10,
                'stock_critico' => 2,
                'id_categoria' => $categoriaId,
            ]);
        }
        $proveedoresAntes = DB::table('proveedores')->count();

        $this->seed(HistorialDemoSeeder::class);

        $this->assertDatabaseHas('clientes', ['id_cliente' => $clienteId, 'nombre' => 'Cliente existente']);
        $this->assertDatabaseHas('users', ['id' => $usuario->id]);
        $this->assertSame($proveedoresAntes, DB::table('proveedores')->count());
        $this->assertSame(80, DB::table('clientes')->where('correo', 'like', '%@jmail.com')->count());
        $this->assertSame(1, DB::table('auditorias')->count());
        $this->assertDatabaseHas('auditorias', ['descripcion' => 'Registro histórico']);
        $this->assertGreaterThan(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('ordenes_compra')->count());

        $meses = DB::table('ventas')->selectRaw("strftime('%Y-%m', fecha) as mes, count(*) as cantidad")
            ->groupBy('mes')->orderBy('mes')->get();
        $this->assertCount(36, $meses);
        $this->assertSame('2023-12', $meses->first()->mes);
        $this->assertSame('2026-11', $meses->last()->mes);
        foreach ($meses as $mes) {
            $this->assertGreaterThanOrEqual(45, $mes->cantidad);
            $this->assertLessThanOrEqual(55, $mes->cantidad);
        }

        $venta = DB::table('ventas')->whereNotNull('cotizacion_version_id')->first();
        $datos = json_decode($venta->datos_cliente, true, flags: JSON_THROW_ON_ERROR);
        foreach (['rut', 'nombre', 'contacto', 'correo', 'telefono', 'direccion', 'region', 'comuna', 'ciudad'] as $campo) {
            $this->assertNotEmpty($datos[$campo]);
        }
        $this->assertStringEndsWith('@jmail.com', $datos['correo']);
        $this->assertSame('no_enviado', DB::table('cotizacion_versiones')->value('email_estado'));

        foreach (DB::table('productos')->get() as $producto) {
            $saldo = 0;
            $movimientos = DB::table('inventario_movimientos')->where('id_producto', $producto->id_producto)
                ->orderBy('created_at')->orderBy('id_movimiento')->get();
            foreach ($movimientos as $movimiento) {
                $this->assertSame($saldo, $movimiento->stock_anterior);
                $saldo += $movimiento->cantidad;
                $this->assertSame($saldo, $movimiento->stock_nuevo);
                $this->assertGreaterThanOrEqual(0, $saldo);
            }
            $this->assertSame($producto->stock, $saldo);

            $reservas = DB::table('reservas_inventario')
                ->join('ventas', 'ventas.cotizacion_version_id', '=', 'reservas_inventario.version_id')
                ->where('reservas_inventario.producto_id', $producto->id_producto)
                ->orderBy('reservas_inventario.created_at')
                ->get(['reservas_inventario.cantidad', 'reservas_inventario.created_at', 'reservas_inventario.consumida_at', 'ventas.id_venta']);
            $consumidaAnterior = null;
            foreach ($reservas as $reserva) {
                if ($consumidaAnterior !== null) {
                    $this->assertLessThanOrEqual($reserva->created_at, $consumidaAnterior);
                }
                $stockDisponible = $movimientos->filter(fn ($movimiento): bool => $movimiento->created_at <= $reserva->created_at
                    && $movimiento->id_venta !== $reserva->id_venta)->sum('cantidad');
                $this->assertGreaterThanOrEqual($reserva->cantidad, $stockDisponible);
                $consumidaAnterior = $reserva->consumida_at;
            }
        }

        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_pending_jobs_prevent_replacing_existing_data(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hay trabajos pendientes en la cola.');

        $this->seed(HistorialDemoSeeder::class);
    }
}
