@extends('layouts.app')

@section('content')
@php
    $esServicio = $solicitud->tipo_solicitud === 'servicio';
    $ultimaVersion = $solicitud->cotizacion?->versiones->sortByDesc('numero_version')->first();
    $venta = $ultimaVersion?->venta ?? $solicitud->venta;
    $ventaCompleta = $venta && $venta->estado_venta === 'confirmada' && $venta->stock_descontado_at;
    $ordenesCompra = $solicitud->cotizacion?->versiones->flatMap->ordenesCompra ?? collect();
    $itemsEdicion = $ultimaVersion
        ? $ultimaVersion->detalles->map(fn ($item) => ['id_producto' => $item->producto_id, 'nombre' => $item->nombre_producto, 'cantidad' => $item->cantidad])
        : $productos->map(fn ($item) => ['id_producto' => $item['producto']?->id_producto, 'nombre' => $item['producto']?->nombre ?? 'Producto no encontrado', 'cantidad' => $item['cantidad']]);
    $datosResumen = $venta?->datos_resumen ?? [];
@endphp
<div class="mx-auto max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('solicitudes.index') }}" class="rounded-md border border-[#d9aaa3] px-4 py-2 text-sm font-bold text-[#9f2f25] shadow-sm hover:bg-[#f7e8e6]">Volver a cotizaciones / servicios</a>
        @unless($esServicio)
            <a href="{{ route('solicitudes.seguimiento', $solicitud->id_solicitud) }}" class="rounded-md border border-[#b52f25] px-5 py-3 text-sm font-bold text-[#9f2f25] shadow-sm hover:bg-[#f7e8e6]">Ver seguimiento completo</a>
        @endunless
    </div>
    @if($errors->any())
        <div class="mt-5 border-l-4 border-red-600 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <p class="font-bold">No se pudo completar la acción:</p>
            <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(260px,0.72fr)_minmax(0,1.6fr)]">
        <section class="bg-white shadow-sm">
            <header class="border-b border-[#ead8d5] bg-[#fffaf9] p-6">
                <p class="text-xs font-black uppercase tracking-wider text-[#b52f25]">{{ $esServicio ? 'Servicio' : 'Cotización' }} #{{ $solicitud->id_solicitud }}</p>
                <h1 class="mt-2 text-3xl font-black">Detalle {{ $esServicio ? 'del servicio' : 'de la cotización' }}</h1>
                <p class="mt-2 text-sm text-gray-500">Recibida el {{ $solicitud->fecha?->format('d/m/Y H:i') }}</p>
                <div class="mt-5 border-l-4 border-amber-500 bg-amber-50 p-4">
                    <p class="font-black">{{ $solicitud->estado_comercial['titulo'] }}</p>
                    <p class="mt-1 text-sm">{{ $solicitud->estado_comercial['detalle'] }}</p>
                </div>
            </header>
            <dl class="space-y-4 p-6 text-sm">
                <div><dt class="font-bold">Cliente</dt><dd>{{ $cliente->nombre }}</dd></div>
                <div><dt class="font-bold">Contacto</dt><dd>{{ $solicitud->contacto ?: 'No informado' }}</dd></div>
                <div><dt class="font-bold">Cargo</dt><dd>{{ $solicitud->cargo ?: 'No informado' }}</dd></div>
                <div><dt class="font-bold">RUT</dt><dd>{{ $cliente->rut_formateado }}</dd></div>
                <div><dt class="font-bold">Correo</dt><dd>{{ $cliente->correo ?: 'No informado' }}</dd></div>
                <div><dt class="font-bold">Teléfono</dt><dd>{{ $cliente->telefono ?: 'No informado' }}</dd></div>
                <div><dt class="font-bold">Dirección</dt><dd>{{ $cliente->direccion ?: 'No informada' }}</dd></div>
            </dl>
        </section>
        <section class="min-w-0 bg-white p-6 shadow-sm">
            @if($esServicio)
                <h2 class="text-xl font-black">{{ $solicitud->tipo_servicio }}</h2>
                <p class="mt-5 whitespace-pre-line border-l-4 border-[#b52f25] bg-[#f7e8e6] p-4">{{ $solicitud->descripcion_servicio }}</p>
                @php
                    $transicionesPermitidas = [
                        'Pendiente' => ['En proceso', 'Rechazado'],
                        'En proceso' => ['Pendiente', 'Rechazado'],
                        'Pagado - pendiente de entrega' => ['En proceso', 'Rechazado'],
                        'Entregado - pago pendiente' => ['En proceso', 'Rechazado'],
                        'Entregado y completado' => [],
                        'Rechazado' => [],
                    ][$solicitud->estado] ?? [];
                @endphp
                @if($transicionesPermitidas)
                    <form method="POST" action="{{ route('solicitudes.estado', $solicitud->id_solicitud) }}" class="mt-6 border-t pt-6" data-confirm-message="¿Confirmas guardar el nuevo estado de este servicio?">
                        @csrf @method('PATCH')
                        <label class="block text-sm font-bold">Siguiente estado
                            <select name="estado" class="mt-2 w-full border p-3">@foreach($transicionesPermitidas as $estadoPermitido)<option value="{{ $estadoPermitido }}">{{ $estadoPermitido }}</option>@endforeach</select>
                        </label>
                        <label class="mt-4 block text-sm font-bold">Observaciones<textarea name="observaciones" rows="3" class="mt-2 w-full border p-3">{{ $solicitud->observaciones }}</textarea></label>
                        <button class="mt-4 rounded-md bg-[#b52f25] px-5 py-3 font-bold text-white shadow-sm hover:bg-[#8f241d]">Guardar estado</button>
                    </form>
                @endif
                @if(! $solicitud->venta_id && ! in_array($solicitud->estado, ['Entregado y completado', 'Rechazado'], true))
                    <form method="POST" action="{{ route('solicitudes.registrar-servicio', $solicitud->id_solicitud) }}" class="mt-6 space-y-4 border-t pt-6" data-confirm-message="¿Registrar el servicio como pagado y descontar los repuestos seleccionados?">
                        @csrf
                        <h3 class="text-lg font-black">Registrar servicio completado</h3>
                        <p class="text-sm text-gray-600">El total será el precio del servicio más los repuestos a su precio de catálogo. La venta quedará pagada.</p>
                        <label class="block text-sm font-bold">Detalle del servicio
                            <input name="detalle_servicio" required maxlength="255" value="{{ old('detalle_servicio', $solicitud->tipo_servicio.' - '.\Illuminate\Support\Str::limit($solicitud->descripcion_servicio, 220)) }}" class="mt-1 block w-full rounded-md border p-2">
                        </label>
                        <label class="block text-sm font-bold">Precio del servicio (IVA incluido)
                            <input name="precio_servicio" type="number" min="0" max="99999999.99" step="0.01" required value="{{ old('precio_servicio') }}" class="mt-1 block w-full rounded-md border p-2">
                        </label>
                        <label class="block text-sm font-bold">Medio de pago
                            <select name="medio_pago" required class="mt-1 block w-full rounded-md border p-2">
                                <option value="">Selecciona un medio de pago</option>
                                @foreach(['Efectivo', 'Transferencia', 'Tarjeta', 'Otro'] as $medio)
                                    <option value="{{ $medio }}" @selected(old('medio_pago') === $medio)>{{ $medio }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-bold">Observaciones finales
                            <textarea name="observaciones" maxlength="2000" rows="3" class="mt-1 block w-full rounded-md border p-2">{{ old('observaciones', $solicitud->observaciones) }}</textarea>
                        </label>
                        <h4 class="font-black">Repuestos opcionales</h4>
                        <div id="repuestos-servicio" class="space-y-2">
                            @foreach(old('repuestos', []) as $indice => $repuesto)
                                <div class="flex flex-wrap gap-2" data-repuesto>
                                    <select name="repuestos[{{ $indice }}][id_producto]" required class="min-w-40 flex-1 border p-2" aria-label="Repuesto">
                                        <option value="">Selecciona un repuesto</option>
                                        @foreach($productosDisponibles as $producto)
                                            <option value="{{ $producto->id_producto }}" @selected((string) ($repuesto['id_producto'] ?? '') === (string) $producto->id_producto)>{{ $producto->nombre }} · {{ $producto->codigo_origen ?: $producto->codigo_barra }} · ${{ number_format($producto->precio, 2, ',', '.') }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="repuestos[{{ $indice }}][cantidad]" value="{{ $repuesto['cantidad'] ?? 1 }}" min="1" max="99999" required class="w-24 border p-2" aria-label="Cantidad">
                                    <button type="button" data-quitar-repuesto class="rounded-md border border-[#d9aaa3] px-3 py-2 text-sm font-bold text-[#9f2f25]">Quitar</button>
                                </div>
                            @endforeach
                        </div>
                        <template id="nuevo-repuesto-servicio">
                            <div class="flex flex-wrap gap-2" data-repuesto>
                                <select name="repuestos[__INDEX__][id_producto]" required class="min-w-40 flex-1 border p-2" aria-label="Repuesto">
                                    <option value="">Selecciona un repuesto</option>
                                    @foreach($productosDisponibles as $producto)
                                        <option value="{{ $producto->id_producto }}">{{ $producto->nombre }} · {{ $producto->codigo_origen ?: $producto->codigo_barra }} · ${{ number_format($producto->precio, 2, ',', '.') }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="repuestos[__INDEX__][cantidad]" value="1" min="1" max="99999" required class="w-24 border p-2" aria-label="Cantidad">
                                <button type="button" data-quitar-repuesto class="rounded-md border border-[#d9aaa3] px-3 py-2 text-sm font-bold text-[#9f2f25]">Quitar</button>
                            </div>
                        </template>
                        <button type="button" id="agregar-repuesto-servicio" class="block rounded-md border border-[#b52f25] px-4 py-2 font-bold text-[#9f2f25] shadow-sm hover:bg-[#f7e8e6]">+ Agregar repuesto</button>
                        <button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white shadow-sm hover:bg-[#8f241d]">Registrar servicio y venta</button>
                    </form>
                @endif
                @if($solicitud->estado === 'Entregado y completado' && ! $ventaCompleta)
                    <a href="{{ route('solicitudes.pdf', $solicitud->id_solicitud) }}" class="mt-4 inline-block rounded-md border border-[#b52f25] px-4 py-2 font-bold text-[#9f2f25] shadow-sm hover:bg-[#f7e8e6]">Descargar resumen interno</a>
                @endif
            @else
                <h2 class="text-xl font-black">Cotización y venta</h2>
                <p class="mt-2 text-sm text-gray-600">El stock se reserva durante la cotización y se descuenta al convertirla en venta.</p>
                @if($solicitud->cotizacion?->comentarios_cliente)
                    <p class="mt-4 whitespace-pre-line border-l-4 border-amber-500 bg-amber-50 p-3 text-sm"><strong>Última respuesta registrada:</strong> {{ $solicitud->cotizacion->comentarios_cliente }}</p>
                @endif
                @foreach($solicitud->cotizacion?->versiones->sortByDesc('numero_version') ?? [] as $version)
                    <div class="mt-3 border p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span>Versión {{ $version->numero_version }} · {{ ucfirst(str_replace('_', ' ', $version->estado)) }} · ${{ number_format($version->total, 0, ',', '.') }} · válida hasta {{ $version->vigente_hasta?->format('d/m/Y H:i') }}</span>
                            <div class="flex flex-wrap gap-3">
                                <a class="inline-block rounded-md border border-[#b52f25] px-4 py-2 font-bold text-[#9f2f25] shadow-sm hover:bg-[#f7e8e6]" href="{{ route('cotizaciones.pdf', $version->id_version) }}">Descargar cotización PDF</a>
                                @if($version->estado === 'borrador')
                                    <form method="POST" action="{{ route('cotizaciones.enviar', $version->id_version) }}" data-confirm-message="¿Enviar esta cotización al cliente?">@csrf<button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white shadow-sm hover:bg-[#8f241d]">Enviar al cliente</button></form>
                                @endif
                            </div>
                        </div>
                        <ul class="mt-2 list-disc pl-5 text-gray-600">
                            @foreach($version->detalles as $detalle)
                                <li>{{ $detalle->nombre_producto }} · {{ $detalle->cantidad }} × ${{ number_format($detalle->precio_unitario, 0, ',', '.') }}</li>
                            @endforeach
                        </ul>
                        @if($version->enviada_at)
                            <p class="mt-2 text-xs text-gray-600">Correo: {{ str_replace('_', ' ', $version->email_estado ?: 'no enviado') }} · WhatsApp: {{ str_replace('_', ' ', $version->whatsapp_estado ?: 'no enviado') }}</p>
                        @endif
                    </div>
                @endforeach

                @if(! $solicitud->cotizacion || in_array($solicitud->cotizacion->estado, ['pendiente_revision', 'borrador', 'cambios_solicitados', 'vencida'], true) || ($solicitud->cotizacion->estado === 'enviada' && in_array('fallido', [$ultimaVersion?->email_estado, $ultimaVersion?->whatsapp_estado], true)))
                    <form method="POST" action="{{ route('solicitudes.cotizacion.crear', $solicitud->id_solicitud) }}" class="mt-6 space-y-3 border-t pt-5" data-confirm-message="Se reservarán las unidades de esta versión. ¿Continuar?">
                        @csrf
                        <input type="hidden" name="items_edicion" value="1">
                        <h3 class="font-black">Preparar cotización</h3>
                        <p class="text-sm text-gray-600">Ajusta cantidades, elimina o agrega repuestos antes de crear la versión. Si un envío falló, puedes crear y enviar una nueva versión.</p>
                        <div id="items-cotizacion" class="space-y-2">
                            @foreach($itemsEdicion as $indice => $item)
                                <div class="flex flex-wrap items-center gap-2" data-item>
                                    <input type="hidden" name="items[{{ $indice }}][id_producto]" value="{{ $item['id_producto'] }}">
                                    <span class="min-w-40 flex-1 text-sm">{{ $item['nombre'] }}</span>
                                    <input type="number" name="items[{{ $indice }}][cantidad]" value="{{ $item['cantidad'] }}" min="1" max="99999" required class="w-24 border p-2" aria-label="Cantidad de {{ $item['nombre'] }}">
                                    <button type="button" data-quitar class="rounded-md border border-[#d9aaa3] px-3 py-2 text-sm font-bold text-[#9f2f25] hover:bg-[#f7e8e6]">Quitar</button>
                                </div>
                            @endforeach
                        </div>
                        <template id="nuevo-item">
                            <div class="flex flex-wrap items-center gap-2" data-item>
                                <select name="items[__INDEX__][id_producto]" required class="min-w-40 flex-1 border p-2" aria-label="Repuesto">
                                    <option value="">Selecciona un repuesto</option>
                                    @foreach($productosDisponibles as $producto)
                                        <option value="{{ $producto->id_producto }}">{{ $producto->nombre }} · {{ $producto->codigo_origen ?: $producto->codigo_barra }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="items[__INDEX__][cantidad]" value="1" min="1" max="99999" required class="w-24 border p-2" aria-label="Cantidad">
                                <button type="button" data-quitar class="rounded-md border border-[#d9aaa3] px-3 py-2 text-sm font-bold text-[#9f2f25] hover:bg-[#f7e8e6]">Quitar</button>
                            </div>
                        </template>
                        <button type="button" id="agregar-item" class="block rounded-md border border-[#b52f25] px-4 py-2 font-bold text-[#9f2f25] shadow-sm hover:bg-[#f7e8e6]">+ Agregar repuesto</button>
                        @if($solicitud->metodo_entrega === 'despacho')
                            <label class="block text-sm font-bold">Costo despacho
                                <input type="number" name="costo_despacho" min="0" step="1" value="{{ $ultimaVersion?->costo_despacho ?? config('services.cotizaciones.costo_despacho', 0) }}" class="mt-1 block border p-2">
                            </label>
                        @endif
                        <button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white shadow-sm hover:bg-[#8f241d]">{{ $ultimaVersion ? 'Crear nueva versión' : 'Crear cotización' }}</button>
                    </form>
                @endif

                @if($ultimaVersion?->estado === 'enviada' && $solicitud->cotizacion?->estado === 'enviada')
                    <form method="POST" action="{{ route('cotizaciones.respuesta-interna', $ultimaVersion->id_version) }}" class="mt-6 space-y-3 border-t pt-5" data-confirm-message="¿Registrar esta respuesta comunicada por el cliente?">
                        @csrf
                        <h3 class="font-black">Registrar respuesta del cliente</h3>
                        <label class="block text-sm font-bold">Respuesta
                            <select name="decision" required class="mt-1 block w-full border p-2">
                                <option value="">Seleccionar</option>
                                <option value="aceptada">Aceptada</option>
                                <option value="rechazada">Rechazada o cancelada</option>
                                <option value="cambios_solicitados">Solicita cambios</option>
                            </select>
                        </label>
                        <label class="block text-sm font-bold">Comentarios<textarea name="comentarios" maxlength="2000" rows="3" class="mt-1 block w-full border p-2">{{ old('comentarios') }}</textarea></label>
                        <button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white shadow-sm hover:bg-[#8f241d]">Registrar respuesta</button>
                    </form>
                @endif
                @if($ultimaVersion?->estado === 'aceptada' && ! $ventaCompleta)
                    <form method="POST" action="{{ route('cotizaciones.convertir-venta', $ultimaVersion->id_version) }}" class="mt-6 border-t pt-5" data-confirm-message="¿Confirmas que se registre la venta y se descuente el stock?">
                        @csrf
                        <p class="mb-3 text-sm">Puedes convertir la cotización aceptada en venta sin adjuntar una orden de compra.</p>
                        <button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white shadow-sm hover:bg-[#8f241d]">Registrar venta</button>
                    </form>
                @endif

                @if($ordenesCompra->isNotEmpty())
                    <div class="mt-6 border-t pt-5">
                        <h3 class="font-black">Órdenes de compra anteriores</h3>
                        @foreach($ordenesCompra as $ordenCompra)
                            <p class="mt-2 text-sm">OC {{ $ordenCompra->numero ?: 'sin número' }}
                                @if($ordenCompra->archivo_path)
                                    · <a href="{{ route('cotizaciones.orden-compra.ver', $ordenCompra->id_orden_compra) }}" class="inline-block rounded-md border border-[#d9aaa3] px-3 py-1 font-bold text-[#9f2f25] hover:bg-[#f7e8e6]">Ver</a>
                                @endif
                            </p>
                        @endforeach
                    </div>
                @endif
            @endif
                @if($ventaCompleta)
                    <form id="form-resumen" method="POST" action="{{ route('ventas.generar-resumen', $venta->id_venta) }}" class="mt-6 space-y-4 border-t pt-5">
                        @csrf
                        <h3 class="text-lg font-black">Generar resumen interno · Venta #{{ $venta->id_venta }}</h3>
                        <p class="text-sm text-amber-800">Este documento no es una factura ni un DTE tributario.</p>
                        <fieldset class="space-y-3 rounded-md border border-[#ead8d5] bg-[#fffaf9] p-4">
                            <legend class="px-1 font-black">Datos del emisor</legend>
                            <p class="text-sm">RESMAP · RUT 76.991.966-K<br>Av. Gabriela Mistral 1255, Bodega 48, Los Ángeles · resmap.la@gmail.com</p>
                            <label class="block text-sm font-bold">Giro y ACTECO de prueba
                                <select name="acteco_emisor" required class="mt-1 block w-full rounded-md border p-2">
                                    <option value="">Selecciona un giro</option>
                                    @foreach(config('services.cotizaciones.actividades_emisor_prueba') as $codigo => $actividad)
                                        <option value="{{ $codigo }}" @selected(old('acteco_emisor', $datosResumen['acteco_emisor'] ?? '') === $codigo)>{{ $actividad }} · {{ $codigo }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <p class="text-xs text-amber-800">Códigos ficticios para pruebas; no son códigos tributarios reales.</p>
                            <label class="block text-sm font-bold">Tipo de venta
                                <select name="tipo_venta" required class="mt-1 block w-full rounded-md border p-2">
                                    <option value="del_giro" @selected(old('tipo_venta', $datosResumen['tipo_venta'] ?? 'del_giro') === 'del_giro')>Del giro</option>
                                </select>
                            </label>
                        </fieldset>
                        <fieldset class="rounded-md border border-[#ead8d5] p-4">
                            <legend class="px-1 font-black">Datos del receptor</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach([
                                    'rut' => ['RUT', $cliente->rut_formateado, 20],
                                    'nombre' => ['Razón social', $cliente->razon_social ?: $cliente->nombre, 255],
                                    'giro' => ['Giro', $cliente->giro, 255],
                                    'direccion' => ['Dirección', $cliente->direccion, 255],
                                    'comuna' => ['Comuna', $cliente->comuna, 100],
                                    'ciudad' => ['Ciudad', $cliente->ciudad, 100],
                                    'contacto' => ['Contacto', $solicitud->contacto ?: $cliente->contacto, 255],
                                    'correo' => ['Correo', $cliente->correo, 255],
                                ] as $campo => [$etiqueta, $valor, $maximo])
                                    <label class="text-sm font-bold">{{ $etiqueta }}
                                        <input name="{{ $campo }}" type="{{ $campo === 'correo' ? 'email' : 'text' }}" value="{{ old($campo, $datosResumen[$campo] ?? $valor) }}" required maxlength="{{ $maximo }}" @if($campo === 'rut') data-rut autocomplete="off" @endif class="mt-1 block w-full rounded-md border p-2">
                                    </label>
                                @endforeach
                                <label class="text-sm font-bold">Tipo de compra
                                    <select name="tipo_compra" required class="mt-1 block w-full rounded-md border p-2">
                                        <option value="del_giro" @selected(old('tipo_compra', $datosResumen['tipo_compra'] ?? 'del_giro') === 'del_giro')>Del giro</option>
                                    </select>
                                </label>
                            </div>
                            <p id="receptor-mensaje" role="status" class="mt-2 text-xs text-gray-600">Los datos se pueden editar para este resumen.</p>
                        </fieldset>
                        <label class="block text-sm font-bold">Número de orden de compra
                            <input name="numero_oc" type="text" value="{{ old('numero_oc', $datosResumen['numero_oc'] ?? '') }}" @unless($esServicio) required @endunless maxlength="100" class="mt-1 block w-full rounded-md border p-2">
                        </label>
                        <button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white shadow-sm hover:bg-[#8f241d]">Generar resumen interno</button>
                    </form>
                @endif
        </section>
    </div>
</div>
<script src="{{ asset('js/rut.js') }}"></script>
<script>
    const repuestosServicio = document.getElementById('repuestos-servicio');
    const botonRepuestoServicio = document.getElementById('agregar-repuesto-servicio');
    if (repuestosServicio && botonRepuestoServicio) {
        let siguienteRepuesto = Math.max(-1, ...Array.from(
            repuestosServicio.querySelectorAll('select[name]'),
            select => Number(select.name.match(/\[(\d+)\]/)?.[1] ?? -1)
        )) + 1;
        botonRepuestoServicio.addEventListener('click', () => {
            const plantilla = document.getElementById('nuevo-repuesto-servicio').innerHTML.replaceAll('__INDEX__', siguienteRepuesto++);
            repuestosServicio.insertAdjacentHTML('beforeend', plantilla);
        });
        repuestosServicio.addEventListener('click', event => {
            if (event.target.closest('[data-quitar-repuesto]')) event.target.closest('[data-repuesto]').remove();
        });
    }

    const itemsCotizacion = document.getElementById('items-cotizacion');
    const botonAgregar = document.getElementById('agregar-item');
    if (itemsCotizacion && botonAgregar) {
        let siguienteItem = itemsCotizacion.querySelectorAll('[data-item]').length;
        botonAgregar.addEventListener('click', () => {
            const plantilla = document.getElementById('nuevo-item').innerHTML.replaceAll('__INDEX__', siguienteItem++);
            itemsCotizacion.insertAdjacentHTML('beforeend', plantilla);
        });
        itemsCotizacion.addEventListener('click', event => {
            if (event.target.closest('[data-quitar]')) event.target.closest('[data-item]').remove();
        });
    }

    const formResumen = document.getElementById('form-resumen');
    if (formResumen) {
        const rutReceptor = formResumen.elements.rut;
        const mensajeReceptor = document.getElementById('receptor-mensaje');
        const camposReceptor = ['nombre', 'giro', 'direccion', 'comuna', 'ciudad', 'contacto', 'correo'];
        let rutAnterior = window.ResmapRut.normalizar(rutReceptor.value);
        let temporizador;
        let controlador;
        const camposEditados = new Set();

        camposReceptor.forEach(campo => {
            formResumen.elements[campo].addEventListener('input', () => camposEditados.add(campo));
        });

        async function buscarReceptor() {
            const rut = window.ResmapRut.normalizar(rutReceptor.value);
            if (!/^\d{7,8}-[\dK]$/.test(rut)) return;

            controlador?.abort();
            controlador = new AbortController();
            mensajeReceptor.textContent = 'Buscando datos del receptor...';
            try {
                const parametros = new URLSearchParams({ rut });
                const respuesta = await fetch('{{ route('pos.cliente.rut') }}?' + parametros, {
                    headers: { Accept: 'application/json' },
                    signal: controlador.signal,
                });
                if (!respuesta.ok) throw new Error('No fue posible buscar el receptor.');
                const resultado = await respuesta.json();
                if (rut !== window.ResmapRut.normalizar(rutReceptor.value)) return;

                if (resultado.encontrado) {
                    const cliente = resultado.cliente;
                    camposReceptor.forEach(campo => {
                        if (camposEditados.has(campo)) return;
                        formResumen.elements[campo].value = campo === 'nombre'
                            ? (cliente.razon_social || cliente.nombre || '')
                            : (cliente[campo] || '');
                    });
                    mensajeReceptor.textContent = 'Datos encontrados. Revísalos y modifícalos si es necesario.';
                } else {
                    mensajeReceptor.textContent = 'RUT no encontrado. Ingresa los datos del receptor.';
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    mensajeReceptor.textContent = 'No fue posible buscar el receptor. Ingresa los datos manualmente.';
                }
            }
        }

        rutReceptor.addEventListener('input', () => {
            const rut = window.ResmapRut.normalizar(rutReceptor.value);
            if (rut === rutAnterior) return;
            rutAnterior = rut;
            clearTimeout(temporizador);
            controlador?.abort();
            camposEditados.clear();
            camposReceptor.forEach(campo => { formResumen.elements[campo].value = ''; });
            mensajeReceptor.textContent = 'Ingresa un RUT completo para buscar los datos del receptor.';
            if (/^\d{7,8}-[\dK]$/.test(rut)) temporizador = setTimeout(buscarReceptor, 600);
        });
    }
</script>
@endsection
