<?php

namespace App\Support;

class Rut
{
    /**
     * Devuelve el RUT en formato canónico de almacenamiento: "12345678-5".
     * Acepta cualquier entrada (con o sin puntos, guion o espacios, K en minúscula).
     */
    public static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $limpio = strtoupper(preg_replace('/[^0-9kK]/', '', $valor) ?? '');

        if (strlen($limpio) < 2) {
            return $limpio === '' ? null : $limpio;
        }

        $cuerpo = ltrim(substr($limpio, 0, -1), '0');

        return ($cuerpo === '' ? '0' : $cuerpo).'-'.substr($limpio, -1);
    }

    /**
     * Devuelve el RUT para mostrar: "12.345.678-5" o "1.234.567-8".
     */
    public static function formatear(?string $valor): ?string
    {
        $normalizado = self::normalizar($valor);

        if ($normalizado === null || ! str_contains($normalizado, '-')) {
            return $normalizado;
        }

        [$cuerpo, $verificador] = explode('-', $normalizado);

        return number_format((int) $cuerpo, 0, ',', '.').'-'.$verificador;
    }
}
