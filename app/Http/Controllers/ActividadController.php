<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActividadFiltroRequest;
use App\Models\Auditoria;
use App\Models\User;
use App\Services\RegistroActividad;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ActividadController extends Controller
{
    public function __construct(private RegistroActividad $actividad) {}

    public function index(ActividadFiltroRequest $request): View
    {
        $filtros = $request->filtros();

        $registros = $this->consulta($filtros)
            ->paginate(config('actividad.por_pagina'))
            ->withQueryString();

        $modelos = Auditoria::query()->select('modelo')->distinct()->orderBy('modelo')->pluck('modelo')
            ->mapWithKeys(fn (string $modelo): array => [$modelo => config("actividad.modelos.{$modelo}.etiqueta") ?? class_basename($modelo)]);

        return view('actividad.index', [
            'registros' => $registros,
            'filtros' => $filtros,
            'categorias' => config('actividad.categorias'),
            'modelos' => $modelos,
            'acciones' => Auditoria::query()->select('accion')->distinct()->orderBy('accion')->pluck('accion'),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Auditoria $auditoria): View
    {
        $this->actividad->evento(
            categoria: 'sistema',
            accion: 'consulta_detalle',
            descripcion: "Consultó el detalle del registro de actividad #{$auditoria->getKey()}",
            modelo: 'Actividad',
            modeloId: $auditoria->getKey(),
        );

        $auditoria->load('usuario');

        return view('actividad.show', [
            'registro' => $auditoria,
            'lote' => $auditoria->lote_id
                ? Auditoria::where('lote_id', $auditoria->lote_id)->count()
                : null,
        ]);
    }

    public function exportar(ActividadFiltroRequest $request, string $formato)
    {
        abort_unless(in_array($formato, ['csv', 'pdf'], true), 404);

        $filtros = $request->filtros();
        $fechaGeneracion = now();
        $limite = $formato === 'pdf' ? config('actividad.maximo_pdf') : config('actividad.maximo_exportacion');
        $consulta = $this->consulta($filtros)->limit($limite);

        $this->actividad->evento(
            categoria: 'exportacion',
            accion: 'exportado',
            descripcion: 'Exportó el registro de actividad ('.strtoupper($formato).')',
            modelo: 'Actividad',
            nuevos: $filtros,
        );

        if ($formato === 'csv') {
            return response()->streamDownload(function () use ($consulta, $fechaGeneracion): void {
                $salida = fopen('php://output', 'w');
                fwrite($salida, "\xEF\xBB\xBF");
                fputcsv($salida, ['REGISTRO DE ACTIVIDAD'], ';');
                fputcsv($salida, ['Fecha de generación', $fechaGeneracion->format('d/m/Y H:i')], ';');
                fputcsv($salida, [], ';');
                fputcsv($salida, ['Fecha', 'Usuario', 'Categoría', 'Módulo', 'Acción', 'Resultado', 'Descripción', 'IP'], ';');

                foreach ($consulta->with('usuario')->cursor() as $registro) {
                    fputcsv($salida, array_map($this->neutralizarFormula(...), [
                        $registro->created_at?->format('d/m/Y H:i'),
                        $registro->nombre_actor,
                        $registro->etiqueta_categoria,
                        $registro->etiqueta_modelo,
                        $registro->accion,
                        $registro->resultado,
                        $registro->descripcion,
                        $registro->ip,
                    ]), ';');
                }

                fclose($salida);
            }, 'registro-actividad-'.$fechaGeneracion->format('Y-m-d').'.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return Pdf::loadView('actividad.pdf', [
            'registros' => $consulta->with('usuario')->get(),
            'filtros' => $filtros,
            'fechaGeneracion' => $fechaGeneracion,
            'limite' => $limite,
        ])->download('registro-actividad-'.$fechaGeneracion->format('Y-m-d').'.pdf');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function consulta(array $filtros): Builder
    {
        return Auditoria::query()
            ->with('usuario')
            ->filtrar($filtros)
            ->when(! isset($filtros['lote']), fn (Builder $query) => $query->where(function (Builder $q): void {
                $q->whereNull('lote_id')->orWhereIn('id_auditoria', fn ($sub) => $sub
                    ->from('auditorias')
                    ->select(DB::raw('MIN(id_auditoria)'))
                    ->whereNotNull('lote_id')
                    ->groupBy('lote_id'));
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id_auditoria');
    }

    /**
     * Evita que una celda se interprete como fórmula al abrir el CSV en Excel.
     */
    private function neutralizarFormula(mixed $valor): mixed
    {
        return is_string($valor) && preg_match('/^[=+\-@\t\r]/', $valor) ? "'".$valor : $valor;
    }
}
