<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

class ActualizarDatosCliente
{
    public function __construct(private RegistroActividad $actividad) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarDesdeSolicitud(array $datos, string $origen): Cliente
    {
        return DB::transaction(function () use ($datos, $origen): Cliente {
            $cliente = Cliente::query()
                ->where('rut', $datos['rut'])
                ->lockForUpdate()
                ->first();

            if (! $cliente) {
                $cliente = $this->actividad->sinRegistrar(
                    fn (): Cliente => Cliente::create($datos)
                );
                $this->actividad->evento(
                    categoria: 'datos',
                    accion: 'creado',
                    descripcion: 'El cliente registró sus datos desde '.$origen.'.',
                    modelo: Cliente::class,
                    modeloId: $cliente->id_cliente,
                    nuevos: $cliente->snapshotDatos(),
                    actorNombre: 'Cliente',
                );

                return $cliente;
            }

            $cliente->fill($datos);
            $camposModificados = array_keys($cliente->getDirty());

            if ($camposModificados === []) {
                return $cliente;
            }

            $campos = array_fill_keys($camposModificados, true);
            $datosAnteriores = array_intersect_key($cliente->getOriginal(), $campos);
            $datosNuevos = array_intersect_key($cliente->getAttributes(), $campos);

            $this->actividad->sinRegistrar(fn (): bool => $cliente->save());
            $this->actividad->evento(
                categoria: 'datos',
                accion: 'editado',
                descripcion: 'El cliente actualizó sus datos desde '.$origen.'.',
                modelo: Cliente::class,
                modeloId: $cliente->id_cliente,
                anteriores: $datosAnteriores,
                nuevos: ['datos_cliente' => $datosNuevos, 'origen' => $origen],
                actorNombre: 'Cliente',
            );

            return $cliente;
        });
    }
}
