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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

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
            'correo' => ['nullable', 'email', 'max:255'],
            'prefijo_telefono' => ['required', 'regex:/^\+[1-9][0-9]{0,4}$/'],
            'telefono' => ['required', 'regex:/^[0-9]{8}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'comuna' => ['required', 'string', 'max:100'],
            'tipo_servicio' => ['required', 'in:Mantención,Reparación,Soldadura'],
            'descripcion_servicio' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'rut.required' => 'Escribe tu RUT, por ejemplo: 12.345.678-5.',
            'nombre.required' => 'Escribe tu nombre o razón social.',
            'correo.email' => 'Escribe un correo electrónico válido.',
            'telefono.required' => 'Escribe los 8 dígitos de tu teléfono.',
            'telefono.regex' => 'El teléfono debe contener exactamente 8 dígitos.',
            'tipo_servicio.required' => 'Selecciona el tipo de servicio.',
            'descripcion_servicio.required' => 'Describe el servicio que necesitas.',
            'descripcion_servicio.min' => 'Describe tu necesidad con al menos 10 caracteres.',
        ]);

        $telefono = $data['prefijo_telefono'].$data['telefono'];

        $solicitud = DB::transaction(function () use ($data, $telefono, $actualizarCliente): SolicitudWeb {
            $cliente = $actualizarCliente->actualizarDesdeSolicitud([
                'rut' => $data['rut'],
                'nombre' => $data['nombre'],
                'contacto' => $data['contacto'],
                'correo' => $data['correo'] ?? null,
                'telefono' => $telefono,
                'direccion' => $data['direccion'] ?? null,
                'region' => $data['region'],
                'comuna' => $data['comuna'],
                'ciudad' => $data['comuna'],
            ], 'la solicitud de servicio');

            return SolicitudWeb::create([
                'fecha' => now(),
                'estado' => 'Pendiente',
                'tipo_solicitud' => 'servicio',
                'tipo_servicio' => $data['tipo_servicio'],
                'descripcion_servicio' => $data['descripcion_servicio'],
                'id_cliente' => $cliente->id_cliente,
                'datos_cliente' => $cliente->snapshotDatos(),
                'contacto' => $data['contacto'],
                'cargo' => $data['cargo'] ?? null,
                'detalles_productos' => [],
            ]);
        });

        $destinatarios = User::whereIn('rol', ['Administrador', 'Vendedor'])->get();
        $fallosNotificacion = $destinatarios->isEmpty();
        if ($fallosNotificacion) {
            Log::warning('La solicitud de servicio quedó registrada sin destinatarios para el correo interno.', [
                'solicitud_id' => $solicitud->id_solicitud,
            ]);
        }
        foreach ($destinatarios as $user) {
            try {
                $user->notify(new SolicitudWebRecibida($solicitud));
            } catch (TransportExceptionInterface $exception) {
                $fallosNotificacion = true;
                Log::warning('La solicitud de servicio quedó registrada, pero falló el correo interno.', [
                    'solicitud_id' => $solicitud->id_solicitud,
                    'usuario_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return redirect()->route('servicios.index')->with(
            $fallosNotificacion ? 'warning' : 'success',
            $fallosNotificacion
                ? 'Tu solicitud quedó registrada, pero no se pudo enviar algún aviso al equipo. Contacta a RESMAP.'
                : 'Solicitud de servicio enviada. Te contactaremos pronto.'
        );
    }
}
