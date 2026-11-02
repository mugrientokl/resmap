<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RegistroActividad
{
    private int $silenciado = 0;

    private ?string $loteId = null;

    private ?string $correlationId = null;

    public function estaSilenciado(): bool
    {
        return $this->silenciado > 0;
    }

    /**
     * Ejecuta el callback sin que los observadores generen registros por fila.
     */
    public function sinRegistrar(callable $callback): mixed
    {
        $this->silenciado++;

        try {
            return $callback();
        } finally {
            $this->silenciado--;
        }
    }

    /**
     * Agrupa una operación masiva en un único registro con un lote_id.
     * El callback devuelve el resumen que se guarda como datos del evento.
     *
     * @param  callable(): array<string, mixed>  $callback
     */
    public function enLote(string $descripcion, callable $callback, string $modelo = 'Producto'): mixed
    {
        $this->loteId = (string) Str::uuid();

        try {
            $resumen = $this->sinRegistrar($callback);
        } catch (Throwable $e) {
            $this->evento('importacion', 'importado', $descripcion, $modelo, resultado: 'fallo', nuevos: ['error' => class_basename($e)]);
            $this->loteId = null;

            throw $e;
        }

        $this->evento('importacion', 'importado', $descripcion, $modelo, nuevos: is_array($resumen) ? $resumen : null);
        $this->loteId = null;

        return $resumen;
    }

    /**
     * @param  array<string, mixed>|null  $anteriores
     * @param  array<string, mixed>|null  $nuevos
     */
    public function evento(
        string $categoria,
        string $accion,
        string $descripcion,
        string $modelo = 'Sistema',
        ?int $modeloId = null,
        string $resultado = 'exito',
        ?array $anteriores = null,
        ?array $nuevos = null,
        ?int $userId = null,
        ?string $actorNombre = null,
    ): ?Auditoria {
        $usuario = Auth::user();

        return $this->registrar([
            'user_id' => $userId ?? $usuario?->getAuthIdentifier(),
            'actor_nombre' => $actorNombre ?? $usuario?->name,
            'modelo' => $modelo,
            'modelo_id' => $modeloId,
            'accion' => $accion,
            'categoria' => $categoria,
            'resultado' => $resultado,
            'descripcion' => Str::limit($descripcion, 250, ''),
            'datos_anteriores' => $anteriores,
            'datos_nuevos' => $nuevos,
        ]);
    }

    /**
     * El registro nunca debe interrumpir la operación de negocio: ante un
     * fallo se deja constancia en un canal de log aparte.
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrar(array $datos): ?Auditoria
    {
        try {
            $request = request();
            $datos += [
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
                'correlation_id' => $this->correlationId ??= (string) Str::uuid(),
                'lote_id' => $this->loteId,
            ];

            return DB::transaction(function () use ($datos): Auditoria {
                $ultimo = Auditoria::query()
                    ->whereNotNull('hash')
                    ->orderByDesc('id_auditoria')
                    ->lockForUpdate()
                    ->first(['hash']);

                $registro = new Auditoria($datos);
                $registro->hash_previo = $ultimo?->hash;
                $registro->created_at = now();
                $registro->updated_at = $registro->created_at;
                $registro->hash = $this->calcularHash($registro);
                $registro->save();

                return $registro;
            });
        } catch (Throwable $e) {
            try {
                Log::channel('actividad')->critical('No se pudo registrar la actividad.', [
                    'accion' => $datos['accion'] ?? null,
                    'modelo' => $datos['modelo'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            } catch (Throwable) {
                Log::critical('No se pudo registrar la actividad.', ['error' => $e->getMessage()]);
            }

            return null;
        }
    }

    public function calcularHash(Auditoria $registro): string
    {
        $contenido = [
            $registro->user_id,
            $registro->actor_nombre,
            $registro->modelo,
            $registro->modelo_id,
            $registro->accion,
            $registro->categoria,
            $registro->resultado,
            $registro->descripcion,
            $this->canonico($registro->datos_anteriores),
            $this->canonico($registro->datos_nuevos),
            $registro->ip,
            $registro->correlation_id,
            $registro->lote_id,
            $registro->created_at?->format('Y-m-d H:i:s'),
        ];

        return hash_hmac(
            'sha256',
            ($registro->hash_previo ?? '').'|'.json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string) config('actividad.clave_integridad')
        );
    }

    private function canonico(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }

        ksort($valor);

        return array_map(fn (mixed $item): mixed => $this->canonico($item), $valor);
    }
}
