@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl">
    <p class="text-sm font-bold uppercase tracking-[.25em] text-[#9f2f25]">Atención técnica RESMAP</p>
    <h1 class="mt-2 text-4xl font-black text-[#241817]">Agenda un servicio</h1>
    <p class="mt-3 max-w-2xl text-gray-600">Cuéntanos qué necesitas y nuestro equipo te contactará para coordinar la atención.</p>
    <a href="{{ route('servicios.index') }}" data-open-servicio class="mt-6 inline-flex bg-[#b52f25] px-6 py-3 font-bold text-white hover:bg-[#8f241d]">Solicitar servicio</a>
</div>
@endsection
