@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl py-6 pb-32">
    <div>
        <p class="text-sm font-bold uppercase tracking-[.25em] text-[#9f2f25]">
            Catálogo de repuestos
        </p>
        <h1 class="mt-2 text-5xl font-black tracking-tight text-[#241817]">
            Encuentra lo que necesitas.
        </h1>
    </div>

    @if(session('success'))
        <div id="success-toast"
            class="fixed inset-x-4 top-4 z-[60] mx-auto flex max-w-2xl items-start gap-4 rounded-2xl border border-green-300 bg-green-50 p-5 text-green-950 shadow-[0_20px_60px_rgba(36,24,23,0.25)] sm:top-8 sm:gap-5 sm:p-7"
            role="status" aria-live="polite" data-auto-dismiss>
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#e5f5e9] text-[#21713e] sm:h-14 sm:w-14" aria-hidden="true">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xl font-black leading-tight text-green-950 sm:text-2xl">¡Tu cotización ha sido enviada con éxito!</p>
                <p class="mt-2 text-base leading-relaxed text-green-900">{{ session('success') }}</p>
            </div>
            <button type="button" class="-mr-2 -mt-2 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-2xl text-green-800 hover:bg-green-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-800" aria-label="Cerrar confirmación" data-dismiss-notification>&times;</button>
        </div>
    @endif

    @if($errors->any())
        <div id="error-toast"
            class="fixed right-5 top-24 z-60 max-w-sm border-l-4 border-red-700 bg-red-50 p-5 text-red-800 shadow-xl"
            role="alert" data-auto-dismiss>
            <p class="font-black">No se pudo enviar la cotización</p>
            <p class="mt-1 text-sm">{{ $errors->first() }}</p>
        </div>
    @endif

    <form method="GET" action="{{ route('catalogo.index') }}"
        class="mt-10 grid gap-3 bg-white p-5 shadow-sm md:grid-cols-[1fr_240px_auto]">

        <div id="catalogo-selector" class="relative min-w-0">
            <label for="catalogo-buscar" class="sr-only">Buscar producto</label>

            <input type="text" id="catalogo-buscar" name="nombre"
                value="{{ request('nombre') }}"
                placeholder="Buscar por nombre o palabra"
                autocomplete="off"
                class="w-full border border-[#d9aaa3] bg-white p-3 text-[#241817] focus:border-[#b52f25] focus:outline-none">

            <div id="catalogo-sugerencias" style="display: none;"
                class="absolute left-0 top-full z-50 mt-1 max-h-40 w-full overflow-y-auto rounded-md border border-[#d9aaa3] bg-white shadow-lg">
            </div>

            @php($datosBuscador = $productosBuscador->map(fn ($p) => [$p->nombre, $p->codigo_origen ?: $p->codigo_barra, $p->id_categoria, $p->id_producto, $p->stock_disponible])->values())
            <script type="application/json" id="catalogo-datos">@json($datosBuscador)</script>
        </div>

        <select id="catalogo-categoria" name="categoria" aria-label="Categoría"
            class="border border-[#d9aaa3] p-3 text-[#241817] focus:border-[#b52f25] focus:outline-none">
            <option value="">Todas las categorías</option>

            @foreach($categorias as $categoria)
                <option value="{{ $categoria->id_categoria }}"
                    @selected(request('categoria') == $categoria->id_categoria)>
                    {{ $categoria->nombre_categoria }}
                </option>
            @endforeach
        </select>

        <button type="submit"
            class="bg-[#b52f25] px-6 py-3 font-bold text-white hover:bg-[#8f241d]">
            Buscar
        </button>
    </form>

    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($productos as $producto)
            <article class="flex flex-col justify-between bg-white p-6 shadow-sm">
                <div>
                    @if($producto->imagen)
                        <img src="{{ asset('storage/'.$producto->imagen) }}"
                            alt="{{ $producto->nombre }}"
                            class="mb-5 h-48 w-full object-cover">
                    @else
                        <div class="mb-5 flex h-48 items-center justify-center bg-[#f7e8e6] text-sm font-bold uppercase tracking-widest text-[#9f2f25]">
                            RESMAP
                        </div>
                    @endif

                    <p class="text-xs font-bold uppercase tracking-wider text-[#9f2f25]">
                        {{ $producto->categoria?->nombre_categoria ?? 'Repuesto' }}
                    </p>

                    <h2 class="mt-3 text-lg font-black text-[#241817]">
                        {{ $producto->nombre }}
                    </h2>

                    @if($producto->stock_disponible <= 0)
                        <p class="mt-2 text-xs font-bold text-amber-700">
                            Sin stock disponible. Stock: 0
                        </p>
                    @endif
                </div>

                <div class="mt-7 flex items-center justify-between gap-3">
                    <span class="text-xl font-black text-[#8f241d]">
                        $ {{ number_format($producto->precio, 0, ',', '.') }}

                        @if($producto->unidad === 'metro')
                            <small class="text-sm font-semibold">/ metro</small>
                        @endif
                    </span>

                    <div id="selector-{{ $producto->id_producto }}"
                        data-nombre="{{ $producto->nombre }}"
                        data-stock="{{ $producto->stock_disponible }}">

                        <button type="button" data-agregar-producto
                            data-producto-id="{{ $producto->id_producto }}"
                            data-producto-nombre="{{ $producto->nombre }}"
                            data-producto-stock="{{ $producto->stock_disponible }}"
                            @disabled($producto->stock_disponible <= 0)
                            class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white enabled:hover:bg-[#8f241d] disabled:cursor-not-allowed disabled:opacity-50">
                            {{ $producto->stock_disponible <= 0 ? 'Sin stock' : 'Agregar' }}
                        </button>
                    </div>
                </div>
            </article>
        @empty
            <p class="bg-white p-6 text-center text-gray-500 sm:col-span-2 lg:col-span-3">
                No se encontraron productos.
            </p>
        @endforelse
    </div>

    <div class="mt-8">{{ $productos->links() }}</div>
</main>

<div id="barra-carrito"
    class="fixed inset-x-0 bottom-0 z-40 hidden border-t-4 border-[#b52f25] bg-[#fff7f5] shadow-[0_-8px_24px_rgba(80,24,18,0.18)]">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:gap-4">
        <span class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#f7e1dc] text-[#9f2f25] sm:flex" aria-hidden="true">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2 11h11l3-8H6m2 12h.01M17 19h.01"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-base font-black leading-snug text-[#8f241d]">Tu carrito</p>
            <div class="flex min-w-0 flex-col items-start gap-1 sm:flex-row sm:items-center sm:gap-3">
                <div id="resumen-carrito"
                    class="flex w-full min-w-0 flex-1 flex-col gap-1 sm:w-auto sm:flex-row sm:gap-4"
                    aria-live="polite">
                </div>

                <button id="ver-mas-carrito" type="button" onclick="abrirCarrito()"
                    class="hidden shrink-0 whitespace-nowrap text-left text-xs font-bold text-[#9f2f25] underline sm:text-sm">
                </button>
            </div>
        </div>

        <button id="abrir-carrito-completo" type="button" onclick="abrirCarrito()"
            class="inline-flex shrink-0 items-center gap-2 rounded-md bg-[#b52f25] px-2 py-3 text-xs font-bold text-white shadow-md transition hover:bg-[#8f241d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d] sm:px-4 sm:text-base">
            Ver carrito
            <span id="contador-carrito"
                class="rounded-full bg-white px-2 py-0.5 text-xs text-[#b52f25]">
                0
            </span>
        </button>
    </div>
</div>

<div id="carrito-modal"
    class="fixed inset-0 z-50 hidden bg-black/50 p-4"
    role="dialog" aria-modal="true" aria-labelledby="carrito-titulo">
    <div class="ml-auto flex h-full w-full max-w-lg flex-col bg-white text-[#241817] shadow-2xl">
        <div class="flex items-center justify-between border-b border-[#e8c8c3] p-5">
            <h2 id="carrito-titulo" class="text-2xl font-black text-[#8f241d]">
                Tu carrito
            </h2>

            <button type="button" onclick="cerrarCarrito()"
                class="rounded-md px-2 text-2xl text-[#8f241d] hover:bg-[#f7e8e6] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]" aria-label="Cerrar carrito">
                &times;
            </button>
        </div>

        <div id="carrito-items" class="flex-1 space-y-3 overflow-y-auto p-5"></div>

        <div id="stock-insuficiente"
            class="hidden mx-5 mb-4 rounded-lg border border-[#f0c7a6] bg-[#fff7ed] p-4 text-[#632a1e] shadow-sm"
            role="status" aria-live="polite">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#f9e3ce] text-[#8f241d]" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.9 2.8 18a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.9a2 2 0 0 0-3.4 0Z"/></svg>
                </span>
                <div>
                    <p class="font-black">Revisa la disponibilidad</p>
                    <p id="stock-mensaje" class="mt-1 text-sm font-medium leading-relaxed"></p>
                </div>
            </div>
        </div>

        <div class="border-t border-[#e8c8c3] p-5">
            <button type="button" onclick="abrirSolicitud()"
                class="w-full rounded-md bg-[#b52f25] p-3 font-bold text-white hover:bg-[#8f241d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]">
                Continuar cotización
            </button>
        </div>
    </div>
</div>

<div id="solicitud-modal"
    class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50 p-4"
    role="dialog" aria-modal="true" aria-labelledby="solicitud-titulo">
    <div class="mx-auto mt-8 max-w-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-[#e8c8c3] p-5">
            <h2 id="solicitud-titulo" class="text-2xl font-black text-[#8f241d]">
                Solicitar cotización
            </h2>

            <button type="button" onclick="cerrarSolicitud()"
                class="text-2xl text-[#8f241d]" aria-label="Cerrar formulario">
                &times;
            </button>
        </div>

        @if($errors->any())
            <div class="mx-6 mt-5 border-l-4 border-red-700 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-black">Revisa los datos ingresados</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('catalogo.solicitudes.store') }}"
            class="grid grid-cols-1 items-start gap-4 p-6 sm:grid-cols-2">
            @csrf

            <div id="detalles_productos" class="contents"></div>

            <div>
                <input id="rut_solicitud" name="rut" value="{{ old('rut') }}" required
                    placeholder="12.345.678-5" data-rut maxlength="12"
                    autocomplete="off"
                    class="w-full border border-[#d9aaa3] p-3 text-[#241817]">
                <p id="mensaje-cliente-solicitud" class="mt-1 text-xs text-gray-600"
                    role="status" aria-live="polite"></p>
            </div>

            <input name="nombre" value="{{ old('nombre') }}" required
                placeholder="Razón social o nombre"
                class="w-full border border-[#d9aaa3] p-3 text-[#241817]">

            <label class="block text-sm font-bold" for="contacto_solicitud">Atención / contacto <span class="text-[#b52f25]" aria-hidden="true">*</span>
                <input id="contacto_solicitud" name="contacto" value="{{ old('contacto') }}" required maxlength="255"
                    placeholder="Nombre de la persona responsable" class="mt-1 w-full border border-[#d9aaa3] p-3 font-normal text-[#241817]">
            </label>
            <label class="block text-sm font-bold" for="cargo_solicitud">Cargo <span class="font-normal text-gray-500">(opcional)</span>
                <input id="cargo_solicitud" name="cargo" value="{{ old('cargo') }}" maxlength="255"
                    placeholder="Ej.: jefe de compras, OT o equipo" class="mt-1 w-full border border-[#d9aaa3] p-3 font-normal text-[#241817]">
            </label>

            <input name="correo" type="email" value="{{ old('correo') }}" required
                placeholder="Correo"
                class="w-full border border-[#d9aaa3] p-3 text-[#241817]">

            <div class="w-full">
                <label for="telefono" class="sr-only">Teléfono chileno</label>
                <div class="flex">
                    <span class="flex items-center border border-r-0 border-[#d9aaa3] bg-[#f7e8e6] px-3 font-bold text-[#8f241d]">
                        +569
                    </span>
                    <input id="telefono" name="telefono"
                        value="{{ old('telefono') }}" required
                        pattern="[0-9]{8}" maxlength="8" inputmode="numeric"
                        placeholder="12345678"
                        class="min-w-0 flex-1 border border-[#d9aaa3] p-3 text-[#241817]">
                </div>
                <p class="mt-1 text-sm text-gray-600">Te contactaremos por WhatsApp a este número para enviarte la cotización.</p>
            </div>

            <input name="direccion" value="{{ old('direccion') }}"
                placeholder="Dirección"
                class="w-full border border-[#d9aaa3] p-3 text-[#241817] sm:col-span-2">

            <button type="submit"
                class="bg-[#b52f25] p-3 font-bold text-white hover:bg-[#8f241d] sm:col-span-2">
                Enviar cotización
            </button>
        </form>
    </div>
</div>

<script src="{{ asset('js/rut.js') }}"></script>
<script src="{{ asset('js/chile-location.js') }}"></script>

<script>
(() => {
    const buscador = document.getElementById('catalogo-buscar');
    const lista = document.getElementById('catalogo-sugerencias');
    const contenedor = document.getElementById('catalogo-selector');
    const categoria = document.getElementById('catalogo-categoria');

    const normalizar = texto => texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const separarPalabras = texto => normalizar(texto)
        .split(/[^a-z0-9]+/)
        .filter(Boolean);

    const productos = JSON.parse(
        document.getElementById('catalogo-datos').textContent
    ).map(([nombre, codigo, categoria]) => ({
        nombre,
        codigo: codigo || '',
        categoria: String(categoria),
        texto: separarPalabras(`${nombre} ${codigo || ''}`)
    }));

    function cerrar() {
        lista.style.display = 'none';
    }

    function mostrar() {
        const palabrasBuscadas = separarPalabras(buscador.value);
        lista.replaceChildren();

        if (!palabrasBuscadas.length) {
            cerrar();
            return;
        }

        const coincidencias = productos.filter(producto => {
            const coincideCategoria =
                !categoria.value || producto.categoria === categoria.value;

            const coincideNombre = palabrasBuscadas.every(textoBuscado =>
                producto.texto.some(palabra => palabra.includes(textoBuscado))
            );

            return coincideCategoria && coincideNombre;
        }).slice(0, 30);

        if (!coincidencias.length) {
            const mensaje = document.createElement('p');
            mensaje.className = 'px-4 py-3 text-sm text-gray-500';
            mensaje.textContent = 'No se encontraron productos.';
            lista.append(mensaje);
        }

        coincidencias.forEach(producto => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className =
                'block w-full border-b border-gray-100 px-4 py-3 text-left text-sm font-semibold text-[#241817] hover:bg-red-50 focus:bg-red-50';

            const nombre = document.createElement('span');
            nombre.className = 'block';
            nombre.textContent = producto.nombre;
            boton.append(nombre);

            if (producto.codigo) {
                const codigo = document.createElement('span');
                codigo.className = 'block text-xs font-normal text-gray-500';
                codigo.textContent = producto.codigo;
                boton.append(codigo);
            }

            boton.addEventListener('click', () => {
                buscador.value = producto.nombre;
                cerrar();
                buscador.form.requestSubmit();
            });

            lista.append(boton);
        });

        lista.style.display = 'block';
    }

    buscador.addEventListener('input', mostrar);
    buscador.addEventListener('focus', mostrar);
    categoria.addEventListener('change', cerrar);

    contenedor.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            buscador.focus();
            cerrar();
            return;
        }

        if (event.key === 'ArrowDown' && event.target === buscador) {
            mostrar();
            const primeraOpcion = lista.querySelector('button');

            if (primeraOpcion) {
                event.preventDefault();
                primeraOpcion.focus();
            }

            return;
        }

        const opciones = Array.from(lista.querySelectorAll('button'));
        const indice = opciones.indexOf(document.activeElement);

        if (indice !== -1 && event.key === 'ArrowDown') {
            event.preventDefault();
            opciones[(indice + 1) % opciones.length].focus();
        }

        if (indice !== -1 && event.key === 'ArrowUp') {
            event.preventDefault();

            if (indice === 0) {
                buscador.focus();
            } else {
                opciones[indice - 1].focus();
            }
        }
    });

    document.addEventListener('click', event => {
        if (!contenedor.contains(event.target)) cerrar();
    });

    contenedor.addEventListener('focusout', event => {
        if (!contenedor.contains(event.relatedTarget)) cerrar();
    });
})();

const carritoStorageKey = 'resmap-carrito';

function leerCarrito() {
    try {
        const datos = JSON.parse(
            window.localStorage.getItem(carritoStorageKey) || '{}'
        );

        return datos && typeof datos === 'object' && !Array.isArray(datos)
            ? datos
            : {};
    } catch {
        return {};
    }
}

const seleccion = leerCarrito();
const stockDisponible = new Map(
    JSON.parse(document.getElementById('catalogo-datos').textContent)
        .map(([, , , id, stock]) => [String(id), Number(stock)])
);

Object.entries(seleccion).forEach(([id, item]) => {
    item.stock = stockDisponible.get(String(id)) ?? 0;
});

function guardarCarrito() {
    window.localStorage.setItem(
        carritoStorageKey,
        JSON.stringify(seleccion)
    );
}

guardarCarrito();

function escaparHTML(valor) {
    const elemento = document.createElement('span');
    elemento.textContent = String(valor);

    return elemento.innerHTML
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function mostrarErrorStock(mensaje) {
    abrirCarrito();
    const elemento = document.getElementById('stock-insuficiente');
    document.getElementById('stock-mensaje').textContent = mensaje;
    elemento.classList.remove('hidden');
}

function limpiarErrorStock() {
    const elemento = document.getElementById('stock-insuficiente');
    document.getElementById('stock-mensaje').textContent = '';
    elemento.classList.add('hidden');
}

function agregar(id, nombre, stock) {
    stock = Number(stock);

    if (stock <= 0) {
        mostrarErrorStock(`Sin stock disponible para ${nombre}.`);
        return;
    }

    seleccion[id] = seleccion[id] || {
        id_producto: id,
        cantidad: 0,
        nombre,
        stock
    };

    seleccion[id].stock = stock;

    if (seleccion[id].cantidad >= Math.min(stock, 99)) {
        mostrarErrorStock(
            stock <= 99 ? `Stock insuficiente para ${nombre}: disponibles ${stock}.` : `El límite por producto es 99 unidades.`
        );
        return;
    }

    seleccion[id].cantidad += 1;
    limpiarErrorStock();
    guardarCarrito();
    renderCarrito();
}

function cambiarCantidad(id, cambio) {
    const item = seleccion[id];
    if (!item) return;

    if (cambio > 0 && item.cantidad >= Math.min(Number(item.stock), 99)) {
        mostrarErrorStock(
            Number(item.stock) <= 99 ? `Stock insuficiente para ${item.nombre}: disponibles ${item.stock}.` : `El límite por producto es 99 unidades.`
        );
        return;
    }

    item.cantidad += cambio;

    if (item.cantidad <= 0) delete seleccion[id];

    limpiarErrorStock();
    guardarCarrito();
    renderCarrito();
}

function establecerCantidad(id, valor) {
    const item = seleccion[id];
    if (!item) return;

    const cantidadSolicitada = Number.parseInt(valor, 10) || 1;
    const stock = Number(item.stock);

    if (stock <= 0) {
        abrirCarrito();
        return;
    }

    item.cantidad = Math.max(1, Math.min(stock, 99, cantidadSolicitada));
    guardarCarrito();
    renderCarrito();

    if (cantidadSolicitada > stock) {
        mostrarErrorStock(
            `Stock insuficiente para ${item.nombre}: disponibles ${stock}.`
        );
    } else if (cantidadSolicitada > 99) {
        mostrarErrorStock('El límite por producto es 99 unidades.');
    }
}

function renderCarrito() {
    document.querySelectorAll('[id^="selector-"]').forEach(selector => {
        const id = Number(selector.id.replace('selector-', ''));

        if (seleccion[id]) {
            seleccion[id].stock = Number(selector.dataset.stock || 0);
        }
    });

    const items = Object.values(seleccion);
    const productoSinStock = items.find(
        item => item.cantidad > Number(item.stock)
    );

    limpiarErrorStock();

    if (productoSinStock) {
        const mensaje = document.getElementById('stock-insuficiente');
        document.getElementById('stock-mensaje').textContent = Number(productoSinStock.stock) === 0
            ? `${productoSinStock.nombre} ya no tiene stock. Elimínalo con el ícono del basurero para continuar.`
            : `De ${productoSinStock.nombre} quedan ${productoSinStock.stock} ${Number(productoSinStock.stock) === 1 ? 'unidad disponible' : 'unidades disponibles'}. Ajusta la cantidad para continuar.`;
        mensaje.classList.remove('hidden');
    }

    document.getElementById('contador-carrito').textContent =
        items.reduce((total, item) => total + item.cantidad, 0);

    document.getElementById('barra-carrito')
        .classList.toggle('hidden', items.length === 0);

    document.getElementById('resumen-carrito').replaceChildren(
        ...items.slice(0, 2).map(item => {
            const resumen = document.createElement('span');
            resumen.className =
                'truncate text-base font-semibold leading-snug text-[#241817]';
            resumen.textContent = `${item.cantidad} × ${item.nombre}`;
            return resumen;
        })
    );

    const verMas = document.getElementById('ver-mas-carrito');
    verMas.classList.toggle('hidden', items.length <= 2);
    verMas.textContent = items.length > 2
        ? `Ver más (${items.length - 2})`
        : '';

    document.getElementById('carrito-items').innerHTML = items.length
        ? items.map(item => {
            const id = Number(item.id_producto);
            const stock = Number(item.stock);
            const cantidad = Number(item.cantidad);

            return `
                <div class="flex items-center gap-3 border-b border-[#e8c8c3] pb-3">
                    <span class="min-w-0 flex-1 break-words font-semibold text-[#241817]">
                        ${escaparHTML(item.nombre)}
                    </span>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button"
                            onclick="cambiarCantidad(${id}, -1)"
                            class="h-8 w-8 rounded-full bg-[#f7e8e6] font-bold text-[#8f241d] hover:bg-white">−</button>
                        <input type="number" min="1" max="${Math.max(1, Math.min(stock, 99))}"
                            value="${cantidad}" aria-label="Cantidad"
                            onchange="establecerCantidad(${id}, this.value)"
                            class="h-8 w-12 border border-[#d9aaa3] bg-white text-center font-bold text-[#8f241d]">
                        <button type="button"
                            onclick="cambiarCantidad(${id}, 1)"
                            class="h-8 w-8 rounded-full bg-[#f7e8e6] font-bold text-[#8f241d] hover:bg-white">+</button>
                        <button type="button"
                            onclick="cambiarCantidad(${id}, -${cantidad})"
                            aria-label="Quitar ${escaparHTML(item.nombre)} del carrito"
                            title="Quitar del carrito"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-[#8f241d] hover:bg-[#f7e8e6] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]">
                            <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('')
        : '<p class="py-8 text-center text-gray-500">Tu carrito está vacío.</p>';

    document.querySelectorAll('[id^="selector-"]').forEach(selector => {
        const id = Number(selector.id.replace('selector-', ''));
        const item = seleccion[id];
        const stock = Number(selector.dataset.stock || 0);

        selector.innerHTML = item
            ? `
                <div class="flex items-center gap-2 rounded-md border border-[#e8c8c3] p-1">
                    <button type="button"
                        onclick="cambiarCantidad(${id}, -1)"
                        class="h-8 w-8 rounded bg-[#f7e8e6] font-bold text-[#8f241d]">−</button>
                    <input type="number" min="1" max="${Math.min(stock, 99)}"
                        value="${Number(item.cantidad)}" aria-label="Cantidad"
                        onchange="establecerCantidad(${id}, this.value)"
                        class="h-8 w-12 border border-[#d9aaa3] text-center font-bold text-[#8f241d]">
                    <button type="button"
                        onclick="cambiarCantidad(${id}, 1)"
                        class="h-8 w-8 rounded bg-[#b52f25] font-bold text-white">+</button>
                </div>
            `
            : `
                <button type="button" data-agregar-producto
                    data-producto-id="${id}"
                    data-producto-nombre="${escaparHTML(selector.dataset.nombre || '')}"
                    data-producto-stock="${stock}"
                    ${stock <= 0 ? 'disabled' : ''}
                    class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white enabled:hover:bg-[#8f241d] disabled:cursor-not-allowed disabled:opacity-50">
                    ${stock <= 0 ? 'Sin stock' : 'Agregar'}
                </button>
            `;
    });

    document.getElementById('detalles_productos').innerHTML =
        items.map(item => {
            const id = Number(item.id_producto);
            const cantidad = Number(item.cantidad);

            return `
                <input type="hidden"
                    name="detalles_productos[${id}][id_producto]" value="${id}">
                <input type="hidden"
                    name="detalles_productos[${id}][cantidad]" value="${cantidad}">
            `;
        }).join('');
}

document.addEventListener('click', event => {
    const boton = event.target.closest('[data-agregar-producto]');

    if (boton && !boton.disabled) {
        agregar(
            Number(boton.dataset.productoId),
            boton.dataset.productoNombre,
            boton.dataset.productoStock || 0
        );
    }
});

function abrirCarrito() {
    document.getElementById('carrito-modal').classList.remove('hidden');
    renderCarrito();
}

function cerrarCarrito() {
    document.getElementById('carrito-modal').classList.add('hidden');
}

function abrirSolicitud() {
    if (!Object.keys(seleccion).length) return;

    const faltante = Object.values(seleccion).find(
        item => item.cantidad > Number(item.stock)
    );

    if (faltante) {
        abrirCarrito();
        return;
    }

    cerrarCarrito();
    document.getElementById('solicitud-modal').classList.remove('hidden');
}

function cerrarSolicitud() {
    document.getElementById('solicitud-modal').classList.add('hidden');
}

renderCarrito();
inicializarFormularioCatalogo();

const solicitudForm = document.querySelector('#solicitud-modal form');

if (solicitudForm) {
    const rutCliente = solicitudForm.querySelector('input[name="rut"]');
    const mensajeCliente = document.getElementById('mensaje-cliente-solicitud');
    const camposCliente = {
        nombre: solicitudForm.querySelector('input[name="nombre"]'),
        contacto: solicitudForm.querySelector('input[name="contacto"]'),
        correo: solicitudForm.querySelector('input[name="correo"]'),
        direccion: solicitudForm.querySelector('input[name="direccion"]'),
        telefono: solicitudForm.querySelector('#telefono'),
        prefijo: solicitudForm.querySelector('#prefijo_telefono'),
        region: solicitudForm.querySelector('#region_solicitud'),
        comuna: solicitudForm.querySelector('#comuna_solicitud')
    };
    let temporizadorBusquedaCliente;
    let controladorBusquedaCliente;
    let numeroBusquedaCliente = 0;
    let ultimoRutConsultado = '';

    const normalizarRut = valor =>
        valor.replace(/[^0-9kK]/g, '').toUpperCase();

    const mostrarMensajeCliente = (texto, estado = 'info') => {
        mensajeCliente.textContent = texto;
        mensajeCliente.className = estado === 'error'
            ? 'mt-1 text-xs font-medium text-red-700'
            : estado === 'success'
                ? 'mt-1 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-xs font-semibold text-green-800'
                : 'mt-1 text-xs text-gray-600';
    };

    const limpiarDatosCliente = () => {
        camposCliente.nombre.value = '';
        camposCliente.contacto.value = '';
        camposCliente.correo.value = '';
        camposCliente.direccion.value = '';
        camposCliente.telefono.value = '';
        camposCliente.prefijo.value = '+569';
        camposCliente.prefijo.dispatchEvent(new Event('change'));
        camposCliente.region.value = '';
        camposCliente.region.dispatchEvent(new Event('change'));
        camposCliente.comuna.value = '';
    };

    const buscarClientePorRut = async () => {
        clearTimeout(temporizadorBusquedaCliente);

        const rutNormalizado = normalizarRut(rutCliente.value);

        if (rutNormalizado.length < 8 || rutNormalizado.length > 9) {
            mostrarMensajeCliente('Ingresa el RUT completo.');
            return;
        }

        if (rutNormalizado === ultimoRutConsultado) return;

        controladorBusquedaCliente?.abort();
        controladorBusquedaCliente = new AbortController();
        const numeroConsulta = ++numeroBusquedaCliente;
        mostrarMensajeCliente('Buscando cliente...');

        try {
            const parametros = new URLSearchParams({
                rut: rutCliente.value.trim()
            });
            const respuesta = await fetch(
                '{{ route('catalogo.cliente.rut') }}?' + parametros.toString(),
                {
                    headers: { 'Accept': 'application/json' },
                    signal: controladorBusquedaCliente.signal
                }
            );
            const datos = await respuesta.json();

            if (numeroConsulta !== numeroBusquedaCliente) return;

            if (!respuesta.ok) {
                const errores = Object.values(datos.errors || {}).flat().join(' ');
                throw new Error(errores || 'No se pudieron consultar los datos del cliente.');
            }

            ultimoRutConsultado = rutNormalizado;

            if (!datos.encontrado) {
                mostrarMensajeCliente(
                    'RUT no registrado. Completa tus datos para solicitar la cotización.'
                );
                return;
            }

            const cliente = datos.cliente;
            camposCliente.nombre.value = cliente.nombre || '';
            camposCliente.contacto.value = cliente.contacto || '';
            camposCliente.correo.value = cliente.correo || '';
            camposCliente.direccion.value = cliente.direccion || '';

            const digitosTelefono = String(cliente.telefono || '').replace(/\D/g, '');
            camposCliente.telefono.value = digitosTelefono.length >= 8
                ? digitosTelefono.slice(-8)
                : '';

            if (digitosTelefono.length > 8) {
                const prefijo = '+' + digitosTelefono.slice(0, -8);
                if (Array.from(camposCliente.prefijo.options).some(
                    opcion => opcion.value === prefijo
                )) {
                    camposCliente.prefijo.value = prefijo;
                    camposCliente.prefijo.dispatchEvent(new Event('change'));
                }
            }

            camposCliente.region.value = cliente.region || '';
            camposCliente.region.dispatchEvent(new Event('change'));
            camposCliente.comuna.value = cliente.comuna || '';

            mostrarMensajeCliente(
                'RUT ya registrado. Se autocompletaron los datos del cliente.',
                'success'
            );
        } catch (error) {
            if (error.name === 'AbortError' || numeroConsulta !== numeroBusquedaCliente) {
                return;
            }

            ultimoRutConsultado = '';
            mostrarMensajeCliente(error.message, 'error');
        }
    };

    rutCliente.addEventListener('input', () => {
        clearTimeout(temporizadorBusquedaCliente);
        controladorBusquedaCliente?.abort();
        numeroBusquedaCliente++;
        ultimoRutConsultado = '';
        limpiarDatosCliente();
        mostrarMensajeCliente('');

        const rutNormalizado = normalizarRut(rutCliente.value);
        if (rutNormalizado.length >= 8 && rutNormalizado.length <= 9) {
            temporizadorBusquedaCliente = setTimeout(buscarClientePorRut, 600);
        }
    });

    rutCliente.addEventListener('blur', buscarClientePorRut);
    rutCliente.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            buscarClientePorRut();
        }
    });

    const obligatorioLabel = (id, texto) => {
        const label = document.createElement('label');
        label.htmlFor = id;
        label.className = 'mb-1 block text-sm font-bold text-[#241817]';
        label.textContent = texto + ' ';

        const asterisco = document.createElement('span');
        asterisco.className = 'text-[#b52f25]';
        asterisco.setAttribute('aria-hidden', 'true');
        asterisco.textContent = '*';
        label.append(asterisco);

        return label;
    };

    const rut = solicitudForm.querySelector('input[name="rut"]');
    rut.id = 'rut_solicitud';
    rut.before(obligatorioLabel(rut.id, 'RUT'));

    const nombre = solicitudForm.querySelector('input[name="nombre"]');
    nombre.id = 'nombre_solicitud';

    const nombreWrapper = document.createElement('div');
    nombre.before(nombreWrapper);
    nombreWrapper.append(
        obligatorioLabel(nombre.id, 'Razón social'),
        nombre
    );

    const correo = solicitudForm.querySelector('input[name="correo"]');
    correo.id = 'correo_solicitud';

    const correoWrapper = document.createElement('div');
    correo.before(correoWrapper);
    correoWrapper.append(
        obligatorioLabel(correo.id, 'Correo electrónico'),
        correo
    );

    const telefonoLabel = solicitudForm.querySelector('label[for="telefono"]');
    telefonoLabel.className = 'mb-1 block text-sm font-bold text-[#241817]';
    telefonoLabel.textContent = 'Teléfono *';

    [
        ['region_solicitud', 'Región'],
        ['comuna_solicitud', 'Comuna']
    ].forEach(([id, texto]) => {
        const campo = solicitudForm.querySelector(`#${id}`);

        if (campo) {
            campo.before(obligatorioLabel(id, texto));
        }
    });

    const leyenda = document.createElement('p');
    leyenda.className = 'text-xs text-gray-500 sm:col-span-2';
    leyenda.textContent = '* Campo obligatorio';
    solicitudForm.querySelector('#detalles_productos').after(leyenda);
}

solicitudForm?.addEventListener('submit', event => {
    const faltantes = Object.values(seleccion).filter(
        item => item.cantidad > Number(item.stock)
    );

    if (!faltantes.length) return;

    event.preventDefault();

    const detalle = faltantes.map(item =>
        `${item.nombre}: solicitas ${item.cantidad}, quedan ${item.stock}`
    ).join('. ');

    cerrarSolicitud();
    mostrarErrorStock(
        `Stock insuficiente: ${detalle}. Ajusta las cantidades para continuar.`
    );
});

const urlParams = new URLSearchParams(window.location.search);

if (urlParams.get('open_cart') === 'true') {
    abrirCarrito();
    urlParams.delete('open_cart');

    const consulta = urlParams.toString();

    window.history.replaceState(
        {},
        document.title,
        window.location.pathname + (consulta ? '?' + consulta : '')
    );
}

const confirmacionEnviada = document.getElementById('success-toast');

if (confirmacionEnviada) {
    window.localStorage.removeItem(carritoStorageKey);
    Object.keys(seleccion).forEach(id => delete seleccion[id]);
    renderCarrito();
}

@if($errors->any())
    abrirSolicitud();
@endif
</script>
@endsection