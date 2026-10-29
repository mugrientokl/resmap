@extends('layouts.app')

@section('content')
@php
    $esServicio = $solicitud->tipo_solicitud === 'servicio';
    $ultimaVersion = $solicitud->cotizacion?->versiones->sortByDesc('numero_version')->first();
    $venta = $ultimaVersion?->venta ?? $solicitud->venta;
    $ordenesCompra = $solicitud->cotizacion?->versiones->flatMap->ordenesCompra ?? collect();
@endphp
<div class="mx-auto max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('solicitudes.index') }}" class="text-sm font-bold text-[#9f2f25]">Volver a cotizaciones</a>
        <a href="{{ route('solicitudes.pdf', $solicitud->id_solicitud) }}" class="inline-flex items-center gap-2 bg-[#b52f25] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#8f241d]">
            <span aria-hidden="true">↓</span> Descargar resumen interno
        </a>
    </div>
    @if(session('success'))<div class="mt-5 border-l-4 border-green-600 bg-green-50 p-4 font-semibold text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mt-5 border-l-4 border-amber-500 bg-amber-50 p-4 font-semibold text-amber-900">{{ session('error') }}</div>@endif
    @if(session('warning'))<div class="mt-5 border-l-4 border-amber-500 bg-amber-50 p-4 font-semibold text-amber-900">{{ session('warning') }}</div>@endif
    @if($errors->any())
        <div class="mt-5 border-l-4 border-red-600 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <p class="font-bold">No se pudo completar la acción:</p>
            <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(260px,0.72fr)_minmax(0,1.6fr)]">
        <section class="overflow-hidden bg-white shadow-sm">
            <header class="border-b border-[#ead8d5] bg-[#fffaf9] p-6 md:p-8">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-[#b52f25]">{{ $esServicio ? 'Servicio' : 'Cotización' }} #{{ $solicitud->id_solicitud }}</p>
                <h1 class="mt-2 text-3xl font-black text-[#241817]">{{ $esServicio ? 'Detalle del servicio' : 'Detalle de la cotización' }}</h1>
                <p class="mt-2 text-sm text-gray-500">Recibida el {{ $solicitud->fecha?->format('d/m/Y H:i') }}</p>
                <div class="mt-5 border-l-4 border-amber-500 bg-amber-50 p-4">
                    <p class="font-black text-amber-950">{{ $solicitud->estado_comercial['titulo'] }}</p>
                    <p class="mt-1 text-sm text-amber-900">{{ $solicitud->estado_comercial['detalle'] }}</p>
                </div>
            </header>
            <dl class="space-y-5 p-6 text-sm md:p-8">
                <div><dt class="text-xs font-bold uppercase tracking-wider text-gray-500">Cliente</dt><dd class="mt-1 text-lg font-black">{{ $solicitud->cliente->nombre }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-gray-500">RUT</dt><dd class="mt-1">{{ $solicitud->cliente->rut }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-gray-500">Correo</dt><dd class="mt-1 wrap-break-word">{{ $solicitud->cliente->correo ?: 'No informado' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-gray-500">Teléfono</dt><dd class="mt-1">{{ $solicitud->cliente->telefono ?: 'No informado' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-gray-500">Dirección</dt><dd class="mt-1">{{ $solicitud->cliente->direccion ?: 'No informada' }}</dd></div>
            </dl>
        </section>
        <section class="min-w-0 bg-white p-6 shadow-sm md:p-8">
            @if($esServicio)
                <h2 class="text-xl font-black">{{ $solicitud->tipo_servicio }}</h2>
                <p class="mt-5 whitespace-pre-line border-l-4 border-[#b52f25] bg-[#f7e8e6] p-4 text-gray-700">{{ $solicitud->descripcion_servicio }}</p>
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
                            <select name="estado" class="mt-2 w-full border border-gray-300 p-3">@foreach($transicionesPermitidas as $estadoPermitido)<option value="{{ $estadoPermitido }}">{{ $estadoPermitido }}</option>@endforeach</select>
                        </label>
                        <label class="mt-4 block text-sm font-bold">Observaciones<textarea name="observaciones" rows="3" class="mt-2 w-full border border-gray-300 p-3">{{ $solicitud->observaciones }}</textarea></label>
                        <button class="mt-4 bg-[#b52f25] px-5 py-3 font-bold text-white">Guardar estado</button>
                    </form>
                @endif
            @else
                <div class="flex items-center justify-between gap-4"><h2 class="text-xl font-black">Repuestos cotizados</h2><span class="text-sm text-gray-500">{{ $productos->count() }} {{ $productos->count() === 1 ? 'línea' : 'líneas' }}</span></div>
                <div class="mt-5 overflow-x-auto border border-[#ead8d5]">
                    <div class="grid min-w-190 grid-cols-[minmax(220px,1fr)_80px_130px_130px_130px] bg-[#241817] px-4 py-3 text-xs font-black uppercase tracking-wider text-white"><span>Descripción</span><span class="text-center">Cantidad</span><span class="text-right">Valor neto</span><span class="text-right">Valor c/IVA</span><span class="text-right">Total c/IVA</span></div>
                    @foreach($productos as $item)
                        <div class="grid min-w-190 grid-cols-[minmax(220px,1fr)_80px_130px_130px_130px] items-center border-t border-[#ead8d5] px-4 py-4 text-sm">
                            <div class="flex items-center gap-4">
                                @if($item['producto']?->imagen)<img src="{{ asset('storage/'.$item['producto']->imagen) }}" class="h-14 w-14 object-cover" alt="">@endif
                                <div><p class="font-bold">{{ $item['producto']->nombre ?? 'Producto no encontrado' }}</p><p class="text-sm text-gray-500">Código: {{ $item['producto']->codigo_origen ?? 'Sin código' }}</p></div>
                            </div>
                            <span class="text-center font-bold">{{ $item['cantidad'] }}</span>
                            <span class="text-right">$ {{ number_format($item['precio_neto'], 0, ',', '.') }}</span>
                            <span class="text-right">$ {{ number_format($item['precio_iva'], 0, ',', '.') }}</span>
                            <span class="text-right font-black">$ {{ number_format($item['total_iva'], 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="ml-auto mt-6 max-w-sm space-y-3 text-sm"><div class="flex justify-between"><span class="text-gray-500">Neto</span><strong>$ {{ number_format($totales['neto'], 0, ',', '.') }}</strong></div><div class="flex justify-between"><span class="text-gray-500">IVA 19%</span><strong>$ {{ number_format($totales['iva'], 0, ',', '.') }}</strong></div><div class="flex justify-between border-t-2 border-[#241817] pt-3 text-lg"><span class="font-black">Total</span><strong class="text-[#b52f25]">$ {{ number_format($totales['total'], 0, ',', '.') }}</strong></div></div>

                <section class="mt-8 border-t border-[#ead8d5] pt-6">
                    <h2 class="text-lg font-black">Etapas de cotización y venta</h2>
                    <p class="mt-1 text-sm text-gray-600">Las unidades están reservadas; el stock disponible solo se descuenta cuando la facturación de prueba termina correctamente.</p>
                    @if($solicitud->cotizacion)
                        <p class="mt-4 text-sm">Estado de cotización: <strong>{{ $solicitud->estado_comercial['titulo'] }}</strong></p>
                        @foreach($solicitud->cotizacion->versiones->sortByDesc('numero_version') as $version)
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border p-3 text-sm">
                                <span>Versión {{ $version->numero_version }} · {{ ucfirst(str_replace('_', ' ', $version->estado)) }} · ${{ number_format($version->total, 0, ',', '.') }} · válida hasta {{ $version->vigente_hasta?->format('d/m/Y H:i') ?? 'Sin fecha' }}</span>
                                <div class="flex gap-3">
                                    <a class="font-bold text-[#9f2f25] underline" href="{{ route('cotizaciones.pdf', $version->id_version) }}">Descargar cotización PDF</a>
                                    @if($version->estado === 'borrador')
                                        <form method="POST" action="{{ route('cotizaciones.enviar', $version->id_version) }}">@csrf<button class="font-bold text-[#9f2f25] underline">Enviar al cliente</button></form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p class="mt-3 text-sm text-gray-600">No se ha creado una versión de cotización.</p>
                    @endif
                    @if(! $solicitud->cotizacion || in_array($solicitud->cotizacion->estado, ['pendiente_revision', 'cambios_solicitados'], true))
                        <form method="POST" action="{{ route('solicitudes.cotizacion.crear', $solicitud->id_solicitud) }}" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf
                            @if($solicitud->metodo_entrega === 'despacho')
                                <label class="text-sm font-bold">Costo despacho
                                    <input type="number" name="costo_despacho" min="0" step="1" value="{{ config('services.cotizaciones.costo_despacho', 0) }}" class="mt-1 block border p-2">
                                </label>
                            @endif
                            <button class="bg-[#b52f25] px-4 py-2 font-bold text-white">Crear cotización</button>
                        </form>
                    @endif

                    @if($ordenesCompra->isNotEmpty())
                        <div class="mt-6 border-t pt-5">
                            <h3 class="font-black">Orden(es) de compra recibida(s)</h3>
                            @foreach($ordenesCompra as $ordenCompra)
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded border bg-gray-50 p-3 text-sm">
                                    <span>OC {{ $ordenCompra->numero ?: 'sin número' }} · {{ $ordenCompra->modalidad === 'cliente' ? 'Enviada por el cliente' : 'Autorizada para esta cotización' }}</span>
                                    @if($ordenCompra->archivo_path)
                                        <div class="flex flex-wrap gap-3">
                                            <a href="{{ route('cotizaciones.orden-compra.ver', $ordenCompra->id_orden_compra) }}" target="_blank" rel="noopener" class="font-bold text-[#9f2f25] underline">Ver OC</a>
                                            <a href="{{ route('cotizaciones.orden-compra', $ordenCompra->id_orden_compra) }}" class="font-bold text-[#9f2f25] underline">Descargar</a>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($venta)
                        <div class="mt-6 border-t pt-5">
                            <h3 class="text-lg font-black">Venta #{{ $venta->id_venta }}</h3>
                            @php
                                $totalVenta = (float) $venta->total;
                                $montoPagado = (float) ($venta->monto_pagado ?? 0);
                                $saldoCredito = max(0, $totalVenta - $montoPagado);
                                $estadoPagoLegible = match ($venta->estado_pago) {
                                    'credito_autorizado' => 'Crédito autorizado',
                                    'credito_parcial' => 'Crédito con abonos',
                                    'pagado' => 'Pagado',
                                    default => 'Pendiente',
                                };
                            @endphp
                            <p class="mt-1 text-sm text-gray-700">Pago: {{ $estadoPagoLegible }} · Facturación: {{ str_replace('_', ' ', $venta->estado_facturacion ?: 'pendiente') }} · Entrega: {{ str_replace('_', ' ', $venta->estado_entrega ?: 'pendiente') }}</p>
                            <div class="mt-3 rounded-lg border border-[#ead8d5] bg-white p-4 text-sm">
                                <p><strong>Modalidad:</strong> {{ $venta->metodo_entrega === 'despacho' ? 'Delivery a domicilio' : 'Retiro en tienda' }}</p>
                                @if($venta->metodo_entrega === 'despacho')
                                    <p class="mt-1"><strong>Dirección:</strong> {{ $venta->direccion_despacho }}, {{ $venta->comuna_despacho }}, {{ $venta->ciudad_despacho }}, {{ $venta->region_despacho }}</p>
                                    @if($venta->referencias_despacho)
                                        <p class="mt-1"><strong>Referencias:</strong> {{ $venta->referencias_despacho }}</p>
                                    @endif
                                @endif
                            </div>
                            @if(in_array($venta->estado_pago, ['credito_autorizado', 'credito_parcial', 'pagado'], true))
                                <div class="mt-3 rounded-lg border border-[#ead8d5] bg-[#fffaf9] p-4 text-sm">
                                    <p><strong>Pagado:</strong> $ {{ number_format($montoPagado, 0, ',', '.') }} · <strong>Saldo:</strong> $ {{ number_format($saldoCredito, 0, ',', '.') }}</p>
                                    @if($venta->creditoAutorizadoPor)
                                        <p class="mt-1 text-gray-600">Crédito autorizado por {{ $venta->creditoAutorizadoPor->name }} el {{ $venta->credito_autorizado_at?->format('d/m/Y H:i') }}.</p>
                                    @endif
                                </div>
                            @endif
                            @if($venta->estado_venta === 'pendiente_confirmacion' && $ordenesCompra->isNotEmpty())
                                <div class="mt-4 flex flex-wrap gap-4">
                                    <form method="POST" action="{{ route('ventas.confirmar-comercial', $venta->id_venta) }}" class="flex flex-wrap items-end gap-2 rounded bg-gray-50 p-4">
                                        @csrf
                                        <input type="hidden" name="tipo_confirmacion" value="pago">
                                        <label class="text-sm font-bold">Pago recibido
                                            <input type="number" name="monto_pagado" min="{{ $venta->total }}" step="1" value="{{ $venta->total }}" required class="mt-1 block w-40 border p-2">
                                        </label>
                                        <label class="text-sm font-bold">Medio
                                            <select name="medio_pago" required class="mt-1 block border p-2"><option value="Transferencia">Transferencia</option><option value="Efectivo">Efectivo</option><option value="Tarjeta">Tarjeta</option></select>
                                        </label>
                                        <button class="bg-[#b52f25] px-4 py-2 font-bold text-white">Confirmar pago</button>
                                    </form>
                                    @if(auth()->user()->rol === 'Administrador')
                                        <form method="POST" action="{{ route('ventas.confirmar-comercial', $venta->id_venta) }}" class="self-end rounded bg-gray-50 p-4">
                                            @csrf
                                            <input type="hidden" name="tipo_confirmacion" value="credito">
                                            <button class="border border-[#b52f25] px-4 py-2 font-bold text-[#9f2f25]">Autorizar crédito</button>
                                        </form>
                                    @endif
                                </div>
                            @elseif($venta->estado_venta === 'pendiente_confirmacion')
                                <p class="mt-3 rounded bg-amber-50 p-3 text-sm text-amber-900">No se puede confirmar la venta hasta recibir o autorizar la orden de compra del cliente.</p>
                            @endif
                            @if(in_array($venta->estado_pago, ['credito_autorizado', 'credito_parcial'], true) && $saldoCredito > 0)
                                <form method="POST" action="{{ route('ventas.registrar-pago-credito', $venta->id_venta) }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border border-[#ead8d5] bg-white p-4">
                                    @csrf
                                    <div class="w-full">
                                        <p class="font-black">Registrar abono al crédito</p>
                                        <p class="mt-1 text-sm text-gray-600">Saldo pendiente: $ {{ number_format($saldoCredito, 0, ',', '.') }}</p>
                                    </div>
                                    <label class="text-sm font-bold">Monto recibido
                                        <input type="number" name="monto_pagado" min="0.01" max="{{ $saldoCredito }}" step="0.01" required class="mt-1 block w-44 border p-2">
                                    </label>
                                    <label class="text-sm font-bold">Medio de pago
                                        <select name="medio_pago" required class="mt-1 block border p-2"><option value="Transferencia">Transferencia</option><option value="Efectivo">Efectivo</option><option value="Tarjeta">Tarjeta</option></select>
                                    </label>
                                    <button class="bg-[#b52f25] px-4 py-2 font-bold text-white">Guardar abono</button>
                                </form>
                            @endif
                            @if($venta->estado_venta === 'confirmada' && $venta->estado_facturacion !== 'simulada_exitosa' && auth()->user()->rol === 'Administrador' && ! app()->isProduction())
                                <div class="mt-4 rounded border border-amber-300 bg-amber-50 p-4">
                                    <p class="font-bold text-amber-950">Facturación de prueba</p>
                                    <p class="mt-1 text-sm text-amber-900">Esta simulación no emite un DTE real. Si resulta exitosa, registra la factura simulada y descuenta el stock.</p>
                                    <form method="POST" action="{{ route('ventas.simular-facturacion', $venta->id_venta) }}" class="mt-3 flex flex-wrap items-end gap-3">
                                        @csrf
                                        <label class="text-sm font-bold">Resultado
                                            <select name="resultado" required class="mt-1 block border p-2"><option value="exito">Simular éxito</option><option value="error">Simular error</option></select>
                                        </label>
                                        <button class="bg-[#b52f25] px-4 py-2 font-bold text-white">Ejecutar simulación</button>
                                    </form>
                                </div>
                            @endif
                            @if($venta->estado_facturacion === 'simulada_exitosa' && $venta->estado_entrega !== 'entregada')
                                <form method="POST" action="{{ route('ventas.registrar-entrega', $venta->id_venta) }}" class="mt-4" data-confirm-message="¿Confirmas que la venta fue despachada o retirada por el cliente?">
                                    @csrf
                                    <button class="bg-[#b52f25] px-4 py-2 font-bold text-white">Registrar despacho o retiro</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </section>
            @endif
        </section>
    </div>
</div>
@endsection
