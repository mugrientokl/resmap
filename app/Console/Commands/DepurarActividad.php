<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Services\RegistroActividad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DepurarActividad extends Command
{
    protected $signature = 'actividad:depurar {--simulacion : Solo muestra cuántos registros se depurarían}';

    protected $description = 'Archiva en CSV comprimido y elimina la actividad que superó su plazo de retención.';

    public function handle(RegistroActividad $actividad): int
    {
        $total = 0;
        $rangos = [];
        $detalle = [];

        foreach (config('actividad.retencion_meses') as $categoria => $meses) {
            $limite = now()->subMonths((int) $meses);
            $consulta = Auditoria::query()->where('categoria', $categoria)->where('created_at', '<', $limite);
            $cantidad = (clone $consulta)->count();

            if ($cantidad === 0) {
                continue;
            }

            $this->line("{$categoria}: {$cantidad} registros anteriores a {$limite->toDateString()}.");

            if ($this->option('simulacion')) {
                $total += $cantidad;

                continue;
            }

            $this->archivar(clone $consulta, $categoria);

            $ids = (clone $consulta)->orderBy('id_auditoria')->pluck('id_auditoria')->all();
            foreach (array_chunk($ids, 500) as $bloque) {
                DB::table('auditorias')->whereIn('id_auditoria', $bloque)->delete();
            }

            $rangos = array_merge($rangos, $this->compactar($ids));
            $detalle[$categoria] = $cantidad;
            $total += $cantidad;
        }

        if ($total > 0 && ! $this->option('simulacion')) {
            $actividad->evento(
                categoria: 'sistema',
                accion: 'depurado',
                descripcion: "Depuró {$total} registros de actividad por retención",
                modelo: 'Actividad',
                nuevos: ['por_categoria' => $detalle, 'rangos' => $rangos],
            );
        }

        $this->info($this->option('simulacion') ? "Se depurarían {$total} registros." : "Registros depurados: {$total}.");

        return self::SUCCESS;
    }

    private function archivar($consulta, string $categoria): void
    {
        $ruta = 'actividad-archivo/'.now()->format('Y-m-d_His')."_{$categoria}.csv.gz";
        Storage::disk('local')->makeDirectory('actividad-archivo');

        $salida = fopen('compress.zlib://'.Storage::disk('local')->path($ruta), 'w');
        fputcsv($salida, ['id', 'fecha', 'usuario', 'categoria', 'modelo', 'modelo_id', 'accion', 'resultado', 'descripcion', 'ip', 'datos_anteriores', 'datos_nuevos', 'hash']);

        foreach ($consulta->orderBy('id_auditoria')->cursor() as $registro) {
            fputcsv($salida, [
                $registro->id_auditoria, $registro->created_at, $registro->nombre_actor, $registro->categoria,
                $registro->modelo, $registro->modelo_id, $registro->accion, $registro->resultado, $registro->descripcion,
                $registro->ip, json_encode($registro->datos_anteriores), json_encode($registro->datos_nuevos), $registro->hash,
            ]);
        }

        fclose($salida);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{0: int, 1: int}>
     */
    private function compactar(array $ids): array
    {
        sort($ids);
        $rangos = [];

        foreach ($ids as $id) {
            $ultimo = array_key_last($rangos);

            if ($ultimo !== null && $rangos[$ultimo][1] === $id - 1) {
                $rangos[$ultimo][1] = $id;
            } else {
                $rangos[] = [$id, $id];
            }
        }

        return $rangos;
    }
}
