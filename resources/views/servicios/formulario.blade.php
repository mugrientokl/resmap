@php($erroresServicio = $errors->any() && (old('_formulario') === 'servicio' || (request()->routeIs('servicios.index') && ! old('_formulario'))))
@php($abrirServicio = $erroresServicio || old('tipo_servicio') || ($tipoServicioSeleccionado ?? null))
<div id="servicio-seleccion-modal" class="solicitud-modal fixed inset-0 z-50 hidden overflow-y-auto bg-black/60 p-4"
    role="dialog" aria-modal="true" aria-labelledby="servicio-seleccion-titulo">
    <div class="servicio-seleccion-panel mx-auto my-6 max-w-6xl overflow-hidden rounded-[2rem] border border-[#e1aaa2] bg-white/70 shadow-2xl backdrop-blur-2xl">
        <div class="flex items-start justify-between border-b border-[#e8c8c3] px-6 py-5 sm:px-8">
            <div>
                <p class="text-xs font-black uppercase tracking-[.22em] text-[#a84a40]">Atención técnica RESMAP</p>
                <h2 id="servicio-seleccion-titulo" class="mt-2 text-3xl font-black text-[#241817] sm:text-4xl">Solicita alguno de nuestros servicios</h2>
            </div>
            <button type="button" class="servicio-seleccion-cerrar flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/70 text-3xl text-[#8f241d] shadow-sm transition hover:bg-[#f7e8e6]" aria-label="Cerrar selección de servicio">&times;</button>
        </div>
        <div class="grid gap-5 p-5 sm:grid-cols-3 sm:p-8">
            <button type="button" data-seleccionar-servicio="Mantención" class="servicio-opcion group flex min-h-72 flex-col rounded-[1.5rem] border border-[#e8c8c3] bg-white/55 p-6 text-left shadow-[0_14px_35px_rgba(100,40,35,.10)] backdrop-blur-xl transition duration-300 hover:-translate-y-2 hover:border-[#f08c80] hover:bg-white/80 hover:shadow-[0_20px_45px_rgba(143,36,29,.20)]">
                <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#8f241d] text-white shadow-lg" aria-hidden="true">
                    <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 1.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM7 3h10v4H7z"/></svg>
                </span>
                <span class="mt-6 text-2xl font-black text-[#8f241d]">Mantención</span>
                <span class="mt-3 text-base leading-7 text-[#65504c]">Prevención programada, inspección de sistemas y puesta a punto para reducir fallas y prolongar la vida útil de tu maquinaria.</span>
                <span class="mt-auto pt-6 text-base font-black text-[#b52f25]">Elegir mantención <span aria-hidden="true">→</span></span>
            </button>
            <button type="button" data-seleccionar-servicio="Reparación" class="servicio-opcion group flex min-h-72 flex-col rounded-[1.5rem] border border-[#e8c8c3] bg-white/55 p-6 text-left shadow-[0_14px_35px_rgba(100,40,35,.10)] backdrop-blur-xl transition duration-300 hover:-translate-y-2 hover:border-[#f08c80] hover:bg-white/80 hover:shadow-[0_20px_45px_rgba(143,36,29,.20)]">
                <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#b52f25] text-white shadow-lg" aria-hidden="true">
                    <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a4 4 0 0 0-5 5L4 17l3 3 5.7-5.7a4 4 0 0 0 5-5l-3 3-3-3 3-3Z"/></svg>
                </span>
                <span class="mt-6 text-2xl font-black text-[#8f241d]">Reparación</span>
                <span class="mt-3 text-base leading-7 text-[#65504c]">Diagnóstico y corrección de averías mecánicas, hidráulicas o técnicas para devolver el equipo a operación con seguridad.</span>
                <span class="mt-auto pt-6 text-base font-black text-[#b52f25]">Elegir reparación <span aria-hidden="true">→</span></span>
            </button>
            <button type="button" data-seleccionar-servicio="Soldadura" class="servicio-opcion group flex min-h-72 flex-col rounded-[1.5rem] border border-[#e8c8c3] bg-white/55 p-6 text-left shadow-[0_14px_35px_rgba(100,40,35,.10)] backdrop-blur-xl transition duration-300 hover:-translate-y-2 hover:border-[#f08c80] hover:bg-white/80 hover:shadow-[0_20px_45px_rgba(143,36,29,.20)]">
                <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#721d18] text-white shadow-lg" aria-hidden="true">
                    <svg class="h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="m14 3-7 9h5l-2 9 7-10h-5l2-8Z"/></svg>
                </span>
                <span class="mt-6 text-2xl font-black text-[#8f241d]">Soldadura</span>
                <span class="mt-3 text-base leading-7 text-[#65504c]">Refuerzo, reparación y fabricación de piezas o estructuras metálicas especializadas para trabajo intenso y exigente.</span>
                <span class="mt-auto pt-6 text-base font-black text-[#b52f25]">Elegir soldadura <span aria-hidden="true">→</span></span>
            </button>
        </div>
    </div>
</div>
<div id="servicio-modal" class="solicitud-modal fixed inset-0 z-50 overflow-y-auto bg-black/60 p-4 {{ $abrirServicio ? '' : 'hidden' }}"
    role="dialog" aria-modal="true" aria-labelledby="servicio-titulo">
    <div class="solicitud-panel mx-auto mt-8 max-w-3xl">
        <div class="solicitud-header flex items-center justify-between border-b p-5">
            <h2 id="servicio-titulo" class="solicitud-title text-2xl font-black">Agenda un servicio</h2>
            <button type="button" class="solicitud-close text-2xl" aria-label="Cerrar formulario de servicio">&times;</button>
        </div>
        @if($erroresServicio)<div class="mx-6 mt-5 rounded-lg border-l-4 border-red-700 bg-red-50 p-4 text-red-800" role="alert"><p class="font-black">Revisa los datos ingresados</p><ul class="mt-1 list-disc pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('servicios.store') }}" class="grid gap-5 p-6 sm:grid-cols-2">
        @csrf
        <input type="hidden" name="_formulario" value="servicio">
        <input id="tipo_servicio_seleccionado" type="hidden" name="tipo_servicio" value="{{ old('tipo_servicio', $tipoServicioSeleccionado ?? '') }}" required>
        <p class="text-sm text-[#65504c] sm:col-span-2">* Campo obligatorio</p>
        <div class="flex items-center justify-between gap-3 rounded-xl border border-[#e8c8c3] bg-white/60 px-4 py-3 sm:col-span-2">
            <div><span class="block text-xs font-bold uppercase tracking-wider text-[#65504c]">Servicio seleccionado</span><strong id="tipo_servicio_titulo" class="mt-1 block text-xl text-[#8f241d]">{{ old('tipo_servicio', $tipoServicioSeleccionado ?? 'Mantención') }}</strong></div>
            <button type="button" id="cambiar-tipo-servicio" class="rounded-full border border-[#d9aaa3] bg-white/70 px-4 py-2 text-sm font-bold text-[#8f241d] hover:bg-[#f7e8e6]">Cambiar</button>
        </div>
        <label class="block text-sm font-bold sm:col-span-2">Detallanos tu problema y nuestro equipo se pondrá en contacto contigo para coordinar la atención. <span class="text-[#b52f25]" aria-hidden="true">*</span><textarea id="descripcion-servicio" name="descripcion_servicio" required minlength="10" maxlength="3000" rows="4" placeholder="Ej.: mantención de maquinaria, soldadura de joystick..." class="mt-2 w-full border border-[#d9aaa3] p-3">{{ old('descripcion_servicio') }}</textarea><span id="descripcion-servicio-contador" class="mt-1 block text-right text-sm font-normal"></span></label>
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
@if(request()->routeIs('home', 'catalogo.index', 'servicios.index'))
<script src="{{ asset('js/rut.js') }}"></script>
<script src="{{ asset('js/chile-location.js') }}"></script>
@endif
<script>
(() => {
    const modal = document.getElementById('servicio-modal');
    const seleccionModal = document.getElementById('servicio-seleccion-modal');
    const cerrar = modal.querySelector('.solicitud-close');
    const cerrarSeleccion = seleccionModal.querySelector('.servicio-seleccion-cerrar');
    const tipoSeleccionado = document.getElementById('tipo_servicio_seleccionado');
    const tipoTitulo = document.getElementById('tipo_servicio_titulo');
    const cambiarTipo = document.getElementById('cambiar-tipo-servicio');
    let origen = null;
    let origenSeleccion = null;
    let overflowAnterior = '';
    const guardarYBloquearFondo = () => {
        overflowAnterior = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    };
    const abrirFormulario = (elemento) => {
        origen = elemento;
        seleccionModal.classList.add('hidden');
        guardarYBloquearFondo();
        modal.classList.remove('hidden');
        cerrar.focus();
    };
    const abrirSeleccion = (elemento) => {
        origenSeleccion = elemento;
        modal.classList.add('hidden');
        guardarYBloquearFondo();
        seleccionModal.classList.remove('hidden');
        seleccionModal.querySelector('[data-seleccionar-servicio]')?.focus();
    };
    const cerrarFormulario = () => {
        modal.classList.add('hidden');
        document.body.style.overflow = overflowAnterior;
        (origen || origenSeleccion)?.focus();
    };
    const cerrarSeleccionModal = () => {
        seleccionModal.classList.add('hidden');
        document.body.style.overflow = overflowAnterior;
        (origenSeleccion || origen)?.focus();
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
        abrirSeleccion(menuAbierto && menu.contains(enlace) ? toggle : enlace);
    });
    seleccionModal.querySelectorAll('[data-seleccionar-servicio]').forEach((opcion) => {
        opcion.addEventListener('click', () => {
            tipoSeleccionado.value = opcion.dataset.seleccionarServicio;
            tipoTitulo.textContent = opcion.dataset.seleccionarServicio;
            abrirFormulario(origenSeleccion);
        });
    });
    cerrarSeleccion.addEventListener('click', cerrarSeleccionModal);
    seleccionModal.addEventListener('click', event => {
        if (event.target === seleccionModal) cerrarSeleccionModal();
    });
    cambiarTipo?.addEventListener('click', () => abrirSeleccion(origen));
    cerrar.addEventListener('click', cerrarFormulario);
    modal.addEventListener('click', event => {
        if (event.target === modal) cerrarFormulario();
    });
    modal.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            cerrarFormulario();
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
    seleccionModal.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            cerrarSeleccionModal();
        }
    });
    if (!modal.classList.contains('hidden')) abrirFormulario(null);
    if (modal.classList.contains('hidden') && seleccionModal.classList.contains('hidden')
        && @json(request()->routeIs('servicios.index'))
        && @json(! session()->has('success') && ! session()->has('warning'))) {
        abrirSeleccion(null);
    }

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
