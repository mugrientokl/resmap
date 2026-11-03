@extends('layouts.app')

@section('content')
@php
    $estilosEtapa = [
        'completada' => ['circulo' => 'bg-green-600 text-white border-green-600', 'texto' => 'text-green-800'],
        'actual' => ['circulo' => 'bg-[#b52f25] text-white border-[#b52f25] ring-4 ring-[#f3c9c4]', 'texto' => 'text-[#9f2f25]'],
        'pendiente' => ['circulo' => 'bg-white text-gray-400 border-gray-300', 'texto' => 'text-gray-400'],
        'rechazada' => ['circulo' => 'bg-red-700 text-white border-red-700', 'texto' => 'text-red-800'],
        'omitida' => ['circulo' => 'bg-gray-100 text-gray-300 border-gray-200', 'texto' => 'text-gray-300'],
    ];
    $estilosEvento = [
        'exito' => ['punto' => 'bg-green-600', 'borde' => 'border-green-200 bg-green-50'],
        'info' => ['punto' => 'bg-[#b52f25]', 'borde' => 'border-[#ead8d5] bg-white'],
        'alerta' => ['punto' => 'bg-amber-500', 'borde' => 'border-amber-200 bg-amber-50'],
        'error' => ['punto' => 'bg-red-700', 'borde' => 'border-red-200 bg-red-50'],
    ];
    $espera = $datos['espera'];
    $esperaCliente = $espera['responsable'] === 'Cliente';
@endphp
<div class="mx-auto max-w-5xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('solicitudes.index') }}" class="text-sm font-bold text-[#9f2f25]">Volver a cotizaciones</a>
        <a href="{{ route('solicitudes.show', $solicitud->id_solicitud) }}" class="text-sm font-bold text-[#9f2f25] underline">Abrir detalle de la cotización</a>
    </div>

    <header class="mt-5 bg-white p-6 shadow-sm md:p-8">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-[#b52f25]">Seguimiento · Cotización #{{ $solicitud->id_solicitud }}</p>
        <h1 class="mt-2 text-3xl font-black text-[#241817]">{{ $solicitud->cliente->nombre }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $solicitud->cliente->rut_formateado }} · Recibida el {{ $solicitud->fecha?->format('d/m/Y H:i') }}</p>

        <div class="mt-6 grid gap-4 md:grid-cols-[1.4fr_1fr]">
            <div class="border-l-4 {{ $datos['cerrada'] ? 'border-gray-400 bg-gray-50' : 'border-amber-500 bg-amber-50' }} p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Estado actual</p>
                <p class="mt-1 text-lg font-black text-[#241817]">{{ $espera['titulo'] }}</p>
                <p class="mt-1 text-sm text-gray-700">{{ $espera['detalle'] }}</p>
            </div>
            <div class="border-l-4 {{ $esperaCliente ? 'border-sky-500 bg-sky-50' : 'border-[#b52f25] bg-[#fff1ef]' }} p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Esperando a</p>
                <p class="mt-1 text-lg font-black text-[#241817]">{{ $espera['responsable'] }}</p>
            </div>
        </div>
    </header>

    <section class="mt-6 bg-white p-6 shadow-sm md:p-8" aria-label="Etapas del proceso">
        <h2 class="text-lg font-black">Etapas del proceso</h2>
        <ol class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-7 lg:gap-2">
            @foreach($datos['etapas'] as $indice => $etapa)
                @php($estilo = $estilosEtapa[$etapa['estado']])
                <li class="flex items-center gap-3 lg:flex-col lg:text-center" @if($etapa['estado'] === 'actual') aria-current="step" @endif>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 text-sm font-black {{ $estilo['circulo'] }}">
                        @if($etapa['estado'] === 'completada') ✓ @elseif($etapa['estado'] === 'rechazada') ✕ @else {{ $indice + 1 }} @endif
                    </span>
                    <span class="text-xs font-bold {{ $estilo['texto'] }}">
                        {{ $etapa['titulo'] }}
                        <span class="block font-normal">{{ ['completada' => 'Completada', 'actual' => 'En curso', 'pendiente' => 'Pendiente', 'rechazada' => 'Rechazada', 'omitida' => 'No aplica'][$etapa['estado']] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="mt-6 bg-white p-6 shadow-sm md:p-8" aria-label="Línea de tiempo">
        <h2 class="text-lg font-black">Línea de tiempo</h2>
        <ol class="relative mt-6 ml-3 border-l-2 border-[#ead8d5]">
            @foreach($datos['eventos'] as $evento)
                @php($estilo = $estilosEvento[$evento['tipo']] ?? $estilosEvento['info'])
                <li class="relative mb-6 ml-6 last:mb-0">
                    <span class="absolute -left-[34px] top-4 h-4 w-4 rounded-full border-4 border-white {{ $estilo['punto'] }} ring-1 ring-[#ead8d5]"></span>
                    <div class="border p-4 {{ $estilo['borde'] }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-black text-[#241817]">{{ $evento['titulo'] }}</p>
                            <time class="text-xs font-bold text-gray-500">{{ $evento['fecha']?->format('d/m/Y H:i') ?? 'Fecha no registrada' }}</time>
                        </div>
                        @if($evento['detalle'])
                            <p class="mt-1 text-sm text-gray-700">{{ $evento['detalle'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
            @unless($datos['cerrada'] || collect($datos['etapas'])->every(fn ($etapa) => $etapa['estado'] === 'completada'))
                <li class="relative ml-6 mt-6">
                    <span class="absolute -left-[34px] top-4 h-4 w-4 rounded-full border-4 border-white bg-gray-300 ring-1 ring-[#ead8d5]"></span>
                    <div class="border border-dashed border-gray-300 p-4">
                        <p class="font-black text-gray-500">Próximo paso</p>
                        <p class="mt-1 text-sm text-gray-600">{{ $espera['titulo'] }}. Esperando a: {{ $espera['responsable'] }}.</p>
                    </div>
                </li>
            @endunless
        </ol>
    </section>
</div>
@endsection
