@extends('layouts.app')

@section('content')

<style>
    dialog.estado-oferta-dialog {
        position: fixed;
        inset: 0;
        margin: auto;
        width: min(calc(100% - 2rem), 32rem);
        max-height: calc(100dvh - 2rem);
        overflow-y: auto;
    }

    dialog.estado-oferta-dialog::backdrop {
        background: rgba(36, 24, 23, .62);
        backdrop-filter: blur(3px);
    }
</style>

<div class="bg-white shadow-md rounded-lg overflow-hidden p-6">

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">
            Inventario de Repuestos y Maquinaria
        </h2>

        <div class="flex flex-wrap gap-2">

            @if(auth()->user()->tienePermiso('movimientos.ver'))
                <a href="{{ route('inventario.movimientos') }}"
                   class="rounded-md border border-[#b52f25] px-4 py-2 font-medium text-[#9f2f25] hover:bg-[#f7e8e6]">
                    Movimientos de Inventario
                </a>
            @endif
            @if(auth()->user()->tienePermiso('productos.crear'))
                <a href="{{ route('productos.create') }}"
                   class="bg-[#b52f25] text-white px-4 py-2 rounded-md hover:bg-[#8f241d] font-medium">
                    + Nuevo Repuesto
                </a>

            @endif

            <a href="{{ route('productos.exportar.excel', request()->query()) }}"
               class="rounded-md border border-[#b52f25] px-4 py-2 font-medium text-[#9f2f25] hover:bg-[#f7e8e6]">
                Excel
            </a>

            <a href="{{ route('productos.exportar.pdf', request()->query()) }}"
               target="_blank"
               class="rounded-md bg-[#b52f25] px-4 py-2 font-medium text-white hover:bg-[#8f241d]">
                PDF
            </a>

        </div>
    </div>

    <form method="GET"
          action="{{ route('productos.index') }}"
          class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-4 lg:grid-cols-5">

        <input
            type="search"
            name="nombre"
            value="{{ request('nombre') }}"
            placeholder="Buscar por nombre, código de barras u origen"
            class="border-gray-300 rounded-md shadow-sm border p-2"
        >

        <select
            name="categoria"
            class="border-gray-300 rounded-md shadow-sm border p-2"
        >

            <option value="">Todas las categorías</option>

            @foreach($categorias as $categoria)

                <option
                    value="{{ $categoria->id_categoria }}"
                    @selected(request('categoria') == $categoria->id_categoria)
                >
                    {{ $categoria->nombre_categoria }}
                </option>

            @endforeach

        </select>

        <select
            name="activo"
            aria-label="Estado del catálogo"
            class="border-gray-300 rounded-md shadow-sm border p-2"
        >
            <option value="">Todos los estados</option>
            <option value="1" @selected(request('activo') === '1')>Activo</option>
            <option value="0" @selected(request('activo') === '0')>Inactivo</option>
        </select>

        <select
            name="stock"
            class="border-gray-300 rounded-md shadow-sm border p-2"
        >

            <option value="">Todo el stock</option>

            <option
                value="disponible"
                @selected(request('stock') === 'disponible')
            >
                Con stock
            </option>

            <option
                value="critico"
                @selected(request('stock') === 'critico')
            >
                Stock crítico
            </option>

            <option
                value="agotado"
                @selected(request('stock') === 'agotado')
            >
                Sin stock
            </option>

        </select>

        <div class="flex gap-2">

            <button
                type="submit"
                class="bg-[#b52f25] text-white px-4 py-2 rounded-md hover:bg-[#8f241d]"
            >
                Filtrar
            </button>

            <a
                href="{{ route('productos.index') }}"
                class="border border-[#d9aaa3] text-[#9f2f25] px-4 py-2 rounded-md hover:bg-[#f7e8e6]"
            >
                Limpiar
            </a>

        </div>

    </form>

    <div class="mx-auto max-w-6xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-center">
            <thead class="bg-gray-50">

                <tr>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Código</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Nombre</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Categoría</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Unidad</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Estado catálogo</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Precio</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Stock</th>
                    <th class="px-6 py-3 text-xs font-medium uppercase tracking-wider text-gray-500">Acciones</th>
                </tr>

            </thead>

            <tbody class="bg-white divide-y divide-gray-200">

                @foreach($productos as $producto)

                    <tr class="transition-colors hover:bg-gray-50">

                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600">
                            {{ $producto->codigo_origen ?: $producto->codigo_barra }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $producto->nombre }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $producto->categoria->nombre_categoria ?? 'Sin Categoría' }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ ucfirst($producto->unidad) }}
                        </td>

                        <td class="px-6 py-4 text-sm">
                            @if(auth()->user()->tienePermiso('productos.editar'))
                                <button type="button"
                                    onclick="document.getElementById('estado-oferta-{{ $producto->id_producto }}').showModal()"
                                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $producto->activo ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                                    title="Configurar estado y oferta">
                                    {{ $producto->activo ? 'Activo' : 'Inactivo' }}
                                    @if($producto->estaEnOferta()) · En oferta @endif
                                </button>
                            @else
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $producto->activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                    {{ $producto->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            @endif
                            @if(!$producto->activo)
                                <p class="mt-1 max-w-xs whitespace-normal break-words text-xs text-gray-500">
                                    Motivo: {{ $motivosDesactivacion[$producto->id_producto] ?? 'Sin motivo registrado' }}
                                </p>
                            @endif
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            @if($producto->estaEnOferta())
                                <span class="mr-1 text-xs text-gray-400 line-through">$ {{ number_format($producto->precio, 0, ',', '.') }}</span>
                                <span class="font-black text-[#b52f25]">$ {{ number_format($producto->precioVigente(), 0, ',', '.') }}</span>
                            @else
                                $ {{ number_format($producto->precio, 0, ',', '.') }}
                            @endif
                            @if($producto->unidad === 'metro') / metro @endif
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm">

                            @if($producto->stock <= 0 || $producto->stock <= $producto->stock_critico)

                                <span
                                    title="Stock actual: {{ $producto->stock }} / Stock crítico: {{ $producto->stock_critico }}"
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 cursor-help"
                                >
                                    {{ $producto->stock }}
                                </span>

                            @else

                                <span
                                    title="Stock actual: {{ $producto->stock }} / Stock crítico: {{ $producto->stock_critico }}"
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 cursor-help"
                                >
                                    {{ $producto->stock }}
                                </span>

                            @endif

                        </td>

                        <td class="space-x-2 whitespace-nowrap px-6 py-4 text-center text-sm font-medium">

                            @if(auth()->user()->tienePermiso('productos.editar'))

                                <a
                                    href="{{ route('productos.edit', $producto->id_producto) }}"
                                    title="Editar producto"
                                    aria-label="Editar producto"
                                    class="inline-flex rounded-md p-2 text-[#9f2f25] hover:bg-[#f7e8e6] hover:text-[#721d18]"
                                >

                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="m16.862 3.487 3.651 3.651M4 20h4l10.862-10.862a2.587 2.587 0 0 0-3.651-3.651L4.349 16.349A2 2 0 0 0 4 17.763V20Z"
                                        />
                                    </svg>

                                </a>

                            @endif
                            @if(auth()->user()->tienePermiso('productos.eliminar'))
                                <form
                                    action="{{ route('productos.destroy', $producto->id_producto, absolute: false) }}"
                                    method="POST"
                                    class="inline-block"
                                    data-confirm-message="¿Estás seguro de eliminar este producto? Esta acción no se puede deshacer."
                                >

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        title="Eliminar producto"
                                        aria-label="Eliminar producto"
                                        class="inline-flex rounded-md p-2 text-red-600 hover:bg-red-50 hover:text-red-900"
                                    >

                                        <svg
                                            class="h-5 w-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="m6 6 12 12M18 6 6 18"
                                            />
                                        </svg>

                                    </button>

                                </form>

                            @endif

                            <a
                                href="{{ route('productos.etiqueta', $producto->id_producto) }}"
                                target="_blank"
                                title="Ver etiqueta y código de barras"
                                aria-label="Ver etiqueta y código de barras"
                                class="inline-flex rounded-md p-2 text-[#9f2f25] hover:bg-[#f7e8e6] hover:text-[#721d18]"
                            >

                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 5v4m0 6v4m4-14v14m4-14v4m0 6v4m4-14v14m4-14v4m0 6v4"
                                    />
                                </svg>

                            </a>

                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

    </div>

    @foreach($productos as $producto)
        <dialog id="estado-oferta-{{ $producto->id_producto }}" class="estado-oferta-dialog rounded-2xl border-0 p-0 shadow-2xl">
            <form method="POST" action="{{ route('productos.estado-oferta', $producto->id_producto) }}" class="bg-white">
                @csrf
                @method('PUT')
                <input type="hidden" name="_dialog_id" value="estado-oferta-{{ $producto->id_producto }}">
                <header class="flex items-center justify-between bg-[#8f241d] px-6 py-4 text-white">
                    <h3 class="text-lg font-black">Estado y oferta</h3>
                    <button type="button" onclick="this.closest('dialog').close()" class="text-2xl leading-none" aria-label="Cerrar">&times;</button>
                </header>
                <div class="space-y-5 px-6 py-5">
                    <div class="oferta-validation-error hidden items-start gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800" role="alert" aria-live="polite">
                        <span class="text-red-600" aria-hidden="true">!</span>
                        <div>
                            <p class="font-bold">Revisa los datos de la oferta</p>
                            <p class="oferta-validation-message mt-1 text-sm"></p>
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-bold text-gray-800">{{ $producto->nombre }}</p>
                        <label class="block text-sm font-medium text-gray-700">Estado en el catálogo</label>
                        <select name="activo" class="estado-producto mt-1 w-full rounded-md border border-gray-300 p-2" data-dialog-id="estado-oferta-{{ $producto->id_producto }}">
                            <option value="1" @selected($producto->activo)>Activo</option>
                            <option value="0" @selected(!$producto->activo)>Inactivo</option>
                        </select>
                        <div class="motivo-desactivacion mt-3 {{ $producto->activo ? 'hidden' : '' }}">
                            <label class="block text-sm font-medium text-gray-700">Motivo de desactivación</label>
                            <textarea name="motivo_desactivacion" maxlength="1000" rows="3" class="mt-1 w-full rounded-md border border-gray-300 p-2"></textarea>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-bold text-gray-800">
                        <input type="checkbox" name="oferta_activa" value="1" class="oferta-activa rounded border-gray-300 text-[#b52f25]"
                            @checked($producto->precio_oferta !== null && $producto->oferta_fin_at?->gte(now()))>
                        Colocar producto en oferta
                    </label>

                    <div class="campos-oferta grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $producto->precio_oferta !== null && $producto->oferta_fin_at?->gte(now()) ? '' : 'hidden' }}">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Descuento (%)</label>
                            <input type="number" name="porcentaje_descuento" min="0" max="100" step="0.01"
                                value="{{ $producto->porcentaje_descuento }}"
                                class="porcentaje-oferta mt-1 w-full rounded-md border border-gray-300 p-2"
                                data-precio-habitual="{{ $producto->precio }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Precio de oferta</label>
                            <input type="number" name="precio_oferta" min="0" max="{{ $producto->precio - 0.01 }}" step="0.01"
                                value="{{ $producto->precio_oferta }}"
                                class="precio-oferta mt-1 w-full rounded-md border border-gray-300 p-2"
                                data-precio-habitual="{{ $producto->precio }}">
                            <p class="mt-1 text-xs text-gray-500">Precio habitual: ${{ number_format($producto->precio, 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Inicia</label>
                            <input type="datetime-local" name="oferta_inicio_at"
                                value="{{ old('oferta_inicio_at', $producto->oferta_inicio_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}"
                                class="mt-1 w-full rounded-md border border-gray-300 p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Termina</label>
                            <input type="datetime-local" name="oferta_fin_at"
                                value="{{ old('oferta_fin_at', $producto->oferta_fin_at?->format('Y-m-d\TH:i')) }}"
                                class="mt-1 w-full rounded-md border border-gray-300 p-2">
                        </div>
                    </div>
                </div>
                <footer class="flex justify-end gap-3 border-t px-6 py-4">
                    <button type="button" onclick="this.closest('dialog').close()" class="rounded-md border border-gray-300 px-4 py-2 font-bold text-gray-700">Cancelar</button>
                    <button type="submit" class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white hover:bg-[#8f241d]">Guardar</button>
                </footer>
            </form>
        </dialog>
    @endforeach

    <div class="mt-6">
        {{ $productos->links() }}
    </div>

</div>

<script>
    document.querySelectorAll('dialog[id^="estado-oferta-"]').forEach((dialog) => {
        const form = dialog.querySelector('form');
        const oferta = dialog.querySelector('.oferta-activa');
        const campos = dialog.querySelector('.campos-oferta');
        const porcentaje = dialog.querySelector('.porcentaje-oferta');
        const precio = dialog.querySelector('.precio-oferta');
        const activo = dialog.querySelector('.estado-producto');
        const motivo = dialog.querySelector('.motivo-desactivacion');
        const motivoTextarea = dialog.querySelector('[name="motivo_desactivacion"]');
        const inicio = dialog.querySelector('[name="oferta_inicio_at"]');
        const fin = dialog.querySelector('[name="oferta_fin_at"]');
        const errorBox = dialog.querySelector('.oferta-validation-error');
        const errorMessage = dialog.querySelector('.oferta-validation-message');

        const actualizarOferta = () => campos.classList.toggle('hidden', !oferta.checked);
        const actualizarMotivo = () => motivo.classList.toggle('hidden', activo.value === '1');
        const mostrarError = (mensaje) => {
            errorMessage.textContent = mensaje;
            errorBox.classList.remove('hidden');
            errorBox.classList.add('flex');
        };
        const limpiarError = () => {
            errorBox.classList.add('hidden');
            errorBox.classList.remove('flex');
            errorMessage.textContent = '';
        };

        oferta.addEventListener('change', actualizarOferta);
        activo.addEventListener('change', actualizarMotivo);

        porcentaje?.addEventListener('input', () => {
            const habitual = Number(porcentaje.dataset.precioHabitual || 0);
            const descuento = Number(porcentaje.value || 0);
            if (habitual > 0) {
                precio.value = (Math.round(habitual * (1 - descuento / 100) * 100) / 100).toFixed(2);
            }
        });

        precio?.addEventListener('input', () => {
            const habitual = Number(precio.dataset.precioHabitual || 0);
            const ofertaPrecio = Number(precio.value || 0);
            if (habitual > 0) {
                porcentaje.value = (Math.round((1 - ofertaPrecio / habitual) * 10000) / 100).toFixed(2);
            }
        });

        form.addEventListener('submit', (event) => {
            limpiarError();

            if (activo.value === '0' && !motivoTextarea.value.trim()) {
                event.preventDefault();
                mostrarError('Indica el motivo por el que se desactiva el producto.');
                motivoTextarea.focus();
                return;
            }

            if (!oferta.checked) {
                return;
            }

            const descuento = Number(porcentaje?.value || 0);
            const precioOferta = Number(precio?.value || 0);
            const habitual = Number(precio?.dataset.precioHabitual || 0);

            if (!porcentaje.value && !precio.value) {
                event.preventDefault();
                mostrarError('Indica el porcentaje de descuento o el precio de oferta.');
                porcentaje.focus();
                return;
            }

            if (!precio.value) {
                event.preventDefault();
                mostrarError('Indica el precio de oferta.');
                precio.focus();
                return;
            }

            if (precioOferta <= 0 || precioOferta >= habitual) {
                event.preventDefault();
                mostrarError('El precio de oferta debe ser mayor que cero y menor que el precio habitual.');
                precio.focus();
                return;
            }

            if (descuento < 0 || descuento > 100) {
                event.preventDefault();
                mostrarError('El descuento debe estar entre 0% y 100%.');
                porcentaje.focus();
                return;
            }

            if (!fin.value) {
                event.preventDefault();
                mostrarError('Indica la fecha y hora en que termina la oferta.');
                fin.focus();
                return;
            }

            if (inicio.value && new Date(fin.value) <= new Date(inicio.value)) {
                event.preventDefault();
                mostrarError('La fecha de término debe ser posterior a la fecha de inicio.');
                fin.focus();
                return;
            }
        });

        actualizarOferta();
        actualizarMotivo();
    });

    @if($errors->any() && old('_dialog_id'))
        const dialogConErrores = document.getElementById(@json(old('_dialog_id')));
        if (dialogConErrores) {
            dialogConErrores.showModal();
            const mensajeServidor = @json($errors->first());
            const errorBox = dialogConErrores.querySelector('.oferta-validation-error');
            const errorMessage = dialogConErrores.querySelector('.oferta-validation-message');
            errorMessage.textContent = mensajeServidor;
            errorBox.classList.remove('hidden');
            errorBox.classList.add('flex');
        }
    @endif
</script>

@endsection