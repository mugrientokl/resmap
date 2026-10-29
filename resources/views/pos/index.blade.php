@extends('layouts.app')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">
            Punto de Venta (POS) - Repuestos
        </h2>

        <div class="mb-4 rounded-md border border-[#e8c8c3] bg-[#fff7f5] p-3">
            <label for="lector-codigo" class="block text-sm font-bold text-[#8f241d]">
                Escanear código
            </label>

            <div class="mt-2 flex gap-2">
                <input type="text" id="lector-codigo" autocomplete="off"
                    placeholder="Apunta el lector y escanea"
                    class="w-full rounded-md border-gray-300 p-2 shadow-sm">

                <button type="button" onclick="buscarCodigo()"
                    class="rounded-md bg-[#b52f25] px-4 py-2 text-sm font-bold text-white">
                    Agregar
                </button>
            </div>

            <p id="lector-mensaje" class="mt-2 text-xs text-gray-500">
                El lector USB funciona como teclado. Pulsa Enter después de escanear.
            </p>
        </div>

        <form id="form-filtros" method="GET" action="{{ url('/pos') }}"
            class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-2">

            <div id="selector-productos" class="relative min-w-0">
                <label for="buscar-producto" class="sr-only">Buscar producto</label>

                <input type="text" id="buscar-producto" name="nombre"
                    value="{{ request('nombre') }}"
                    placeholder="Escribe una palabra del producto"
                    autocomplete="off"
                    class="w-full rounded-md border-gray-300 shadow-sm border p-2 text-gray-900 bg-white">

                <div id="lista-sugerencias" style="display: none;"
                    class="absolute left-0 top-full z-50 mt-1 w-full max-h-64 overflow-y-auto rounded-md border border-gray-300 bg-white shadow-lg">
                </div>

                <select id="datos-productos" hidden aria-hidden="true" tabindex="-1">
                    @foreach($productosBuscador as $producto)
                        <option value="{{ $producto->nombre }}"
                            data-codigo="{{ $producto->codigo_origen ?: $producto->codigo_barra }}">
                            {{ $producto->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <select name="categoria" aria-label="Categoría"
                class="rounded-md border-gray-300 shadow-sm border p-2">
                <option value="">Todas las categorías</option>

                @foreach($categorias as $categoria)
                    <option value="{{ $categoria->id_categoria }}"
                        @selected(request('categoria') == $categoria->id_categoria)>
                        {{ $categoria->nombre_categoria }}
                    </option>
                @endforeach
            </select>

            <div class="flex gap-2">
                <button type="submit"
                    class="bg-[#b52f25] text-white px-3 py-2 rounded-md hover:bg-[#8f241d]">
                    Filtrar
                </button>

                <a href="{{ url('/pos') }}"
                    class="border border-[#d9aaa3] px-3 py-2 text-[#9f2f25] rounded-md hover:bg-[#f7e8e6]">
                    Limpiar
                </a>
            </div>
        </form>

        <p class="mb-3 text-sm text-gray-500">
            {{ $productos->total() }} productos encontrados
        </p>

        <div class="overflow-x-auto max-h-96 overflow-y-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nombre</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Acción</th>
                    </tr>
                </thead>

                <tbody id="tabla-productos" class="bg-white divide-y divide-gray-200">
                    @forelse($productos as $prod)
                        <tr>
                            <td class="px-4 py-2 text-sm font-mono text-gray-600">
                                {{ $prod->codigo_barra }}
                            </td>
                            <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                {{ $prod->nombre }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                $ {{ number_format($prod->precio, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-500">
                                {{ $prod->stock_disponible }}
                            </td>
                            <td class="px-4 py-2 text-center text-sm">
                                <button type="button"
                                    data-id="{{ $prod->id_producto }}"
                                    data-nombre="{{ $prod->nombre }}"
                                    data-precio="{{ $prod->precio }}"
                                    data-stock="{{ $prod->stock_disponible }}"
                                    onclick="agregarAlCarro(
                                        Number(this.dataset.id),
                                        this.dataset.nombre,
                                        Number(this.dataset.precio),
                                        Number(this.dataset.stock)
                                    )"
                                    class="bg-[#b52f25] text-white px-3 py-1 rounded hover:bg-[#8f241d] text-xs">
                                    Agregar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
                                No se encontraron productos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $productos->links() }}</div>
    </div>

    <div class="bg-white shadow-md rounded-lg p-6 flex flex-col justify-between">
        <div>
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                Datos del Cliente (Defontana)
            </h3>

            <div class="space-y-3 mb-6">
                <div>
                    <label for="rut" class="block text-xs font-medium text-gray-700">
                        RUT Empresa / Cliente
                    </label>
                    <input type="text" id="rut" placeholder="12.345.678-9"
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
                    <input type="text" id="nombre_cliente" placeholder="Constructora SpA"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                </div>

                <div>
                    <label for="giro_cliente" class="block text-xs font-medium text-gray-700">
                        Giro
                    </label>
                    <input type="text" id="giro_cliente" placeholder="Comercio de repuestos"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="correo_cliente" class="block text-xs font-medium text-gray-700">
                            Correo
                        </label>
                        <input type="email" id="correo_cliente" placeholder="contacto@empresa.cl"
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
                            <input type="text" id="telefono_cliente" placeholder="12345678"
                                pattern="[0-9]{8}" maxlength="8" inputmode="numeric"
                                class="min-w-0 flex-1 rounded-r-md border border-l-0 border-gray-300 p-2 text-sm">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="direccion_cliente" class="block text-xs font-medium text-gray-700">
                        Dirección
                    </label>
                    <input type="text" id="direccion_cliente" placeholder="Faena / Dirección"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="region_cliente" class="block text-xs font-medium text-gray-700">
                            Región
                        </label>
                        <select id="region_cliente" required
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white p-2 text-sm">
                            <option value="">Selecciona una región</option>
                        </select>
                    </div>

                    <div>
                        <label for="comuna_cliente" class="block text-xs font-medium text-gray-700">
                            Comuna
                        </label>
                        <select id="comuna_cliente" required disabled
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white p-2 text-sm">
                            <option value="">Selecciona una comuna</option>
                        </select>
                    </div>
                </div>
            </div>

            <h3 class="text-lg font-bold text-gray-800 mb-2">Documento y Pago</h3>

            <div class="grid grid-cols-2 gap-2 mb-4">
                <div>
                    <label for="tipo_documento" class="block text-xs font-medium text-gray-700">
                        Documento SII
                    </label>
                    <select id="tipo_documento"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm">
                        <option value="Boleta Electrónica">Boleta (39)</option>
                        <option value="Factura Electrónica">Factura (33)</option>
                    </select>
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

            <h3 class="font-bold text-gray-800 mb-2">Ítems Seleccionados</h3>

            <div class="max-h-40 overflow-y-auto mb-4 border rounded p-2 bg-gray-50">
                <table class="min-w-full text-xs" id="tabla-carro">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left pb-1">Repuesto</th>
                            <th class="text-center pb-1">Cant</th>
                            <th class="text-right pb-1">Subtotal</th>
                            <th class="text-center pb-1"></th>
                        </tr>
                    </thead>
                    <tbody id="carro-items"></tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="border-t pt-3 mb-4 space-y-1 text-sm">
                <div class="flex justify-between text-gray-600">
                    <span>Neto:</span>
                    <span id="label-neto">$ 0</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>IVA (19%):</span>
                    <span id="label-iva">$ 0</span>
                </div>
                <div class="flex justify-between font-bold text-gray-900 text-base">
                    <span>Total:</span>
                    <span id="label-total">$ 0</span>
                </div>
            </div>

            <button type="button" id="registrar-venta" onclick="procesarVenta()"
                class="w-full bg-[#8f241d] text-white py-2 rounded-md hover:bg-[#721d18] font-bold disabled:opacity-50">
                Registrar venta e imprimir
            </button>
        </div>
    </div>
</div>

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
    'giro_cliente',
    'correo_cliente',
    'prefijo_telefono_cliente',
    'telefono_cliente',
    'direccion_cliente',
    'region_cliente',
    'comuna_cliente',
    'tipo_documento',
    'medio_pago'
];

inicializarUbicacionChile('region_cliente', 'comuna_cliente');
inicializarSelectorTelefono('prefijo_telefono_cliente', 'telefono_cliente');

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
                document.getElementById(id).value = campos[id];
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

        document.querySelectorAll(
            '#tabla-productos button[data-id]'
        ).forEach(boton => {
            const item = carro.find(
                producto => producto.id_producto === Number(boton.dataset.id)
            );

            if (item) {
                item.stockMax = Number(boton.dataset.stock);
                item.precio = Number(boton.dataset.precio);
            }
        });

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

function normalizarBusqueda(texto) {
    return texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

const productosSugeridos = Array.from(
    document.getElementById('datos-productos').options
).map(opcion => ({
    nombre: opcion.value,
    codigo: opcion.dataset.codigo || ''
}));

function cerrarSugerencias() {
    listaSugerencias.style.display = 'none';
}

function mostrarSugerencias() {
    const texto = normalizarBusqueda(buscadorProducto.value);
    listaSugerencias.replaceChildren();

    if (!texto) {
        cerrarSugerencias();
        return;
    }

    const palabras = texto.split(/\s+/);

    const coincidencias = productosSugeridos.filter(producto => {
        const nombre = normalizarBusqueda(producto.nombre);
        return palabras.every(palabra => nombre.includes(palabra));
    }).slice(0, 30);

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
        codigo.textContent = producto.codigo;

        boton.append(nombre, codigo);

        boton.addEventListener('click', () => {
            buscadorProducto.value = producto.nombre;
            buscadorProducto.focus();
            cerrarSugerencias();
        });

        listaSugerencias.append(boton);
    });

    listaSugerencias.style.display = 'block';
}

buscadorProducto.addEventListener('input', mostrarSugerencias);
buscadorProducto.addEventListener('focus', mostrarSugerencias);

selectorProductos.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        buscadorProducto.focus();
        cerrarSugerencias();
    }

    if (event.key === 'ArrowDown' && event.target === buscadorProducto) {
        mostrarSugerencias();
        const primera = listaSugerencias.querySelector('button');

        if (primera) {
            event.preventDefault();
            primera.focus();
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

    let total = 0;

    carro.forEach(item => {
        const subtotal = item.precio * item.cantidad;
        total += subtotal;

        const fila = document.createElement('tr');
        fila.className = 'border-b';
        fila.innerHTML =
            '<td class="py-1"></td>' +
            '<td class="py-1 text-center"></td>' +
            '<td class="py-1 text-right"></td>' +
            '<td class="py-1 text-center"></td>';

        fila.children[0].textContent = item.nombre;
        fila.children[2].textContent =
            '$ ' + subtotal.toLocaleString('es-CL');

        const menos = document.createElement('button');
        menos.type = 'button';
        menos.className = 'px-1 bg-gray-200 rounded';
        menos.textContent = '-';
        menos.onclick = () => cambiarCantidad(item.id_producto, -1);

        const cantidad = document.createElement('span');
        cantidad.className = 'mx-1';
        cantidad.textContent = item.cantidad;

        const mas = document.createElement('button');
        mas.type = 'button';
        mas.className = 'px-1 bg-gray-200 rounded';
        mas.textContent = '+';
        mas.onclick = () => cambiarCantidad(item.id_producto, 1);

        fila.children[1].append(menos, cantidad, mas);

        const quitar = document.createElement('button');
        quitar.type = 'button';
        quitar.className = 'text-red-500 font-bold';
        quitar.textContent = '×';
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
    const telefono = document.getElementById('telefono_cliente').value;
    const region = document.getElementById('region_cliente').value;
    const comuna = document.getElementById('comuna_cliente').value;

    if (!rut || !nombre) {
        window.resmapAlert('Ingresa el RUT y el nombre del cliente.', 'warning');
        return;
    }

    if (!/^\d{8}$/.test(telefono)) {
        window.resmapAlert(
            'El teléfono debe contener exactamente 8 dígitos.',
            'warning'
        );
        return;
    }

    if (!region || !comuna) {
        window.resmapAlert('Selecciona una región y una comuna.', 'warning');
        return;
    }

    if (carro.some(item => item.cantidad > item.stockMax)) {
        window.resmapAlert(
            'Hay productos cuya cantidad supera el stock disponible. Ajusta el carrito.',
            'warning'
        );
        return;
    }

    const datos = {
        tipo_documento: document.getElementById('tipo_documento').value,
        medio_pago: document.getElementById('medio_pago').value,
        rut,
        nombre_cliente: nombre,
        razon_social_cliente: nombre,
        giro_cliente: document.getElementById('giro_cliente').value,
        correo_cliente: document.getElementById('correo_cliente').value,
        telefono_cliente:
            document.getElementById('prefijo_telefono_cliente').value + telefono,
        direccion_cliente: document.getElementById('direccion_cliente').value,
        region_cliente: region,
        comuna_cliente: comuna,
        detalles: carro.map(item => ({
            id_producto: item.id_producto,
            cantidad: item.cantidad
        }))
    };

    guardarBorrador();

    const boton = document.getElementById('registrar-venta');
    ventaEnProceso = true;
    boton.disabled = true;

    try {
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

            await window.resmapAlert(
                '¡Venta registrada y stock actualizado!',
                'success'
            );

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
        console.error('Error al registrar la venta:', error);

        window.resmapAlert(
            'No se pudo confirmar el resultado. Revisa si la venta quedó registrada antes de intentarlo nuevamente.',
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
renderCarro();

(() => {
    const rut = document.getElementById('rut');
    const mensaje = document.getElementById('mensaje-cliente');

    const campos = {
        nombre: document.getElementById('nombre_cliente'),
        giro: document.getElementById('giro_cliente'),
        correo: document.getElementById('correo_cliente'),
        direccion: document.getElementById('direccion_cliente')
    };

    const telefono = document.getElementById('telefono_cliente');
    const prefijo = document.getElementById('prefijo_telefono_cliente');
    const region = document.getElementById('region_cliente');
    const comuna = document.getElementById('comuna_cliente');

    let temporizador;
    let controlador;
    let numeroConsulta = 0;

    const normalizarRut = valor =>
        valor.replace(/[^0-9kK]/g, '').toUpperCase();

    let ultimoRutConsultado = normalizarRut(rut.value);

    function mostrarMensaje(texto, error = false) {
        mensaje.textContent = texto;
        mensaje.className = error
            ? 'mt-1 text-xs text-red-700'
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
                campo.value = cliente[nombre] || '';
            });

            const numero = String(cliente.telefono || '').replace(/\D/g, '');
            telefono.value = numero.length >= 8 ? numero.slice(-8) : '';

            if (numero.length > 8) {
                const codigo = '+' + numero.slice(0, -8);

                if (Array.from(prefijo.options).some(
                    opcion => opcion.value === codigo
                )) {
                    prefijo.value = codigo;
                    prefijo.dispatchEvent(new Event('change'));
                }
            }

            region.value = cliente.region || '';
            region.dispatchEvent(new Event('change'));
            comuna.value = cliente.comuna || '';

            mostrarMensaje('Cliente encontrado. Datos completados automáticamente.');
            guardarBorrador();
        } catch (error) {
            if (error.name === 'AbortError' || consulta !== numeroConsulta) return;

            ultimoRutConsultado = '';
            mostrarMensaje(error.message, true);
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

document.getElementById('form-filtros')
    .addEventListener('submit', guardarBorrador);

window.addEventListener('pagehide', guardarBorrador);

lectorCodigo.focus();
</script>
@endsection