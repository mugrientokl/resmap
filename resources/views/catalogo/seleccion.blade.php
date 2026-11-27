<style>
    #abrir-carrito-completo, #carrito-modal .carrito-redondeado { border-radius: 9999px !important; }
    #barra-carrito {
        position: fixed;
        bottom: calc(1.25rem + var(--carrito-limite, 0px));
        right: 1.25rem;
        width: max-content;
        max-width: calc(100% - 2.5rem);
    }
    @media (min-width: 640px) {
        #barra-carrito { bottom: calc(1.75rem + var(--carrito-limite, 0px)); right: 1.75rem; }
    }
    #barra-carrito.en-contacto #abrir-carrito-completo { padding: .5rem; gap: .25rem; }
    #barra-carrito.en-contacto .carrito-boton-texto {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    #carrito-modal .carrito-cantidad { border-radius: .4rem; appearance: textfield; -moz-appearance: textfield; }
    #carrito-modal .carrito-cantidad::-webkit-inner-spin-button,
    #carrito-modal .carrito-cantidad::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
</style>

@php($datosCarrito = $productosStock->map(fn ($producto) => [$producto->id_producto, $producto->stock_disponible, $producto->precioVigente(), $producto->unidad])->values())
<script type="application/json" id="carrito-stock">@json($datosCarrito)</script>

<div id="barra-carrito" class="z-40 hidden">
    <button id="abrir-carrito-completo" type="button" onclick="abrirCarrito()"
        class="inline-flex min-h-12 items-center gap-2 border border-[#d56a5d] bg-[#8f241d] px-5 py-3 text-sm font-bold text-white shadow-[0_8px_25px_rgba(0,0,0,.4)] transition hover:bg-[#b52f25] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h8l4 4v5M14 3v4h4M6 3v18h7M9 11h5M9 15h3"/><circle cx="18" cy="18" r="4"/><path stroke-linecap="round" d="M18 16v4m-2-2h4"/></svg>
        <span class="carrito-boton-texto">Solicitar cotización</span>
        <span id="contador-carrito" class="flex h-6 min-w-6 items-center justify-center rounded-full bg-[#f7d8d2] px-1 text-xs text-[#8f241d]" aria-live="polite">0</span>
    </button>
</div>

<div id="carrito-modal"
    class="fixed inset-0 z-50 hidden bg-black/60"
    role="dialog" aria-modal="true" aria-labelledby="carrito-titulo">
    <div id="carrito-panel" class="resmap-glass ml-auto flex h-full w-full max-w-[26rem] flex-col text-[#241817] shadow-2xl">
        <div class="flex items-center justify-between gap-3 border-b border-[#e8c8c3] p-5">
            <h2 id="carrito-titulo" class="text-2xl font-black text-[#8f241d]">
                Productos seleccionados
            </h2>

            <button type="button" onclick="cerrarCarrito()"
                class="carrito-redondeado flex h-10 w-10 shrink-0 items-center justify-center bg-[#f7e8e6] text-2xl text-[#8f241d] hover:bg-[#f0d1cc] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]" aria-label="Cerrar selección">
                &times;
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-5">
            <div id="carrito-items" class="space-y-3"></div>
            <div id="carrito-vacio" class="hidden py-8 text-center">
                <p class="text-[#65504c]">Aún no has seleccionado repuestos.</p>
                <a id="carrito-ir-catalogo" href="{{ route('catalogo.index') }}"
                    class="carrito-redondeado mt-5 inline-flex min-h-12 items-center justify-center bg-[#b52f25] px-6 py-3 font-bold text-white transition hover:bg-[#8f241d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]">
                    Agregar productos
                </a>
            </div>
        </div>

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
            <div class="mb-4 flex items-end justify-between gap-4 text-sm">
                <div><p class="text-[#65504c]">Total de repuestos:</p><p id="carrito-total-cantidad" class="mt-1 font-black" aria-live="polite"></p></div>
                <div class="text-right"><p class="text-[#65504c]">Total estimado:</p><p id="carrito-total-precio" class="mt-1 font-black" aria-live="polite"></p></div>
            </div>
            <p id="carrito-vacio-aviso" class="mb-3 text-sm font-semibold text-[#8f241d]" role="status">Agrega al menos un producto para continuar con la cotización.</p>
            <button id="continuar-cotizacion" type="button" onclick="abrirSolicitud()" disabled
                aria-describedby="carrito-vacio-aviso"
                class="carrito-redondeado w-full bg-[#d92c24] p-3 font-bold text-white shadow-lg enabled:hover:bg-[#b52f25] disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                Continuar cotización <span aria-hidden="true">→</span>
            </button>
        </div>
    </div>
</div>

<div id="solicitud-modal"
    class="solicitud-modal fixed inset-0 z-50 hidden overflow-y-auto bg-black/50 p-4"
    role="dialog" aria-modal="true" aria-labelledby="solicitud-titulo">
    <div class="solicitud-panel mx-auto mt-8 max-w-2xl shadow-2xl">
        <div class="solicitud-header flex items-center justify-between border-b p-5">
            <h2 id="solicitud-titulo" class="solicitud-title text-2xl font-black text-[#8f241d]">
                Solicitar cotización
            </h2>

            <button type="button" onclick="cerrarSolicitud()"
                class="solicitud-close text-2xl" aria-label="Cerrar formulario">
                &times;
            </button>
        </div>

        @if($errors->any() && old('_formulario') !== 'servicio')
            <div class="mx-6 mt-5 rounded-lg border-l-4 border-red-700 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-black">Revisa los datos ingresados</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('catalogo.solicitudes.store') }}"
            data-region-initial="{{ old('region') }}"
            data-comuna-initial="{{ old('comuna') }}"
            class="grid grid-cols-1 items-start gap-4 p-6 sm:grid-cols-2">
            @csrf

            <div id="detalles_productos" class="contents"></div>

            <fieldset class="sm:col-span-2">
                <legend class="text-sm font-bold">Tipo de cliente <span class="text-[#b52f25]" aria-hidden="true">*</span></legend>
                <div class="mt-2 flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 font-semibold">
                        <input type="radio" name="tipo_cliente" value="Persona" required @checked(old('tipo_cliente', 'Persona') === 'Persona')>
                        Persona
                    </label>
                    <label class="flex items-center gap-2 font-semibold">
                        <input type="radio" name="tipo_cliente" value="Empresa" @checked(old('tipo_cliente', 'Persona') === 'Empresa')>
                        Empresa
                    </label>
                </div>
            </fieldset>

            <div>
                <input id="rut_solicitud" name="rut" value="{{ old('rut') }}" required
                    placeholder="12.345.678-5" data-rut maxlength="12"
                    autocomplete="off"
                    class="w-full border border-[#d9aaa3] p-3 text-[#241817]">
                <p class="mt-1 text-xs text-gray-600"
                    role="status" aria-live="polite"></p>
            </div>

            <input name="nombre" value="{{ old('nombre') }}" required
                placeholder="Razón social o nombre"
                class="w-full border border-[#d9aaa3] p-3 text-[#241817]">

            <div id="giro_solicitud_wrapper" class="hidden">
                <label class="block text-sm font-bold" for="giro_solicitud">Giro <span class="text-[#b52f25]" aria-hidden="true">*</span>
                    <input id="giro_solicitud" name="giro" value="{{ old('giro') }}" maxlength="255"
                        placeholder="Ej.: comercio de repuestos"
                        class="mt-1 w-full border border-[#d9aaa3] p-3 font-normal text-[#241817]">
                </label>
            </div>

            <label class="block text-sm font-bold" for="contacto_solicitud">Atención / contacto <span class="text-[#b52f25]" aria-hidden="true">*</span>
                <input id="contacto_solicitud" name="contacto" value="{{ old('contacto') }}" required maxlength="255"
                    placeholder="Nombre de la persona responsable" class="mt-1 w-full border border-[#d9aaa3] p-3 font-normal text-[#241817]">
            </label>
            <label class="block text-sm font-bold" for="cargo_solicitud">Cargo o área <span class="font-normal text-gray-500">(opcional)</span>
                <input id="cargo_solicitud" name="cargo" value="{{ old('cargo') }}" maxlength="255"
                    class="mt-1 w-full border border-[#d9aaa3] p-3 font-normal text-[#241817]">
            </label>

            <input name="correo" type="email" value="{{ old('correo') }}" required pattern="{{ \App\Rules\CorreoConDominio::PATRON }}" title="Ingresa un correo con dominio completo, por ejemplo nombre@ejemplo.cl"
                placeholder="Correo"
                class="w-full border border-[#d9aaa3] p-3 text-[#241817]">

            <div class="w-full">
                <label for="telefono" class="sr-only">Teléfono chileno</label>
                <div class="flex">
                    <span class="flex items-center border border-r-0 border-[#d9aaa3] bg-[#f7e8e6] px-3 font-bold text-[#8f241d]">
                        +56
                    </span>
                    <input id="telefono" name="telefono"
                        value="{{ old('telefono') }}" required
                        pattern="[2-9][0-9]{8}" maxlength="9" inputmode="numeric"
                        placeholder="912345678"
                        class="min-w-0 flex-1 border border-[#d9aaa3] p-3 text-[#241817]">
                </div>
            </div>

            <input name="direccion" value="{{ old('direccion') }}"
                placeholder="Dirección"
                class="w-full border border-[#d9aaa3] p-3 text-[#241817] sm:col-span-2">

            <p id="aviso-whatsapp-solicitud" class="text-sm text-gray-600 sm:col-span-2">Enviaremos la cotización por WhatsApp al número de teléfono indicado.</p>
            <button type="submit"
                class="bg-[#b52f25] p-3 font-bold text-white hover:bg-[#8f241d] sm:col-span-2">
                Solicitar cotización
            </button>
        </form>
    </div>
</div>

<script src="{{ asset('js/rut.js') }}"></script>
<script src="{{ asset('js/chile-location.js') }}"></script>

<script>
const carritoStorageKey = 'resmap-carrito';

function leerCarrito() {
    try {
        const datos = JSON.parse(
            window.sessionStorage.getItem(carritoStorageKey) || '{}'
        );

        return datos && typeof datos === 'object' && !Array.isArray(datos)
            ? datos
            : {};
    } catch {
        return {};
    }
}

const seleccion = leerCarrito();
const datosProductos = new Map(
    JSON.parse(document.getElementById('carrito-stock').textContent)
        .map(([id, stock, precio, unidad]) => [String(id), { stock: Number(stock), precio: precio === null ? null : Number(precio), unidad }])
);

Object.entries(seleccion).forEach(([id, item]) => {
    item.stock = datosProductos.get(String(id))?.stock ?? 0;
});

const formatoPrecio = new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'CLP',
    maximumFractionDigits: 0
});

function guardarCarrito() {
    window.sessionStorage.setItem(
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

    const cantidadTotal = items.reduce((total, item) => total + Number(item.cantidad), 0);
    document.getElementById('contador-carrito').textContent = cantidadTotal;
    document.getElementById('carrito-total-cantidad').textContent =
        `${cantidadTotal} ${cantidadTotal === 1 ? 'seleccionado' : 'seleccionados'}`;

    const preciosDisponibles = items.every(item => {
        const producto = datosProductos.get(String(item.id_producto));
        return producto && Number.isFinite(producto.precio);
    });
    const totalEstimado = items.reduce((total, item) => {
        const producto = datosProductos.get(String(item.id_producto));
        return total + (producto ? producto.precio * Number(item.cantidad) : 0);
    }, 0);
    document.getElementById('carrito-total-precio').textContent =
        preciosDisponibles ? formatoPrecio.format(totalEstimado) : 'No disponible';

    document.getElementById('barra-carrito')
        .classList.toggle('hidden', items.length === 0 && ! @json(request()->routeIs('home')));

    document.getElementById('continuar-cotizacion').disabled = items.length === 0;
    document.getElementById('carrito-vacio-aviso').classList.toggle('hidden', items.length > 0);
    document.getElementById('carrito-vacio').classList.toggle('hidden', items.length > 0);

    document.getElementById('carrito-items').innerHTML = items.length
        ? items.map(item => {
            const id = Number(item.id_producto);
            const stock = Number(item.stock);
            const cantidad = Number(item.cantidad);
            const producto = datosProductos.get(String(id));
            const precio = producto && Number.isFinite(producto.precio)
                ? `${formatoPrecio.format(producto.precio)}${producto.unidad === 'metro' ? ' / metro' : ''}`
                : 'Precio no disponible';

            return `
                <div class="flex items-center gap-3 rounded-xl border border-[#d9aaa3] bg-white/80 p-3 shadow-sm">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[#8f241d] text-white" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M7 7v10m10-10v10M5 17h14M9 11h6"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="break-words text-sm font-bold leading-tight text-[#241817]">${escaparHTML(item.nombre)}</p>
                        <p class="mt-1 text-sm text-[#65504c]">${precio}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <div class="flex items-center overflow-hidden rounded-full bg-[#d92c24]">
                        <button type="button"
                            onclick="cambiarCantidad(${id}, -1)" aria-label="Disminuir cantidad de ${escaparHTML(item.nombre)}"
                            class="h-8 w-8 font-bold text-white hover:bg-[#a72821]">−</button>
                        <input type="number" min="1" max="${Math.max(1, Math.min(stock, 99))}"
                            value="${cantidad}" aria-label="Cantidad de ${escaparHTML(item.nombre)}"
                            onchange="establecerCantidad(${id}, this.value)"
                            class="carrito-cantidad h-8 w-10 border-0 bg-white text-center font-bold text-[#8f241d]">
                        <button type="button"
                            onclick="cambiarCantidad(${id}, 1)" aria-label="Aumentar cantidad de ${escaparHTML(item.nombre)}"
                            class="h-8 w-8 font-bold text-white hover:bg-[#a72821]">+</button>
                        </div>
                        <button type="button"
                            onclick="cambiarCantidad(${id}, -${cantidad})"
                            aria-label="Quitar ${escaparHTML(item.nombre)} de la selección"
                            title="Quitar de la selección"
                            class="inline-flex h-8 w-8 items-center justify-center text-[#8f241d] hover:bg-[#f7e8e6] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]">
                            <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M10 11v6m4-6v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('')
        : '';

    document.querySelectorAll('[id^="selector-"]').forEach(selector => {
        const id = Number(selector.id.replace('selector-', ''));
        const item = seleccion[id];
        const stock = Number(selector.dataset.stock || 0);

        selector.innerHTML = item && stock > 0
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

document.getElementById('carrito-modal').addEventListener('click', (event) => {
    if (event.target === event.currentTarget) {
        cerrarCarrito();
    }
});

document.getElementById('carrito-ir-catalogo').addEventListener('click', event => {
    if (! @json(request()->routeIs('catalogo.index')) || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    cerrarCarrito();
});

document.addEventListener('DOMContentLoaded', () => {
    const barraCarrito = document.getElementById('barra-carrito');
    const limiteCarrito = document.querySelector('.home-footer-info') || document.querySelector('body > footer');
    const seccionContacto = document.querySelector('.contact-footer > section');
    let actualizacionPendiente = false;

    function actualizarPosicionCarrito() {
        if (actualizacionPendiente) return;
        actualizacionPendiente = true;
        requestAnimationFrame(() => {
            const enContacto = seccionContacto && seccionContacto.getBoundingClientRect().top < window.innerHeight;
            const distanciaAlPie = window.innerHeight - limiteCarrito.getBoundingClientRect().top;
            const desplazamiento = Math.max(0, distanciaAlPie);
            barraCarrito.classList.toggle('en-contacto', Boolean(enContacto));
            barraCarrito.style.setProperty('--carrito-limite', `${desplazamiento}px`);
            actualizacionPendiente = false;
        });
    }

    window.addEventListener('scroll', actualizarPosicionCarrito, { passive: true });
    window.addEventListener('resize', actualizarPosicionCarrito);
    window.addEventListener('load', actualizarPosicionCarrito);
    actualizarPosicionCarrito();
});

document.addEventListener('click', event => {
    const enlace = event.target.closest('[data-open-carrito]');
    if (!enlace || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    const menuToggle = document.getElementById('public-menu-toggle');
    if (menuToggle?.getAttribute('aria-expanded') === 'true') menuToggle.click();
    abrirCarrito();
});

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
    const rutLabel = obligatorioLabel(rut.id, 'RUN');
    rut.before(rutLabel);

    const nombre = solicitudForm.querySelector('input[name="nombre"]');
    nombre.id = 'nombre_solicitud';

    const nombreWrapper = document.createElement('div');
    nombre.before(nombreWrapper);
    nombreWrapper.append(
        obligatorioLabel(nombre.id, 'Nombre o razón social'),
        nombre
    );

    const tiposCliente = solicitudForm.querySelectorAll('input[name="tipo_cliente"]');
    const giroWrapper = solicitudForm.querySelector('#giro_solicitud_wrapper');
    const giro = solicitudForm.querySelector('#giro_solicitud');
    const actualizarTipoCliente = () => {
        const esEmpresa = solicitudForm.querySelector('input[name="tipo_cliente"]:checked')?.value === 'Empresa';
        rutLabel.firstChild.textContent = esEmpresa ? 'RUT ' : 'RUN ';
        giroWrapper?.classList.toggle('hidden', !esEmpresa);
        if (giro) giro.required = esEmpresa;
    };
    tiposCliente.forEach(tipoCliente => tipoCliente.addEventListener('change', actualizarTipoCliente));
    actualizarTipoCliente();

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
    if (!Object.keys(seleccion).length) {
        event.preventDefault();
        cerrarSolicitud();
        abrirCarrito();
        return;
    }

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
const abrirSeleccionPorEnlace = window.location.hash === '#seleccion-cotizacion';

if (urlParams.get('open_cotizacion') === 'true' || urlParams.get('open_cart') === 'true' || abrirSeleccionPorEnlace) {
    abrirCarrito();
    urlParams.delete('open_cotizacion');
    urlParams.delete('open_cart');

    const consulta = urlParams.toString();

    window.history.replaceState(
        {},
        document.title,
        window.location.pathname + (consulta ? '?' + consulta : '') + (abrirSeleccionPorEnlace ? '' : window.location.hash)
    );
}

const confirmacionEnviada = document.getElementById('success-toast');

if (confirmacionEnviada) {
    window.sessionStorage.removeItem(carritoStorageKey);
    Object.keys(seleccion).forEach(id => delete seleccion[id]);
    renderCarrito();
}

@if($errors->any() && old('_formulario') !== 'servicio')
    document.getElementById('solicitud-modal').classList.remove('hidden');
@endif
</script>
