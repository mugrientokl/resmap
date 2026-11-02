<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Services\RegistroActividad;
use Illuminate\Console\Command;

class VerificarActividad extends Command
{
    protected $signature = 'actividad:verificar';

    protected $description = 'Verifica la integridad de la cadena de firmas del registro de actividad.';

    public function handle(RegistroActividad $actividad): int
    {
        $purgados = $this->rangosDepurados();
        $anterior = null;
        $revisados = 0;
        $problemas = [];

        Auditoria::query()->whereNotNull('hash')->orderBy('id_auditoria')->each(
            function (Auditoria $registro) use ($actividad, $purgados, &$anterior, &$revisados, &$problemas): void {
                $revisados++;

                if (! hash_equals((string) $registro->hash, $actividad->calcularHash($registro))) {
                    $problemas[] = "Registro #{$registro->id_auditoria}: contenido alterado.";
                }

                if ($anterior !== null && $registro->hash_previo !== $anterior->hash
                    && ! $this->cubierto($purgados, $anterior->id_auditoria + 1, $registro->id_auditoria - 1)) {
                    $problemas[] = "Registro #{$registro->id_auditoria}: faltan registros anteriores en la cadena.";
                }

                $anterior = $registro;
            },
            500
        );

        foreach ($problemas as $problema) {
            $this->error($problema);
        }

        if ($problemas !== []) {
            $actividad->evento('sistema', 'integridad_comprometida', 'La verificación de integridad detectó '.count($problemas).' problema(s)', 'Actividad', resultado: 'fallo', nuevos: ['detalle' => array_slice($problemas, 0, 20)]);

            return self::FAILURE;
        }

        $this->info("Integridad verificada: {$revisados} registros sin alteraciones.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0: int, 1: int}>
     */
    private function rangosDepurados(): array
    {
        $rangos = [];

        Auditoria::query()->where('accion', 'depurado')->each(function (Auditoria $registro) use (&$rangos): void {
            $rangos = array_merge($rangos, $registro->datos_nuevos['rangos'] ?? []);
        });

        return $rangos;
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $rangos
     */
    private function cubierto(array $rangos, int $desde, int $hasta): bool
    {
        if ($desde > $hasta) {
            return true;
        }

        usort($rangos, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        foreach ($rangos as [$inicio, $fin]) {
            if ($inicio <= $desde && $fin >= $desde) {
                $desde = $fin + 1;
            }
        }

        return $desde > $hasta;
    }
}
