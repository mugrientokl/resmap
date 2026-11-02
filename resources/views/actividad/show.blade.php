@extends('layouts.app')

@section('content')
@php
    $anteriores = $registro->datos_anteriores ?? [];
    $nuevos = $registro->datos_nuevos ?? [];
    $campos = array_unique(array_merge(array_keys($anteriores), array_keys($nuevos)));
    $formato = fn (mixed $valor): string => is_null($valor) ? '—' : (is_scalar($valor) ? (string) $valor : json_encode($valor, JSON_UNESCAPED_UNICODE));
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Registro de Actividad</p>
            <h1 class="text-3xl font-black text-gray-900">Detalle del evento #{{ $registro->id_auditoria }}</h1>
        </div>
        <a href="{{ route('actividad.index') }}" class="rounded-md border border-[#b52f25] px-4 py-2 font-bold text-[#9f2f25] hover:bg-[#f7e8e6]">Volver al registro</a>
    </div>

    <section class="grid gap-4 rounded-lg bg-white p-5 text-sm shadow-sm md:grid-cols-3">
        <div><span class="text-gray-500">Fecha</span><strong class="block">{{ $registro->created_at->format('d/m/Y H:i:s') }}</strong></div>
        <div><span class="text-gray-500">Usuario</span><strong class="block">{{ $registro->nombre_actor }}</strong></div>
        <div><span class="text-gray-500">Resultado</span><strong class="block">{{ $registro->resultado === 'fallo' ? 'Fallo' : 'Éxito' }}</strong></div>
        <div><span class="text-gray-500">Categoría</span><strong class="block">{{ $registro->etiqueta_categoria }}</strong></div>
        <div><span class="text-gray-500">Módulo</span><strong class="block">{{ $registro->etiqueta_modelo }}@if($registro->modelo_id) #{{ $registro->modelo_id }}@endif</strong></div>
        <div><span class="text-gray-500">Acción</span><strong class="block">{{ ucfirst(str_replace('_', ' ', $registro->accion)) }}</strong></div>
        <div class="md:col-span-3"><span class="text-gray-500">Descripción</span><strong class="block">{{ $registro->descripcion ?? '—' }}</strong></div>
        <div><span class="text-gray-500">Dirección IP</span><strong class="block">{{ $registro->ip ?? '—' }}</strong></div>
        <div class="md:col-span-2"><span class="text-gray-500">Navegador</span><strong class="block break-all">{{ $registro->user_agent ?? '—' }}</strong></div>
        <div><span class="text-gray-500">ID de solicitud</span><strong class="block break-all">{{ $registro->correlation_id ?? '—' }}</strong></div>
        @if($registro->lote_id)
            <div class="md:col-span-2"><span class="text-gray-500">Lote</span><strong class="block"><a class="text-[#9f2f25] underline" href="{{ route('actividad.index', ['lote' => $registro->lote_id]) }}">Ver los {{ $lote }} registros del lote</a></strong></div>
        @endif
        <div class="md:col-span-3"><span class="text-gray-500">Integridad</span><strong class="block">{{ $registro->hash ? 'Protegido con firma encadenada' : 'Anterior al control de integridad' }}</strong></div>
    </section>

    @if($campos !== [])
        <section class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <div class="border-b border-gray-100 p-5"><h2 class="text-lg font-black">Cambios registrados</h2></div>
            <table class="min-w-full text-left text-sm">
                <thead class="bg-[#f7e8e6] text-xs uppercase text-[#8f241d]"><tr><th class="px-5 py-3">Campo</th><th class="px-5 py-3">Antes</th><th class="px-5 py-3">Después</th></tr></thead>
                <tbody class="divide-y">
                    @foreach($campos as $campo)
                        <tr><td class="px-5 py-3 font-bold">{{ $campo }}</td><td class="break-all px-5 py-3">{{ $formato($anteriores[$campo] ?? null) }}</td><td class="break-all px-5 py-3">{{ $formato($nuevos[$campo] ?? null) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</div>
@endsection
