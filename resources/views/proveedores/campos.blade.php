<label class="block text-sm font-medium text-gray-700">Nombre de la marca o proveedor <span class="text-[#b52f25]" aria-hidden="true">*</span>
    <input id="{{ $prefix }}-name" name="nombre" value="{{ $useOld ? old('nombre') : '' }}" required maxlength="255" class="mt-1 w-full rounded-md border border-gray-300 p-2">
</label>
<label class="block text-sm font-medium text-gray-700">Vendedor o contacto <span class="text-[#b52f25]" aria-hidden="true">*</span>
    <input id="{{ $prefix }}-contact" name="contacto" value="{{ $useOld ? old('contacto') : '' }}" required maxlength="255" class="mt-1 w-full rounded-md border border-gray-300 p-2">
</label>
<div>
    <label for="{{ $prefix }}-phone" class="block text-sm font-medium text-gray-700">Teléfono chileno <span class="text-[#b52f25]" aria-hidden="true">*</span></label>
    <div class="mt-1 flex">
        <span class="flex items-center rounded-l-md border border-r-0 border-gray-300 bg-[#f7e8e6] px-3 font-bold text-[#8f241d]">+56 9</span>
        <input id="{{ $prefix }}-phone" name="telefono" type="tel" value="{{ $useOld ? old('telefono') : '' }}" required pattern="[0-9]{8}" maxlength="8" inputmode="numeric" placeholder="12345678" title="Ingresa los 8 dígitos del teléfono." class="min-w-0 flex-1 rounded-r-md border border-gray-300 p-2">
    </div>
    <p id="{{ $prefix }}-legacy-phone" class="mt-1 hidden text-sm text-gray-600"></p>
</div>
<label class="block text-sm font-medium text-gray-700">Correo electrónico <span class="text-[#b52f25]" aria-hidden="true">*</span>
    <input id="{{ $prefix }}-email" name="correo" type="email" value="{{ $useOld ? old('correo') : '' }}" required maxlength="255" class="mt-1 w-full rounded-md border border-gray-300 p-2">
</label>
<label class="block text-sm font-medium text-gray-700">Página web (opcional)
    <input id="{{ $prefix }}-website" name="pagina_web" type="url" value="{{ $useOld ? old('pagina_web') : '' }}" maxlength="255" placeholder="https://ejemplo.cl" class="mt-1 w-full rounded-md border border-gray-300 p-2">
</label>
