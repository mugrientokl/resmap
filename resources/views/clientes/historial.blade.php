@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="rounded-lg bg-white p-6 shadow-md">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">
                    Historial del cliente
                </h2>

                <p class="mt-1 text-lg font-semibold text-[#9f2f25]">
                    {{ $cliente->nombre }}
                </p>

                <p class="text-sm text-gray-500">
                    RUT: {{ $cliente->rut }}
                </p>
            </div>

            <a
                href="{{ route('clientes.index') }}"
                class="rounded-md border border-[#d9aaa3] px-4 py-2 text-[#9f2f25] hover:bg-[#f7e8e6]"
            >
                Volver a clientes
            </a>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
            <div>
                <span class="font-semibold">Correo:</span>
                {{ $cliente->correo ?: 'Sin registro' }}
            </div>

            <div>
                <span class="font-semibold">Teléfono:</span>
                {{ $cliente->telefono ?: 'Sin registro' }}
            </div>

            <div>
                <span class="font-semibold">Dirección:</span>
                {{ $cliente->direccion ?: 'Sin registro' }}
            </div>
        </div>
    </div>

    <div class="rounded-lg bg-white p-6 shadow-md">
        <h3 class="mb-4 text-lg font-bold text-gray-800">
            Ventas asociadas: {{ $ventas->total() }}
        </h3>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left">Venta</th>
                        <th class="px-4 py-3 text-left">Fecha</th>
                        <th class="px-4 py-3 text-left">Estado</th>
                        <th class="px-4 py-3 text-left">Pago</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-center">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">
                    @forelse($ventas as $venta)
                        <tr>
                            <td class="px-4 py-3 font-semibold">
                                #{{ $venta->id_venta }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                {{ $venta->fecha?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ str_replace('_', ' ', $venta->estado_venta ?? 'Histórica') }}
                            </td>

                            <td class="px-4 py-3">
                                {{ str_replace('_', ' ', $venta->estado_pago ?? 'Sin registro') }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                $ {{ number_format($venta->total, 0, ',', '.') }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <button
                                    type="button"
                                    data-modal="venta-modal-{{ $venta->id_venta }}"
                                    onclick="abrirDetalleVenta(this)"
                                    class="rounded-md bg-[#b52f25] px-3 py-2 text-white hover:bg-[#8f241d]"
                                >
                                    Ver detalle
                                </button>

                                <a
                                    href="{{ route('clientes.ventas.resumen', [
                                        'id' => $cliente->id_cliente,
                                        'ventaId' => $venta->id_venta
                                    ]) }}"
                                    class="inline-block rounded-md border border-[#d9aaa3] px-3 py-2 text-[#9f2f25] hover:bg-[#f7e8e6]"
                                >
                                    Descargar resumen
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                Este cliente todavía no tiene ventas registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $ventas->links() }}
        </div>
    </div>
</div>

@foreach($ventas as $venta)
    <dialog
        id="venta-modal-{{ $venta->id_venta }}"
        aria-labelledby="venta-titulo-{{ $venta->id_venta }}"
        class="m-auto w-full max-w-3xl rounded-xl bg-white p-0 shadow-xl"
        style="max-height: 90vh; width: min(95vw, 48rem);"
    >
        <div class="flex items-center justify-between gap-3 bg-[#8f241d] px-6 py-4 text-white">
            <h3
                id="venta-titulo-{{ $venta->id_venta }}"
                class="text-lg font-bold"
            >
                Detalle de venta #{{ $venta->id_venta }}
            </h3>

            <button
                type="button"
                onclick="this.closest('dialog').close()"
                aria-label="Cerrar detalle"
                class="rounded px-3 py-1 text-2xl hover:bg-white/20"
            >
                &times;
            </button>
        </div>

        <div class="space-y-5 p-6">
            <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <p>
                    <strong>Cliente:</strong>
                    {{ $cliente->nombre }}
                </p>

                <p>
                    <strong>RUT:</strong>
                    {{ $cliente->rut }}
                </p>

                <p>
                    <strong>Fecha:</strong>
                    {{ $venta->fecha?->format('d/m/Y H:i') ?? 'Sin fecha' }}
                </p>

                <p>
                    <strong>Responsable:</strong>
                    {{ $venta->user?->name ?? 'Sin registro' }}
                </p>

                <p>
                    <strong>Documento seleccionado:</strong>
                    {{ $venta->tipo_documento ?? 'Sin registro' }}
                </p>

                <p>
                    <strong>Medio de pago:</strong>
                    {{ $venta->medio_pago ?? 'Sin registro' }}
                </p>

                <p>
                    <strong>Estado de venta:</strong>
                    {{ str_replace('_', ' ', $venta->estado_venta ?? 'Histórica') }}
                </p>

                <p>
                    <strong>Estado de pago:</strong>
                    {{ str_replace('_', ' ', $venta->estado_pago ?? 'Sin registro') }}
                </p>

                <p>
                    <strong>Facturación:</strong>
                    {{ str_replace('_', ' ', $venta->estado_facturacion ?? 'Sin registro') }}
                </p>

                <p>
                    <strong>Entrega:</strong>
                    {{ str_replace('_', ' ', $venta->estado_entrega ?? 'Sin registro') }}
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left">Producto / Servicio</th>
                            <th class="px-3 py-2 text-center">Cantidad</th>
                            <th class="px-3 py-2 text-right">Precio unitario</th>
                            <th class="px-3 py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @forelse($venta->detalles as $detalle)
                            <tr>
                                <td class="px-3 py-2">
                                    {{ $detalle->descripcion ?: ($detalle->producto?->nombre ?? 'Producto eliminado') }}
                                </td>

                                <td class="px-3 py-2 text-center">
                                    {{ $detalle->cantidad }}
                                </td>

                                <td class="whitespace-nowrap px-3 py-2 text-right">
                                    $ {{ number_format($detalle->precio_unitario, 0, ',', '.') }}
                                </td>

                                <td class="whitespace-nowrap px-3 py-2 text-right">
                                    $ {{ number_format($detalle->subtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-gray-500">
                                    No hay ítems registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="ml-auto max-w-xs space-y-2 border-t pt-3 text-sm">
                <div class="flex justify-between">
                    <span>Neto:</span>
                    <span>$ {{ number_format($venta->neto, 0, ',', '.') }}</span>
                </div>

                <div class="flex justify-between">
                    <span>IVA:</span>
                    <span>$ {{ number_format($venta->iva, 0, ',', '.') }}</span>
                </div>

                <div class="flex justify-between text-base font-bold">
                    <span>Total:</span>
                    <span>$ {{ number_format($venta->total, 0, ',', '.') }}</span>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t pt-4">
                <button
                    type="button"
                    onclick="this.closest('dialog').close()"
                    class="rounded-md border border-gray-300 px-4 py-2 hover:bg-gray-50"
                >
                    Cerrar
                </button>

                <a
                    href="{{ route('clientes.ventas.resumen', [
                        'id' => $cliente->id_cliente,
                        'ventaId' => $venta->id_venta
                    ]) }}"
                    class="rounded-md bg-[#b52f25] px-4 py-2 text-white hover:bg-[#8f241d]"
                >
                    Descargar resumen PDF
                </a>
            </div>
        </div>
    </dialog>
@endforeach

<style>
    dialog::backdrop {
        background: rgba(0, 0, 0, 0.55);
    }
</style>

<script>
    function abrirDetalleVenta(boton) {
        const modal = document.getElementById(boton.dataset.modal);

        if (modal) {
            modal.showModal();
        }
    }
</script>
@endsection