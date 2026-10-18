@extends('layouts.app')

@section('content')
<main class="mx-auto max-w-7xl px-6 py-6 pb-32 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-5"><div><p class="text-sm font-bold uppercase tracking-[.25em] text-[#9f2f25]">Catálogo de repuestos</p><h1 class="mt-2 text-5xl font-black tracking-tight text-[#241817]">Encuentra lo que necesitas.</h1></div></div>
    @if(session('success'))<div id="success-toast" class="fixed right-5 top-24 z-60 max-w-sm border-l-4 border-[#8f241d] bg-white p-5 text-[#8f241d] shadow-xl" role="status"><p class="font-black">Solicitud enviada</p><p class="mt-1 text-sm text-gray-600">Te contactaremos pronto para confirmar disponibilidad.</p></div>@endif
    @if($errors->any())<div id="error-toast" class="fixed right-5 top-24 z-60 max-w-sm border-l-4 border-red-700 bg-white p-5 text-red-800 shadow-xl" role="alert"><p class="font-black">No se pudo enviar la solicitud</p><p class="mt-1 text-sm">{{ $errors->first() }}</p></div>@endif
    <form method="GET" class="mt-10 grid gap-3 bg-white p-5 shadow-sm md:grid-cols-[1fr_240px_auto]"><input name="nombre" value="{{ request('nombre') }}" placeholder="Buscar por nombre" class="border border-[#d9aaa3] p-3 text-[#241817] focus:border-[#b52f25] focus:outline-none"><select name="categoria" class="border border-[#d9aaa3] p-3 text-[#241817] focus:border-[#b52f25] focus:outline-none"><option value="">Todas las categorías</option>@foreach($categorias as $categoria)<option value="{{ $categoria->id_categoria }}" @selected(request('categoria') == $categoria->id_categoria)>{{ $categoria->nombre_categoria }}</option>@endforeach</select><button class="bg-[#b52f25] px-6 py-3 font-bold text-white hover:bg-[#8f241d]">Buscar</button></form>
    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($productos as $producto)<article class="flex flex-col justify-between bg-white p-6 shadow-sm"><div>@if($producto->imagen)<img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}" class="mb-5 h-48 w-full object-cover">@else<div class="mb-5 flex h-48 items-center justify-center bg-[#f7e8e6] text-sm font-bold uppercase tracking-widest text-[#9f2f25]">RESMAP</div>@endif<p class="text-xs font-bold uppercase tracking-wider text-[#9f2f25]">{{ $producto->categoria->nombre_categoria ?? 'Repuesto' }}</p><h2 class="mt-3 text-lg font-black text-[#241817]">{{ $producto->nombre }}</h2>@if($producto->stock <= 0)<p class="mt-2 text-xs font-bold text-amber-700">Sin stock disponible. Stock: 0</p>@endif</div><div class="mt-7 flex items-center justify-between gap-3"><span class="text-xl font-black text-[#8f241d]">$ {{ number_format($producto->precio, 0, ',', '.') }} @if($producto->unidad === 'metro')<small class="text-sm font-semibold">/ metro</small>@endif</span><div id="selector-{{ $producto->id_producto }}" data-nombre="{{ e($producto->nombre) }}" data-stock="{{ $producto->stock }}"><button type="button" data-agregar-producto data-producto-id="{{ $producto->id_producto }}" data-producto-nombre="{{ e($producto->nombre) }}" data-producto-stock="{{ $producto->stock }}" @disabled($producto->stock <= 0) class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white enabled:hover:bg-[#8f241d] disabled:cursor-not-allowed disabled:opacity-50">{{ $producto->stock <= 0 ? 'Sin stock' : 'Agregar' }}</button></div></div></article>@endforeach
    </div>
    <div class="mt-8">{{ $productos->links() }}</div>
</main>

<div id="barra-carrito" class="fixed inset-x-0 bottom-0 z-40 hidden border-t border-[#e8c8c3] bg-white shadow-[0_-4px_18px_rgba(36,24,23,0.14)]">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:gap-6">
        <div class="flex min-w-0 flex-1 flex-col gap-1 sm:flex-row sm:items-center sm:gap-4">
            <div id="resumen-carrito" class="flex min-w-0 flex-1 flex-col gap-1 sm:flex-row sm:gap-4" aria-live="polite"></div>
            <button id="ver-mas-carrito" type="button" onclick="abrirCarrito()" class="hidden shrink-0 text-left text-sm font-bold text-[#9f2f25] underline hover:text-[#8f241d]"></button>
        </div>
        <button id="abrir-carrito-completo" type="button" onclick="abrirCarrito()" class="inline-flex shrink-0 items-center gap-2 rounded-md bg-[#b52f25] px-4 py-3 font-bold text-white shadow hover:bg-[#8f241d]" aria-label="Abrir carrito completo">
            Ver carrito <span id="contador-carrito" class="rounded-full bg-white px-2 py-0.5 text-xs text-[#b52f25]">0</span>
        </button>
    </div>
</div>

<div id="carrito-modal" class="fixed inset-0 z-50 hidden bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="carrito-titulo"><div class="ml-auto flex h-full w-full max-w-lg flex-col bg-white shadow-2xl"><div class="flex items-center justify-between border-b border-[#e8c8c3] p-5"><h2 id="carrito-titulo" class="text-2xl font-black text-[#8f241d]">Tu carrito</h2><button type="button" onclick="cerrarCarrito()" class="text-2xl text-[#8f241d]" aria-label="Cerrar carrito">&times;</button></div><div id="carrito-items" class="flex-1 space-y-3 overflow-y-auto p-5"></div><p id="stock-insuficiente" class="hidden px-5 pb-4 text-sm font-bold text-red-700" role="status" aria-live="polite"></p><div class="border-t border-[#e8c8c3] p-5"><button type="button" onclick="abrirSolicitud()" class="w-full rounded-md bg-[#b52f25] p-3 font-bold text-white hover:bg-[#8f241d]">Continuar solicitud</button></div></div></div>

<div id="solicitud-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="solicitud-titulo"><div class="mx-auto mt-8 max-w-2xl bg-white shadow-2xl"><div class="flex items-center justify-between border-b border-[#e8c8c3] p-5"><h2 id="solicitud-titulo" class="text-2xl font-black text-[#8f241d]">Enviar solicitud</h2><button type="button" onclick="cerrarSolicitud()" class="text-2xl text-[#8f241d]" aria-label="Cerrar formulario">&times;</button></div>@if($errors->any())<div class="mx-6 mt-5 border-l-4 border-red-700 bg-red-50 p-4 text-sm text-red-800" role="alert"><p class="font-black">Revisa los datos ingresados</p><ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif<form method="POST" action="{{ route('catalogo.solicitudes.store') }}" class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">@csrf<div id="detalles_productos" class="contents"></div><div><input name="rut" value="{{ old('rut') }}" required placeholder="12345678-5" pattern="[0-9]{7,8}-[0-9Kk]" class="w-full border border-[#d9aaa3] p-3 text-[#241817]"><p class="mt-1 text-xs text-gray-500">Escribe tu RUT sin puntos y con guion.</p></div><input name="nombre" value="{{ old('nombre') }}" required placeholder="Nombre o razón social" class="w-full border border-[#d9aaa3] p-3 text-[#241817]"><input name="correo" type="email" value="{{ old('correo') }}" placeholder="Correo" class="w-full border border-[#d9aaa3] p-3 text-[#241817]"><div class="w-full"><label for="telefono" class="sr-only">Teléfono chileno</label><div class="flex"><span class="flex items-center border border-r-0 border-[#d9aaa3] bg-[#f7e8e6] px-3 font-bold text-[#8f241d]">+569</span><input id="telefono" name="telefono" value="{{ old('telefono') }}" required pattern="[0-9]{8}" maxlength="8" inputmode="numeric" placeholder="12345678" class="min-w-0 flex-1 border border-[#d9aaa3] p-3 text-[#241817] focus:border-[#b52f25] focus:outline-none"></div></div><input name="direccion" value="{{ old('direccion') }}" placeholder="Dirección" class="w-full border border-[#d9aaa3] p-3 text-[#241817] sm:col-span-2"><button class="bg-[#b52f25] p-3 font-bold text-white hover:bg-[#8f241d] sm:col-span-2">Enviar solicitud</button></form></div></div>

<script src="{{ asset('js/chile-location.js') }}"></script>
<script>
const carritoStorageKey = 'resmap-carrito';
const leerCarrito = () => { try { return JSON.parse(window.localStorage.getItem(carritoStorageKey) || '{}'); } catch { return {}; } };
const seleccion = leerCarrito();
function guardarCarrito() { window.localStorage.setItem(carritoStorageKey, JSON.stringify(seleccion)); }
function mostrarErrorStock(mensaje) {
    const elemento = document.getElementById('stock-insuficiente');
    elemento.textContent = mensaje;
    elemento.classList.remove('hidden');
    abrirCarrito();
}
function limpiarErrorStock() {
    document.getElementById('stock-insuficiente').textContent = '';
    document.getElementById('stock-insuficiente').classList.add('hidden');
}
function agregar(id, nombre, stock) {
    stock = Number(stock);
    seleccion[id] = seleccion[id] || { id_producto: id, cantidad: 0, nombre, stock };
    seleccion[id].stock = stock;
    if (seleccion[id].cantidad >= stock) {
        mostrarErrorStock(`Stock insuficiente para ${nombre}: disponibles ${stock}.`);
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
    if (cambio > 0 && item.cantidad >= Number(item.stock)) {
        mostrarErrorStock(`Stock insuficiente para ${item.nombre}: disponibles ${item.stock}.`);
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
    if (cantidadSolicitada > stock) {
        mostrarErrorStock(`Stock insuficiente para ${item.nombre}: disponibles ${stock}.`);
    } else {
        limpiarErrorStock();
    }
    item.cantidad = Math.max(1, Math.min(stock, 99, cantidadSolicitada));
    guardarCarrito();
    renderCarrito();
}
function renderCarrito() {
    document.querySelectorAll('[id^="selector-"]').forEach(selector => {
        const id = Number(selector.id.replace('selector-', ''));
        if (seleccion[id]) seleccion[id].stock = Number(selector.dataset.stock || 0);
    });
    const items = Object.values(seleccion);
    const productoSinStock = items.find(item => item.cantidad > Number(item.stock));
    if (productoSinStock) {
        document.getElementById('stock-insuficiente').textContent = `Stock insuficiente para ${productoSinStock.nombre}: solicitas ${productoSinStock.cantidad} y quedan ${productoSinStock.stock}.`;
        document.getElementById('stock-insuficiente').classList.remove('hidden');
    }
    const cantidadTotal = items.reduce((total, item) => total + item.cantidad, 0);
    document.getElementById('contador-carrito').textContent = cantidadTotal;
    const barraCarrito = document.getElementById('barra-carrito');
    barraCarrito.classList.toggle('hidden', items.length === 0);
    const resumenCarrito = document.getElementById('resumen-carrito');
    resumenCarrito.replaceChildren(...items.slice(0, 2).map(item => {
        const resumenProducto = document.createElement('span');
        resumenProducto.className = 'truncate text-sm font-semibold text-[#241817]';
        resumenProducto.textContent = `${item.cantidad} × ${item.nombre}`;
        return resumenProducto;
    }));
    const verMasCarrito = document.getElementById('ver-mas-carrito');
    verMasCarrito.classList.toggle('hidden', items.length <= 2);
    verMasCarrito.textContent = items.length > 2 ? `Ver más (${items.length - 2})` : '';
    document.getElementById('carrito-items').innerHTML = items.length ? items.map(item => `<div class="flex items-center justify-between gap-3 border-b border-[#f0d7d3] pb-3"><span class="font-semibold text-[#241817]">${item.nombre}</span><div class="flex items-center gap-2"><button type="button" onclick="cambiarCantidad(${item.id_producto}, -1)" class="h-8 w-8 rounded-full bg-[#f7e8e6] font-bold text-[#8f241d]">-</button><input type="number" min="1" max="${item.stock}" value="${item.cantidad}" onchange="establecerCantidad(${item.id_producto}, this.value)" class="h-8 w-12 border border-[#d9aaa3] text-center font-bold text-[#8f241d]"><button type="button" onclick="cambiarCantidad(${item.id_producto}, 1)" class="h-8 w-8 rounded-full bg-[#b52f25] font-bold text-white">+</button></div></div>`).join('') : '<p class="py-8 text-center text-gray-500">Tu carrito está vacío.</p>';
    document.querySelectorAll('[id^="selector-"]').forEach(selector => {
        const id = Number(selector.id.replace('selector-', ''));
        const item = seleccion[id];
        const stock = Number(selector.dataset.stock || 0);
        selector.innerHTML = item ? `<div class="flex items-center gap-2 rounded-md border border-[#e8c8c3] p-1"><button type="button" onclick="cambiarCantidad(${id}, -1)" class="h-8 w-8 rounded bg-[#f7e8e6] font-bold text-[#8f241d]">−</button><input type="number" min="1" max="${stock}" value="${item.cantidad}" onchange="establecerCantidad(${id}, this.value)" class="h-8 w-12 border border-[#d9aaa3] text-center font-bold text-[#8f241d]"><button type="button" onclick="cambiarCantidad(${id}, 1)" class="h-8 w-8 rounded bg-[#b52f25] font-bold text-white">+</button></div>` : `<button type="button" data-agregar-producto data-producto-id="${id}" data-producto-nombre="${selector.dataset.nombre || ''}" data-producto-stock="${stock}" ${stock <= 0 ? 'disabled' : ''} class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white enabled:hover:bg-[#8f241d] disabled:cursor-not-allowed disabled:opacity-50">${stock <= 0 ? 'Sin stock' : 'Agregar'}</button>`;
    });
    document.getElementById('detalles_productos').innerHTML = items.map(item => `<input type="hidden" name="detalles_productos[${item.id_producto}][id_producto]" value="${item.id_producto}"><input type="hidden" name="detalles_productos[${item.id_producto}][cantidad]" value="${item.cantidad}">`).join('');
}
document.addEventListener('click', event => { const button = event.target.closest('[data-agregar-producto]'); if (button && !button.disabled) agregar(Number(button.dataset.productoId), button.dataset.productoNombre, button.dataset.productoStock || button.closest('[id^="selector-"]')?.dataset.stock || 0); });
function abrirCarrito() { document.getElementById('carrito-modal').classList.remove('hidden'); renderCarrito(); }
function cerrarCarrito() { document.getElementById('carrito-modal').classList.add('hidden'); }
function abrirSolicitud() { if (!Object.keys(seleccion).length) return; cerrarCarrito(); document.getElementById('solicitud-modal').classList.remove('hidden'); }
function cerrarSolicitud() { document.getElementById('solicitud-modal').classList.add('hidden'); }
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
    rut.parentElement.insertBefore(obligatorioLabel(rut.id, 'RUT'), rut);

    const nombre = solicitudForm.querySelector('input[name="nombre"]');
    nombre.id = 'nombre_solicitud';
    const nombreWrapper = document.createElement('div');
    nombre.before(nombreWrapper);
    nombreWrapper.append(obligatorioLabel(nombre.id, 'Nombre o razón social'), nombre);

    const telefonoLabel = solicitudForm.querySelector('label[for="telefono"]');
    telefonoLabel.className = 'mb-1 block text-sm font-bold text-[#241817]';
    telefonoLabel.textContent = 'Teléfono ';
    const telefonoAsterisco = document.createElement('span');
    telefonoAsterisco.className = 'text-[#b52f25]';
    telefonoAsterisco.setAttribute('aria-hidden', 'true');
    telefonoAsterisco.textContent = '*';
    telefonoLabel.append(telefonoAsterisco);

    const camposRegion = [
        ['region_solicitud', 'Región'],
        ['comuna_solicitud', 'Comuna'],
    ];
    camposRegion.forEach(([id, texto]) => {
        const campo = solicitudForm.querySelector(`#${id}`);
        campo.parentElement.insertBefore(obligatorioLabel(id, texto), campo);
    });

    const leyenda = document.createElement('p');
    leyenda.className = 'text-xs text-gray-500 sm:col-span-2';
    leyenda.textContent = '* Campo obligatorio';
    solicitudForm.querySelector('#detalles_productos').after(leyenda);
}
document.querySelector('#solicitud-modal form')?.addEventListener('submit', event => {
    const faltantes = Object.values(seleccion).filter(item => item.cantidad > Number(item.stock));
    if (!faltantes.length) return;
    event.preventDefault();
    const detalle = faltantes.map(item => `${item.nombre}: solicitas ${item.cantidad}, quedan ${item.stock}`).join('. ');
    mostrarErrorStock(`Stock insuficiente: ${detalle}. Ajusta las cantidades para continuar.`);
});
// Abrir carrito si viene del carrusel
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('open_cart') === 'true') {
    abrirCarrito();
    window.history.replaceState({}, document.title, window.location.pathname);
}

// Limpiar carrito después de solicitud exitosa
const successToast = document.getElementById('success-toast');
if (successToast) {
    window.localStorage.removeItem(carritoStorageKey);
    renderCarrito();
    // Recargar la página después de 3 segundos para mostrar carrito vacío
    setTimeout(() => {
        location.reload();
    }, 3000);
}

setTimeout(() => document.getElementById('success-toast')?.remove(), 5000);
setTimeout(() => document.getElementById('error-toast')?.remove(), 7000);
@if($errors->any()) abrirSolicitud(); @endif
</script>
@endsection