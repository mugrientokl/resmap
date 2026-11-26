@php($erroresServicio = $errors->any() && (old('_formulario') === 'servicio' || (request()->routeIs('servicios.index') && ! old('_formulario'))))
@php($abrirServicio = $erroresServicio || (request()->routeIs('servicios.index') && ! session()->has('success') && ! session()->has('warning')))
<div id="servicio-modal" class="solicitud-modal fixed inset-0 z-50 overflow-y-auto bg-black/60 p-4 {{ $abrirServicio ? '' : 'hidden' }}"
    role="dialog" aria-modal="true" aria-labelledby="servicio-titulo">
    <div class="solicitud-panel mx-auto mt-8 max-w-3xl">
        <div class="solicitud-header flex items-center justify-between border-b p-5">
            <h2 id="servicio-titulo" class="solicitud-title text-2xl font-black">Agenda un servicio</h2>
            <button type="button" class="solicitud-close text-2xl" aria-label="Cerrar formulario de servicio">&times;</button>
        </div>
        <p class="px-6 pt-5 text-base text-[#65504c]">Cuéntanos qué necesitas y nuestro equipo te contactará para coordinar la atención.</p>
        @if($erroresServicio)<div class="mx-6 mt-5 rounded-lg border-l-4 border-red-700 bg-red-50 p-4 text-red-800" role="alert"><p class="font-black">Revisa los datos ingresados</p><ul class="mt-1 list-disc pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('servicios.store') }}" class="grid gap-5 p-6 sm:grid-cols-2">
        @csrf
        <input type="hidden" name="_formulario" value="servicio">
        <p class="text-sm text-[#65504c] sm:col-span-2">* Campo obligatorio</p>
        <div class="sm:col-span-2"><h3 class="text-xl font-black text-[#8f241d]">¿Qué servicio necesitas?</h3></div>
        <label class="block text-sm font-bold sm:col-span-2">Tipo de servicio <span class="text-[#b52f25]" aria-hidden="true">*</span><select name="tipo_servicio" required class="mt-2 w-full border border-[#d9aaa3] p-3"><option value="">Selecciona una opción</option>@foreach(['Mantención', 'Reparación', 'Soldadura'] as $tipo)<option value="{{ $tipo }}" @selected(old('tipo_servicio', $tipoServicioSeleccionado ?? null) === $tipo)>{{ $tipo }}</option>@endforeach</select></label>
        <label class="block text-sm font-bold sm:col-span-2">Describe tu necesidad <span class="text-[#b52f25]" aria-hidden="true">*</span><textarea id="descripcion-servicio" name="descripcion_servicio" required minlength="10" maxlength="3000" rows="4" placeholder="Ej.: mantención de maquinaria, soldadura de joystick..." class="mt-2 w-full border border-[#d9aaa3] p-3">{{ old('descripcion_servicio') }}</textarea><span id="descripcion-servicio-contador" class="mt-1 block text-right text-sm font-normal"></span></label>
        <div class="border-t border-[#e8c8c3] pt-5 sm:col-span-2"><h3 class="text-xl font-black text-[#8f241d]">Tus datos</h3></div>
        <label class="block text-sm font-bold sm:col-span-2">Tipo de cliente <span class="text-[#b52f25]" aria-hidden="true">*</span><select id="tipo_cliente_servicio" name="tipo_cliente" required class="mt-2 w-full border border-[#d9aaa3] bg-white p-3"><option value="Persona" @selected(old('tipo_cliente', 'Persona') === 'Persona')>Persona</option><option value="Empresa" @selected(old('tipo_cliente', 'Persona') === 'Empresa')>Empresa</option></select></label>
        <label class="block text-sm font-bold"><span id="tipo-identificacion-servicio">{{ old('tipo_cliente', 'Persona') === 'Empresa' ? 'RUT' : 'RUN' }}</span> <span class="text-[#b52f25]" aria-hidden="true">*</span><input name="rut" value="{{ old('rut') }}" required placeholder="12.345.678-5" data-rut maxlength="12" class="mt-2 w-full border border-[#d9aaa3] p-3"><span class="mt-1 block text-xs font-normal text-gray-500">Puedes escribirlo con o sin puntos y guion.</span></label>
        <label class="block text-sm font-bold">Nombre o razón social <span class="text-[#b52f25]" aria-hidden="true">*</span><input name="nombre" value="{{ old('nombre') }}" required class="mt-2 w-full border border-[#d9aaa3] p-3"></label>
        <label id="giro_servicio_wrapper" class="hidden block text-sm font-bold">Giro <span class="text-[#b52f25]" aria-hidden="true">*</span><input id="giro_servicio" name="giro" value="{{ old('giro') }}" maxlength="255" placeholder="Ej.: comercio de repuestos" class="mt-2 w-full border border-[#d9aaa3] p-3"></label>
        <label class="block text-sm font-bold">Contacto <span class="text-[#b52f25]" aria-hidden="true">*</span><input name="contacto" value="{{ old('contacto') }}" required maxlength="255" placeholder="Persona o responsable" class="mt-2 w-full border border-[#d9aaa3] p-3"></label>
        <label class="block text-sm font-bold">Cargo o área <span class="font-normal text-gray-500">(opcional)</span><input name="cargo" value="{{ old('cargo') }}" maxlength="255" placeholder="Ej.: OT, compras o administración" class="mt-2 w-full border border-[#d9aaa3] p-3"></label>
        <label class="block text-sm font-bold">Correo <span class="text-[#b52f25]" aria-hidden="true">*</span><input name="correo" type="email" value="{{ old('correo') }}" required maxlength="255" pattern="{{ \App\Rules\CorreoConDominio::PATRON }}" title="Ingresa un correo con dominio completo, por ejemplo nombre@ejemplo.cl" placeholder="correo@ejemplo.cl" class="mt-2 w-full border border-[#d9aaa3] p-3"></label>
        <div class="block text-sm font-bold"><label for="telefono_servicio">Teléfono <span class="text-[#b52f25]" aria-hidden="true">*</span></label><div class="mt-2 flex"><select id="prefijo_telefono_servicio" name="prefijo_telefono" required aria-label="Código de país"></select><input id="telefono_servicio" name="telefono" value="{{ old('telefono') }}" required pattern="[2-9][0-9]{8}" maxlength="9" inputmode="numeric" placeholder="912345678" class="min-w-0 flex-1 border border-[#d9aaa3] p-3"></div></div>
        <label class="block text-sm font-bold sm:col-span-2">Dirección <span class="text-[#b52f25]" aria-hidden="true">*</span><input name="direccion" value="{{ old('direccion') }}" required maxlength="255" class="mt-2 w-full border border-[#d9aaa3] p-3"></label>
        <label class="block text-sm font-bold">Región <span class="text-[#b52f25]" aria-hidden="true">*</span><select id="region_servicio" name="region" required class="mt-2 w-full border border-[#d9aaa3] bg-white p-3"><option value="">Selecciona una región</option></select></label>
        <label class="block text-sm font-bold">Comuna <span class="text-[#b52f25]" aria-hidden="true">*</span><select id="comuna_servicio" name="comuna" required disabled class="mt-2 w-full border border-[#d9aaa3] bg-white p-3"><option value="">Selecciona una comuna</option></select></label>
        <button type="submit" class="bg-[#b52f25] px-6 py-3 font-bold text-white transition hover:bg-[#8f241d] sm:col-span-2">Enviar solicitud de servicio</button>
    </form>
    </div>
</div>
@if(request()->routeIs('servicios.index'))
<script src="{{ asset('js/rut.js') }}"></script>
<script src="{{ asset('js/chile-location.js') }}"></script>
@endif
<script>
(() => {
    const modal = document.getElementById('servicio-modal');
    const cerrar = modal.querySelector('.solicitud-close');
    let origen = null;
    let overflowAnterior = '';
    const abrir = (elemento) => {
        origen = elemento;
        overflowAnterior = document.body.style.overflow;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        cerrar.focus();
    };
    const cerrarModal = () => {
        modal.classList.add('hidden');
        document.body.style.overflow = overflowAnterior;
        origen?.focus();
    };
    document.addEventListener('click', event => {
        const enlace = event.target.closest('[data-open-servicio]');
        if (!enlace || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const menu = document.getElementById('public-menu');
        const toggle = document.getElementById('public-menu-toggle');
        const menuAbierto = menu?.classList.contains('is-open');
        menu?.classList.remove('is-open');
        toggle?.setAttribute('aria-expanded', 'false');
        toggle?.setAttribute('aria-label', 'Abrir menú');
        abrir(menuAbierto && menu.contains(enlace) ? toggle : enlace);
    });
    cerrar.addEventListener('click', cerrarModal);
    modal.addEventListener('click', event => {
        if (event.target === modal) cerrarModal();
    });
    modal.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            cerrarModal();
        }
        if (event.key !== 'Tab') return;
        const controles = [...modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])')]
            .filter(elemento => elemento.getClientRects().length);
        const primero = controles[0];
        const ultimo = controles[controles.length - 1];
        if (event.shiftKey && document.activeElement === primero) {
            event.preventDefault();
            ultimo.focus();
        } else if (!event.shiftKey && document.activeElement === ultimo) {
            event.preventDefault();
            primero.focus();
        }
    });
    if (!modal.classList.contains('hidden')) abrir(null);

    inicializarSelectorTelefono('prefijo_telefono_servicio', 'telefono_servicio');
    document.getElementById('telefono_servicio').value = window.telefonoNacionalChile(document.getElementById('telefono_servicio').value);
    inicializarUbicacionChile('region_servicio', 'comuna_servicio', @json(old('region')), @json(old('comuna')));
    const tipoCliente = document.getElementById('tipo_cliente_servicio');
    const giroWrapper = document.getElementById('giro_servicio_wrapper');
    const giro = document.getElementById('giro_servicio');
    const actualizarTipoCliente = () => {
        const esEmpresa = tipoCliente?.value === 'Empresa';
        document.getElementById('tipo-identificacion-servicio').textContent = esEmpresa ? 'RUT' : 'RUN';
        giroWrapper?.classList.toggle('hidden', !esEmpresa);
        if (giro) giro.required = esEmpresa;
    };
    tipoCliente?.addEventListener('change', actualizarTipoCliente);
    actualizarTipoCliente();
    const descripcion = document.getElementById('descripcion-servicio');
    const contador = document.getElementById('descripcion-servicio-contador');
    const actualizarContador = () => { contador.textContent = `${descripcion.value.length} / ${descripcion.maxLength} caracteres`; };
    descripcion.addEventListener('input', actualizarContador);
    actualizarContador();
})();
</script>
