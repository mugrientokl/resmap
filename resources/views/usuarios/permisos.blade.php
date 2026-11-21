<div>
    <!-- Do what you can, with what you have, where you are. - Theodore Roosevelt -->
</div>
@php
    $nombresModulos = [
        'dashboard' => 'Dashboard',
        'productos' => 'Inventario',
        'movimientos' => 'Movimientos de inventario',
        'ventas' => 'Ventas',
        'cotizaciones' => 'Cotizaciones y servicios',
        'clientes' => 'Clientes',
        'reportes' => 'Reportes',
        'categorias' => 'Categorías',
        'proveedores' => 'Proveedores',
    ];
    $gruposPermisos = collect(config('permisos.opciones'))->groupBy(fn ($etiqueta, $permiso) => explode('.', $permiso, 2)[0], true);
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">Elige las acciones permitidas para cada módulo.</p>
        <div class="flex flex-wrap gap-2">
            <button type="button" data-permissions-select hidden class="rounded-md border border-[#d9aaa3] bg-white px-3 py-1.5 text-xs font-bold text-[#9f2f25] hover:bg-[#f7e8e6]">Seleccionar todos</button>
            <button type="button" data-permissions-clear hidden class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-bold text-gray-600 hover:bg-gray-50">Limpiar selección</button>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($gruposPermisos as $modulo => $permisos)
            <fieldset class="min-w-0 rounded-lg border border-[#ead8d5] bg-white p-4">
                <legend class="px-1 text-sm font-black text-[#721d18]">{{ $nombresModulos[$modulo] ?? ucfirst($modulo) }}</legend>
                <div class="space-y-3">
                    @foreach($permisos as $permiso => $etiqueta)
                        <label class="flex cursor-pointer items-start gap-3 text-sm text-gray-700">
                            <input type="checkbox" name="permisos[]" value="{{ $permiso }}" @checked(in_array($permiso, $seleccionados, true)) class="mt-0.5 h-4 w-4 shrink-0 accent-[#b52f25]">
                            <span>{{ ucfirst(\Illuminate\Support\Str::after($etiqueta, ': ')) }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>

    <details class="rounded-md border border-[#ead8d5] bg-[#fffaf9] px-4 py-3 text-sm text-gray-600">
        <summary class="cursor-pointer font-bold text-[#9f2f25]">Información importante sobre los permisos</summary>
        <div class="mt-3 space-y-2 leading-6">
            <p>Los administradores tienen acceso completo. Al seleccionar una acción también se habilita la visualización del módulo.</p>
            <p>Registrar ventas desde cotizaciones o servicios requiere «Ventas: registrar ventas» y «Cotizaciones y servicios: gestionar y enviar».</p>
            <p>El dashboard y los reportes incluyen indicadores de ventas y stock. El POS y las cotizaciones usan datos de clientes y productos; registrar una venta puede actualizar clientes y descontar stock. Editar productos permite modificar stock y desactivarlos.</p>
        </div>
    </details>
</div>
