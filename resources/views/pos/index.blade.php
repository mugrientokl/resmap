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
                <input
                    type="text"
                    id="lector-codigo"
                    autocomplete="off"
                    placeholder="Apunta el lector y escanea"
                    class="w-full rounded-md border-gray-300 p-2 shadow-sm"
                >

                <button
                    type="button"
                    onclick="buscarCodigo()"
                    class="rounded-md bg-[#b52f25] px-4 py-2 text-sm font-bold text-white"
                >
                    Agregar
                </button>
            </div>

            <p id="lector-mensaje" class="mt-2 text-xs text-gray-500">
                El lector USB funciona como teclado. Pulsa Enter después de escanear.
            </p>
        </div>

        <form
            method="GET"
            action="{{ url('/pos') }}"
            class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-2"
        >
            <div id="selector-productos" class="relative min-w-0">
                <label for="buscar-producto" class="sr-only">
                    Buscar producto
                </label>

                <input
                    type="text"
                    id="buscar-producto"
                    name="nombre"
                    value="{{ request('nombre') }}"
                    placeholder="Escribe una palabra del producto"
                    autocomplete="off"
                    class="w-full rounded-md border-gray-300 shadow-sm border p-2 text-gray-900 bg-white"
                >

                <div
                    id="lista-sugerencias"
                    style="display: none;"
                    class="absolute left-0 top-full z-50 mt-1 w-full max-h-64 overflow-y-auto rounded-md border border-gray-300 bg-white shadow-lg"
                ></div>

                <select id="datos-productos" hidden aria-hidden="true" tabindex="-1">
                    @foreach($productosBuscador as $producto)
                        <option
                            value="{{ $producto->nombre }}"
                            data-codigo="{{ $producto->codigo_origen ?: $producto->codigo_barra }}"
                        >
                            {{ $producto->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <select
                name="categoria"
                aria-label="Categoría"
                class="rounded-md border-gray-300 shadow-sm border p-2"
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

            <div class="flex gap-2">
                <button
                    type="submit"
                    class="bg-[#b52f25] text-white px-3 py-2 rounded-md hover:bg-[#8f241d]"
                >
                    Filtrar
                </button>

                <a
                    href="{{ url('/pos') }}"
                    class="border border-[#d9aaa3] px-3 py-2 text-[#9f2f25] rounded-md hover:bg-[#f7e8e6]"
                >
                    Limpiar
                </a>
            </div>
        </form>

        <div class="mb-3 text-sm text-gray-500">
            {{ $productos->total() }} productos encontrados
        </div>

        <div class="overflow-x-auto max-h-96 overflow-y-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Código
                        </th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Nombre
                        </th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Precio
                        </th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                            Stock
                        </th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">
                            Acción
                        </th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200" id="tabla-productos">
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
                                <button
                                    type="button"
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
                                    class="bg-[#b52f25] text-white px-3 py-1 rounded hover:bg-[#8f241d] text-xs"
                                >
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

        <div class="mt-4">
            {{ $productos->links() }}
        </div>
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

                    <input
                        type="text"
                        id="rut"
                        placeholder="12.345.678-9"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm"
                    >
                </div>

                <div>
                    <label for="nombre_cliente" class="block text-xs font-medium text-gray-700">
                        Razón Social / Nombre
                    </label>

                    <input
                        type="text"
                        id="nombre_cliente"
                        placeholder="Constructora SpA"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm"
                    >
                </div>

                <div>
                    <label for="giro_cliente" class="block text-xs font-medium text-gray-700">
                        Giro
                    </label>

                    <input
                        type="text"
                        id="giro_cliente"
                        placeholder="Comercio de repuestos"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm"
                    >
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="correo_cliente" class="block text-xs font-medium text-gray-700">
                            Correo
                        </label>

                        <input
                            type="email"
                            id="correo_cliente"
                            placeholder="contacto@empresa.cl"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm"
                        >
                    </div>

                    <div>
                        <label for="telefono_cliente" class="block text-xs font-medium text-gray-700">
                            Teléfono
                        </label>

                        <div class="mt-1 flex">
                            <select
                                id="prefijo_telefono_cliente"
                                class="min-w-0 rounded-l-md border border-gray-300 bg-white p-2 text-sm"
                                aria-label="Código de país"
                            ></select>

                            <input
                                type="text"
                                id="telefono_cliente"
                                placeholder="12345678"
                                pattern="[0-9]{8}"
                                maxlength="8"
                                inputmode="numeric"
                                class="min-w-0 flex-1 rounded-r-md border border-l-0 border-gray-300 p-2 text-sm"
                            >
                        </div>
                    </div>
                </div>

                <div>
                    <label for="direccion_cliente" class="block text-xs font-medium text-gray-700">
                        Dirección
                    </label>

                    <input
                        type="text"
                        id="direccion_cliente"
                        placeholder="Faena / Dirección"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm"
                    >
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="region_cliente" class="block text-xs font-medium text-gray-700">
                            Región
                        </label>

                        <select
                            id="region_cliente"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white p-2 text-sm"
                        >
                            <option value="">Selecciona una región</option>
                        </select>
                    </div>

                    <div>
                        <label for="comuna_cliente" class="block text-xs font-medium text-gray-700">
                            Comuna
                        </label>

                        <select
                            id="comuna_cliente"
                            required
                            disabled
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white p-2 text-sm"
                        >
                            <option value="">Selecciona una comuna</option>
                        </select>
                    </div>
                </div>
            </div>

            <h3 class="text-lg font-bold text-gray-800 mb-2">
                Documento y Pago
            </h3>

            <div class="grid grid-cols-2 gap-2 mb-4">
                <div>
                    <label for="tipo_documento" class="block text-xs font-medium text-gray-700">
                        Documento SII
                    </label>

                    <select
                        id="tipo_documento"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm"
                    >
                        <option value="Boleta Electrónica">Boleta (39)</option>
                        <option value="Factura Electrónica">Factura (33)</option>
                    </select>
                </div>

                <div>
                    <label for="medio_pago" class="block text-xs font-medium text-gray-700">
                        Medio de Pago
                    </label>

                    <select
                        id="medio_pago"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm border p-2 text-sm"
                    >
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia</option>
                        <option value="Tarjeta">Tarjeta</option>
                    </select>
                </div>
            </div>

            <h3 class="text-md font-bold text-gray-800 mb-2">
                Ítems Seleccionados
            </h3>

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

            <button
                type="button"
                onclick="procesarVenta()"
                class="w-full bg-[#8f241d] text-white py-2 rounded-md hover:bg-[#721d18] font-bold text-center block"
            >
                Registrar venta e imprimir
            </button>
        </div>
    </div>
</div>

<script src="{{ asset('js/chile-location.js') }}"></script>

<script>
    let carro = [];

    const buscadorProducto = document.getElementById('buscar-producto');
    const listaSugerencias = document.getElementById('lista-sugerencias');
    const selectorProductos = document.getElementById('selector-productos');

    const productosSugeridos = Array.from(
        document.getElementById('datos-productos').options
    ).map(opcion => ({
        nombre: opcion.value,
        codigo: opcion.dataset.codigo || ''
    }));

    function normalizarBusqueda(texto) {
        return texto
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

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

        if (coincidencias.length === 0) {
            const mensaje = document.createElement('p');
            mensaje.className = 'px-3 py-3 text-sm text-gray-500';
            mensaje.textContent = 'No se encontraron productos.';
            listaSugerencias.appendChild(mensaje);
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

            listaSugerencias.appendChild(boton);
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

            const primeraOpcion = listaSugerencias.querySelector('button');

            if (primeraOpcion) {
                event.preventDefault();
                primeraOpcion.focus();
            }
        }
    });

    document.addEventListener('click', event => {
        if (!selectorProductos.contains(event.target)) {
            cerrarSugerencias();
        }
    });

    selectorProductos.addEventListener('focusout', event => {
        if (!selectorProductos.contains(event.relatedTarget)) {
            cerrarSugerencias();
        }
    });

    function agregarAlCarro(id_producto, nombre, precio, stockMax) {
        if (stockMax <= 0) {
            window.resmapAlert(
                'El producto no tiene stock disponible.',
                'warning'
            );
            return;
        }

        let existente = carro.find(item => item.id_producto === id_producto);

        if (existente) {
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
        let item = carro.find(i => i.id_producto === id_producto);

        if (item) {
            item.cantidad += delta;

            if (item.cantidad <= 0) {
                carro = carro.filter(i => i.id_producto !== id_producto);
            } else if (item.cantidad > item.stockMax) {
                item.cantidad = item.stockMax;
                window.resmapAlert('Stock máximo alcanzado.', 'warning');
            }
        }

        renderCarro();
    }

    function renderCarro() {
        let tbody = document.getElementById('carro-items');
        tbody.innerHTML = '';

        let totalGeneral = 0;

        carro.forEach(item => {
            let subtotal = item.precio * item.cantidad;
            totalGeneral += subtotal;

            let row = document.createElement('tr');
            row.className = 'border-b';
            row.innerHTML =
                '<td class="py-1"></td>' +
                '<td class="py-1 text-center"></td>' +
                '<td class="py-1 text-right"></td>' +
                '<td class="py-1 text-center"></td>';

            row.children[0].textContent = item.nombre;
            row.children[2].textContent =
                '$ ' + subtotal.toLocaleString('es-CL');

            let decrease = document.createElement('button');
            decrease.type = 'button';
            decrease.className = 'px-1 bg-gray-200 rounded';
            decrease.textContent = '-';
            decrease.onclick = () => cambiarCantidad(item.id_producto, -1);

            let increase = decrease.cloneNode(true);
            increase.textContent = '+';
            increase.onclick = () => cambiarCantidad(item.id_producto, 1);

            let quantity = document.createElement('span');
            quantity.className = 'mx-1';
            quantity.textContent = item.cantidad;

            row.children[1].append(decrease, quantity, increase);

            let remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'text-red-500 font-bold';
            remove.textContent = '×';
            remove.onclick = () =>
                cambiarCantidad(item.id_producto, -item.cantidad);

            row.children[3].append(remove);
            tbody.append(row);
        });

        let neto = Math.round(totalGeneral / 1.19);
        let iva = totalGeneral - neto;

        document.getElementById('label-neto').innerText =
            '$ ' + neto.toLocaleString('es-CL');

        document.getElementById('label-iva').innerText =
            '$ ' + iva.toLocaleString('es-CL');

        document.getElementById('label-total').innerText =
            '$ ' + totalGeneral.toLocaleString('es-CL');
    }

    function procesarVenta() {
        if (carro.length === 0) {
            window.resmapAlert('El carrito está vacío.', 'warning');
            return;
        }

        let rut = document.getElementById('rut').value;
        let nombre_cliente = document.getElementById('nombre_cliente').value;

        if (!rut || !nombre_cliente) {
            window.resmapAlert(
                'Ingresa el RUT y el nombre del cliente.',
                'warning'
            );
            return;
        }

        const telefonoLocal = document.getElementById('telefono_cliente').value;
        const region = document.getElementById('region_cliente').value;
        const comuna = document.getElementById('comuna_cliente').value;

        if (!/^\d{8}$/.test(telefonoLocal)) {
            window.resmapAlert(
                'El teléfono debe contener exactamente 8 dígitos.',
                'warning'
            );
            return;
        }

        if (!region || !comuna) {
            window.resmapAlert(
                'Selecciona una región y una comuna.',
                'warning'
            );
            return;
        }

        let datos = {
            tipo_documento: document.getElementById('tipo_documento').value,
            medio_pago: document.getElementById('medio_pago').value,
            rut: rut,
            nombre_cliente: nombre_cliente,
            razon_social_cliente: nombre_cliente,
            giro_cliente: document.getElementById('giro_cliente').value,
            correo_cliente: document.getElementById('correo_cliente').value,
            telefono_cliente:
                document.getElementById('prefijo_telefono_cliente').value +
                telefonoLocal,
            direccion_cliente: document.getElementById('direccion_cliente').value,
            region_cliente: region,
            comuna_cliente: comuna,
            detalles: carro.map(i => ({
                id_producto: i.id_producto,
                cantidad: i.cantidad
            }))
        };

        fetch('{{ url('/ventas') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(datos)
        })
        .then(response => response.json().then(data => ({
            status: response.status,
            body: data
        })))
        .then(res => {
            if (res.status === 201) {
                window.resmapAlert(
                    '¡Venta registrada y stock actualizado!',
                    'success'
                ).then(() => window.location.reload());
            } else {
                const errors = Object.values(res.body.errors || {})
                    .flat()
                    .join('\n');

                window.resmapAlert(
                    res.body.error || errors || 'No se pudo registrar la venta.',
                    'error'
                );
            }
        })
        .catch(err => {
            console.error('No se pudo registrar la venta:', err);

            window.resmapAlert(
                'No fue posible comunicarse con el sistema para registrar la venta. Inténtalo nuevamente.',
                'error'
            );
        });
    }

    const lectorCodigo = document.getElementById('lector-codigo');

    lectorCodigo.focus();

    lectorCodigo.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            buscarCodigo();
        }
    });

    async function buscarCodigo() {
        const input = document.getElementById('lector-codigo');
        const mensaje = document.getElementById('lector-mensaje');
        const codigo = input.value.trim();

        if (!codigo) {
            return;
        }

        try {
            const response = await fetch(
                '{{ route('pos.producto.codigo') }}?codigo=' +
                encodeURIComponent(codigo),
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            const producto = await response.json();

            if (!response.ok) {
                throw new Error(
                    producto.message || 'No se encontró el producto.'
                );
            }

            if (producto.stock <= 0) {
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

    inicializarUbicacionChile('region_cliente', 'comuna_cliente');
    inicializarSelectorTelefono('prefijo_telefono_cliente', 'telefono_cliente');
</script>
@endsection