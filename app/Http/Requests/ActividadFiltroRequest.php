<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActividadFiltroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('ver-actividad');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'categoria' => ['nullable', 'string', Rule::in(array_keys(config('actividad.categorias')))],
            'accion' => ['nullable', 'string', 'max:40'],
            'modelo' => ['nullable', 'string', 'max:120'],
            'usuario' => ['nullable', 'integer', 'min:1'],
            'registro' => ['nullable', 'integer', 'min:1'],
            'resultado' => ['nullable', 'string', Rule::in(['exito', 'fallo'])],
            'lote' => ['nullable', 'uuid'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'formato' => ['nullable', Rule::in(['csv', 'pdf'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        return array_filter(
            $this->safe()->except('formato'),
            fn (mixed $valor): bool => $valor !== null && $valor !== ''
        );
    }
}
