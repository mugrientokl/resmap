@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <a href="{{ route('solicitudes.index') }}" class="text-sm font-bold text-[#9f2f25]">Volver a solicitudes</a>
        <a href="{{ route('solicitudes.pdf', $solicitud->id_solicitud) }}" class="inline-flex items-center gap-2 bg-[#b52f25] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#8f241d]">
            <span aria-hidden="true">↓</span> Descargar resumen de solicitud
        </a>
    </div>
    @if(session('success'))<div class="mt-5 border-l-4 border-green-600 bg-green-50 p-4 font-semibold text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mt-5 border-l-4 border-amber-500 bg-amber-50 p-4 font-semibold text-amber-900">{{ session('error') }}</div>@endif
    <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(260px,0.72fr)_minmax(0,1.6fr)]">
        <section class="overflow-hidden bg-white shadow-sm">
            <header class="border-b border-[#ead8d5] bg-[#fffaf9] p-6 md:p-8">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-[#b52f25]">Resumen de solicitud · Solicitud #{{ $solicitud->id_solicitud }}</p>
                    <h1 class="mt-2 text-3xl font-black text-[#241817]">{{ $solicitud->tipo_solicitud === 'servicio' ? 'Detalle del servicio' : 'Detalle del pedido' }}</h1>
                    <p class="mt-2 text-sm text-gray-500">Emitida el {{ $solicitud->fecha?->format('d/m/Y H:i') }}</p>
                </div>
                <span class="border border-[#e4b9b3] bg-white px-3 py-2 text-xs font-black uppercase tracking-wider text-[#9f2f25]">{{ $solicitud->estado }}</span>
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
            @if($solicitud->tipo_solicitud === 'servicio')
                <h2 class="text-xl font-black">{{ $solicitud->tipo_servicio }}</h2>
                <p class="mt-5 whitespace-pre-line border-l-4 border-[#b52f25] bg-[#f7e8e6] p-4 text-gray-700">{{ $solicitud->descripcion_servicio }}</p>
            @else
                <div class="flex items-center justify-between gap-4"><h2 class="text-xl font-black">Repuestos solicitados</h2><span class="text-sm text-gray-500">{{ $productos->count() }} {{ $productos->count() === 1 ? 'línea' : 'líneas' }}</span></div>
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
            @endif
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
            <form method="POST" action="{{ route('solicitudes.estado', $solicitud->id_solicitud) }}" class="mt-6 border-t pt-6">
                @csrf @method('PATCH')
                <label class="block text-sm font-bold">Cambio permitido<select name="estado" class="mt-2 w-full border border-gray-300 p-3">@foreach($transicionesPermitidas as $estadoPermitido)<option value="{{ $estadoPermitido }}">{{ $estadoPermitido }}</option>@endforeach</select></label>
                <label class="mt-4 block text-sm font-bold">Observaciones<textarea name="observaciones" rows="3" class="mt-2 w-full border border-gray-300 p-3">{{ $solicitud->observaciones }}</textarea></label>
                <button class="mt-4 bg-[#b52f25] px-5 py-3 font-bold text-white" onclick="return confirmarCambioEstado(this.form)">Guardar estado</button>
            </form>
            @else
                <p class="mt-6 border-t pt-6 text-sm font-bold text-gray-600">Esta solicitud está cerrada y no admite más cambios.</p>
            @endif
        </section>
    </div>
</div>
<script>
    function confirmarCambioEstado(formulario) {
        const estado = formulario.querySelector('select[name="estado"]');
        if (!estado) return false;
        const mensaje = estado.value === 'Rechazado'
            ? 'Rechazar esta solicitud la cancelará definitivamente y no podrá revertirse. ¿Deseas continuar?'
            : `¿Confirmas cambiar la solicitud a "${estado.value}"?`;
        return window.confirm(mensaje);
    }
</script>
@endsection
