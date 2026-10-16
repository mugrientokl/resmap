<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Notifications\SolicitudWebRecibida;
use App\Rules\RutChileno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'rut' => ['required', 'string', 'max:20', 'regex:/^[0-9]{7,8}-[0-9Kk]$/', new RutChileno],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'prefijo_telefono' => ['required', 'regex:/^\+[1-9][0-9]{0,4}$/'],
            'telefono' => ['required', 'regex:/^[0-9]{8}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:100'],
            'comuna' => ['required', 'string', 'max:100'],
            'tipo_servicio' => ['required', 'in:Mantención,Reparación,Soldadura'],
            'descripcion_servicio' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'rut.required' => 'Escribe tu RUT sin puntos y con guion, por ejemplo: 12345678-5.',
            'rut.regex' => 'El RUT debe escribirse sin puntos y con guion, por ejemplo: 12345678-5.',
            'nombre.required' => 'Escribe tu nombre o razón social.',
            'correo.email' => 'Escribe un correo electrónico válido.',
            'telefono.required' => 'Escribe los 8 dígitos de tu teléfono.',
            'telefono.regex' => 'El teléfono debe contener exactamente 8 dígitos.',
            'tipo_servicio.required' => 'Selecciona el tipo de servicio.',
            'descripcion_servicio.required' => 'Describe el servicio que necesitas.',
            'descripcion_servicio.min' => 'Describe tu necesidad con al menos 10 caracteres.',
        ]);

        $telefono = $data['prefijo_telefono'].$data['telefono'];

        $solicitud = DB::transaction(function () use ($data, $telefono): SolicitudWeb {
            $cliente = Cliente::updateOrCreate(['rut' => $data['rut']], [
                'nombre' => $data['nombre'],
                'correo' => $data['correo'] ?? null,
                'telefono' => $telefono,
                'direccion' => $data['direccion'] ?? null,
                'region' => $data['region'],
                'comuna' => $data['comuna'],
                'ciudad' => $data['comuna'],
            ]);

            return SolicitudWeb::create([
                'fecha' => now(),
                'estado' => 'Pendiente',
                'tipo_solicitud' => 'servicio',
                'tipo_servicio' => $data['tipo_servicio'],
                'descripcion_servicio' => $data['descripcion_servicio'],
                'id_cliente' => $cliente->id_cliente,
                'detalles_productos' => [],
            ]);
        });

        User::whereIn('rol', ['Administrador', 'Vendedor'])->get()->each(fn (User $user) => $user->notify(new SolicitudWebRecibida($solicitud)));

        return redirect()->route('servicios.index')->with('success', 'Solicitud de servicio enviada. Te contactaremos pronto.');
    }
}
