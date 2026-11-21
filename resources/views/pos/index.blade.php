@extends('layouts.app')

@section('content')
<style>
    .pos-page {
        max-width: 100rem;
        margin: 0 auto;
    }

    .pos-header {
        margin-bottom: 1.5rem;
    }

    .pos-kicker {
        color: #9f2f25;
        font-size: .75rem;
        font-weight: 800;
        letter-spacing: .14em;
        text-transform: uppercase;
    }

    .pos-grid {
        display: grid;
        gap: 1.5rem;
        align-items: start;
    }

    .pos-panel {
        min-width: 0;
        border: 1px solid #ead8d5;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 8px 24px rgba(80, 38, 31, .06);
    }

    .pos-section-heading {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
    }

    .pos-section-number {
        display: grid;
        width: 2rem;
        height: 2rem;
        flex: none;
        place-items: center;
        border-radius: .625rem;
        background: #f7e8e6;
        color: #8f241d;
        font-weight: 800;
    }

    .pos-client-fields {
        display: grid;
        gap: 1rem;
    }

    .pos-input-row {
        display: grid;
        gap: 1rem;
    }

    @media (min-width: 640px) {
        .pos-client-fields,
        .pos-input-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pos-field-wide {
            grid-column: 1 / -1;
        }
    }

    @media (min-width: 1280px) {
        .pos-grid {
            grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
        }
    }

    .pos-resumen label {
        color: #382723;
        font-size: .9375rem;
        font-weight: 600;
        line-height: 1.4;
    }

    .pos-resumen input:not([type="hidden"]),
    .pos-resumen select {
        min-height: 2.75rem;
        font-size: 1rem;
        color: #241817;
    }

    .pos-resumen select:not(.sr-only) {
        border: 1px solid #d5c3bf;
    }

    .pos-panel input:focus-visible,
    .pos-panel select:focus-visible {
        outline: 2px solid #b52f25;
        outline-offset: 1px;
    }

    .pos-resumen p.text-xs {
        font-size: .875rem;
        line-height: 1.4;
    }
</style>
<div class="pos-page">
    <header class="pos-header">
        <p class="pos-kicker">Ventas / Punto de venta</p>
        <h2 class="mt-1 text-2xl font-black text-[#241817] sm:text-3xl">Punto de Venta (POS) - Repuestos</h2>
        <p class="mt-2 text-sm text-gray-600">Agrega los repuestos y completa los datos para registrar la venta.</p>
    </header>

    <div class="pos-grid">
    <section class="pos-panel p-5 sm:p-6" aria-labelledby="titulo-productos-pos">
        <div class="pos-section-heading mb-5">
            <span class="pos-section-number" aria-hidden="true">1</span>
            <div>
                <h3 id="titulo-productos-pos" class="text-lg font-black text-[#721d18]">Productos de la venta</h3>
                <p class="mt-1 text-sm text-gray-600">Escanea un código o busca el repuesto por su nombre.</p>
            </div>
        </div>

        <div class="mb-4 rounded-xl border border-[#e8c8c3] bg-[#fff7f5] p-4">
            <label for="lector-codigo" class="block text-sm font-bold text-[#8f241d]">
                Escanear código
            </label>

            <div class="mt-2 flex gap-2">
                <input type="text" id="lector-codigo" autocomplete="off"
                    placeholder="Apunta el lector y escanea"
                    class="min-w-0 flex-1 rounded-md border border-gray-300 bg-white p-2 shadow-sm">

                <button type="button" onclick="buscarCodigo()"
                    class="shrink-0 rounded-md bg-[#b52f25] px-4 py-2 text-sm font-bold text-white hover:bg-[#8f241d] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]">
                    Agregar
                </button>
            </div>

            <p id="lector-mensaje" class="mt-2 text-xs text-gray-500">
                El lector USB funciona como teclado. Pulsa Enter después de escanear.
            </p>
        </div>

        <div class="mb-6">
            <div id="selector-productos" class="relative min-w-0">
                <label for="buscar-producto" class="mb-1 block text-sm font-bold text-gray-700">Buscar producto por nombre o código</label>
                <input type="text" id="buscar-producto" maxlength="100"
                    placeholder="Escribe parte del nombre o código"
                    autocomplete="off"
                    class="w-full rounded-md border-gray-300 shadow-sm border p-2 text-gray-900 bg-white">
                <div id="lista-sugerencias" style="display: none;" role="status" aria-live="polite"
                    class="absolute left-0 top-full z-40 mt-1 w-full max-h-60 overflow-y-auto rounded-lg border border-gray-300 bg-white shadow-lg">
                </div>
            </div>
        </div>

        <div class="border-t border-[#ead8d5] pt-5">
            <h3 class="mb-3 text-lg font-black text-[#721d18]">Ítems Seleccionados</h3>
            <div class="mb-5 max-h-80 overflow-y-auto rounded-xl border border-[#ead8d5] bg-[#fffaf9] p-3">
                <table class="block w-full text-sm" id="tabla-carro">
                    <thead class="sr-only">
                        <tr>
                            <th>Repuesto</th>
                            <th>Cantidad</th>
                            <th>Subtotal</th>
                            <th>Quitar</th>
                        </tr>
                    </thead>
                    <tbody id="carro-items" class="block space-y-3"></tbody>
                </table>
                <p id="carro-vacio" class="px-3 py-6 text-center text-sm text-gray-600">Aún no hay productos. Escanea un código o usa el buscador para comenzar.</p>
            </div>

            <div class="mb-5 space-y-2 rounded-xl bg-[#f7eeec] p-4 text-base">
                <div class="flex justify-between text-gray-700">
                    <span>Neto:</span>
                    <span id="label-neto">$ 0</span>
                </div>
                <div class="flex justify-between text-gray-700">
                    <span>IVA (19%):</span>
                    <span id="label-iva">$ 0</span>
                </div>
                <div class="flex justify-between border-t border-[#e5cbc6] pt-3 text-xl font-black text-[#721d18]">
                    <span>Total:</span>
                    <span id="label-total">$ 0</span>
                </div>
            </div>

            @if(auth()->user()->tienePermiso('ventas.crear'))
            <button type="button" id="registrar-venta" onclick="procesarVenta()"
                class="w-full rounded-md bg-[#8f241d] px-4 py-3 text-base font-bold text-white shadow-sm hover:bg-[#721d18] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d] disabled:opacity-50">
                Registrar venta
            </button>
            @endif

            @if (session('resumen_venta_id'))
                <a href="{{ route('ventas.resumen-interno', session('resumen_venta_id')) }}"
                    class="mt-3 block w-full rounded-md border border-[#8f241d] py-2 text-center text-sm font-bold text-[#8f241d] hover:bg-[#fff8f6]">
                    ↓ Descargar resumen interno de la venta #{{ session('resumen_venta_id') }}
                </a>
            @endif
        </div>
    </section>

    <section class="pos-panel pos-resumen p-5 sm:p-6" aria-labelledby="titulo-datos-venta">
            <div class="pos-section-heading mb-5">
                <span class="pos-section-number" aria-hidden="true">2</span>
                <div>
                    <h3 id="titulo-datos-venta" class="text-lg font-black text-[#721d18]">Datos de venta y facturación</h3>
                    <p class="mt-1 text-sm text-gray-600">Estos datos también se utilizan para el resumen interno, que no es un documento tributario electrónico.</p>
                </div>
            </div>

            <div class="pos-client-fields">
                <div>
                    <label for="rut" class="block text-xs font-medium text-gray-700">
                        RUT Empresa / Cliente
                    </label>
                    <input type="text" id="rut" placeholder="12.345.678-9" data-rut maxlength="12"
                        autocomplete="off"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                    <p id="mensaje-cliente" class="mt-1 text-xs text-gray-600" role="status">
                        Ingresa el RUT para buscar los datos del cliente.
                    </p>
                </div>

                <div>
                    <label for="nombre_cliente" class="block text-xs font-medium text-gray-700">
                        Razón Social / Nombre
                    </label>
                    <input type="text" id="nombre_cliente" required placeholder="Constructora SpA"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                </div>

                <div class="pos-field-wide">
                    <label for="giro_cliente" class="block text-xs font-medium text-gray-700">
                        <span id="etiqueta-giro-cliente">Giro del cliente (opcional para boleta)</span>
                    </label>
                    <input type="text" id="giro_cliente" maxlength="255" placeholder="Comercio de repuestos"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                </div>

                <div class="pos-input-row pos-field-wide">
                    <div>
                        <label for="contacto_cliente" class="block text-xs font-medium text-gray-700">Contacto *</label>
                        <input type="text" id="contacto_cliente" maxlength="255" required placeholder="Persona o responsable"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                    </div>
                    <div>
                        <label for="cargo" class="block text-xs font-medium text-gray-700">Cargo (opcional)</label>
                        <input type="text" id="cargo" maxlength="255" placeholder="OT, equipo o patente"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                    </div>
                </div>

                <div class="pos-input-row pos-field-wide">
                    <div>
                        <label for="correo_cliente" class="block text-xs font-medium text-gray-700">
                            Correo *
                        </label>
                        <input type="email" id="correo_cliente" required maxlength="255" placeholder="contacto@empresa.cl"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                    </div>

                    <div>
                        <label for="telefono_cliente" class="block text-xs font-medium text-gray-700">
                            Teléfono
                        </label>
                        <div class="mt-1 flex">
                            <select id="prefijo_telefono_cliente" aria-label="Código de país"
                                class="min-w-0 rounded-l-md border border-gray-300 bg-white p-2 text-sm">
                            </select>
                            <input type="text" id="telefono_cliente" placeholder="912345678"
                                pattern="[2-9][0-9]{8}" maxlength="9" inputmode="numeric"
                                class="min-w-0 flex-1 rounded-r-md border border-l-0 border-gray-300 p-2 text-sm">
                        </div>
                    </div>
                </div>

                <div class="pos-field-wide">
                    <label for="direccion_cliente" class="block text-xs font-medium text-gray-700">
                        Dirección *
                    </label>
                    <input type="text" id="direccion_cliente" required maxlength="255" placeholder="Faena / Dirección"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                </div>

                <div class="pos-input-row pos-field-wide">
                    <div>
                        <label for="region_cliente" class="block text-xs font-medium text-gray-700">
                            Región *
                        </label>
                        <select id="region_cliente" required
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white p-2 text-sm">
                            <option value="">Selecciona una región</option>
                        </select>
                    </div>

                    <div>
                        <label for="comuna_cliente" class="block text-xs font-medium text-gray-700">
                            Comuna *
                        </label>
                        <select id="comuna_cliente" required disabled
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white p-2 text-sm">
                            <option value="">Selecciona una comuna</option>
                        </select>
                    </div>
                </div>

                <div class="pos-field-wide">
                    <label for="ciudad_cliente" class="block text-xs font-medium text-gray-700">
                        Ciudad * <span class="font-normal text-gray-500">(se sugiere la comuna)</span>
                    </label>
                    <input type="text" id="ciudad_cliente" required maxlength="100" placeholder="Los Ángeles"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                </div>
                <div class="pos-field-wide">
                    <label for="acteco_emisor" class="block text-xs font-medium text-gray-700">
                        Giro del emisor y ACTECO
                    </label>
                    <select id="acteco_emisor" required
                        class="mt-1 block w-full rounded-md border-gray-300 bg-white p-2 text-sm">
                        <option value="">Selecciona un giro</option>
                        @foreach(config('services.cotizaciones.actividades_emisor') as $codigo => $actividad)
                            <option value="{{ $codigo }}">{{ $actividad }} · {{ $codigo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pos-field-wide">
                    <label for="numero_oc" class="block text-xs font-medium text-gray-700">
                        Número de orden de compra (opcional)
                    </label>
                    <input type="text" id="numero_oc" maxlength="100"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                </div>
                <div class="pos-input-row pos-field-wide">
                    <div>
                        <label for="tipo_documento" class="block text-xs font-medium text-gray-700">
                            Documento SII
                        </label>
                        <select id="tipo_documento"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                            <option value="Boleta Electrónica">Boleta (39)</option>
                            <option value="Factura Electrónica">Factura (33)</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Solo referencia; no se emite al SII.</p>
                    </div>

                    <div>
                        <label for="medio_pago" class="block text-xs font-medium text-gray-700">
                            Medio de Pago
                        </label>
                        <select id="medio_pago"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Tarjeta">Tarjeta</option>
                        </select>
                    </div>
                </div>
            </div>

    </section>
    </div>
</div>

<script src="{{ asset('js/rut.js') }}"></script>
<script src="{{ asset('js/chile-location.js') }}"></script>

<script>
let carro = [];
let ventaEnProceso = false;
let borradorHabilitado = true;
let busquedaClienteEnProceso = false;

const claveBorrador = 'resmap-pos-venta-pendiente';

const idsCamposCliente = [
    'rut',
    'nombre_cliente',
    'contacto_cliente',
    'cargo',
    'giro_cliente',
    'correo_cliente',
    'prefijo_telefono_cliente',
    'telefono_cliente',
    'direccion_cliente',
    'region_cliente',
    'comuna_cliente',
    'ciudad_cliente',
    'acteco_emisor',
    'numero_oc',
    'tipo_documento',
    'medio_pago'
];

inicializarUbicacionChile('region_cliente', 'comuna_cliente');
inicializarSelectorTelefono('prefijo_telefono_cliente', 'telefono_cliente');

const comunaCliente = document.getElementById('comuna_cliente');
const ciudadCliente = document.getElementById('ciudad_cliente');
const tipoDocumentoSelect = document.getElementById('tipo_documento');
const giroClienteInput = document.getElementById('giro_cliente');
const etiquetaGiroCliente = document.getElementById('etiqueta-giro-cliente');

comunaCliente.addEventListener('change', () => {
    if (!ciudadCliente.value.trim()) {
        ciudadCliente.value = comunaCliente.value;
    }
});

function actualizarRequisitoGiro() {
    const requiereGiro = tipoDocumentoSelect.value === 'Factura Electrónica';
    giroClienteInput.required = requiereGiro;
    etiquetaGiroCliente.textContent = requiereGiro
        ? 'Giro del cliente *'
        : 'Giro del cliente (opcional para boleta)';
}

tipoDocumentoSelect.addEventListener('change', actualizarRequisitoGiro);

function guardarBorrador() {
    if (!borradorHabilitado) return;

    const campos = {};

    idsCamposCliente.forEach(id => {
        campos[id] = document.getElementById(id).value;
    });

    try {
        sessionStorage.setItem(
            claveBorrador,
            JSON.stringify({ campos, carro })
        );
    } catch (error) {
        console.error('No se pudo guardar la venta pendiente:', error);
    }
}

function restaurarBorrador() {
    try {
        const contenido = sessionStorage.getItem(claveBorrador);
        if (!contenido) return;

        const borrador = JSON.parse(contenido);
        const campos = borrador.campos || {};

        idsCamposCliente.forEach(id => {
            if (id === 'region_cliente' || id === 'comuna_cliente') return;

            if (typeof campos[id] === 'string') {
                if (id === 'prefijo_telefono_cliente') return;
                document.getElementById(id).value = id === 'telefono_cliente' && campos.prefijo_telefono_cliente === '+569'
                    ? window.telefonoNacionalChile(campos[id])
                    : campos[id];
            }
        });

        document.getElementById('prefijo_telefono_cliente')
            .dispatchEvent(new Event('change'));

        const region = document.getElementById('region_cliente');
        const comuna = document.getElementById('comuna_cliente');

        region.value = campos.region_cliente || '';
        region.dispatchEvent(new Event('change'));
        comuna.value = campos.comuna_cliente || '';

        if (Array.isArray(borrador.carro)) {
            carro = borrador.carro.map(item => ({
                id_producto: Number(item.id_producto),
                nombre: String(item.nombre || ''),
                precio: Number(item.precio),
                cantidad: Number(item.cantidad),
                stockMax: Number(item.stockMax)
            })).filter(item =>
                Number.isInteger(item.id_producto) &&
                item.id_producto > 0 &&
                Number.isFinite(item.precio) &&
                item.precio >= 0 &&
                Number.isInteger(item.cantidad) &&
                item.cantidad > 0 &&
                Number.isFinite(item.stockMax)
            );
        }

        if (campos.rut || carro.length) {
            document.getElementById('mensaje-cliente').textContent =
                'Venta pendiente recuperada. Puedes seguir agregando productos.';
        }
    } catch (error) {
        console.error('No se pudo recuperar la venta pendiente:', error);
    }
}

function limpiarBorrador() {
    borradorHabilitado = false;

    try {
        sessionStorage.removeItem(claveBorrador);
    } catch (error) {
        console.error('No se pudo limpiar el borrador:', error);
    }

    carro = [];
    renderCarro();
}

const buscadorProducto = document.getElementById('buscar-producto');
const listaSugerencias = document.getElementById('lista-sugerencias');
const selectorProductos = document.getElementById('selector-productos');

let temporizadorProductos;
let controladorProductos;
let consultaProductos = 0;

function cerrarSugerencias() {
    clearTimeout(temporizadorProductos);
    controladorProductos?.abort();
    consultaProductos++;
    listaSugerencias.style.display = 'none';
}

async function mostrarSugerencias() {
    const texto = buscadorProducto.value.trim();
    listaSugerencias.replaceChildren();
    controladorProductos?.abort();
    const consulta = ++consultaProductos;
    if (!texto) {
        cerrarSugerencias();
        return;
    }

    controladorProductos = new AbortController();
    let coincidencias;
    try {
        const respuesta = await fetch('{{ route('pos.productos.buscar') }}?' + new URLSearchParams({ buscar: texto }), {
            headers: { 'Accept': 'application/json' },
            signal: controladorProductos.signal
        });
        const datos = await respuesta.json();
        if (!respuesta.ok) throw new Error(datos.message || 'No se pudieron buscar productos.');
        if (consulta !== consultaProductos) return;
        coincidencias = datos;
    } catch (error) {
        if (error.name === 'AbortError' || consulta !== consultaProductos) return;
        listaSugerencias.textContent = 'No se pudieron buscar productos. Inténtalo nuevamente.';
        listaSugerencias.style.display = 'block';
        console.error('Error al buscar productos:', error);
        return;
    }

    if (!coincidencias.length) {
        const mensaje = document.createElement('p');
        mensaje.className = 'px-3 py-3 text-sm text-gray-500';
        mensaje.textContent = 'No se encontraron productos.';
        listaSugerencias.append(mensaje);
    }

    coincidencias.forEach(producto => {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className =
            'block w-full border-b border-gray-100 px-3 py-2 text-left hover:bg-red-50 focus:bg-red-50';

        const nombre = document.createElement('span');
        nombre.className = 'block text-sm font-medium text-gray-900';
        nombre.textContent = producto.nombre;

        const codigo = document.createElement('span');
        codigo.className = 'block text-xs text-gray-500';
        codigo.textContent = `${producto.codigo || 'Sin código'} · $ ${Number(producto.precio).toLocaleString('es-CL')} · Stock: ${producto.stock}`;

        boton.append(nombre, codigo);

        boton.addEventListener('click', () => {
            agregarAlCarro(Number(producto.id_producto), producto.nombre, Number(producto.precio), Number(producto.stock));
            buscadorProducto.value = '';
            cerrarSugerencias();
            buscadorProducto.focus();
        });

        listaSugerencias.append(boton);
    });

    listaSugerencias.style.display = 'block';
}

buscadorProducto.addEventListener('input', () => {
    clearTimeout(temporizadorProductos);
    controladorProductos?.abort();
    consultaProductos++;
    listaSugerencias.style.display = 'none';
    temporizadorProductos = setTimeout(mostrarSugerencias, 250);
});
buscadorProducto.addEventListener('focus', mostrarSugerencias);

selectorProductos.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        buscadorProducto.focus();
        cerrarSugerencias();
        return;
    }

    if (event.key === 'ArrowDown' && event.target === buscadorProducto) {
        const primera = listaSugerencias.querySelector('button');

        if (primera) {
            event.preventDefault();
            primera.focus();
        }

        return;
    }

    if (event.key === 'Enter' && event.target === buscadorProducto && listaSugerencias.style.display !== 'none') {
        const primera = listaSugerencias.querySelector('button');
        if (primera) {
            event.preventDefault();
            primera.click();
        }
        return;
    }

    const opciones = Array.from(listaSugerencias.querySelectorAll('button'));
    const indice = opciones.indexOf(document.activeElement);

    if (indice !== -1 && event.key === 'ArrowDown') {
        event.preventDefault();
        opciones[(indice + 1) % opciones.length].focus();
    } else if (indice !== -1 && event.key === 'ArrowUp') {
        event.preventDefault();

        if (indice === 0) {
            buscadorProducto.focus();
        } else {
            opciones[indice - 1].focus();
        }
    }
});

document.addEventListener('click', event => {
    if (!selectorProductos.contains(event.target)) cerrarSugerencias();
});

selectorProductos.addEventListener('focusout', event => {
    if (!selectorProductos.contains(event.relatedTarget)) cerrarSugerencias();
});

function agregarAlCarro(id_producto, nombre, precio, stockMax) {
    if (stockMax <= 0) {
        window.resmapAlert('El producto no tiene stock disponible.', 'warning');
        return;
    }

    const existente = carro.find(item => item.id_producto === id_producto);

    if (existente) {
        existente.stockMax = stockMax;
        existente.precio = precio;

        if (existente.cantidad < stockMax) {
            existente.cantidad++;
        } else {
            window.resmapAlert('Stock máximo alcanzado.', 'warning');
        }
    } else {
        carro.push({
            id_producto,
            nombre,
            precio,
            cantidad: 1,
            stockMax
        });
    }

    renderCarro();
}

function cambiarCantidad(id_producto, delta) {
    const item = carro.find(producto => producto.id_producto === id_producto);
    if (!item) return;

    const nuevaCantidad = item.cantidad + delta;

    if (nuevaCantidad <= 0) {
        carro = carro.filter(producto => producto.id_producto !== id_producto);
    } else if (delta > 0 && nuevaCantidad > item.stockMax) {
        window.resmapAlert('Stock máximo alcanzado.', 'warning');
        return;
    } else {
        item.cantidad = nuevaCantidad;
    }

    renderCarro();
}

function renderCarro() {
    const tbody = document.getElementById('carro-items');
    tbody.replaceChildren();
    document.getElementById('carro-vacio').hidden = carro.length > 0;

    let total = 0;

    carro.forEach(item => {
        const subtotal = item.precio * item.cantidad;
        total += subtotal;

        const fila = document.createElement('tr');
        fila.className = 'grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2 rounded-md border border-[#ead8d5] bg-white p-3';
        fila.innerHTML =
            '<td class="col-start-1 row-start-1 min-w-0 break-words text-base font-bold leading-snug text-[#241817]"></td>' +
            '<td class="col-start-1 row-start-2 flex items-center gap-1"></td>' +
            '<td class="col-start-2 row-start-2 whitespace-nowrap text-right text-base font-bold text-[#721d18]"></td>' +
            '<td class="col-start-2 row-start-1 justify-self-end"></td>';

        fila.children[0].textContent = item.nombre;
        fila.children[2].textContent =
            '$ ' + subtotal.toLocaleString('es-CL');

        const menos = document.createElement('button');
        menos.type = 'button';
        menos.className = 'h-9 w-9 rounded-md bg-[#f7e8e6] text-lg font-bold text-[#8f241d] hover:bg-[#efd2cc] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]';
        menos.textContent = '-';
        menos.setAttribute('aria-label', `Disminuir cantidad de ${item.nombre}`);
        menos.onclick = () => cambiarCantidad(item.id_producto, -1);

        const cantidad = document.createElement('span');
        cantidad.className = 'min-w-6 text-center text-base font-bold text-[#241817]';
        cantidad.textContent = item.cantidad;

        const mas = document.createElement('button');
        mas.type = 'button';
        mas.className = 'h-9 w-9 rounded-md bg-[#f7e8e6] text-lg font-bold text-[#8f241d] hover:bg-[#efd2cc] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]';
        mas.textContent = '+';
        mas.setAttribute('aria-label', `Aumentar cantidad de ${item.nombre}`);
        mas.onclick = () => cambiarCantidad(item.id_producto, 1);

        fila.children[1].append(menos, cantidad, mas);

        const quitar = document.createElement('button');
        quitar.type = 'button';
        quitar.className = 'h-9 w-9 rounded-md text-xl font-bold text-[#a12b24] hover:bg-[#f7e8e6] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8f241d]';
        quitar.textContent = '×';
        quitar.setAttribute('aria-label', `Quitar ${item.nombre} de la venta`);
        quitar.onclick = () => cambiarCantidad(
            item.id_producto,
            -item.cantidad
        );

        fila.children[3].append(quitar);
        tbody.append(fila);
    });

    const neto = Math.round(total / 1.19);
    const iva = total - neto;

    document.getElementById('label-neto').textContent =
        '$ ' + neto.toLocaleString('es-CL');
    document.getElementById('label-iva').textContent =
        '$ ' + iva.toLocaleString('es-CL');
    document.getElementById('label-total').textContent =
        '$ ' + total.toLocaleString('es-CL');

    guardarBorrador();
}

async function procesarVenta() {
    if (ventaEnProceso) return;

    if (busquedaClienteEnProceso) {
        window.resmapAlert('Espera a que termine la búsqueda del cliente.', 'warning');
        return;
    }

    if (!carro.length) {
        window.resmapAlert('El carrito está vacío.', 'warning');
        return;
    }

    const rut = document.getElementById('rut').value.trim();
    const nombre = document.getElementById('nombre_cliente').value.trim();
    const contacto = document.getElementById('contacto_cliente').value.trim();
    const telefono = document.getElementById('telefono_cliente').value;
    const region = document.getElementById('region_cliente').value;
    const comuna = document.getElementById('comuna_cliente').value;
    const ciudad = document.getElementById('ciudad_cliente').value.trim();
    const tipoDocumento = tipoDocumentoSelect.value;
    const requiereGiro = tipoDocumento === 'Factura Electrónica';

    if (!rut || !nombre) {
        window.resmapAlert('Ingresa el RUT y el nombre del cliente.', 'warning');
        return;
    }

    if (!contacto) {
        window.resmapAlert('Ingresa la persona de contacto.', 'warning');
        return;
    }

    const giro = giroClienteInput.value.trim();
    const correo = document.getElementById('correo_cliente');
    const direccion = document.getElementById('direccion_cliente').value.trim();
    const actecoEmisor = document.getElementById('acteco_emisor').value;
    const datosFaltantes = [];

    if (requiereGiro && !giro) {
        datosFaltantes.push('giro del cliente');
    }

    if (!correo.value.trim()) {
        datosFaltantes.push('correo del cliente');
    } else if (!correo.checkValidity()) {
        datosFaltantes.push('un correo válido');
    }

    if (!direccion) {
        datosFaltantes.push('dirección del cliente');
    }

    if (!actecoEmisor) {
        datosFaltantes.push('actividad del emisor');
    }

    if (datosFaltantes.length > 0) {
        window.resmapAlert(`Completa: ${datosFaltantes.join(', ')}.`, 'warning');
        return;
    }

    if (!/^[2-9]\d{8}$/.test(telefono)) {
        window.resmapAlert(
            'Ingresa los 9 dígitos de un teléfono chileno.',
            'warning'
        );
        return;
    }

    if (!region || !comuna || !ciudad) {
        window.resmapAlert('Completa la región, comuna y ciudad del cliente.', 'warning');
        return;
    }

    if (carro.some(item => item.cantidad > item.stockMax)) {
        window.resmapAlert(
            'Hay productos cuya cantidad supera el stock disponible. Ajusta el carrito.',
            'warning'
        );
        return;
    }

    const medioPago = document.getElementById('medio_pago').value;
    const datos = {
        tipo_documento: tipoDocumento,
        medio_pago: medioPago,
        rut,
        nombre_cliente: nombre,
        contacto,
        cargo: document.getElementById('cargo').value.trim(),
        razon_social_cliente: nombre,
        giro_cliente: giro,
        correo_cliente: correo.value.trim(),
        telefono_cliente:
            document.getElementById('prefijo_telefono_cliente').value + telefono,
        direccion_cliente: direccion,
        region_cliente: region,
        comuna_cliente: comuna,
        ciudad_cliente: ciudad,
        acteco_emisor: document.getElementById('acteco_emisor').value,
        tipo_venta: 'del_giro',
        tipo_compra: 'del_giro',
        numero_oc: document.getElementById('numero_oc').value.trim(),
        detalles: carro.map(item => ({
            id_producto: item.id_producto,
            cantidad: item.cantidad
        }))
    };

    guardarBorrador();

    const boton = document.getElementById('registrar-venta');
    ventaEnProceso = true;
    boton.disabled = true;
    let solicitudIniciada = false;

    try {
        const detalleProductos = carro.map(item => {
            const subtotal = new Intl.NumberFormat('es-CL', {
                maximumFractionDigits: 0,
            }).format(item.precio * item.cantidad);

            return `• ${item.nombre}: ${item.cantidad} × $ ${subtotal}`;
        }).join('\n');
        const totalVenta = document.getElementById('label-total').textContent.trim();
        const confirmado = await window.resmapConfirm(
            `Revisa la venta antes de registrarla:\n${detalleProductos}\n\nCliente: ${nombre}\nDocumento: ${tipoDocumento}\nMedio de pago: ${medioPago}\nTotal: ${totalVenta}\n\nAl continuar, se registrará la venta y se descontará el stock.`,
            { title: 'Confirmar venta', confirmText: 'Registrar venta' }
        );

        if (!confirmado) {
            return;
        }

        solicitudIniciada = true;
        const respuesta = await fetch('{{ url('/ventas') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(datos)
        });

        if (respuesta.status === 201) {
            limpiarBorrador();
            window.location.reload();
            return;
        }

        const resultado = await respuesta.json();
        const errores = Object.values(resultado.errors || {}).flat().join('\n');

        window.resmapAlert(
            resultado.error || errores || resultado.message ||
            'No se pudo registrar la venta.',
            'error'
        );
    } catch (error) {
        console.error(
            solicitudIniciada ? 'No se pudo confirmar el resultado de la venta.' : 'No se pudo mostrar la confirmación de venta.',
            error
        );

        window.resmapAlert(
            solicitudIniciada
                ? 'No se pudo confirmar el resultado. Revisa si la venta quedó registrada antes de intentarlo nuevamente.'
                : 'No se pudo abrir la confirmación. La venta no fue enviada; vuelve a intentarlo.',
            'error'
        );
    } finally {
        ventaEnProceso = false;
        boton.disabled = false;
    }
}

const lectorCodigo = document.getElementById('lector-codigo');

lectorCodigo.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
        event.preventDefault();
        buscarCodigo();
    }
});

async function buscarCodigo() {
    const input = document.getElementById('lector-codigo');
    const mensaje = document.getElementById('lector-mensaje');
    const codigo = input.value.trim();

    if (!codigo) return;

    try {
        const respuesta = await fetch(
            '{{ route('pos.producto.codigo') }}?codigo=' +
            encodeURIComponent(codigo),
            { headers: { 'Accept': 'application/json' } }
        );

        const producto = await respuesta.json();

        if (!respuesta.ok) {
            throw new Error(producto.message || 'No se encontró el producto.');
        }

        if (Number(producto.stock) <= 0) {
            throw new Error('El producto no tiene stock disponible.');
        }

        agregarAlCarro(
            Number(producto.id_producto),
            producto.nombre,
            Number(producto.precio),
            Number(producto.stock)
        );

        mensaje.textContent = 'Agregado: ' + producto.nombre;
        mensaje.className = 'mt-2 text-xs text-green-700';
        input.value = '';
    } catch (error) {
        mensaje.textContent = error.message;
        mensaje.className = 'mt-2 text-xs text-red-700';
    }

    input.focus();
}

restaurarBorrador();
actualizarRequisitoGiro();
renderCarro();

(() => {
    const rut = document.getElementById('rut');
    const mensaje = document.getElementById('mensaje-cliente');

    const campos = {
        nombre: document.getElementById('nombre_cliente'),
        contacto: document.getElementById('contacto_cliente'),
        giro: document.getElementById('giro_cliente'),
        correo: document.getElementById('correo_cliente'),
        direccion: document.getElementById('direccion_cliente'),
        ciudad: document.getElementById('ciudad_cliente')
    };

    const telefono = document.getElementById('telefono_cliente');
    const region = document.getElementById('region_cliente');
    const comuna = document.getElementById('comuna_cliente');

    let temporizador;
    let controlador;
    let numeroConsulta = 0;

    const normalizarRut = valor =>
        valor.replace(/[^0-9kK]/g, '').toUpperCase();

    let ultimoRutConsultado = normalizarRut(rut.value);

    function mostrarMensaje(texto, estado = 'info') {
        mensaje.textContent = texto;
        mensaje.className = estado === 'error'
            ? 'mt-1 text-xs font-medium text-red-700'
            : estado === 'success'
                ? 'mt-1 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-xs font-semibold text-green-800'
                : 'mt-1 text-xs text-gray-600';
    }

    function limpiarDatosCliente() {
        Object.values(campos).forEach(campo => campo.value = '');
        telefono.value = '';
        region.value = '';
        region.dispatchEvent(new Event('change'));
        comuna.value = '';
    }

    async function buscarCliente() {
        clearTimeout(temporizador);

        const valor = normalizarRut(rut.value);

        if (valor.length < 8 || valor.length > 9) {
            mostrarMensaje('Ingresa el RUT completo.');
            return;
        }

        if (valor === ultimoRutConsultado) return;

        controlador?.abort();
        controlador = new AbortController();

        const consulta = ++numeroConsulta;
        busquedaClienteEnProceso = true;
        mostrarMensaje('Buscando cliente...');

        try {
            const parametros = new URLSearchParams({
                rut: rut.value.trim()
            });

            const respuesta = await fetch(
                '{{ route('pos.cliente.rut') }}?' + parametros.toString(),
                {
                    headers: { 'Accept': 'application/json' },
                    signal: controlador.signal
                }
            );

            const datos = await respuesta.json();
            if (consulta !== numeroConsulta) return;

            if (!respuesta.ok) {
                const errores = Object.values(datos.errors || {}).flat().join(' ');
                throw new Error(errores || 'No se pudo consultar el cliente.');
            }

            ultimoRutConsultado = valor;

            if (!datos.encontrado) {
                mostrarMensaje(
                    'Cliente nuevo. Completa sus datos; se guardarán al registrar la venta.'
                );
                guardarBorrador();
                return;
            }

            const cliente = datos.cliente;

            Object.entries(campos).forEach(([nombre, campo]) => {
                const valor = nombre === 'nombre'
                    ? (cliente.razon_social || cliente.nombre)
                    : cliente[nombre];
                campo.value = valor || '';
            });

            telefono.value = window.telefonoNacionalChile(cliente.telefono);

            region.value = cliente.region || '';
            region.dispatchEvent(new Event('change'));
            comuna.value = cliente.comuna || '';

            mostrarMensaje(
                'RUT ya registrado. Se autocompletaron los datos del cliente.',
                'success'
            );
            guardarBorrador();
        } catch (error) {
            if (error.name === 'AbortError' || consulta !== numeroConsulta) return;

            ultimoRutConsultado = '';
            mostrarMensaje(error.message, 'error');
        } finally {
            if (consulta === numeroConsulta) {
                busquedaClienteEnProceso = false;
            }
        }
    }

    rut.addEventListener('input', () => {
        clearTimeout(temporizador);
        controlador?.abort();

        numeroConsulta++;
        busquedaClienteEnProceso = false;
        ultimoRutConsultado = '';

        limpiarDatosCliente();
        mostrarMensaje('');
        guardarBorrador();

        const valor = normalizarRut(rut.value);

        if (valor.length >= 8 && valor.length <= 9) {
            temporizador = setTimeout(buscarCliente, 600);
        }
    });

    rut.addEventListener('blur', buscarCliente);

    rut.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            buscarCliente();
        }
    });
})();

idsCamposCliente.forEach(id => {
    const campo = document.getElementById(id);
    campo.addEventListener('input', guardarBorrador);
    campo.addEventListener('change', guardarBorrador);
});

window.addEventListener('pagehide', guardarBorrador);

lectorCodigo.focus();
</script>
@endsection