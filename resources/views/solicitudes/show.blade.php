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
        <a href="{{ route('solicitudes.index') }}" class="text-sm font-bold text-[#9f2f25]">Volver a cotizaciones</a>
        @unless($esServicio)
            <a href="{{ route('solicitudes.seguimiento', $solicitud->id_solicitud) }}" class="border border-[#b52f25] px-5 py-3 text-sm font-bold text-[#b52f25]">Ver seguimiento completo</a>
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
                        'Pendiente' => ['Pagado - pendiente de entrega', 'Entregado - pago pendiente', 'Entregado y completado', 'Rechazado'],
                        'Pagado - pendiente de entrega' => ['Entregado y completado'],
                        'Entregado - pago pendiente' => ['Entregado y completado'],
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
                        <button class="mt-4 bg-[#b52f25] px-5 py-3 font-bold text-white">Guardar estado</button>
                    </form>
                @endif
                @if($solicitud->estado === 'Entregado y completado')
                    <a href="{{ route('solicitudes.pdf', $solicitud->id_solicitud) }}" class="mt-4 inline-block font-bold text-[#9f2f25] underline">Descargar resumen interno</a>
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
                                <a class="font-bold text-[#9f2f25] underline" href="{{ route('cotizaciones.pdf', $version->id_version) }}">Descargar cotización PDF</a>
                                @if($version->estado === 'borrador')
                                    <form method="POST" action="{{ route('cotizaciones.enviar', $version->id_version) }}" data-confirm-message="¿Enviar esta cotización al cliente?">@csrf<button class="font-bold text-[#9f2f25] underline">Enviar al cliente</button></form>
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
                                    <button type="button" data-quitar class="text-sm font-bold text-[#9f2f25]">Quitar</button>
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
                                <button type="button" data-quitar class="text-sm font-bold text-[#9f2f25]">Quitar</button>
                            </div>
                        </template>
                        <button type="button" id="agregar-item" class="block font-bold text-[#9f2f25]">+ Agregar repuesto</button>
                        @if($solicitud->metodo_entrega === 'despacho')
                            <label class="block text-sm font-bold">Costo despacho
                                <input type="number" name="costo_despacho" min="0" step="1" value="{{ $ultimaVersion?->costo_despacho ?? config('services.cotizaciones.costo_despacho', 0) }}" class="mt-1 block border p-2">
                            </label>
                        @endif
                        <button class="bg-[#b52f25] px-4 py-2 font-bold text-white">{{ $ultimaVersion ? 'Crear nueva versión' : 'Crear cotización' }}</button>
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
                        <button class="bg-[#b52f25] px-4 py-2 font-bold text-white">Registrar respuesta</button>
                    </form>
                @endif
                @if($ultimaVersion?->estado === 'aceptada' && ! $ventaCompleta)
                    <form method="POST" action="{{ route('cotizaciones.convertir-venta', $ultimaVersion->id_version) }}" class="mt-6 border-t pt-5" data-confirm-message="¿Confirmas que se registre la venta y se descuente el stock?">
                        @csrf
                        <p class="mb-3 text-sm">Puedes convertir la cotización aceptada en venta sin adjuntar una orden de compra.</p>
                        <button class="bg-[#b52f25] px-4 py-2 font-bold text-white">Convertir en venta y descontar stock</button>
                    </form>
                @endif

                @if($ordenesCompra->isNotEmpty())
                    <div class="mt-6 border-t pt-5">
                        <h3 class="font-black">Órdenes de compra anteriores</h3>
                        @foreach($ordenesCompra as $ordenCompra)
                            <p class="mt-2 text-sm">OC {{ $ordenCompra->numero ?: 'sin número' }}
                                @if($ordenCompra->archivo_path)
                                    · <a href="{{ route('cotizaciones.orden-compra.ver', $ordenCompra->id_orden_compra) }}" class="font-bold text-[#9f2f25] underline">Ver</a>
                                @endif
                            </p>
                        @endforeach
                    </div>
                @endif
                @if($ventaCompleta)
                    <form method="POST" action="{{ route('ventas.generar-resumen', $venta->id_venta) }}" class="mt-6 space-y-3 border-t pt-5">
                        @csrf
                        <h3 class="text-lg font-black">Generar resumen interno · Venta #{{ $venta->id_venta }}</h3>
                        <p class="text-sm text-amber-800">Este documento no es una factura ni un DTE tributario.</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach([
                                'nombre' => ['Razón social o nombre', $cliente->razon_social ?: $cliente->nombre],
                                'rut' => ['RUT', $cliente->rut_formateado],
                                'giro' => ['Giro', $cliente->giro],
                                'direccion' => ['Dirección', $cliente->direccion],
                                'comuna' => ['Comuna', $cliente->comuna],
                                'correo' => ['Correo', $cliente->correo],
                                'contacto' => ['Contacto', $solicitud->contacto ?: $cliente->contacto],
                                'numero_oc' => ['Número real de OC', ''],
                            ] as $campo => [$etiqueta, $valor])
                                <label class="text-sm font-bold">{{ $etiqueta }}
                                    <input name="{{ $campo }}" type="{{ $campo === 'correo' ? 'email' : 'text' }}" value="{{ old($campo, $datosResumen[$campo] ?? $valor) }}" required maxlength="255" class="mt-1 block w-full border p-2">
                                </label>
                            @endforeach
                        </div>
                        <button class="bg-[#b52f25] px-4 py-2 font-bold text-white">Generar resumen interno</button>
                        <a href="{{ route('solicitudes.pdf', $solicitud->id_solicitud) }}" class="ml-3 font-bold text-[#9f2f25] underline">Descargar resumen</a>
                    </form>
                @endif
            @endif
        </section>
    </div>
</div>
@unless($esServicio)
<script>
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
</script>
@endunless
@endsection
