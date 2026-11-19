@extends('layouts.app')

@section('content')
<div class="bg-white shadow-md rounded-lg p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Movimientos de Inventario</h2>
        <button type="button" id="open-movement-modal" class="rounded-md bg-[#b52f25] px-4 py-2 font-medium text-white hover:bg-[#8f241d]">
            Registrar Movimiento
        </button>
    </div>

    @if($errors->any() && old('_form_context') !== 'movement')
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('inventario.movimientos') }}" class="mb-6 rounded-lg border bg-gray-50 p-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="product-picker relative" data-picker-required="false">
                <label for="movement-filter-product-search" class="mb-1 block text-sm font-medium text-gray-700">Producto</label>
                <input id="movement-filter-product-search" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="movement-filter-product-options" autocomplete="off" placeholder="Todos los productos" value="{{ $productoFiltrado ? $productoFiltrado->nombre.' | Cat: '.($productoFiltrado->categoria->nombre_categoria ?? 'Sin Categoría').' | Stock: '.$productoFiltrado->stock : '' }}" class="w-full rounded-md border border-gray-300 p-2 text-sm focus:border-[#b52f25] focus:outline-none">
                <input type="hidden" id="movement-filter-product-value" name="producto" value="{{ request('producto') }}">
                <div id="movement-filter-product-options" role="listbox" class="absolute left-0 top-full z-30 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-md border border-gray-300 bg-white shadow-lg">
                    <p class="picker-no-results hidden px-3 py-2 text-sm text-gray-500">No hay repuestos que coincidan con la búsqueda.</p>
                </div>
            </div>
            <div>
                <label for="movement-filter-reason" class="mb-1 block text-sm font-medium text-gray-700">Motivo</label>
                <select id="movement-filter-reason" name="motivo" class="w-full rounded-md border border-gray-300 p-2 text-sm">
                    <option value="">Todos los motivos</option>
                    @foreach($motivos as $valor => $motivo)
                        <option value="{{ $valor }}" @selected(request('motivo') === $valor)>{{ $motivo }}</option>
                    @endforeach
                    @if(request('motivo') && ! array_key_exists(request('motivo'), $motivos))
                        <option value="{{ request('motivo') }}" selected>{{ request('motivo') }}</option>
                    @endif
                </select>
            </div>
            <div>
                <label for="movement-filter-from" class="mb-1 block text-sm font-medium text-gray-700">Fecha del evento desde</label>
                <input id="movement-filter-from" type="date" name="fecha_desde" value="{{ request('fecha_desde') }}" class="w-full rounded-md border border-gray-300 p-2 text-sm">
            </div>
            <div>
                <label for="movement-filter-to" class="mb-1 block text-sm font-medium text-gray-700">Fecha del evento hasta</label>
                <input id="movement-filter-to" type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}" class="w-full rounded-md border border-gray-300 p-2 text-sm">
            </div>
        </div>
        <div class="mt-4 flex flex-wrap justify-end gap-2">
            <a href="{{ route('inventario.movimientos') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Limpiar filtros</a>
            <button type="submit" class="rounded-md bg-[#8f241d] px-4 py-2 text-sm font-medium text-white hover:bg-[#721d18]">Filtrar movimientos</button>
        </div>
    </form>

    <!-- Tabla de movimientos -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-center text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Fecha Registro</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Fecha Evento</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Producto</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Tipo</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Cantidad</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Stock Resultante</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Motivo</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Proveedor</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Usuario</th>
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($movimientos as $mov)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500">
                            {{ $mov->created_at ? $mov->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ $mov->fecha_evento?->format('d/m/Y') ?? '-' }}
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $mov->producto->nombre ?? 'Producto no encontrado' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 text-xs rounded-full {{ in_array($mov->tipo, ['ingreso', 'entrada', 'adquisicion']) ? 'bg-green-100 text-green-800' : (in_array($mov->tipo, ['egreso', 'merma', 'venta']) ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') }}">
                                {{ ucfirst($mov->tipo) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-bold">{{ $mov->cantidad > 0 ? '+' : '' }}{{ $mov->cantidad }}</td>
                        <td class="px-4 py-3">{{ $mov->stock_nuevo }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $mov->motivo }}</td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ $mov->proveedor ?: '—' }}
                            @if($mov->contacto_proveedor)<div class="text-xs text-gray-500">{{ $mov->contacto_proveedor }}</div>@endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $mov->usuario->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3"><button type="button" class="movement-detail-button font-medium text-[#8f241d] underline" data-url="{{ route('inventario.movimientos.show', $mov) }}">Ver detalle</button></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-500">
                            No hay movimientos de inventario registrados aún.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $movimientos->links() }}
    </div>
</div>
<div id="movement-detail-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="movement-detail-title">
    <div class="mx-auto my-6 w-full max-w-2xl rounded-lg bg-white p-5 shadow-2xl">
        <div class="flex items-center justify-between border-b pb-3">
            <h3 id="movement-detail-title" class="text-xl font-bold text-[#8f241d]">Detalle del movimiento</h3>
            <button type="button" id="close-movement-detail" class="text-2xl text-gray-500" aria-label="Cerrar detalle">&times;</button>
        </div>
        <dl id="movement-detail-content" class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2" aria-live="polite"></dl>
    </div>
</div>
<div id="movement-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="movement-modal-title" @if($errors->any() && old('_form_context') === 'movement') data-open-on-load="true" @endif>
    <div class="mx-auto my-6 w-full max-w-4xl rounded-lg bg-white shadow-2xl sm:my-10">
        <div class="flex items-center justify-between border-b border-[#e8c8c3] p-5">
            <h3 id="movement-modal-title" class="text-xl font-bold text-[#8f241d]">Registrar Movimiento</h3>
            <button type="button" id="close-movement-modal" class="text-2xl leading-none text-gray-500 hover:text-gray-800" aria-label="Cerrar formulario">&times;</button>
        </div>
        @if($errors->any() && old('_form_context') === 'movement')
            <div class="mx-5 mt-5 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form id="movement-form" action="{{ route('inventario.movimientos.store') }}" method="POST" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2" data-confirm-message="Confirma el movimiento de inventario y verifica el stock resultante." data-confirm-title="Confirmar movimiento de inventario" data-confirm-text="Registrar movimiento">
            @csrf
            <input type="hidden" name="_form_context" value="movement">

            <div>
                <label for="movement-type" class="mb-1 block text-sm font-medium text-gray-700">Tipo de movimiento</label>
                <select id="movement-type" name="tipo" class="w-full rounded-md border border-gray-300 p-2 text-sm" required>
                    <option value="ingreso" @selected(old('tipo', 'ingreso') === 'ingreso')>Ingreso (+)</option>
                    <option value="egreso" @selected(old('tipo') === 'egreso')>Egreso (-)</option>
                    <option value="ajuste" @selected(old('tipo') === 'ajuste')>Ajuste de Stock</option>
                </select>
            </div>
            <div>
                <label for="movement-reason" class="mb-1 block text-sm font-medium text-gray-700">Motivo</label>
                <select id="movement-reason" name="motivo" class="w-full rounded-md border border-gray-300 p-2 text-sm" required>
                    <option value="">Seleccione motivo</option>
                    <option data-types="ingreso" value="Compra / Adquisición" @selected(old('motivo') === 'Compra / Adquisición')>Compra / Adquisición</option>
                    <option data-types="egreso" value="Merma / Daño" @selected(old('motivo') === 'Merma / Daño')>Merma / Daño</option>
                    <option data-types="egreso" value="Devolución" @selected(old('motivo') === 'Devolución')>Devolución al proveedor</option>
                    <option data-types="ajuste" value="Ajuste de inventario" @selected(old('motivo') === 'Ajuste de inventario')>Ajuste de inventario</option>
                    <option data-types="ajuste" value="Uso interno" @selected(old('motivo') === 'Uso interno')>Uso interno</option>
                </select>
            </div>
            <div id="movement-provider-field" class="hidden">
                <label for="movement-provider" class="mb-1 block text-sm font-medium text-gray-700">Proveedor</label>
                <select id="movement-provider" name="proveedor_id" disabled class="w-full rounded-md border border-gray-300 p-2 text-sm">
                    <option value="">Seleccione un proveedor</option>
                    @foreach($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}" @selected((string) old('proveedor_id') === (string) $proveedor->id)>{{ $proveedor->nombre }} · {{ $proveedor->contacto }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-600">La compra es un ingreso y la devolución al proveedor es un egreso. <a href="{{ route('proveedores.index') }}" class="font-bold text-[#9f2f25] underline">Administrar proveedores</a></p>
            </div>
            <div>
                <label for="movement-event-date" class="mb-1 block text-sm font-medium text-gray-700">Fecha del evento</label>
                <input id="movement-event-date" type="date" name="fecha_evento" value="{{ old('fecha_evento', now()->toDateString()) }}" class="w-full rounded-md border border-gray-300 p-2 text-sm" required>
            </div>
            <div id="movement-document-field" class="hidden">
                <label for="movement-document" class="mb-1 block text-sm font-medium text-gray-700">Número de folio / documento</label>
                <input id="movement-document" name="documento_proveedor" value="{{ old('documento_proveedor') }}" maxlength="100" class="w-full rounded-md border border-gray-300 p-2 text-sm">
            </div>
            <div class="md:col-span-2">
                <label for="movement-observations" class="mb-1 block text-sm font-medium text-gray-700">Observaciones</label>
                <textarea id="movement-observations" name="observaciones" maxlength="2000" rows="2" class="w-full rounded-md border border-gray-300 p-2 text-sm">{{ old('observaciones') }}</textarea>
            </div>
            <div id="movement-lines" class="space-y-4 md:col-span-2">
                @foreach(old('productos', [['id_producto' => '', 'cantidad' => '', 'precio_entrada' => '']]) as $index => $linea)
                    <div class="movement-line grid grid-cols-1 gap-3 border-t pt-4 sm:grid-cols-2" data-index="{{ $index }}">
                        <div class="product-picker relative sm:col-span-2" data-picker-required="true">
                            <label for="movement-product-{{ $index }}-search" class="mb-1 block text-sm font-medium text-gray-700">Buscar / Seleccionar Producto</label>
                            <input id="movement-product-{{ $index }}-search" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="movement-product-{{ $index }}-options" autocomplete="off" placeholder="Escribe el nombre o código del repuesto..." class="w-full rounded-md border border-gray-300 p-2 text-sm focus:border-[#b52f25] focus:outline-none">
                            <input type="hidden" name="productos[{{ $index }}][id_producto]" value="{{ $linea['id_producto'] ?? '' }}">
                            <div id="movement-product-{{ $index }}-options" role="listbox" class="absolute left-0 top-full z-30 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-md border border-gray-300 bg-white shadow-lg">
                                <p class="picker-no-results hidden px-3 py-2 text-sm text-gray-500">No hay repuestos que coincidan con la búsqueda.</p>
                            </div>
                            <label for="movement-product-{{ $index }}-category" class="mb-1 mt-3 block text-sm font-medium text-gray-700">Filtrar por categoría</label>
                            <select id="movement-product-{{ $index }}-category" class="w-full rounded-md border border-gray-300 p-2 text-sm">
                                <option value="">Todas las categorías</option>
                                @foreach($categorias as $categoria)
                                    <option value="{{ $categoria->id_categoria }}">{{ $categoria->nombre_categoria }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="movement-product-{{ $index }}-quantity" class="movement-quantity-label mb-1 block text-sm font-medium text-gray-700">Cantidad</label>
                            <input id="movement-product-{{ $index }}-quantity" type="number" name="productos[{{ $index }}][cantidad]" value="{{ $linea['cantidad'] ?? '' }}" min="1" max="2147483647" class="movement-quantity w-full rounded-md border border-gray-300 p-2 text-sm">
                        </div>
                        <div class="movement-price-field hidden">
                            <label for="movement-product-{{ $index }}-price" class="mb-1 block text-sm font-medium text-gray-700">Costo neto total de adquisición</label>
                            <input id="movement-product-{{ $index }}-price" type="number" name="productos[{{ $index }}][precio_entrada]" value="{{ $linea['precio_entrada'] ?? '' }}" min="0" max="99999999.99" step="0.01" class="movement-price w-full rounded-md border border-gray-300 p-2 text-sm">
                            <p class="mt-1 text-xs text-gray-600">El precio del producto se actualizará al costo neto unitario de esta adquisición más IVA (19 %).</p>
                            <p class="movement-last-purchase mt-1 text-xs text-gray-600" aria-live="polite"></p>
                        </div>
                        <button type="button" class="remove-movement-line justify-self-start text-sm font-medium text-red-700 underline">Quitar producto</button>
                    </div>
                @endforeach
            </div>
            <div class="md:col-span-2">
                <button type="button" id="add-movement-line" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50">+ Agregar otro producto</button>
            </div>
            <div class="flex items-end justify-end gap-2 md:col-span-2">
                <button type="button" id="cancel-movement-modal" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancelar</button>
                <button type="submit" class="rounded-md bg-[#b52f25] px-4 py-2 text-sm font-medium text-white hover:bg-[#8f241d]">Registrar Movimiento</button>
            </div>
        </form>
    </div>
</div>
<script type="application/json" id="movement-products-data">@json($catalogoProductos)</script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const detailModal = document.getElementById('movement-detail-modal');
        const detailContent = document.getElementById('movement-detail-content');
        const closeDetail = () => {
            detailModal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        };
        document.getElementById('close-movement-detail').addEventListener('click', closeDetail);
        detailModal.addEventListener('click', (event) => { if (event.target === detailModal) closeDetail(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !detailModal.classList.contains('hidden')) closeDetail(); });
        document.querySelectorAll('.movement-detail-button').forEach((button) => button.addEventListener('click', async () => {
            detailModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            detailContent.textContent = 'Cargando detalle...';
            try {
                const response = await fetch(button.dataset.url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('No se pudo cargar el detalle.');
                const detail = await response.json();
                detailContent.replaceChildren();
                const fields = {
                    'Producto': detail.producto, 'Tipo': detail.tipo, 'Motivo': detail.motivo,
                    'Fecha del evento': detail.fecha_evento, 'Fecha de registro': detail.fecha,
                    'Cantidad': detail.cantidad, 'Stock anterior': detail.stock_anterior, 'Stock resultante': detail.stock_nuevo,
                    'Usuario': detail.usuario, 'Proveedor': detail.proveedor, 'Contacto': detail.contacto_proveedor,
                    'Folio / documento': detail.documento_proveedor, 'Precio total de adquisición': detail.precio_entrada,
                    'Cantidad adquirida': detail.cantidad_adquirida, 'Precio unitario neto': detail.precio_unitario_adquisicion,
                    'Precio unitario con IVA': detail.precio_venta_recomendado, 'Observaciones': detail.observaciones,
                    'Venta': detail.venta ? `#${detail.venta.id_venta} - ${detail.venta.cliente} - ${detail.venta.tipo_documento}` : null,
                    'Total de venta': detail.venta?.total,
                    'Productos de la venta': detail.venta?.detalles.map((item) => `${item.producto}: ${item.cantidad} x subtotal ${item.subtotal}`).join('; '),
                };
                Object.entries(fields).forEach(([label, value]) => {
                    if (value === null || value === undefined || value === '') return;
                    const item = document.createElement('div');
                    const term = document.createElement('dt');
                    const description = document.createElement('dd');
                    term.className = 'font-medium text-gray-500';
                    term.textContent = label;
                    description.className = 'text-gray-900';
                    description.textContent = value;
                    item.append(term, description);
                    detailContent.appendChild(item);
                });
            } catch (error) {
                detailContent.textContent = 'No se pudo cargar el detalle del movimiento.';
            }
        }));
        const normalize = (value) => value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('es')
            .trim();

        const productCatalog = JSON.parse(document.getElementById('movement-products-data').textContent);

        // Las opciones se generan una sola vez desde el JSON para no duplicar ~1.000 botones por selector en el HTML.
        const buildProductOptions = (suggestions) => {
            const prefix = suggestions.id.replace(/-options$/, '-option-');
            const fragment = document.createDocumentFragment();

            productCatalog.forEach((product) => {
                const option = document.createElement('button');
                option.id = prefix + product.id;
                option.type = 'button';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.dataset.productId = product.id;
                option.dataset.categoryId = product.categoria ?? '';
                option.dataset.currentStock = product.stock;
                option.dataset.search = product.busqueda;
                option.dataset.label = product.etiqueta;
                option.className = 'hidden w-full px-3 py-2 text-left text-sm hover:bg-[#f7e8e6] focus:bg-[#f7e8e6] focus:outline-none';
                option.textContent = product.etiqueta;
                fragment.appendChild(option);
            });

            suggestions.prepend(fragment);
        };

        const initializePicker = (picker) => {
            const searchInput = picker.querySelector('[role="combobox"]');
            const selectedProductInput = picker.querySelector('input[type="hidden"]');
            const suggestions = picker.querySelector('[role="listbox"]');
            const noResults = picker.querySelector('.picker-no-results');
            const categorySelect = picker.querySelector('select');

            if (!searchInput || !selectedProductInput || !suggestions || !noResults) {
                return;
            }

            buildProductOptions(suggestions);
            const productOptions = Array.from(suggestions.querySelectorAll('[data-product-id]'));
            let visibleOptions = [];
            let highlightedIndex = -1;
            let selectedLabel = '';

            const setSuggestionsOpen = (isOpen) => {
                suggestions.classList.toggle('hidden', !isOpen);
                searchInput.setAttribute('aria-expanded', String(isOpen));
            };

            const updateHighlight = () => {
                productOptions.forEach((option) => {
                    option.setAttribute('aria-selected', 'false');
                    option.classList.remove('bg-[#f7e8e6]');
                });

                const highlightedOption = visibleOptions[highlightedIndex];
                if (highlightedOption) {
                    highlightedOption.setAttribute('aria-selected', 'true');
                    highlightedOption.classList.add('bg-[#f7e8e6]');
                    searchInput.setAttribute('aria-activedescendant', highlightedOption.id);
                } else {
                    searchInput.removeAttribute('aria-activedescendant');
                }
            };

            const filterProducts = () => {
                const searchTerms = normalize(searchInput.value).split(/\s+/).filter(Boolean);
                const selectedCategory = categorySelect?.value || '';

                visibleOptions = productOptions.filter((option) => {
                    const matchesCategory = !selectedCategory || option.dataset.categoryId === selectedCategory;
                    const searchableText = normalize(option.dataset.search || option.textContent);
                    const matchesSearch = searchTerms.every((term) => searchableText.includes(term));

                    option.classList.toggle('hidden', !matchesCategory || !matchesSearch);

                    return matchesCategory && matchesSearch;
                });

                noResults.classList.toggle('hidden', visibleOptions.length > 0);
                highlightedIndex = -1;
                updateHighlight();
            };

            const selectProduct = (option) => {
                selectedProductInput.value = option.dataset.productId;
                selectedLabel = option.dataset.label;
                searchInput.value = selectedLabel;
                searchInput.setCustomValidity('');
                setSuggestionsOpen(false);
                picker.dispatchEvent(new Event('productchange'));
            };

            const initiallySelectedProduct = productOptions.find((option) => option.dataset.productId === selectedProductInput.value);
            const initialProductMatchesCategory = initiallySelectedProduct
                && (!categorySelect?.value || initiallySelectedProduct.dataset.categoryId === categorySelect.value);

            if (initialProductMatchesCategory) {
                selectedLabel = initiallySelectedProduct.dataset.label;
                searchInput.value = selectedLabel;
                searchInput.setCustomValidity('');
            } else if (searchInput.required) {
                selectedProductInput.value = '';
                searchInput.setCustomValidity('Selecciona un repuesto de la lista de sugerencias.');
            }

            filterProducts();

            searchInput.addEventListener('focus', () => {
                filterProducts();
                setSuggestionsOpen(true);
            });

            searchInput.addEventListener('input', () => {
                if (searchInput.value !== selectedLabel) {
                    selectedProductInput.value = '';
                    selectedLabel = '';
                    if (searchInput.required || searchInput.value.trim()) {
                        searchInput.setCustomValidity('Selecciona un repuesto de la lista de sugerencias.');
                    } else {
                        searchInput.setCustomValidity('');
                    }
                    picker.dispatchEvent(new Event('productchange'));
                }

                filterProducts();
                setSuggestionsOpen(true);
            });

            searchInput.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    setSuggestionsOpen(true);
                    if (visibleOptions.length === 0) {
                        return;
                    }

                    if (highlightedIndex === -1) {
                        highlightedIndex = event.key === 'ArrowDown' ? 0 : visibleOptions.length - 1;
                    } else {
                        const direction = event.key === 'ArrowDown' ? 1 : -1;
                        highlightedIndex = (highlightedIndex + direction + visibleOptions.length) % visibleOptions.length;
                    }
                    updateHighlight();
                } else if (event.key === 'Enter' && !suggestions.classList.contains('hidden')) {
                    const highlightedOption = visibleOptions[highlightedIndex]
                        || (visibleOptions.length === 1 ? visibleOptions[0] : null);
                    if (highlightedOption) {
                        event.preventDefault();
                        selectProduct(highlightedOption);
                    }
                } else if (event.key === 'Escape') {
                    event.stopPropagation();
                    setSuggestionsOpen(false);
                }
            });

            productOptions.forEach((option) => {
                option.addEventListener('click', () => selectProduct(option));
            });

            categorySelect?.addEventListener('change', () => {
                const selectedOption = productOptions.find((option) => option.dataset.productId === selectedProductInput.value);
                if (selectedOption && categorySelect.value && selectedOption.dataset.categoryId !== categorySelect.value) {
                    selectedProductInput.value = '';
                    selectedLabel = '';
                    searchInput.value = '';
                    searchInput.setCustomValidity('Selecciona un repuesto de la lista de sugerencias.');
                    picker.dispatchEvent(new Event('productchange'));
                }

                filterProducts();
                setSuggestionsOpen(true);
            });

            document.addEventListener('click', (event) => {
                if (!picker.contains(event.target)) {
                    setSuggestionsOpen(false);
                }
            });
        };

        const movementModal = document.getElementById('movement-modal');
        const movementForm = document.getElementById('movement-form');
        const movementType = document.getElementById('movement-type');
        const movementReason = document.getElementById('movement-reason');
        const providerField = document.getElementById('movement-provider-field');
        const providerSelect = document.getElementById('movement-provider');
        const movementLines = document.getElementById('movement-lines');
        const addMovementLineButton = document.getElementById('add-movement-line');
        let nextLineIndex = Math.max(...Array.from(movementLines.querySelectorAll('.movement-line'), (line) => Number(line.dataset.index)), -1) + 1;
        const openModalButton = document.getElementById('open-movement-modal');
        const closeModalButtons = [
            document.getElementById('close-movement-modal'),
            document.getElementById('cancel-movement-modal'),
        ];
        let previousFocus = null;

        const updateMovementLines = () => {
            const lines = Array.from(movementLines.querySelectorAll('.movement-line'));
            const isPurchase = movementReason.value === 'Compra / Adquisición';
            lines.forEach((line, index) => {
                const search = line.querySelector('[role="combobox"]');
                const product = line.querySelector('input[type="hidden"]');
                const quantity = line.querySelector('.movement-quantity');
                const price = line.querySelector('.movement-price');
                const selected = Boolean(product.value);
                search.required = true;
                if (!selected) search.setCustomValidity(search.required || search.value.trim() ? 'Selecciona un repuesto de la lista de sugerencias.' : '');
                product.disabled = !selected;
                quantity.disabled = !selected;
                quantity.required = selected;
                quantity.min = movementType.value === 'ajuste' ? '0' : '1';
                line.querySelector('.movement-quantity-label').textContent = movementType.value === 'ajuste' ? 'Nuevo stock' : 'Cantidad';
                line.querySelector('.movement-price-field').classList.toggle('hidden', !isPurchase || !selected);
                price.disabled = !selected || !isPurchase;
                price.required = selected && isPurchase;
                const lastPurchase = productCatalog.find((item) => String(item.id) === product.value)?.ultima_adquisicion;
                const lastPurchaseMessage = line.querySelector('.movement-last-purchase');
                if (!selected) {
                    lastPurchaseMessage.textContent = '';
                } else if (!lastPurchase) {
                    lastPurchaseMessage.textContent = 'Sin adquisiciones anteriores registradas.';
                } else {
                    const money = (amount) => `$${new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 }).format(amount)}`;
                    const details = [lastPurchase.fecha ? `Fecha: ${lastPurchase.fecha}` : null];
                    if (lastPurchase.cantidad !== null) details.push(`Cantidad: ${lastPurchase.cantidad}`);
                    if (lastPurchase.costo_total !== null) {
                        details.push(`Costo neto total: ${money(lastPurchase.costo_total)}`);
                        if (lastPurchase.cantidad > 0) details.push(`Unitario: ${money(lastPurchase.costo_total / lastPurchase.cantidad)}`);
                    } else {
                        details.push('Costo no registrado');
                    }
                    if (lastPurchase.proveedor) details.push(`Proveedor: ${lastPurchase.proveedor}`);
                    lastPurchaseMessage.textContent = `Última adquisición registrada: ${details.filter(Boolean).join(' · ')}`;
                }
                line.querySelector('.remove-movement-line').classList.toggle('hidden', lines.length === 1);
            });
            addMovementLineButton.disabled = lines.length >= 100 || lines.some((line) => !line.querySelector('input[type="hidden"]').value);
        };

        const addMovementLine = () => {
            const source = movementLines.querySelector('.movement-line');
            const oldIndex = source.dataset.index;
            const newIndex = nextLineIndex++;
            const line = source.cloneNode(true);
            line.dataset.index = newIndex;
            line.querySelectorAll('[data-product-id]').forEach((option) => option.remove());
            line.querySelectorAll('[id], [for], [aria-controls], [name]').forEach((element) => {
                ['id', 'for', 'aria-controls', 'name'].forEach((attribute) => {
                    if (element.hasAttribute(attribute)) {
                        element.setAttribute(attribute, element.getAttribute(attribute)
                            .replace(`movement-product-${oldIndex}-`, `movement-product-${newIndex}-`)
                            .replace(`productos[${oldIndex}]`, `productos[${newIndex}]`));
                    }
                });
            });
            line.querySelectorAll('input').forEach((input) => { input.value = ''; input.setCustomValidity(''); });
            line.querySelector('select').value = '';
            line.querySelector('[role="listbox"]').classList.add('hidden');
            line.querySelector('[role="combobox"]').setAttribute('aria-expanded', 'false');
            line.querySelector('[role="combobox"]').removeAttribute('aria-activedescendant');
            movementLines.appendChild(line);
            setupMovementLine(line);
            updateMovementLines();
            line.querySelector('[role="combobox"]').focus();
        };

        const setupMovementLine = (line) => {
            const picker = line.querySelector('.product-picker');
            picker.addEventListener('productchange', updateMovementLines);
            line.querySelector('.remove-movement-line').addEventListener('click', () => {
                line.remove();
                updateMovementLines();
            });
            initializePicker(picker);
        };

        const updateProviderField = () => {
            const isPurchase = movementReason.value === 'Compra / Adquisición';
            const isReturn = movementReason.value === 'Devolución';
            const required = isPurchase || isReturn;
            providerField.classList.toggle('hidden', !required);
            providerSelect.disabled = !required;
            providerSelect.required = required;
            const documentField = document.getElementById('movement-document-field');
            documentField.classList.toggle('hidden', !required);
            document.getElementById('movement-document').disabled = !required;
            updateMovementLines();
        };

        const updateReasons = () => {
            Array.from(movementReason.options).forEach((option) => {
                if (!option.value) return;
                const allowed = option.dataset.types === movementType.value;
                option.hidden = !allowed;
                option.disabled = !allowed;
                if (!allowed && option.selected) movementReason.value = '';
            });
            updateProviderField();
        };

        const openMovementModal = () => {
            if (!movementModal) {
                return;
            }

            previousFocus = document.activeElement;
            movementModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            movementModal.querySelector('[role="combobox"]')?.focus();
        };

        const closeMovementModal = () => {
            if (!movementModal) {
                return;
            }

            movementModal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            previousFocus?.focus();
        };

        openModalButton?.addEventListener('click', openMovementModal);
        addMovementLineButton.addEventListener('click', addMovementLine);
        closeModalButtons.forEach((button) => button?.addEventListener('click', closeMovementModal));
        movementType?.addEventListener('change', () => {
            updateReasons();
        });
        movementReason?.addEventListener('change', updateProviderField);
        movementForm?.addEventListener('submit', () => {
            const summary = Array.from(movementLines.querySelectorAll('.movement-line')).filter((line) => line.querySelector('input[type="hidden"]').value).map((line) => {
                const productId = line.querySelector('input[type="hidden"]').value;
                const product = productCatalog.find((item) => String(item.id) === productId);
                const quantity = Number(line.querySelector('.movement-quantity').value);
                const result = movementType.value === 'ajuste' ? quantity : product.stock + (movementType.value === 'egreso' ? -quantity : quantity);
                return `${product.etiqueta} → ${result}`;
            }).join('\n');
            movementForm.dataset.confirmMessage = `Movimiento: ${movementType.selectedOptions[0].textContent.trim()}\nMotivo: ${movementReason.selectedOptions[0].textContent.trim()}\n\n${summary}\n\n¿Deseas registrar estos cambios?`;
        });
        movementModal?.addEventListener('click', (event) => {
            if (event.target === movementModal) {
                closeMovementModal();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && movementModal && !movementModal.classList.contains('hidden')) {
                closeMovementModal();
            }
        });

        if (movementModal?.dataset.openOnLoad === 'true') {
            openMovementModal();
        }
        initializePicker(document.querySelector('#movement-filter-product-search').closest('.product-picker'));
        movementLines.querySelectorAll('.movement-line').forEach(setupMovementLine);
        updateReasons();
    });
</script>
@endsection