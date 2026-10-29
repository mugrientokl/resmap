@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl">
    <div class="mb-8">
        <p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Atención comercial</p>
        <h1 class="mt-1 text-3xl font-black text-gray-900">Cotizaciones web</h1>
        <p class="mt-2 text-gray-500">Gestiona las cotizaciones de repuestos y consulta las solicitudes de servicio recibidas desde el sitio.</p>
    </div>
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
        <div>
            <label for="estado" class="block text-xs font-bold text-gray-600">Etapa comercial</label>
            <select id="estado" name="estado" class="mt-1 rounded-md border p-2">
                <option value="">Todas</option>
                @foreach([
                    'pendiente_comercial' => 'Pendiente del equipo comercial',
                    'esperando_cliente' => 'Esperando al cliente',
                    'cambios_solicitados' => 'Cambios solicitados',
                    'aceptada' => 'Cotización aceptada',
                    'pendiente_equipo' => 'Pendiente del equipo comercial',
                    'pendiente_facturacion' => 'Pendiente de facturación',
                    'pendiente_entrega' => 'Pendiente de entrega',
                    'completada' => 'Venta completada',
                    'cerrada' => 'Proceso cerrado',
                ] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="desde" class="block text-xs font-bold text-gray-600">Desde</label><input id="desde" type="date" name="desde" value="{{ request('desde') }}" class="mt-1 rounded-md border p-2"></div>
        <div><label for="hasta" class="block text-xs font-bold text-gray-600">Hasta</label><input id="hasta" type="date" name="hasta" value="{{ request('hasta') }}" class="mt-1 rounded-md border p-2"></div>
        <button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Filtrar</button>
    </form>
    <div class="space-y-4">
        @forelse($solicitudes as $solicitud)
            @php($esPendiente = ! in_array($solicitud->estado_comercial['estado'], ['cerrada', 'completada', 'servicio'], true))
            <article class="border-l-4 {{ $esPendiente ? 'border-amber-500 bg-amber-50' : 'border-green-500 bg-white' }} p-6 shadow-sm">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-lg font-black">
                                {{ $solicitud->tipo_solicitud === 'servicio' ? 'Servicio' : 'Cotización' }} #{{ $solicitud->id_solicitud }}
                            </h2>
                            <span class="rounded-full {{ $esPendiente ? 'bg-amber-200 text-amber-900' : 'bg-green-100 text-green-800' }} px-3 py-1 text-xs font-bold">
                                {{ $solicitud->estado_comercial['titulo'] }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-gray-600">{{ $solicitud->cliente->nombre }} · {{ $solicitud->cliente->rut }} · {{ $solicitud->cliente->correo ?: 'Sin correo' }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $solicitud->fecha->format('d/m/Y H:i') }}</p>
                    </div>
                    <a href="{{ route('solicitudes.show', $solicitud->id_solicitud) }}" class="rounded-md bg-[#b52f25] px-4 py-2 text-center text-sm font-bold text-white">Abrir detalle</a>
                </div>
                <p class="mt-4 border-t border-current/10 pt-4 text-sm {{ $esPendiente ? 'text-amber-900' : 'text-gray-600' }}">{{ $solicitud->estado_comercial['detalle'] }}</p>
                <p class="mt-2 text-xs text-gray-600">
                    {{ $solicitud->tipo_solicitud === 'servicio' ? 'Servicio de '.$solicitud->tipo_servicio : count($solicitud->detalles_productos).' producto(s) solicitados' }}
                </p>
            </article>
        @empty
            <div class="bg-white p-8 text-center text-gray-500">No hay cotizaciones o servicios recibidos para estos filtros.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $solicitudes->links() }}</div>
</div>
@endsection
