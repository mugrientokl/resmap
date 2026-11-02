@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Control de gestión</p><h1 class="text-3xl font-black text-gray-900">Reporte de ventas</h1><p class="mt-1 text-sm text-gray-500">Ventas recientes, facturas y trazabilidad comercial.</p></div><div class="flex gap-2"><a href="{{ route('reportes.exportar', request()->query() + ['formato' => 'csv']) }}" class="rounded-md border border-[#b52f25] px-4 py-2 font-bold text-[#9f2f25]">Reporte de ventas Excel</a><a href="{{ route('reportes.exportar', request()->query() + ['formato' => 'pdf']) }}" class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Reporte de ventas PDF</a></div></header>
        <form method="GET" class="grid gap-3 rounded-lg bg-white p-5 shadow-sm md:grid-cols-2 lg:grid-cols-5"><input type="date" name="desde" value="{{ $desde->toDateString() }}" class="rounded-md border p-2" aria-label="Desde"><input type="date" name="hasta" value="{{ $hasta->toDateString() }}" class="rounded-md border p-2" aria-label="Hasta"><input type="number" name="venta_id" value="{{ request('venta_id') }}" placeholder="N° de venta" class="rounded-md border p-2"><input type="search" name="cliente" value="{{ request('cliente') }}" placeholder="Cliente o RUT" class="rounded-md border p-2"><select name="usuario" class="rounded-md border p-2"><option value="">Todos los vendedores</option>@foreach($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected((string) request('usuario') === (string) $usuario->id)>{{ $usuario->name }}</option>@endforeach</select><select name="medio_pago" class="rounded-md border p-2"><option value="">Todos los medios de pago</option>@foreach(['Efectivo','Transferencia','Tarjeta','Otro'] as $medio)<option value="{{ $medio }}" @selected(request('medio_pago') === $medio)>{{ $medio }}</option>@endforeach</select><button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white lg:col-start-5">Aplicar filtros</button></form>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2"><div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Ventas completadas</p><p class="mt-2 text-3xl font-black text-[#9f2f25]">{{ $resumen->cantidad }}</p></div><div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Total vendido</p><p class="mt-2 text-3xl font-black text-[#9f2f25]">$ {{ number_format($resumen->total, 0, ',', '.') }}</p></div></div>
        <section class="overflow-x-auto rounded-lg bg-white shadow-sm"><div class="border-b border-gray-100 p-5"><h2 class="text-lg font-black">Últimas ventas</h2><p class="mt-1 text-sm text-gray-500">Consulta los detalles y descarga nuevamente los resúmenes de ventas.</p></div><table class="min-w-full text-left text-sm"><thead class="bg-[#f7e8e6] text-xs uppercase text-[#8f241d]"><tr><th class="px-5 py-3">Venta</th><th class="px-5 py-3">Fecha</th><th class="px-5 py-3">Cliente</th><th class="px-5 py-3">Vendedor</th><th class="px-5 py-3">Pago</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Acciones</th></tr></thead><tbody class="divide-y">@forelse($ventas as $venta)<tr><td class="px-5 py-3 font-bold">#{{ $venta->id_venta }}</td><td class="whitespace-nowrap px-5 py-3">{{ $venta->fecha->format('d/m/Y H:i') }}</td><td class="px-5 py-3">{{ $venta->cliente?->nombre ?? 'Sin cliente' }}</td><td class="px-5 py-3">{{ $venta->user?->name ?? 'Sin usuario' }}</td><td class="px-5 py-3">{{ $venta->medio_pago }}</td><td class="px-5 py-3 font-bold">$ {{ number_format($venta->total, 0, ',', '.') }}</td><td class="px-5 py-3"><button type="button" data-sale-detail="{{ route('reportes.ventas.detalle', $venta->id_venta) }}" data-sale-invoice="{{ route('reportes.ventas.resumen', $venta->id_venta) }}" class="rounded-md bg-[#b52f25] px-3 py-2 text-xs font-bold text-white">Ver resumen</button></td></tr>@empty<tr><td colspan="7" class="px-5 py-8 text-center text-gray-500">No hay ventas para los filtros seleccionados.</td></tr>@endforelse</tbody></table><div class="p-5">{{ $ventas->appends(request()->query())->links() }}</div></section>
</div>

<div id="sale-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="sale-modal-title"><div class="mx-auto mt-8 max-w-3xl rounded-lg bg-white shadow-2xl"><div class="flex items-center justify-between border-b p-5"><div><p class="text-xs font-bold uppercase tracking-wider text-[#9f2f25]">Resumen interno de venta</p><h2 id="sale-modal-title" class="text-xl font-black">Detalle</h2></div><button type="button" id="close-sale-modal" class="rounded-md px-3 py-2 text-2xl text-gray-500" aria-label="Cerrar">&times;</button></div><div id="sale-modal-content" class="p-5"><p class="text-gray-500">Cargando detalle...</p></div><div class="flex justify-end gap-2 border-t bg-gray-50 p-5"><a id="sale-invoice-link" href="#" class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Descargar resumen interno</a><button type="button" id="cancel-sale-modal" class="rounded-md border px-4 py-2 font-bold">Cerrar</button></div></div></div>
<script>
const saleModal = document.getElementById('sale-modal');
const saleContent = document.getElementById('sale-modal-content');
const invoiceLink = document.getElementById('sale-invoice-link');
const money = value => '$ ' + new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(value);
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
}[character]));
const closeSaleModal = () => saleModal?.classList.add('hidden');
document.querySelectorAll('[data-sale-detail]').forEach(button => button.addEventListener('click', async () => {
    saleModal.classList.remove('hidden');
    saleContent.innerHTML = '<p class="text-gray-500">Cargando detalle...</p>';
    invoiceLink.href = button.dataset.saleInvoice;
    try {
        const response = await fetch(button.dataset.saleDetail, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error(`El servidor respondió ${response.status}.`);
        }
        const sale = await response.json();
        saleContent.innerHTML = `<div class="grid gap-4 text-sm md:grid-cols-3"><div><span class="text-gray-500">Venta</span><strong class="block">#${escapeHtml(sale.id_venta)}</strong></div><div><span class="text-gray-500">Fecha</span><strong class="block">${escapeHtml(sale.fecha)}</strong></div><div><span class="text-gray-500">Cliente</span><strong class="block">${escapeHtml(sale.cliente)}</strong></div><div><span class="text-gray-500">Vendedor</span><strong class="block">${escapeHtml(sale.usuario)}</strong></div><div><span class="text-gray-500">Pago</span><strong class="block">${escapeHtml(sale.medio_pago)}</strong></div><div><span class="text-gray-500">Venta</span><strong class="block">${escapeHtml(sale.estado_venta)}</strong></div><div><span class="text-gray-500">Estado pago</span><strong class="block">${escapeHtml(sale.estado_pago)}</strong></div><div><span class="text-gray-500">Facturación</span><strong class="block">${escapeHtml(sale.estado_facturacion)}</strong></div><div><span class="text-gray-500">Entrega</span><strong class="block">${escapeHtml(sale.estado_entrega)}</strong></div></div><div class="mt-6 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-[#f7e8e6] text-xs uppercase text-[#8f241d]"><tr><th class="px-3 py-2">Producto</th><th class="px-3 py-2">Cantidad</th><th class="px-3 py-2">Subtotal</th></tr></thead><tbody class="divide-y">${sale.detalles.map(item => `<tr><td class="px-3 py-2">${escapeHtml(item.producto)}</td><td class="px-3 py-2">${escapeHtml(item.cantidad)}</td><td class="px-3 py-2 font-bold">${money(item.subtotal)}</td></tr>`).join('')}</tbody></table></div><div class="mt-5 ml-auto max-w-xs space-y-2 text-right text-sm"><p>Neto: <strong>${money(sale.neto)}</strong></p><p>IVA: <strong>${money(sale.iva)}</strong></p><p class="border-t pt-2 text-lg">Total: <strong class="text-[#b52f25]">${money(sale.total)}</strong></p></div>`;
        if (sale.cotizacion_id) {
            const quote = document.createElement('p');
            quote.className = 'mt-4 text-sm';
            quote.textContent = `Cotización de origen: #${sale.cotizacion_id}, versión ${sale.cotizacion_version}.`;
            saleContent.append(quote);
        }
        sale.ordenes_compra.forEach(order => {
            const purchaseOrder = document.createElement('p');
            purchaseOrder.className = 'mt-2 text-sm';
            purchaseOrder.textContent = `Orden de compra: ${order.numero || 'sin número'} (${order.modalidad})`;
            saleContent.append(purchaseOrder);
        });
    } catch (error) {
        console.error('Error al cargar el resumen de venta:', error);
        saleContent.innerHTML = `<p class="text-red-700">No fue posible cargar el resumen. ${escapeHtml(error.message)}</p>`;
    }
}));
document.getElementById('close-sale-modal')?.addEventListener('click', closeSaleModal);
document.getElementById('cancel-sale-modal')?.addEventListener('click', closeSaleModal);
saleModal?.addEventListener('click', event => { if (event.target === saleModal) closeSaleModal(); });
</script>
@endsection
