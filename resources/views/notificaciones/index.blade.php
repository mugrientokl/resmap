@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="mb-8"><p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Centro de avisos</p><h1 class="mt-1 text-3xl font-black text-gray-900">Notificaciones operativas</h1><p class="mt-2 text-gray-500">Cotizaciones, servicios nuevos y productos que necesitan reposición.</p></div>
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('notificaciones.index') }}" @if(!$papelera) aria-current="page" @endif class="rounded px-4 py-2 text-sm font-bold {{ !$papelera ? 'bg-[#b52f25] text-white' : 'bg-white text-[#9f2f25]' }}">Bandeja de entrada</a>
        <a href="{{ route('notificaciones.trash') }}" @if($papelera) aria-current="page" @endif class="rounded px-4 py-2 text-sm font-bold {{ $papelera ? 'bg-[#b52f25] text-white' : 'bg-white text-[#9f2f25]' }}">Papelera</a>
        @if(!$papelera)
            <form method="POST" action="{{ route('notificaciones.read-all') }}">
                @csrf
                <button type="submit" class="rounded bg-white px-4 py-2 text-sm font-bold text-[#9f2f25] hover:bg-[#f7e8e6]">Marcar todo como leído</button>
            </form>
            <form method="POST" action="{{ route('notificaciones.move-read-to-trash') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded border border-red-300 bg-red-50 px-4 py-2 text-sm font-bold text-red-800 hover:bg-red-100">
                    <svg aria-hidden="true" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M10 11v6m4-6v6M5 7l1 13h12l1-13M9 7V4h6v3" /></svg>
                    Mover leídos a la papelera
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('notificaciones.restore-all') }}">
                @csrf
                <button type="submit" class="rounded bg-white px-4 py-2 text-sm font-bold text-[#9f2f25] hover:bg-[#f7e8e6]">Restaurar todos</button>
            </form>
        @endif
    </div>
    <div class="space-y-3">
        @forelse($notificaciones as $notificacion)
            @php($data = $notificacion->data)
            <div class="flex flex-wrap items-start justify-between gap-4 border-l-4 {{ $notificacion->read_at ? 'border-amber-400 bg-gray-100' : 'border-[#b52f25] bg-amber-50' }} p-5 shadow-sm">
                <div><span class="inline-block rounded-full px-3 py-1 text-sm font-bold {{ $notificacion->read_at ? 'bg-amber-100 text-amber-900' : 'bg-red-100 text-red-800' }}">{{ $notificacion->read_at ? 'Leído' : 'No leído' }}</span><h2 class="mt-1 font-bold text-gray-900">{{ $data['titulo'] }}</h2><p class="mt-1 text-sm text-gray-600">{{ $data['mensaje'] }}</p><p class="mt-2 text-xs text-gray-500">{{ $notificacion->created_at->diffForHumans() }}</p>@if(!$papelera)<a href="{{ $data['url'] ?? route('notificaciones.index') }}" class="mt-3 inline-block text-sm font-bold text-[#9f2f25] hover:text-[#721d18]">{{ $data['tipo'] === 'stock_critico' ? 'Ver inventario →' : (($data['tipo_solicitud'] ?? null) === 'servicio' ? 'Ver detalle del servicio →' : 'Ver cotización →') }}</a>@endif</div>
                <div class="flex shrink-0 flex-col items-end gap-3">
                    @if($papelera)
                        <form method="POST" action="{{ route('notificaciones.restore', $notificacion->id) }}">@csrf<button type="submit" class="text-sm font-semibold text-[#9f2f25] hover:text-[#721d18]">Restaurar</button></form>
                        <form method="POST" action="{{ route('notificaciones.destroy', $notificacion->id) }}" data-confirm-message="¿Eliminar este aviso definitivamente? Esta acción no se puede deshacer." data-confirm-title="Eliminar aviso" data-confirm-text="Eliminar definitivamente">@csrf @method('DELETE')<button type="submit" class="text-sm font-semibold text-red-700 hover:text-red-900">Eliminar definitivamente</button></form>
                    @else
                        @if(!$notificacion->read_at)<form method="POST" action="{{ route('notificaciones.read', $notificacion->id) }}">@csrf<button type="submit" class="text-sm font-semibold text-[#9f2f25] hover:text-[#721d18]">Marcar leído</button></form>@endif
                        <form method="POST" action="{{ route('notificaciones.move-to-trash', $notificacion->id) }}">@csrf<button type="submit" class="text-sm font-semibold text-gray-600 hover:text-red-700">Mover a la papelera</button></form>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white p-8 text-center text-gray-500">{{ $papelera ? 'La papelera está vacía.' : 'No hay avisos pendientes.' }}</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $notificaciones->links() }}</div>
</div>
@endsection