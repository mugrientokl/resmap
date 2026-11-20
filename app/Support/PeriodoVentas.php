<?php

namespace App\Support;

use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PeriodoVentas
{
    /** @return array{anio: int, mes: ?int, desde: Carbon, hasta: Carbon} */
    public static function resolver(Request $request): array
    {
        $datos = $request->query();
        if (isset($datos['mes']) && is_string($datos['mes']) && preg_match('/^(\d{4})-(\d{2})$/', $datos['mes'], $partes)) {
            abort_if(isset($datos['anio']) && (string) $datos['anio'] !== $partes[1], 422, 'El mes no corresponde al año seleccionado.');
            $datos['anio'] ??= $partes[1];
            $datos['mes'] = (int) $partes[2];
        }

        $validados = Validator::make($datos, [
            'anio' => ['nullable', 'integer', 'between:1900,9999'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ])->validate();

        $anio = (int) ($validados['anio'] ?? now()->year);
        $mes = isset($validados['mes']) && $validados['mes'] !== '' ? (int) $validados['mes'] : null;
        $inicio = Carbon::create($anio, $mes ?? 1, 1, 0, 0, 0);

        return [
            'anio' => $anio,
            'mes' => $mes,
            'desde' => $inicio,
            'hasta' => $mes === null ? $inicio->copy()->endOfYear() : $inicio->copy()->endOfMonth(),
        ];
    }

    /** @return array<int, int> */
    public static function aniosDisponibles(): array
    {
        return Venta::completadas()
            ->whereNotNull('fecha')
            ->selectRaw('DISTINCT SUBSTR(CAST(fecha AS CHAR(32)), 1, 4) as anio')
            ->pluck('anio')
            ->map(fn ($anio): int => (int) $anio)
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
