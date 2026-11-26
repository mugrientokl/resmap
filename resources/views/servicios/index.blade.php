@extends('layouts.app')

@section('content')
<div class="resmap-glass mx-auto max-w-5xl overflow-hidden rounded-[2rem] border p-6 sm:p-10">
    <p class="text-sm font-black uppercase tracking-[.25em] text-[#9f2f25]">Atención técnica RESMAP</p>
    <h1 class="mt-3 text-4xl font-black text-[#241817] sm:text-5xl">Servicio especializado para tu maquinaria</h1>
    <p class="mt-5 max-w-2xl text-lg leading-8 text-[#65504c]">Selecciona el tipo de atención que necesitas y completa una solicitud coordinada por nuestro equipo técnico.</p>
    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        @foreach([
            'Mantención' => 'Prevención e inspección para mantener tu equipo operativo.',
            'Reparación' => 'Diagnóstico y corrección de averías mecánicas o técnicas.',
            'Soldadura' => 'Refuerzo y reparación de estructuras y piezas metálicas.',
        ] as $tipo => $descripcion)
            <article class="rounded-[1.25rem] border border-[#e8c8c3] bg-white/55 p-5 backdrop-blur-xl">
                <h2 class="text-xl font-black text-[#8f241d]">{{ $tipo }}</h2>
                <p class="mt-2 text-sm leading-6 text-[#65504c]">{{ $descripcion }}</p>
            </article>
        @endforeach
    </div>
    <a href="{{ route('servicios.index') }}" data-open-servicio class="mt-8 inline-flex min-h-14 items-center justify-center rounded-xl bg-[#b52f25] px-7 py-3 font-black text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-[#8f241d]">Solicitar servicio</a>
</div>
@endsection
