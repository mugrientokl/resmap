<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CorreoConDominio implements ValidationRule
{
    public const PATRON = '.+@.+\.(?:[A-Za-z]{2,63}|[xX][nN]--[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*)';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/\A(?:'.self::PATRON.')\z/', $value)) {
            $fail('Ingresa un correo electrónico con un dominio completo, por ejemplo nombre@ejemplo.cl.');
        }
    }
}
