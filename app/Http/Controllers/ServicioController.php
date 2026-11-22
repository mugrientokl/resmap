<?php

namespace App\Http\Controllers;

use App\Models\SolicitudWeb;
use App\Models\User;
use App\Notifications\SolicitudWebRecibida;
use App\Rules\RutChileno;
use App\Services\ActualizarDatosCliente;
use App\Support\Rut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServicioController extends Controller
{
    public function index(Request $request)
    {
        $tiposServicio = ['Mantención', 'Reparación', 'Soldadura'];
        $tipoServicio = $request->query('tipo_servicio');

        return view('servicios.index', [
            'tipoServicioSeleccionado' => in_array($tipoServicio, $tiposServicio, true) ? $tipoServicio : null,
        ]);
    }

    public function store(Request $request, ActualizarDatosCliente $actualizarCliente)
    {
        $request->merge(['rut' => Rut::normalizar((string) $request->input('rut'))]);

        $data = $request->validate([
            'rut' => ['required', 'string', 'max:20', new RutChileno],
            'nombre' => ['required', 'string', 'max:255'],
            'contacto' => ['required', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255'],
            'prefijo_telefono' => ['required', 'in:+56,+569'],
            'telefono' => ['required', 'regex:/^[0-9]{8,9}$/'],
            'direccion' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'comuna' => ['required', 'string', 'max:100'],
            'tipo_servicio' => ['required', 'in:Mantención,Reparación,Soldadura'],
            'descripcion_servicio' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'rut.required' => 'Escribe tu RUT, por ejemplo: 12.345.678-5.',
            'nombre.required' => 'Escribe tu nombre o razón social.',
            'correo.required' => 'Escribe tu correo electrónico.',
            'correo.email' => 'Escribe un correo electrónico válido.',
            'telefono.required' => 'Escribe tu teléfono chileno.',
            'telefono.regex' => 'El teléfono debe contener los dígitos nacionales.',
            'direccion.required' => 'Escribe tu dirección.',
            'tipo_servicio.required' => 'Selecciona el tipo de servicio.',
            'descripcion_servicio.required' => 'Describe el servicio que necesitas.',
            'descripcion_servicio.min' => 'Describe tu necesidad con al menos 10 caracteres.',
        ]);

        $telefono = $data['prefijo_telefono'].$data['telefono'];
        validator(['telefono' => $telefono], [
            'telefono' => ['regex:/^\+56[2-9][0-9]{8}$/'],
        ], ['telefono.regex' => 'Ingresa un teléfono chileno válido.'])->validate();

        $solicitud = DB::transaction(function () use ($data, $telefono, $actualizarCliente): SolicitudWeb {
            $datosCliente = [
                'rut' => $data['rut'],
                'nombre' => $data['nombre'],
                'contacto' => $data['contacto'],
                'correo' => $data['correo'],
                'telefono' => $telefono,
                'direccion' => $data['direccion'],
                'region' => $data['region'],
                'comuna' => $data['comuna'],
                'ciudad' => $data['comuna'],
            ];
            $cliente = $actualizarCliente->actualizarDesdeSolicitud(
                $datosCliente,
                'la solicitud de servicio',
            );

            return SolicitudWeb::create([
                'fecha' => now(),
                'estado' => 'Pendiente',
                'tipo_solicitud' => 'servicio',
                'tipo_servicio' => $data['tipo_servicio'],
                'descripcion_servicio' => $data['descripcion_servicio'],
                'id_cliente' => $cliente->id_cliente,
                'datos_cliente' => $datosCliente,
                'contacto' => $data['contacto'],
                'cargo' => $data['cargo'] ?? null,
                'detalles_productos' => [],
            ]);
        });

        $destinatarios = User::whereIn('rol', ['Administrador', 'Vendedor'])->get()
            ->filter(fn (User $user): bool => $user->tienePermiso('cotizaciones.ver'));
        $fallosNotificacion = $destinatarios->isEmpty();
        if ($fallosNotificacion) {
            Log::warning('La solicitud de servicio quedó registrada sin destinatarios para el correo interno.', [
                'solicitud_id' => $solicitud->id_solicitud,
            ]);
        }
        $notificacion = new SolicitudWebRecibida($solicitud);
        foreach ($destinatarios as $user) {
            try {
                $user->notify($notificacion->afterCommit());
            } catch (\Throwable $exception) {
                $fallosNotificacion = true;
                Log::warning('No fue posible enviar el correo interno de la solicitud de servicio.', [
                    'solicitud_id' => $solicitud->id_solicitud,
                    'usuario_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($destinatarios->isEmpty()) {
            return redirect()->route('servicios.index')->with(
                'warning',
                'Tu solicitud quedó registrada, pero no hay destinatarios disponibles para avisar al equipo. Contacta a RESMAP.'
            );
        }

        return redirect()->route('servicios.index')->with(
            $fallosNotificacion ? 'warning' : 'success',
            $fallosNotificacion
                ? 'Tu solicitud quedó registrada y el equipo recibió un aviso en el sistema, pero no se pudo enviar algún correo interno.'
                : 'Solicitud de servicio enviada. Te contactaremos pronto.'
        );
    }
}
