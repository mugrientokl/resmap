<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RutChileno implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rut = strtoupper(preg_replace('/[^0-9K]/', '', (string) $value));
        if (! preg_match('/^[0-9]{7,8}[0-9K]$/', $rut)) {
            $fail('El RUT chileno no tiene un formato válido.');

            return;
        }

        $cuerpo = substr($rut, 0, -1);
        $verificador = substr($rut, -1);
        $multiplicador = 2;
        $suma = 0;

        for ($indice = strlen($cuerpo) - 1; $indice >= 0; $indice--) {
            $suma += (int) $cuerpo[$indice] * $multiplicador;
            $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
        }

        $resto = 11 - ($suma % 11);
        $calculado = match ($resto) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };

        if ($calculado !== $verificador) {
            $fail('El dígito verificador del RUT no es válido.');
        }
    }
}
