@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl">
    <div class="mb-8">
        <p class="text-sm font-bold uppercase tracking-[.25em] text-[#9f2f25]">Atención técnica RESMAP</p>
        <h1 class="mt-2 text-4xl font-black text-[#241817]">Agenda un servicio</h1>
        <p class="mt-3 max-w-2xl text-gray-600">Cuéntanos qué necesitas y nuestro equipo te contactará para coordinar la atención.</p>
    </div>
    @if(session('success'))<div class="mb-6 border-l-4 border-[#8f241d] bg-white p-5 text-[#8f241d] shadow-sm" role="status"><p class="font-black">Solicitud enviada</p><p class="mt-1 text-sm text-gray-600">{{ session('success') }}</p></div>@endif
    @if($errors->any())<div class="mb-6 border-l-4 border-red-700 bg-white p-5 text-red-800 shadow-sm" role="alert"><p class="font-black">Revisa los datos ingresados</p><ul class="mt-1 list-disc pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('servicios.store') }}" class="grid gap-8 bg-white p-6 shadow-sm md:grid-cols-2 md:p-8">
        @csrf
        <div class="md:col-span-2"><h2 class="text-xl font-black text-[#8f241d]">¿Qué servicio necesitas?</h2></div>
        <label class="block text-sm font-bold">Tipo de servicio <span class="text-[#b52f25]" aria-hidden="true">*</span><select name="tipo_servicio" required class="mt-2 w-full border border-[#d9aaa3] p-3"><option value="">Selecciona una opción</option>@foreach(['Mantención', 'Reparación', 'Soldadura'] as $tipo)<option value="{{ $tipo }}" @selected(old('tipo_servicio', $tipoServicioSeleccionado) === $tipo)>{{ $tipo }}</option>@endforeach</select></label>
        <label class="block text-sm font-bold md:col-span-2">Describe tu necesidad <span class="text-[#b52f25]" aria-hidden="true">*</span><textarea name="descripcion_servicio" required minlength="10" maxlength="3000" rows="5" placeholder="Ej.: mantención de maquinaria, soldadura de joystick..." class="mt-2 w-full border border-[#d9aaa3] p-3">{{ old('descripcion_servicio') }}</textarea></label>
        <div class="border-t border-[#e8c8c3] pt-6 md:col-span-2"><h2 class="text-xl font-black text-[#8f241d]">Tus datos</h2></div>
        <label class="block text-sm font-bold">RUT <span class="text-[#b52f25]" aria-hidden="true">*</span><input name="rut" value="{{ old('rut') }}" required placeholder="12345678-5" pattern="[0-9]{7,8}-[0-9Kk]" class="mt-2 w-full border border-[#d9aaa3] p-3"><span class="mt-1 block text-xs font-normal text-gray-500">Sin puntos y con guion.</span></label>
        <label class="block text-sm font-bold">Nombre o razón social <span class="text-[#b52f25]" aria-hidden="true">*</span><input name="nombre" value="{{ old('nombre') }}" required class="mt-2 w-full border border-[#d9aaa3] p-3"></label>
        <label class="block text-sm font-bold">Correo<input name="correo" type="email" value="{{ old('correo') }}" placeholder="correo@ejemplo.cl" class="mt-2 w-full border border-[#d9aaa3] p-3"></label>
        <div class="block text-sm font-bold"><label for="telefono">Teléfono <span class="text-[#b52f25]" aria-hidden="true">*</span></label><div class="mt-2 flex"><select id="prefijo_telefono" name="prefijo_telefono" required aria-label="Código de país"></select><input id="telefono" name="telefono" value="{{ old('telefono') }}" required pattern="[0-9]{8}" maxlength="8" inputmode="numeric" placeholder="12345678" class="min-w-0 flex-1 border border-[#d9aaa3] p-3"></div></div>
        <label class="block text-sm font-bold md:col-span-2">Dirección <span class="font-normal text-gray-500">(opcional)</span><input name="direccion" value="{{ old('direccion') }}" class="mt-2 w-full border border-[#d9aaa3] p-3"></label>
        <label class="block text-sm font-bold">Región <span class="text-[#b52f25]" aria-hidden="true">*</span><select id="region_solicitud" name="region" required class="mt-2 w-full border border-[#d9aaa3] bg-white p-3"><option value="">Selecciona una región</option></select></label>
        <label class="block text-sm font-bold">Comuna <span class="text-[#b52f25]" aria-hidden="true">*</span><select id="comuna_solicitud" name="comuna" required disabled class="mt-2 w-full border border-[#d9aaa3] bg-white p-3"><option value="">Selecciona una comuna</option></select></label>
        <div class="flex justify-end md:col-span-2"><button class="bg-[#b52f25] px-6 py-3 font-bold text-white hover:bg-[#8f241d]">Enviar solicitud de servicio</button></div>
    </form>
</div>
<script src="{{ asset('js/chile-location.js') }}"></script>
<script>
    inicializarSelectorTelefono('prefijo_telefono', 'telefono');
    inicializarUbicacionChile('region_solicitud', 'comuna_solicitud', @json(old('region')), @json(old('comuna')));
</script>
@endsection
