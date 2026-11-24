@extends('layouts.app')

@section('content')
<div class="space-y-6">
    @if($errors->any())
        <div class="rounded-lg bg-red-50 p-4 text-red-700" role="alert">{{ $errors->first() }}</div>
    @endif
    @php
        $parametrosExportacion = array_merge(request()->query(), ['anio' => $periodo['anio'], 'mes' => $periodo['mes']]);
    @endphp
    <header class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm font-bold uppercase tracking-wider text-[#9f2f25]">Control de gestión</p><h1 class="text-3xl font-black text-gray-900">Reporte de ventas</h1><p class="mt-1 text-sm text-gray-500">Ventas recientes, facturas y trazabilidad comercial.</p></div><div class="flex gap-2"><a href="{{ route('reportes.exportar', array_merge($parametrosExportacion, ['formato' => 'csv'])) }}" class="rounded-md border border-[#b52f25] px-4 py-2 font-bold text-[#9f2f25]">Reporte de ventas Excel</a><a href="{{ route('reportes.exportar', array_merge($parametrosExportacion, ['formato' => 'pdf'])) }}" class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Reporte de ventas PDF</a></div></header>
        <form method="GET" action="{{ route('reportes.index') }}" id="report-filter-form" class="report-filters" data-current-year="{{ now()->year }}" data-current-month="{{ now()->month }}">
            <details id="report-filter-panel" class="report-filter-panel">
                <summary class="report-filter-toggle">
                    <span class="report-filters-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16M7 12h10M10 19h4" stroke-linecap="round"/></svg>
                    </span>
                    <span>Filtros de búsqueda</span>
                    <svg class="report-filter-chevron" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </summary>
            <div class="report-filter-section">
                <div class="report-section-heading">
                    <div><h3>Período</h3><p>El rango de fechas acota el año y mes seleccionados. Los accesos rápidos conservan las fechas ingresadas.</p></div>
                    <div class="report-quick-periods" aria-label="Selección rápida de período" hidden>
                        <button type="button" data-report-period="year">Este año</button>
                        <button type="button" data-report-period="month">Este mes</button>
                    </div>
                </div>
                <div class="report-filter-grid">
                    <label class="report-field"><span>Año</span><select name="anio">@foreach($aniosDisponibles as $anio)<option value="{{ $anio }}" @selected($anio === $periodo['anio'])>{{ $anio }}</option>@endforeach</select></label>
                    <label class="report-field"><span>Mes</span><select name="mes"><option value="">Todos los meses</option>@foreach(range(1, 12) as $mes)<option value="{{ $mes }}" @selected($mes === $periodo['mes'])>{{ ucfirst(\Carbon\Carbon::create(2000, $mes, 1)->locale('es')->isoFormat('MMMM')) }}</option>@endforeach</select></label>
                    <label class="report-field"><span>Desde <small>Opcional</small></span><input type="date" name="desde" value="{{ request('desde') }}"></label>
                    <label class="report-field"><span>Hasta <small>Opcional</small></span><input type="date" name="hasta" value="{{ request('hasta') }}"></label>
                </div>
            </div>

            <div class="report-filter-section report-filter-section-details">
                <div class="report-section-heading"><div><h3>Detalles de la venta</h3><p>Refina la búsqueda con uno o más filtros.</p></div></div>
                <div class="report-filter-grid">
                    <label class="report-field"><span>N.º de venta</span><input type="number" name="venta_id" value="{{ request('venta_id') }}" placeholder="Ej. 123"></label>
                    <label class="report-field"><span>Cliente, RUT o RUN</span><input type="search" name="cliente" value="{{ request('cliente') }}" placeholder="Nombre, RUT o RUN"></label>
                    <label class="report-field"><span>Vendedor</span><select name="usuario"><option value="">Todos los vendedores</option>@foreach($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected((string) request('usuario') === (string) $usuario->id)>{{ $usuario->name }}</option>@endforeach</select></label>
                    <label class="report-field"><span>Medio de pago</span><select name="medio_pago"><option value="">Todos los medios de pago</option>@foreach(['Efectivo','Transferencia','Tarjeta','Otro'] as $medio)<option value="{{ $medio }}" @selected(request('medio_pago') === $medio)>{{ $medio }}</option>@endforeach</select></label>
                </div>
            </div>
            </details>

            <div class="report-filters-footer">
                <div class="report-applied-period">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18" stroke-linecap="round"/></svg>
                    <div><span>Período aplicado</span><strong>{{ $desde->lte($hasta) ? $desde->format('d/m/Y').' - '.$hasta->format('d/m/Y') : 'sin coincidencia entre el período y el rango de fechas' }}</strong></div>
                </div>
                <button type="submit" class="report-apply">Aplicar filtros <span aria-hidden="true">→</span></button>
            </div>
            <p id="report-filter-pending" class="report-filter-pending" role="status" hidden>Hay cambios sin aplicar. Los resultados y las exportaciones siguen mostrando los filtros aplicados.</p>
        </form>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2"><div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Ventas completadas</p><p class="mt-2 text-3xl font-black text-[#9f2f25]">{{ $resumen->cantidad }}</p></div><div class="rounded-lg bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Total vendido</p><p class="mt-2 text-3xl font-black text-[#9f2f25]">$ {{ number_format($resumen->total, 0, ',', '.') }}</p></div></div>
        <section class="overflow-x-auto rounded-lg bg-white shadow-sm"><div class="border-b border-gray-100 p-5"><h2 class="text-lg font-black">Últimas ventas</h2><p class="mt-1 text-sm text-gray-500">Consulta los detalles y descarga nuevamente los resúmenes de ventas.</p></div><table class="min-w-full text-left text-sm"><thead class="bg-[#f7e8e6] text-xs uppercase text-[#8f241d]"><tr><th class="px-5 py-3">Venta</th><th class="px-5 py-3">Fecha</th><th class="px-5 py-3">Cliente</th><th class="px-5 py-3">Vendedor</th><th class="px-5 py-3">Pago</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Acciones</th></tr></thead><tbody class="divide-y">@forelse($ventas as $venta)<tr><td class="px-5 py-3 font-bold">#{{ $venta->id_venta }}</td><td class="whitespace-nowrap px-5 py-3">{{ $venta->fecha->format('d/m/Y H:i') }}</td><td class="px-5 py-3">        {{ $venta->cliente_snapshot->nombre ?: 'Sin cliente' }}</td><td class="px-5 py-3">{{ $venta->user?->name ?? 'Sin usuario' }}</td><td class="px-5 py-3">{{ $venta->medio_pago }}</td><td class="px-5 py-3 font-bold">$ {{ number_format($venta->total, 0, ',', '.') }}</td><td class="px-5 py-3"><button type="button" data-sale-detail="{{ route('reportes.ventas.detalle', $venta->id_venta) }}" data-sale-invoice="{{ route('reportes.ventas.resumen', $venta->id_venta) }}" class="rounded-md bg-[#b52f25] px-3 py-2 text-xs font-bold text-white">Ver resumen</button></td></tr>@empty<tr><td colspan="7" class="px-5 py-8 text-center text-gray-500">No hay ventas para los filtros seleccionados.</td></tr>@endforelse</tbody></table><div class="p-5">{{ $ventas->appends(request()->query())->links() }}</div></section>
</div>

<div id="sale-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="sale-modal-title"><div class="mx-auto mt-8 max-w-3xl rounded-lg bg-white shadow-2xl"><div class="flex items-center justify-between border-b p-5"><div><p class="text-xs font-bold uppercase tracking-wider text-[#9f2f25]">Resumen interno de venta</p><h2 id="sale-modal-title" class="text-xl font-black">Detalle</h2></div><button type="button" id="close-sale-modal" class="rounded-md px-3 py-2 text-2xl text-gray-500" aria-label="Cerrar">&times;</button></div><div id="sale-modal-content" class="p-5"><p class="text-gray-500">Cargando detalle...</p></div><div class="flex justify-end gap-2 border-t bg-gray-50 p-5"><a id="sale-invoice-link" href="#" class="rounded-md bg-[#b52f25] px-4 py-2 font-bold text-white">Descargar resumen interno</a><button type="button" id="cancel-sale-modal" class="rounded-md border px-4 py-2 font-bold">Cerrar</button></div></div></div>
<style>
    .report-filters {
        overflow: hidden;
        border: 1px solid #eee1de;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 8px 26px rgba(62, 32, 29, .06);
    }
    .report-filter-panel > summary { list-style: none; }
    .report-filter-panel > summary::-webkit-details-marker { display: none; }
    .report-filter-toggle {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 64px;
        padding: 12px 24px;
        cursor: pointer;
        color: #30211f;
        font-size: 1rem;
        font-weight: 800;
        background: linear-gradient(110deg, #fff8f6, #fff 70%);
    }
    .report-filter-chevron { width: 20px; height: 20px; margin-left: auto; transition: transform .15s ease; }
    .report-filter-panel[open] .report-filter-chevron { transform: rotate(180deg); }
    .report-filters-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 22px 24px;
        border-bottom: 1px solid #f1e8e6;
        background: linear-gradient(110deg, #fff8f6, #fff 70%);
    }
    .report-filters-heading { display: flex; align-items: center; gap: 13px; }
    .report-filters-icon {
        display: grid;
        flex: 0 0 44px;
        width: 44px;
        height: 44px;
        place-items: center;
        border-radius: 12px;
        background: #f9e4e0;
        color: #a32d23;
    }
    .report-filters-icon svg, .report-applied-period svg { width: 22px; height: 22px; flex: none; }
    .report-filters-heading h2 { margin: 0; color: #30211f; font-size: 1.05rem; font-weight: 800; }
    .report-filters-heading p, .report-section-heading p { margin: 3px 0 0; color: #786f6d; font-size: .8rem; }
    .report-reset { color: #9f2f25; font-size: .8rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .report-reset:hover { text-decoration: underline; }
    .report-filter-section { padding: 20px 24px; }
    .report-filter-section-details { border-top: 1px solid #f3edeb; }
    .report-section-heading { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
    .report-section-heading h3 { margin: 0; color: #382b29; font-size: .9rem; font-weight: 800; }
    .report-quick-periods { display: flex; flex-wrap: wrap; gap: 6px; }
    .report-quick-periods[hidden], .report-filter-pending[hidden] { display: none; }
    .report-quick-periods button {
        padding: 6px 11px;
        border: 1px solid #edd5d0;
        background: #fff8f6;
        color: #9f2f25;
        font-size: .75rem;
        font-weight: 700;
        cursor: pointer;
    }
    .report-quick-periods button:hover { border-color: #cf8c82; background: #fae9e5; }
    .report-filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .report-field { display: flex; flex-direction: column; min-width: 0; gap: 7px; color: #514644; font-size: .8rem; font-weight: 700; }
    .report-field small { margin-left: 4px; color: #9b908e; font-size: .7rem; font-weight: 500; }
    .report-field input, .report-field select {
        box-sizing: border-box;
        width: 100%;
        min-width: 0;
        min-height: 44px;
        padding: 9px 12px;
        border: 1px solid #e3dcda;
        background: #fff;
        color: #382b29;
        font-size: .875rem;
        font-weight: 400;
        transition: border-color .15s, box-shadow .15s;
    }
    .report-field input::placeholder { color: #a49b99; }
    .report-field input:hover, .report-field select:hover { border-color: #c9aaa4; }
    .report-field input:focus, .report-field select:focus { outline: none; border-color: #b52f25; box-shadow: 0 0 0 3px rgba(181, 47, 37, .12); }
    .report-filters-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        padding: 17px 24px;
        border-top: 1px solid #f1e8e6;
        background: #fcfaf9;
    }
    .report-applied-period { display: flex; align-items: center; gap: 10px; min-width: 0; color: #a33a2f; }
    .report-applied-period span { display: block; color: #887a77; font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .report-applied-period strong { display: block; color: #473936; font-size: .85rem; overflow-wrap: anywhere; }
    .report-apply {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 22px;
        min-height: 44px;
        padding: 10px 20px;
        border: 0;
        background: #b52f25;
        color: #fff;
        font-size: .85rem;
        font-weight: 800;
        cursor: pointer;
        transition: background .15s, box-shadow .15s;
    }
    .report-apply:hover { background: #8f241d; box-shadow: 0 4px 12px rgba(143, 36, 29, .2); }
    .report-apply:focus-visible, .report-reset:focus-visible, .report-quick-periods button:focus-visible { outline: 3px solid #d88a7f; outline-offset: 3px; }
    .report-filter-pending { margin: 0; padding: 10px 24px; background: #fff4dc; color: #79500e; font-size: .8rem; }
    @media (max-width: 900px) { .report-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 560px) {
        .report-filters-header { align-items: flex-start; flex-direction: column; }
        .report-filters-header, .report-filter-section, .report-filters-footer { padding-left: 16px; padding-right: 16px; }
        .report-filter-grid { grid-template-columns: minmax(0, 1fr); }
        .report-apply { width: 100%; }
    }
</style>
<script>
const reportFilterForm = document.getElementById('report-filter-form');
const reportFilterPending = document.getElementById('report-filter-pending');
const initialReportFilters = new URLSearchParams(new FormData(reportFilterForm)).toString();
const updateReportFilterState = () => {
    reportFilterPending.hidden = new URLSearchParams(new FormData(reportFilterForm)).toString() === initialReportFilters;
};
reportFilterForm.addEventListener('input', updateReportFilterState);
reportFilterForm.addEventListener('change', updateReportFilterState);
reportFilterForm.querySelector('.report-quick-periods').hidden = false;
reportFilterForm.querySelectorAll('[data-report-period]').forEach(button => button.addEventListener('click', () => {
    reportFilterForm.elements.anio.value = reportFilterForm.dataset.currentYear;
    reportFilterForm.elements.mes.value = button.dataset.reportPeriod === 'month' ? reportFilterForm.dataset.currentMonth : '';
    updateReportFilterState();
    reportFilterForm.requestSubmit();
}));

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
    invoiceLink.classList.add('hidden');
    invoiceLink.href = button.dataset.saleInvoice;
    try {
        const response = await fetch(button.dataset.saleDetail, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error(`El servidor respondió ${response.status}.`);
        }
        const sale = await response.json();
        invoiceLink.classList.toggle('hidden', !sale.resumen_disponible);
        saleContent.innerHTML = `<div class="grid gap-4 text-sm md:grid-cols-3"><div><span class="text-gray-500">Venta</span><strong class="block">#${escapeHtml(sale.id_venta)}</strong></div><div><span class="text-gray-500">Fecha</span><strong class="block">${escapeHtml(sale.fecha)}</strong></div><div><span class="text-gray-500">Cliente</span><strong class="block">${escapeHtml(sale.cliente)}</strong></div><div><span class="text-gray-500">Vendedor</span><strong class="block">${escapeHtml(sale.usuario)}</strong></div><div><span class="text-gray-500">Pago</span><strong class="block">${escapeHtml(sale.medio_pago)}</strong></div><div><span class="text-gray-500">Venta</span><strong class="block">${escapeHtml(sale.estado_venta)}</strong></div><div><span class="text-gray-500">Facturación</span><strong class="block">${escapeHtml(sale.estado_facturacion)}</strong></div></div><div class="mt-6 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-[#f7e8e6] text-xs uppercase text-[#8f241d]"><tr><th class="px-3 py-2">Producto</th><th class="px-3 py-2">Cantidad</th><th class="px-3 py-2">Subtotal</th></tr></thead><tbody class="divide-y">${sale.detalles.map(item => `<tr><td class="px-3 py-2">${escapeHtml(item.producto)}</td><td class="px-3 py-2">${escapeHtml(item.cantidad)}</td><td class="px-3 py-2 font-bold">${money(item.subtotal)}</td></tr>`).join('')}</tbody></table></div><div class="mt-5 ml-auto max-w-xs space-y-2 text-right text-sm"><p>Neto: <strong>${money(sale.neto)}</strong></p><p>IVA: <strong>${money(sale.iva)}</strong></p><p class="border-t pt-2 text-lg">Total: <strong class="text-[#b52f25]">${money(sale.total)}</strong></p></div>`;
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
