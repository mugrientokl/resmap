@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="mb-8">
        <p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Centro de avisos</p>
        <h1 class="mt-1 text-3xl font-black text-gray-900">Notificaciones operativas</h1>
        <p class="mt-2 text-gray-500">Los avisos se clasifican como leídos o no leídos y se eliminan automáticamente después de 180 días.</p>
    </div>
    <div class="mb-5 flex flex-wrap justify-end gap-2 rounded-xl border border-[#ead8d5] bg-white p-3 shadow-sm">
        <form method="POST" action="{{ route('notificaciones.read-all') }}">
            @csrf
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-[#e8d1cd] bg-white px-4 py-2 text-sm font-bold text-[#8f241d] hover:bg-[#fff7f5]">
                Marcar todo como leído
            </button>
        </form>
    </div>
    <div class="space-y-3">
        @forelse($notificaciones as $notificacion)
            @php($data = $notificacion->data)
            <div class="grid grid-cols-1 items-start gap-4 border-l-4 {{ $notificacion->read_at ? 'border-amber-400 bg-gray-100' : 'border-[#b52f25] bg-amber-50' }} p-5 shadow-sm sm:grid-cols-[minmax(0,1fr)_auto]">
                <div class="min-w-0 break-words">
                    <span class="inline-block rounded-full px-3 py-1 text-sm font-bold {{ $notificacion->read_at ? 'bg-amber-100 text-amber-900' : 'bg-red-100 text-red-800' }}">{{ $notificacion->read_at ? 'Leído' : 'No leído' }}</span>
                    <h2 class="mt-1 font-bold text-gray-900">{{ $data['titulo'] }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ $data['mensaje'] }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ $notificacion->created_at->diffForHumans() }}</p>
                    <a href="{{ $data['url'] ?? route('notificaciones.index') }}" class="mt-3 inline-block text-sm font-bold text-[#9f2f25] hover:text-[#721d18]">{{ $data['tipo'] === 'stock_critico' ? 'Ver inventario →' : (($data['tipo_solicitud'] ?? null) === 'servicio' ? 'Ver detalle del servicio →' : 'Ver cotización →') }}</a>
                </div>
                @if(!$notificacion->read_at)
                    <form method="POST" action="{{ route('notificaciones.read', $notificacion->id) }}">
                        @csrf
                        <button type="submit" class="text-sm font-semibold text-[#9f2f25] hover:text-[#721d18]">Marcar leído</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="bg-white p-8 text-center text-gray-500">No hay avisos pendientes.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $notificaciones->links() }}</div>
</div>
@endsection
