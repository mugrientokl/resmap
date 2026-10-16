@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl">
    <div class="mb-8"><p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Atención comercial</p><h1 class="mt-1 text-3xl font-black text-gray-900">Solicitudes web</h1><p class="mt-2 text-gray-500">Pedidos y servicios recibidos desde el sitio público.</p></div>
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm"><div><label class="block text-xs font-bold text-gray-600">Estado</label><select name="estado" class="mt-1 rounded-md border p-2"><option value="">Todos</option>@foreach(['Pendiente','Pagado - pendiente de entrega','Entregado - pago pendiente','Entregado y completado','Rechazado'] as $estado)<option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ $estado }}</option>@endforeach</select></div><div><label class="block text-xs font-bold text-gray-600">Desde</label><input type="date" name="desde" value="{{ request('desde') }}" class="mt-1 rounded-md border p-2"></div><div><label class="block text-xs font-bold text-gray-600">Hasta</label><input type="date" name="hasta" value="{{ request('hasta') }}" class="mt-1 rounded-md border p-2"></div><button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Filtrar</button></form>
    <div class="space-y-4">
        @forelse($solicitudes as $solicitud)
            @php($estadoPendiente = in_array($solicitud->estado, ['Pendiente', 'Pagado - pendiente de entrega', 'Entregado - pago pendiente'], true))
            <article class="border-l-4 {{ $estadoPendiente ? 'border-amber-500 bg-amber-50' : 'border-green-500 bg-white' }} p-6 shadow-sm"><div class="flex flex-col justify-between gap-4 md:flex-row md:items-start"><div><div class="flex items-center gap-3"><h2 class="text-lg font-black">Solicitud #{{ $solicitud->id_solicitud }}</h2><span class="rounded-full {{ $estadoPendiente ? 'bg-amber-200 text-amber-900' : 'bg-green-100 text-green-800' }} px-3 py-1 text-xs font-bold">{{ $solicitud->estado }}</span></div><p class="mt-2 text-sm text-gray-600">{{ $solicitud->cliente->nombre }} · {{ $solicitud->cliente->rut }} · {{ $solicitud->cliente->correo ?: 'Sin correo' }}</p><p class="mt-1 text-xs text-gray-500">{{ $solicitud->fecha->format('d/m/Y H:i') }}</p></div><a href="{{ route('solicitudes.show', $solicitud->id_solicitud) }}" class="rounded-md bg-[#b52f25] px-4 py-2 text-sm font-bold text-white">Ver detalle</a></div><div class="mt-4 border-t border-current/10 pt-4 text-sm {{ $estadoPendiente ? 'text-amber-900' : 'text-gray-600' }}">{{ $solicitud->tipo_solicitud === 'servicio' ? 'Servicio de '.$solicitud->tipo_servicio : count($solicitud->detalles_productos).' producto(s) solicitado(s)' }}</div></article>
        @empty
            <div class="bg-white p-8 text-center text-gray-500">No hay solicitudes recibidas.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $solicitudes->links() }}</div>
</div>
@endsection