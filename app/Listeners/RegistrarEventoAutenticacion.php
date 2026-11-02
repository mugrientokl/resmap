<?php

namespace App\Listeners;

use App\Services\RegistroActividad;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

class RegistrarEventoAutenticacion
{
    public function __construct(private RegistroActividad $actividad) {}

    public function login(Login $evento): void
    {
        $this->registrar('inicio_sesion', 'Inició sesión', $evento->user);
    }

    public function logout(Logout $evento): void
    {
        if ($evento->user !== null) {
            $this->registrar('cierre_sesion', 'Cerró sesión', $evento->user);
        }
    }

    public function passwordReset(PasswordReset $evento): void
    {
        $this->registrar('restablecimiento_clave', 'Restableció su contraseña', $evento->user);
    }

    private function registrar(string $accion, string $descripcion, mixed $usuario): void
    {
        $this->actividad->evento(
            categoria: 'seguridad',
            accion: $accion,
            descripcion: $descripcion,
            modelo: 'Autenticación',
            modeloId: (int) $usuario->getAuthIdentifier(),
            userId: (int) $usuario->getAuthIdentifier(),
            actorNombre: $usuario->name ?? null,
        );
    }
}
