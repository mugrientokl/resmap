<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['solicitud_webs', 'cotizacion_versiones', 'ventas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->json('datos_cliente')->nullable();
            });
        }

        $this->backfillSolicitudes();
        $this->backfillVersiones();
        $this->backfillVentas();
    }

    public function down(): void
    {
        foreach (['solicitud_webs', 'cotizacion_versiones', 'ventas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table): void {
                $table->dropColumn('datos_cliente');
            });
        }
    }

    private function backfillSolicitudes(): void
    {
        DB::table('solicitud_webs')
            ->whereNotNull('id_cliente')
            ->whereNull('datos_cliente')
            ->orderBy('id_solicitud')
            ->chunkById(100, function ($solicitudes): void {
                foreach ($solicitudes as $solicitud) {
                    $snapshot = $this->snapshotAt($solicitud->id_cliente, $solicitud->created_at ?? $solicitud->fecha);
                    if ($snapshot !== []) {
                        DB::table('solicitud_webs')
                            ->where('id_solicitud', $solicitud->id_solicitud)
                            ->update(['datos_cliente' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
                    }
                }
            }, 'id_solicitud');
    }

    private function backfillVersiones(): void
    {
        DB::table('cotizacion_versiones')
            ->join('cotizaciones', 'cotizaciones.id_cotizacion', '=', 'cotizacion_versiones.cotizacion_id')
            ->join('solicitud_webs', 'solicitud_webs.id_solicitud', '=', 'cotizaciones.solicitud_id')
            ->whereNull('cotizacion_versiones.datos_cliente')
            ->select('cotizacion_versiones.id_version', 'solicitud_webs.datos_cliente')
            ->orderBy('cotizacion_versiones.id_version')
            ->chunkById(100, function ($versiones): void {
                foreach ($versiones as $version) {
                    if ($version->datos_cliente !== null) {
                        DB::table('cotizacion_versiones')
                            ->where('id_version', $version->id_version)
                            ->update(['datos_cliente' => $version->datos_cliente]);
                    }
                }
            }, 'cotizacion_versiones.id_version', 'id_version');
    }

    private function backfillVentas(): void
    {
        DB::table('ventas')
            ->whereNotNull('id_cliente')
            ->whereNull('datos_cliente')
            ->orderBy('id_venta')
            ->chunkById(100, function ($ventas): void {
                foreach ($ventas as $venta) {
                    $snapshot = null;
                    if ($venta->cotizacion_version_id !== null) {
                        $snapshot = DB::table('cotizacion_versiones')
                            ->where('id_version', $venta->cotizacion_version_id)
                            ->value('datos_cliente');
                    }

                    if ($snapshot === null) {
                        $datos = $this->snapshotAt($venta->id_cliente, $venta->created_at ?? $venta->fecha);
                        $snapshot = $datos === [] ? null : json_encode($datos, JSON_THROW_ON_ERROR);
                    }

                    if ($snapshot !== null) {
                        DB::table('ventas')
                            ->where('id_venta', $venta->id_venta)
                            ->update(['datos_cliente' => $snapshot]);
                    }
                }
            }, 'id_venta');
    }

    /**
     * Reconstructs customer data from its creation audit and changes recorded
     * up to the operation timestamp.
     *
     * @return array<string, mixed>
     */
    private function snapshotAt(int $clienteId, string $fecha): array
    {
        $campos = [
            'rut', 'nombre', 'contacto', 'razon_social', 'giro', 'correo',
            'telefono', 'telefono_whatsapp', 'direccion', 'region', 'comuna', 'ciudad',
        ];

        if (! Schema::hasTable('auditorias')) {
            return [];
        }

        $auditorias = DB::table('auditorias')
            ->where('modelo', 'App\\Models\\Cliente')
            ->where('modelo_id', $clienteId)
            ->where('created_at', '<=', $fecha)
            ->orderBy('created_at')
            ->orderBy('id_auditoria')
            ->get(['accion', 'datos_nuevos']);
        $creacion = $auditorias->firstWhere('accion', 'creado');
        if (! $creacion) {
            return [];
        }

        $datos = $this->decodificarDatos($creacion->datos_nuevos);
        $datos = array_intersect_key($datos, array_flip($campos));

        foreach ($auditorias as $auditoria) {
            if ($auditoria->accion !== 'editado') {
                continue;
            }

            foreach ($this->decodificarDatos($auditoria->datos_nuevos) as $campo => $valor) {
                if (in_array($campo, $campos, true)) {
                    $datos[$campo] = $valor;
                }
            }
        }

        return $datos;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodificarDatos(mixed $datos): array
    {
        if (is_array($datos)) {
            return $datos;
        }

        $decodificados = json_decode((string) $datos, true);

        return is_array($decodificados) ? $decodificados : [];
    }
};
