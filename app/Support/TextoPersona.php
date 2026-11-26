<?php

namespace App\Support;

class TextoPersona
{
    public static function normalizar(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }

        $texto = preg_replace('/\s+/u', ' ', trim($texto)) ?? trim($texto);
        if ($texto === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower($texto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    public static function normalizarLista(?string $texto): ?string
    {
        return self::normalizar($texto);
    }
}
