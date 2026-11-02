<?php

namespace App\Observers;

use App\Models\User;
use App\Services\RegistroActividad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditoriaObserver
{
    public function __construct(private RegistroActividad $actividad) {}

    public function created(Model $modelo): void
    {
        $this->registrar($modelo, 'creado', null, $this->limpiar($modelo->getAttributes()));
    }

    public function updated(Model $modelo): void
    {
        $cambios = array_diff_key($modelo->getChanges(), array_flip(config('actividad.campos_ignorados')));

        if ($cambios === [] || ! $this->edicionRelevante($modelo, array_keys($cambios))) {
            return;
        }

        $anteriores = array_intersect_key($modelo->getOriginal(), $cambios);

        $this->registrar($modelo, 'editado', $this->limpiar($anteriores), $this->limpiar($cambios), array_keys($cambios));
    }

    public function deleted(Model $modelo): void
    {
        $this->registrar($modelo, 'eliminado', $this->limpiar($modelo->getOriginal()), null);
    }

    /**
     * @param  array<int, string>  $campos
     */
    private function edicionRelevante(Model $modelo, array $campos): bool
    {
        $ediciones = config('actividad.modelos.'.$modelo::class.'.ediciones', true);

        return $ediciones === true || array_intersect($campos, (array) $ediciones) !== [];
    }

    /**
     * @param  array<string, mixed>|null  $anteriores
     * @param  array<string, mixed>|null  $nuevos
     * @param  array<int, string>  $camposCambiados
     */
    private function registrar(Model $modelo, string $accion, ?array $anteriores, ?array $nuevos, array $camposCambiados = []): void
    {
        if ($this->actividad->estaSilenciado()) {
            return;
        }

        $etiqueta = config('actividad.modelos.'.$modelo::class.'.etiqueta', class_basename($modelo));
        $verbo = ['creado' => 'Creó', 'editado' => 'Editó', 'eliminado' => 'Eliminó'][$accion];
        $nombre = $modelo->getAttribute('nombre') ?? $modelo->getAttribute('name') ?? $modelo->getAttribute('nombre_categoria');
        $descripcion = "{$verbo} {$etiqueta} #{$modelo->getKey()}".($nombre ? ' ('.Str::limit((string) $nombre, 80).')' : '');

        if ($accion === 'editado') {
            $descripcion .= ': '.implode(', ', array_slice($camposCambiados, 0, 6));
        }

        $this->actividad->evento(
            categoria: $this->categoria($modelo),
            accion: $accion,
            descripcion: $descripcion,
            modelo: $modelo::class,
            modeloId: is_numeric($modelo->getKey()) ? (int) $modelo->getKey() : null,
            anteriores: $anteriores,
            nuevos: $nuevos,
        );
    }

    private function categoria(Model $modelo): string
    {
        return $modelo instanceof User ? 'seguridad' : 'datos';
    }

    /**
     * Oculta secretos y acota valores largos antes de guardarlos.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function limpiar(array $datos): array
    {
        $ocultos = array_flip(config('actividad.campos_ocultos'));
        $ignorados = array_flip(config('actividad.campos_ignorados'));

        foreach ($datos as $campo => $valor) {
            if (isset($ocultos[$campo])) {
                $datos[$campo] = '[oculto]';
            } elseif (isset($ignorados[$campo])) {
                unset($datos[$campo]);
            } elseif (is_string($valor) && mb_strlen($valor) > 500) {
                $datos[$campo] = Str::limit($valor, 500);
            }
        }

        return $datos;
    }
}
