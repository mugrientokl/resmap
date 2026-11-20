@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="mb-8"><p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Centro de avisos</p><h1 class="mt-1 text-3xl font-black text-gray-900">Notificaciones operativas</h1><p class="mt-2 text-gray-500">Cotizaciones, servicios nuevos y productos que necesitan reposición.</p></div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-[#ead8d5] bg-white p-3 shadow-sm">
        <nav aria-label="Bandejas de avisos" class="flex flex-wrap gap-1 rounded-lg bg-[#f7eeec] p-1">
            <a href="{{ route('notificaciones.index') }}" @if(!$papelera) aria-current="page" @endif class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b52f25] {{ !$papelera ? 'bg-[#b52f25] text-white shadow-sm hover:bg-[#8f241d]' : 'text-[#7f3932] hover:bg-white hover:text-[#8f241d]' }}">
                <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 13.5h5l2 3h4l2-3h5M5 5h14l2 8.5v5.5H3v-5.5L5 5Z" /></svg>
                Bandeja de entrada
            </a>
            <a href="{{ route('notificaciones.trash') }}" @if($papelera) aria-current="page" @endif class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b52f25] {{ $papelera ? 'bg-[#b52f25] text-white shadow-sm hover:bg-[#8f241d]' : 'text-[#7f3932] hover:bg-white hover:text-[#8f241d]' }}">
                <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M10 11v6m4-6v6M5 7l1 13h12l1-13M9 7V4h6v3" /></svg>
                Papelera
            </a>
        </nav>
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
            @if(!$papelera)
                <form method="POST" action="{{ route('notificaciones.read-all') }}" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-[#e8d1cd] bg-white px-4 py-2 text-sm font-bold text-[#8f241d] transition hover:border-[#c98479] hover:bg-[#fff7f5] hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b52f25] active:bg-[#f7e8e6]">
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 12 4 4L17 6m-5 10 2 2L22 9" /></svg>
                        Marcar todo como leído
                    </button>
                </form>
                <form method="POST" action="{{ route('notificaciones.move-read-to-trash') }}" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-bold text-red-800 transition hover:border-red-400 hover:bg-red-100 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 active:bg-red-200">
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M10 11v6m4-6v6M5 7l1 13h12l1-13M9 7V4h6v3" /></svg>
                        Mover leídos a la papelera
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('notificaciones.restore-all') }}" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-[#e8d1cd] bg-white px-4 py-2 text-sm font-bold text-[#8f241d] transition hover:border-[#c98479] hover:bg-[#fff7f5] hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b52f25] active:bg-[#f7e8e6]">
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5m4-1v5l3 2" /></svg>
                        Restaurar todos
                    </button>
                </form>
                <form method="POST" action="{{ route('notificaciones.destroy-all') }}" class="w-full sm:w-auto" data-confirm-message="¿Eliminar definitivamente todos los avisos de la papelera? Esta acción no se puede deshacer." data-confirm-title="Vaciar papelera" data-confirm-text="Eliminar todos">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-bold text-red-800 transition hover:border-red-400 hover:bg-red-100 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 active:bg-red-200">
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M10 11v6m4-6v6M5 7l1 13h12l1-13M9 7V4h6v3" /></svg>
                        Eliminar todos definitivamente
                    </button>
                </form>
            @endif
        </div>
    </div>
    <div class="space-y-3">
        @forelse($notificaciones as $notificacion)
            @php($data = $notificacion->data)
            <div class="grid grid-cols-1 items-start gap-4 border-l-4 {{ $notificacion->read_at ? 'border-amber-400 bg-gray-100' : 'border-[#b52f25] bg-amber-50' }} p-5 shadow-sm sm:grid-cols-[minmax(0,1fr)_auto]">
                <div class="min-w-0 break-words"><span class="inline-block rounded-full px-3 py-1 text-sm font-bold {{ $notificacion->read_at ? 'bg-amber-100 text-amber-900' : 'bg-red-100 text-red-800' }}">{{ $notificacion->read_at ? 'Leído' : 'No leído' }}</span><h2 class="mt-1 font-bold text-gray-900">{{ $data['titulo'] }}</h2><p class="mt-1 text-sm text-gray-600">{{ $data['mensaje'] }}</p><p class="mt-2 text-xs text-gray-500">{{ $notificacion->created_at->diffForHumans() }}</p>@if(!$papelera)<a href="{{ $data['url'] ?? route('notificaciones.index') }}" class="mt-3 inline-block text-sm font-bold text-[#9f2f25] hover:text-[#721d18]">{{ $data['tipo'] === 'stock_critico' ? 'Ver inventario →' : (($data['tipo_solicitud'] ?? null) === 'servicio' ? 'Ver detalle del servicio →' : 'Ver cotización →') }}</a>@endif</div>
                <div class="flex flex-col items-end gap-3">
                    @if($papelera)
                        <form method="POST" action="{{ route('notificaciones.restore', $notificacion->id) }}" class="w-full">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-[#e8d1cd] bg-white px-4 py-2 text-sm font-bold text-[#8f241d] transition hover:border-[#c98479] hover:bg-[#fff7f5] hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b52f25] active:bg-[#f7e8e6]">
                                <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5m4-1v5l3 2" /></svg>
                                Restaurar
                            </button>
                        </form>
                        <form method="POST" action="{{ route('notificaciones.destroy', $notificacion->id) }}" class="w-full" data-confirm-message="¿Eliminar este aviso definitivamente? Esta acción no se puede deshacer." data-confirm-title="Eliminar aviso" data-confirm-text="Eliminar definitivamente">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-bold text-red-800 transition hover:border-red-400 hover:bg-red-100 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 active:bg-red-200">
                                <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M10 11v6m4-6v6M5 7l1 13h12l1-13M9 7V4h6v3" /></svg>
                                Eliminar definitivamente
                            </button>
                        </form>
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