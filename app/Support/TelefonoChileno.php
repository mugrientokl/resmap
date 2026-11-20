<?php

namespace App\Support;

class TelefonoChileno
{
    public static function normalizar(?string $telefono): ?string
    {
        if ($telefono === null || trim($telefono) === '') {
            return null;
        }

        $telefono = trim($telefono);
        if (! preg_match('/^\+?[0-9\s().-]+$/', $telefono)) {
            return $telefono;
        }

        $digitos = preg_replace('/\D/', '', $telefono);
        if (preg_match('/^[0-9]{8}$/', $digitos) && ! str_starts_with($telefono, '+')) {
            return '+569'.$digitos;
        }
        if (preg_match('/^[2-9][0-9]{8}$/', $digitos) && ! str_starts_with($telefono, '+')) {
            return '+56'.$digitos;
        }
        if (preg_match('/^56[2-9][0-9]{8}$/', $digitos)) {
            return '+'.$digitos;
        }

        return $telefono;
    }
}
