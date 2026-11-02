@extends('layouts.app')

@section('content')
@php
    $etiquetaAccion = fn (string $accion): string => ucfirst(str_replace('_', ' ', $accion));
    $colorCategoria = ['seguridad' => 'bg-red-100 text-red-800', 'importacion' => 'bg-amber-100 text-amber-800', 'exportacion' => 'bg-blue-100 text-blue-800', 'sistema' => 'bg-gray-100 text-gray-700', 'datos' => 'bg-emerald-100 text-emerald-800'];
@endphp
<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Seguridad y trazabilidad</p>
            <h1 class="text-3xl font-black text-gray-900">Registro de Actividad</h1>
            <p class="mt-1 text-sm text-gray-500">Quién hizo qué y cuándo en el sistema. Los registros son de solo lectura.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('actividad.exportar', ['formato' => 'csv'] + request()->query()) }}" class="rounded-md border border-[#b52f25] px-4 py-2 font-bold text-[#9f2f25]">Exportar Excel</a>
            <a href="{{ route('actividad.exportar', ['formato' => 'pdf'] + request()->query()) }}" class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Exportar PDF</a>
        </div>
    </header>

    @if($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('actividad.index') }}" class="grid gap-3 rounded-lg bg-white p-5 shadow-sm md:grid-cols-3 lg:grid-cols-4">
        <select name="categoria" class="rounded-md border p-2" aria-label="Categoría"><option value="">Todas las categorías</option>@foreach($categorias as $clave => $nombre)<option value="{{ $clave }}" @selected(($filtros['categoria'] ?? null) === $clave)>{{ $nombre }}</option>@endforeach</select>
        <select name="modelo" class="rounded-md border p-2" aria-label="Módulo"><option value="">Todos los módulos</option>@foreach($modelos as $clave => $nombre)<option value="{{ $clave }}" @selected(($filtros['modelo'] ?? null) === $clave)>{{ $nombre }}</option>@endforeach</select>
        <select name="accion" class="rounded-md border p-2" aria-label="Acción"><option value="">Todas las acciones</option>@foreach($acciones as $accion)<option value="{{ $accion }}" @selected(($filtros['accion'] ?? null) === $accion)>{{ $etiquetaAccion($accion) }}</option>@endforeach</select>
        <select name="usuario" class="rounded-md border p-2" aria-label="Usuario"><option value="">Todos los usuarios</option>@foreach($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected((string) ($filtros['usuario'] ?? '') === (string) $usuario->id)>{{ $usuario->name }}</option>@endforeach</select>
        <select name="resultado" class="rounded-md border p-2" aria-label="Resultado"><option value="">Cualquier resultado</option><option value="exito" @selected(($filtros['resultado'] ?? null) === 'exito')>Éxito</option><option value="fallo" @selected(($filtros['resultado'] ?? null) === 'fallo')>Fallo</option></select>
        <input type="number" min="1" name="registro" value="{{ $filtros['registro'] ?? '' }}" placeholder="ID de registro" class="rounded-md border p-2" aria-label="ID de registro">
        <input type="date" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="rounded-md border p-2" aria-label="Desde">
        <input type="date" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="rounded-md border p-2" aria-label="Hasta">
        <div class="flex gap-2 lg:col-start-4">
            <button class="flex-1 rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Filtrar</button>
            <a href="{{ route('actividad.index') }}" class="rounded-md border px-4 py-2 font-bold text-gray-600">Limpiar</a>
        </div>
    </form>

    <section class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-[#f7e8e6] text-xs uppercase text-[#8f241d]"><tr><th class="px-5 py-3">Fecha</th><th class="px-5 py-3">Usuario</th><th class="px-5 py-3">Categoría</th><th class="px-5 py-3">Módulo</th><th class="px-5 py-3">Descripción</th><th class="px-5 py-3">Resultado</th><th class="px-5 py-3"></th></tr></thead>
            <tbody class="divide-y">
                @forelse($registros as $registro)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3">{{ $registro->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-5 py-3">{{ $registro->nombre_actor }}</td>
                        <td class="px-5 py-3"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $colorCategoria[$registro->categoria] ?? $colorCategoria['sistema'] }}">{{ $registro->etiqueta_categoria }}</span></td>
                        <td class="px-5 py-3">{{ $registro->etiqueta_modelo }}</td>
                        <td class="px-5 py-3">{{ $registro->descripcion ?? $etiquetaAccion($registro->accion).' #'.($registro->modelo_id ?? '—') }}</td>
                        <td class="px-5 py-3 font-bold {{ $registro->resultado === 'fallo' ? 'text-red-700' : 'text-emerald-700' }}">{{ $registro->resultado === 'fallo' ? 'Fallo' : 'Éxito' }}</td>
                        <td class="px-5 py-3"><a href="{{ route('actividad.show', $registro) }}" class="font-bold text-[#9f2f25]">Ver detalle</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-8 text-center text-gray-500">No hay actividad para los filtros seleccionados.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-5">{{ $registros->links() }}</div>
    </section>
</div>
@endsection
