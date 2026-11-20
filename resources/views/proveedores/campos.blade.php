<label class="block text-sm font-medium text-gray-700">Nombre de la marca o proveedor <span class="text-[#b52f25]" aria-hidden="true">*</span>
    <input id="{{ $prefix }}-name" name="nombre" value="{{ $useOld ? old('nombre') : '' }}" required maxlength="255" class="mt-1 w-full rounded-md border border-gray-300 p-2">
</label>
<label class="block text-sm font-medium text-gray-700">Vendedor o contacto <span class="text-[#b52f25]" aria-hidden="true">*</span>
    <input id="{{ $prefix }}-contact" name="contacto" value="{{ $useOld ? old('contacto') : '' }}" required maxlength="255" class="mt-1 w-full rounded-md border border-gray-300 p-2">
</label>
<div>
    <label for="{{ $prefix }}-phone" class="block text-sm font-medium text-gray-700">Teléfono chileno <span class="text-[#b52f25]" aria-hidden="true">*</span></label>
    <div class="mt-1 flex">
        <select id="{{ $prefix }}-phone-prefix" aria-label="Código de país"></select>
        <input id="{{ $prefix }}-phone" name="telefono" type="tel" value="{{ $useOld ? old('telefono') : '' }}" required pattern="[2-9][0-9]{8}" maxlength="9" inputmode="numeric" placeholder="912345678" class="min-w-0 flex-1 rounded-r-md border border-gray-300 p-2">
    </div>
    <script src="{{ asset('js/chile-location.js') }}"></script>
    <script>
        inicializarSelectorTelefono(@json($prefix.'-phone-prefix'), @json($prefix.'-phone'));
        document.getElementById(@json($prefix.'-phone')).value = window.telefonoNacionalChile(document.getElementById(@json($prefix.'-phone')).value);
    </script>
    <p id="{{ $prefix }}-legacy-phone" class="mt-1 hidden text-sm text-gray-600"></p>
</div>
<label class="block text-sm font-medium text-gray-700">Correo electrónico <span class="text-[#b52f25]" aria-hidden="true">*</span>
    <input id="{{ $prefix }}-email" name="correo" type="email" value="{{ $useOld ? old('correo') : '' }}" required maxlength="255" class="mt-1 w-full rounded-md border border-gray-300 p-2">
</label>
<label class="block text-sm font-medium text-gray-700">Página web (opcional)
    <input id="{{ $prefix }}-website" name="pagina_web" type="url" value="{{ $useOld ? old('pagina_web') : '' }}" maxlength="255" placeholder="https://ejemplo.cl" class="mt-1 w-full rounded-md border border-gray-300 p-2">
</label>
