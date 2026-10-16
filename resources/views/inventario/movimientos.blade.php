@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-gray-800">Historial de inventario</h1>
            <p class="text-sm text-gray-500">Cada cambio conserva el usuario, motivo y stock resultante.</p>
        </div>
        @if (session('success'))
            <div class="rounded-md bg-green-100 px-4 py-2 text-sm font-bold text-green-800">{{ session('success') }}</div>
        @endif
    </div>

    <form method="GET" class="grid grid-cols-1 gap-3 rounded-lg bg-white p-4 shadow-md md:grid-cols-3">
        <select name="producto" class="rounded-md border p-2">
            <option value="">Todos los productos</option>
            @foreach ($productos as $producto)
                <option value="{{ $producto->id_producto }}" @selected(request('producto') == $producto->id_producto)>{{ $producto->nombre }}</option>
            @endforeach
        </select>
        <select name="tipo" class="rounded-md border p-2">
            <option value="">Todos los movimientos</option>
            @foreach (['adquisicion', 'merma', 'ajuste', 'venta', 'ingreso'] as $tipo)
                <option value="{{ $tipo }}" @selected(request('tipo') === $tipo)>{{ ucfirst($tipo) }}</option>
            @endforeach
        </select>
        <button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white hover:bg-[#8f241d]">Filtrar</button>
    </form>

    <form id="movement-form" method="POST" action="{{ route('inventario.movimientos.store') }}" class="grid grid-cols-1 gap-3 rounded-lg border border-[#e8c8c3] bg-[#fff7f5] p-4 md:grid-cols-4">
        @csrf
        <select id="movement-type" name="tipo" required class="rounded-md border p-2">
            <option value="adquisicion">Adquisición de mercancía</option>
            <option value="merma">Merma / pérdida</option>
            <option value="ajuste">Ajuste por inventario físico</option>
        </select>
        <select name="id_producto" required class="rounded-md border p-2">
            <option value="">Producto afectado</option>
            @foreach ($productos as $producto)
                <option value="{{ $producto->id_producto }}">{{ $producto->nombre }} ({{ $producto->stock }})</option>
            @endforeach
        </select>
        <div id="quantity-field"><input name="cantidad" type="number" min="1" placeholder="Cantidad" class="w-full rounded-md border p-2"></div>
        <div id="stock-field" class="hidden"><input name="stock_nuevo" type="number" min="0" placeholder="Stock nuevo" class="w-full rounded-md border p-2"></div>
        <input name="motivo" required maxlength="255" placeholder="Motivo del movimiento" class="rounded-md border p-2">
        <div id="purchase-fields" class="contents">
            <input name="proveedor" maxlength="255" placeholder="Proveedor" class="rounded-md border p-2">
            <input name="precio_entrada" type="number" min="0" step="0.01" placeholder="Total pagado al proveedor" class="rounded-md border p-2">
            <input name="documento_proveedor" maxlength="100" placeholder="N° factura / guía" class="rounded-md border p-2">
        </div>
        <textarea name="observaciones" maxlength="2000" placeholder="Observaciones o detalle adicional" class="rounded-md border p-2 md:col-span-2"></textarea>
        <button class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Registrar movimiento</button>
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow-md">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-gray-100 text-xs uppercase text-gray-600"><tr><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Producto</th><th class="px-4 py-3">Cambio</th><th class="px-4 py-3">Motivo</th><th class="px-4 py-3">Usuario</th><th class="px-4 py-3">Detalle</th></tr></thead>
            <tbody class="divide-y">
                @forelse ($movimientos as $movimiento)
                    <tr><td class="px-4 py-3">{{ $movimiento->created_at->format('d/m/Y H:i') }}</td><td class="px-4 py-3 font-semibold">{{ $movimiento->producto?->nombre ?? 'Producto eliminado' }}<span class="block text-xs font-normal uppercase text-gray-500">{{ ucfirst($movimiento->tipo) }}</span></td><td class="px-4 py-3 {{ $movimiento->cantidad < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $movimiento->cantidad > 0 ? '+' : '' }}{{ $movimiento->cantidad }}<span class="block text-xs text-gray-500">{{ $movimiento->stock_anterior }} &rarr; {{ $movimiento->stock_nuevo }}</span></td><td class="px-4 py-3">{{ $movimiento->motivo }}</td><td class="px-4 py-3">{{ $movimiento->usuario?->name ?? 'Sistema' }}</td><td class="px-4 py-3"><button type="button" data-movement-detail="{{ route('inventario.movimientos.show', $movimiento) }}" data-movement-invoice="{{ $movimiento->id_venta ? route('reportes.ventas.factura', $movimiento->id_venta) : '' }}" class="rounded-md border border-[#b52f25] px-3 py-1 text-xs font-bold text-[#9f2f25] hover:bg-[#f7e8e6]">Ver detalle</button></td></tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Todavía no hay movimientos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $movimientos->links() }}
</div>

<div id="movement-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="movement-modal-title">
    <div class="mx-auto mt-8 max-w-3xl rounded-lg bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b p-5"><div><p class="text-xs font-bold uppercase tracking-wider text-[#9f2f25]">Trazabilidad de inventario</p><h2 id="movement-modal-title" class="text-xl font-black">Detalle del movimiento</h2></div><button type="button" id="close-movement-modal" class="rounded-md px-3 py-2 text-2xl text-gray-500" aria-label="Cerrar">&times;</button></div>
        <div id="movement-modal-content" class="p-5"><p class="text-gray-500">Cargando detalle...</p></div>
        <div class="flex justify-end gap-2 border-t bg-gray-50 p-5"><a id="movement-invoice-link" href="#" class="hidden rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Descargar boleta / factura</a><button type="button" id="cancel-movement-modal" class="rounded-md border px-4 py-2 font-bold">Cerrar</button></div>
    </div>
</div>

<script>
const movementType = document.getElementById('movement-type');
const quantityField = document.getElementById('quantity-field');
const stockField = document.getElementById('stock-field');
const purchaseFields = document.getElementById('purchase-fields');
const syncMovementFields = () => {
    const isAdjustment = movementType.value === 'ajuste';
    const isPurchase = movementType.value === 'adquisicion';
    quantityField.classList.toggle('hidden', isAdjustment);
    stockField.classList.toggle('hidden', !isAdjustment);
    purchaseFields.classList.toggle('hidden', !isPurchase);
    purchaseFields.querySelectorAll('input').forEach(input => { input.required = isPurchase && ['proveedor', 'precio_entrada'].includes(input.name); });
    quantityField.querySelector('input').required = !isAdjustment;
    stockField.querySelector('input').required = isAdjustment;
};
movementType?.addEventListener('change', syncMovementFields);
syncMovementFields();

const movementModal = document.getElementById('movement-modal');
const movementContent = document.getElementById('movement-modal-content');
const movementInvoice = document.getElementById('movement-invoice-link');
const money = value => '$ ' + new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 }).format(value);
const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
const closeMovementModal = () => movementModal?.classList.add('hidden');
document.querySelectorAll('[data-movement-detail]').forEach(button => button.addEventListener('click', async () => {
    movementModal.classList.remove('hidden');
    movementContent.innerHTML = '<p class="text-gray-500">Cargando detalle...</p>';
    movementInvoice.classList.toggle('hidden', !button.dataset.movementInvoice);
    movementInvoice.href = button.dataset.movementInvoice || '#';
    try {
        const movement = await fetch(button.dataset.movementDetail, { headers: { Accept: 'application/json' } }).then(response => response.json());
        const purchase = movement.tipo === 'adquisicion' ? `<div class="mt-5 rounded-md bg-[#fff7f5] p-4"><h3 class="font-bold text-[#9f2f25]">Datos de adquisición</h3><div class="mt-3 grid gap-3 text-sm md:grid-cols-2"><p><span class="text-gray-500">Proveedor</span><strong class="block">${escapeHtml(movement.proveedor) || 'No informado'}</strong></p><p><span class="text-gray-500">Documento</span><strong class="block">${escapeHtml(movement.documento_proveedor) || 'No informado'}</strong></p><p><span class="text-gray-500">Cantidad comprada</span><strong class="block">${movement.cantidad_adquirida}</strong></p><p><span class="text-gray-500">Total pagado</span><strong class="block">${money(movement.precio_entrada)}</strong></p><p><span class="text-gray-500">Valor unitario de adquisición</span><strong class="block">${money(movement.precio_unitario_adquisicion)}</strong></p><p><span class="text-gray-500">Venta recomendada con IVA (19%)</span><strong class="block text-[#b52f25]">${money(movement.precio_venta_recomendado)}</strong></p></div></div>` : '';
        const sale = movement.venta ? `<div class="mt-5 rounded-md bg-gray-50 p-4"><h3 class="font-bold">${escapeHtml(movement.venta.tipo_documento)} #${movement.venta.id_venta}</h3><p class="mt-1 text-sm text-gray-600">Cliente: ${escapeHtml(movement.venta.cliente)} · Total: ${money(movement.venta.total)}</p><ul class="mt-3 space-y-1 text-sm">${movement.venta.detalles.map(item => `<li>${escapeHtml(item.producto)} · ${item.cantidad} · ${money(item.subtotal)}</li>`).join('')}</ul></div>` : '';
        movementContent.innerHTML = `<div class="grid gap-4 text-sm md:grid-cols-3"><p><span class="text-gray-500">Fecha</span><strong class="block">${escapeHtml(movement.fecha)}</strong></p><p><span class="text-gray-500">Producto</span><strong class="block">${escapeHtml(movement.producto)}</strong></p><p><span class="text-gray-500">Usuario</span><strong class="block">${escapeHtml(movement.usuario)}</strong></p><p><span class="text-gray-500">Tipo</span><strong class="block">${escapeHtml(movement.tipo)}</strong></p><p><span class="text-gray-500">Cambio</span><strong class="block">${movement.cantidad > 0 ? '+' : ''}${movement.cantidad}</strong></p><p><span class="text-gray-500">Stock resultante</span><strong class="block">${movement.stock_anterior} &rarr; ${movement.stock_nuevo}</strong></p></div><div class="mt-5 border-t pt-4 text-sm"><p><strong>Motivo:</strong> ${escapeHtml(movement.motivo)}</p><p class="mt-2"><strong>Observaciones:</strong> ${escapeHtml(movement.observaciones) || 'Sin observaciones'}</p></div>${purchase}${sale}`;
    } catch (error) { movementContent.innerHTML = '<p class="text-red-700">No fue posible cargar el detalle.</p>'; }
}));
document.getElementById('close-movement-modal')?.addEventListener('click', closeMovementModal);
document.getElementById('cancel-movement-modal')?.addEventListener('click', closeMovementModal);
movementModal?.addEventListener('click', event => { if (event.target === movementModal) closeMovementModal(); });
</script>
@endsection
