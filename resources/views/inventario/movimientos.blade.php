@extends('layouts.app')

@section('content')
<div class="bg-white shadow-md rounded-lg p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Movimientos de Inventario</h2>
        <button type="button" id="open-movement-modal" class="rounded-md bg-[#b52f25] px-4 py-2 font-medium text-white hover:bg-[#8f241d]">
            Registrar Movimiento
        </button>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ session('success') }}
        </div>
    @endif

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
                    @foreach($productos as $prod)
                        @php($catNombre = $prod->categoria->nombre_categoria ?? 'Sin Categoría')
                        @php($productoLabel = $prod->nombre.' | Cat: '.$catNombre.' | Stock: '.$prod->stock)
                        <button id="movement-filter-product-option-{{ $prod->id_producto }}" type="button" role="option" aria-selected="false" data-product-id="{{ $prod->id_producto }}" data-search="{{ $prod->nombre.' '.$catNombre.' '.$prod->codigo_barra.' '.($prod->codigo_origen ?? '') }}" data-label="{{ $productoLabel }}" class="hidden w-full px-3 py-2 text-left text-sm hover:bg-[#f7e8e6] focus:bg-[#f7e8e6] focus:outline-none">{{ $productoLabel }}</button>
                    @endforeach
                    <p class="picker-no-results hidden px-3 py-2 text-sm text-gray-500">No hay repuestos que coincidan con la búsqueda.</p>
                </div>
            </div>
            <div>
                <label for="movement-filter-type" class="mb-1 block text-sm font-medium text-gray-700">Tipo de movimiento</label>
                <select id="movement-filter-type" name="tipo" class="w-full rounded-md border border-gray-300 p-2 text-sm">
                    <option value="">Todos los tipos</option>
                    <option value="ingreso" @selected(request('tipo') === 'ingreso')>Ingreso</option>
                    <option value="egreso" @selected(request('tipo') === 'egreso')>Egreso</option>
                    <option value="ajuste" @selected(request('tipo') === 'ajuste')>Ajuste</option>
                    <option value="adquisicion" @selected(request('tipo') === 'adquisicion')>Adquisición</option>
                    <option value="merma" @selected(request('tipo') === 'merma')>Merma</option>
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
                    <th class="px-4 py-3 text-xs font-medium uppercase text-gray-500">Usuario</th>
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
                            <span class="px-2 py-1 text-xs rounded-full {{ in_array($mov->tipo, ['ingreso', 'adquisicion']) ? 'bg-green-100 text-green-800' : (in_array($mov->tipo, ['egreso', 'merma']) ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800') }}">
                                {{ ucfirst($mov->tipo) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-bold">{{ abs($mov->cantidad) }}</td>
                        <td class="px-4 py-3">{{ $mov->stock_nuevo }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $mov->motivo }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $mov->usuario->name ?? 'Sistema' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
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
        <form action="{{ route('inventario.movimientos.store') }}" method="POST" class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
            @csrf
            <input type="hidden" name="_form_context" value="movement">

            <div class="product-picker relative md:col-span-2" data-picker-required="true">
                <label for="movement-product-search" class="mb-1 block text-sm font-medium text-gray-700">Buscar / Seleccionar Producto</label>
                <input id="movement-product-search" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="movement-product-options" autocomplete="off" required placeholder="Escribe el nombre o código del repuesto..." class="w-full rounded-md border border-gray-300 p-2 text-sm focus:border-[#b52f25] focus:outline-none">
                <input type="hidden" id="movement-product-value" name="id_producto" value="{{ old('id_producto') }}" required>
                <div id="movement-product-options" role="listbox" class="absolute left-0 top-full z-30 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-md border border-gray-300 bg-white shadow-lg">
                    @foreach($productos as $prod)
                        @php($catNombre = $prod->categoria->nombre_categoria ?? 'Sin Categoría')
                        @php($productoLabel = $prod->nombre.' | Cat: '.$catNombre.' | Stock: '.$prod->stock)
                        <button id="movement-product-option-{{ $prod->id_producto }}" type="button" role="option" aria-selected="false" data-product-id="{{ $prod->id_producto }}" data-category-id="{{ $prod->id_categoria }}" data-search="{{ $prod->nombre.' '.$catNombre.' '.$prod->codigo_barra.' '.($prod->codigo_origen ?? '') }}" data-label="{{ $productoLabel }}" class="hidden w-full px-3 py-2 text-left text-sm hover:bg-[#f7e8e6] focus:bg-[#f7e8e6] focus:outline-none">{{ $productoLabel }}</button>
                    @endforeach
                    <p class="picker-no-results hidden px-3 py-2 text-sm text-gray-500">No hay repuestos que coincidan con la búsqueda.</p>
                </div>
                <label for="movement-product-category" class="mb-1 mt-3 block text-sm font-medium text-gray-700">Filtrar por categoría</label>
                <select id="movement-product-category" class="w-full rounded-md border border-gray-300 p-2 text-sm">
                    <option value="">Todas las categorías</option>
                    @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id_categoria }}" @selected((string) old('categoria') === (string) $categoria->id_categoria)>{{ $categoria->nombre_categoria }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="movement-type" class="mb-1 block text-sm font-medium text-gray-700">Tipo de movimiento</label>
                <select id="movement-type" name="tipo" class="w-full rounded-md border border-gray-300 p-2 text-sm" required>
                    <option value="ingreso" @selected(old('tipo', 'ingreso') === 'ingreso')>Ingreso (+)</option>
                    <option value="egreso" @selected(old('tipo') === 'egreso')>Egreso (-)</option>
                    <option value="ajuste" @selected(old('tipo') === 'ajuste')>Ajuste de Stock</option>
                </select>
            </div>
            <div>
                <label for="movement-quantity" class="mb-1 block text-sm font-medium text-gray-700">Cantidad</label>
                <input id="movement-quantity" type="number" name="cantidad" value="{{ old('cantidad', old('stock_nuevo')) }}" min="1" class="w-full rounded-md border border-gray-300 p-2 text-sm" required>
            </div>
            <div>
                <label for="movement-reason" class="mb-1 block text-sm font-medium text-gray-700">Motivo</label>
                <select id="movement-reason" name="motivo" class="w-full rounded-md border border-gray-300 p-2 text-sm" required>
                    <option value="">Seleccione motivo</option>
                    <option value="Compra / Adquisición" @selected(old('motivo') === 'Compra / Adquisición')>Compra / Adquisición</option>
                    <option value="Merma / Daño" @selected(old('motivo') === 'Merma / Daño')>Merma / Daño</option>
                    <option value="Devolución" @selected(old('motivo') === 'Devolución')>Devolución</option>
                    <option value="Ajuste de inventario" @selected(old('motivo') === 'Ajuste de inventario')>Ajuste de inventario</option>
                    <option value="Uso interno" @selected(old('motivo') === 'Uso interno')>Uso interno</option>
                </select>
            </div>
            <div>
                <label for="movement-event-date" class="mb-1 block text-sm font-medium text-gray-700">Fecha del evento</label>
                <input id="movement-event-date" type="date" name="fecha_evento" value="{{ old('fecha_evento', now()->toDateString()) }}" class="w-full rounded-md border border-gray-300 p-2 text-sm" required>
            </div>
            <div class="flex items-end justify-end gap-2 md:col-span-2">
                <button type="button" id="cancel-movement-modal" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancelar</button>
                <button type="submit" class="rounded-md bg-[#b52f25] px-4 py-2 text-sm font-medium text-white hover:bg-[#8f241d]">Registrar Movimiento</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const normalize = (value) => value
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('es')
            .trim();

        document.querySelectorAll('.product-picker').forEach((picker) => {
            const searchInput = picker.querySelector('[role="combobox"]');
            const selectedProductInput = picker.querySelector('input[type="hidden"]');
            const suggestions = picker.querySelector('[role="listbox"]');
            const noResults = picker.querySelector('.picker-no-results');
            const categorySelect = picker.querySelector('select');

            if (!searchInput || !selectedProductInput || !suggestions || !noResults) {
                return;
            }

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
            };

            const initiallySelectedProduct = productOptions.find((option) => option.dataset.productId === selectedProductInput.value);
            const initialProductMatchesCategory = initiallySelectedProduct
                && (!categorySelect?.value || initiallySelectedProduct.dataset.categoryId === categorySelect.value);

            if (initialProductMatchesCategory) {
                selectedLabel = initiallySelectedProduct.dataset.label;
                searchInput.value = selectedLabel;
                searchInput.setCustomValidity('');
            } else if (picker.dataset.pickerRequired === 'true') {
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
                    if (picker.dataset.pickerRequired === 'true') {
                        searchInput.setCustomValidity('Selecciona un repuesto de la lista de sugerencias.');
                    }
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
                }

                filterProducts();
                setSuggestionsOpen(true);
            });

            document.addEventListener('click', (event) => {
                if (!picker.contains(event.target)) {
                    setSuggestionsOpen(false);
                }
            });
        });

        const movementModal = document.getElementById('movement-modal');
        const movementType = document.getElementById('movement-type');
        const movementQuantity = document.getElementById('movement-quantity');
        const movementQuantityLabel = document.querySelector('label[for="movement-quantity"]');
        const openModalButton = document.getElementById('open-movement-modal');
        const closeModalButtons = [
            document.getElementById('close-movement-modal'),
            document.getElementById('cancel-movement-modal'),
        ];
        let previousFocus = null;

        const updateMovementQuantityField = () => {
            if (!movementType || !movementQuantity || !movementQuantityLabel) {
                return;
            }

            const isStockAdjustment = movementType.value === 'ajuste';
            movementQuantity.name = isStockAdjustment ? 'stock_nuevo' : 'cantidad';
            movementQuantity.min = isStockAdjustment ? '0' : '1';
            movementQuantityLabel.textContent = isStockAdjustment ? 'Nuevo stock' : 'Cantidad';
        };

        const openMovementModal = () => {
            if (!movementModal) {
                return;
            }

            previousFocus = document.activeElement;
            movementModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            movementModal.querySelector('#movement-product-search')?.focus();
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
        closeModalButtons.forEach((button) => button?.addEventListener('click', closeMovementModal));
        movementType?.addEventListener('change', updateMovementQuantityField);
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
        updateMovementQuantityField();
    });
</script>
@endsection