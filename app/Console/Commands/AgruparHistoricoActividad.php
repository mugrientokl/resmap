<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgruparHistoricoActividad extends Command
{
    protected $signature = 'actividad:agrupar-historico {--simulacion : Solo muestra los lotes que se crearían}';

    protected $description = 'Asigna un lote a los registros históricos de importaciones masivas, sin eliminar nada.';

    public function handle(): int
    {
        $grupos = [];

        $filas = DB::table('auditorias')
            ->whereNull('lote_id')
            ->whereNull('hash')
            ->orderBy('id_auditoria')
            ->select(['id_auditoria', 'user_id', 'accion', 'modelo', 'created_at'])
            ->cursor();

        foreach ($filas as $fila) {
            $clave = substr((string) $fila->created_at, 0, 16).'|'.$fila->user_id.'|'.$fila->accion;
            $grupos[$clave]['ids'][] = $fila->id_auditoria;
            $grupos[$clave]['modelo'] = $fila->modelo;
        }

        $lotes = 0;

        foreach ($grupos as $grupo) {
            $cantidad = count($grupo['ids']);

            if ($cantidad <= 5) {
                continue;
            }

            $lotes++;

            if ($this->option('simulacion')) {
                continue;
            }

            $loteId = (string) Str::uuid();
            $modelo = config("actividad.modelos.{$grupo['modelo']}.etiqueta") ?? class_basename($grupo['modelo']);

            foreach (array_chunk($grupo['ids'], 500) as $bloque) {
                DB::table('auditorias')->whereIn('id_auditoria', $bloque)->update([
                    'lote_id' => $loteId,
                    'categoria' => 'importacion',
                ]);
            }

            DB::table('auditorias')->where('id_auditoria', min($grupo['ids']))->update([
                'descripcion' => "Operación masiva histórica: {$cantidad} registros de {$modelo}",
            ]);
        }

        $this->info($this->option('simulacion') ? "Se crearían {$lotes} lotes." : "Lotes creados: {$lotes}.");

        return self::SUCCESS;
    }
}
