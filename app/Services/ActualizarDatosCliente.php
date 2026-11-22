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

            return $cliente;
        });
    }
}
