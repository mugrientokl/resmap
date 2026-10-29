<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Responder cotización #{{ $version->cotizacion_id }} · RESMAP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#f7f3f0] font-sans text-[#241817]">
    <header class="bg-[#8f241d] shadow-lg">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-5 py-4">
            <a href="{{ route('home') }}" aria-label="RESMAP">
                <img src="{{ asset('images/resmap sin fondo.png') }}" alt="RESMAP" class="h-14 w-auto">
            </a>
            <span class="hidden text-right text-xs font-bold uppercase tracking-[.2em] text-white sm:block">Cotización comercial</span>
        </div>
    </header>
    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <div class="mb-7">
            <p class="text-sm font-bold uppercase tracking-[.2em] text-[#9f2f25]">Revisión de cotización</p>
            <h1 class="mt-2 text-3xl font-black sm:text-4xl">Cotización #{{ $version->cotizacion_id }}</h1>
            <p class="mt-2 text-gray-600">Versión {{ $version->numero_version }} · Emitida el {{ $version->created_at->format('d/m/Y') }}</p>
        </div>
        @if($errors->any())
            <div class="mb-6 flex gap-3 rounded-lg border-l-4 border-red-600 bg-white p-5 text-red-900 shadow-sm" role="alert">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-50 font-black">!</span>
                <div><p class="font-black">No pudimos registrar tu respuesta</p><ul class="mt-1 list-disc pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            </div>
        @endif
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(280px,.7fr)]">
            <section class="rounded-xl bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-[#ead8d5] pb-5">
                    <div>
                        <h2 class="text-lg font-black">Detalle de productos</h2>
                        <p class="mt-1 text-sm text-gray-500">Válida hasta {{ $version->vigente_hasta?->format('d/m/Y H:i') ?? 'fecha no disponible' }}</p>
                    </div>
                </div>
                <div class="mt-5 space-y-3">
                    @foreach($version->detalles as $detalle)
                        <article class="flex flex-col justify-between gap-3 rounded-lg border border-[#f0e2df] p-4 sm:flex-row sm:items-center">
                            <div>
                                <h3 class="font-bold">{{ $detalle->nombre_producto }}</h3>
                                <p class="mt-1 text-sm text-gray-500">Código: {{ $detalle->codigo_producto ?: 'No informado' }} · Cantidad: {{ $detalle->cantidad }}</p>
                                <p class="mt-1 text-xs text-gray-500">Valor unitario: $ {{ number_format($detalle->precio_unitario, 0, ',', '.') }}</p>
                            </div>
                            <p class="text-lg font-black text-[#8f241d]">$ {{ number_format($detalle->subtotal, 0, ',', '.') }}</p>
                        </article>
                    @endforeach
                </div>
                <div class="ml-auto mt-6 max-w-sm space-y-3 border-t border-[#ead8d5] pt-5 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Neto</span><strong>$ {{ number_format($version->neto, 0, ',', '.') }}</strong></div>
                    <div class="flex justify-between"><span class="text-gray-500">IVA</span><strong>$ {{ number_format($version->iva, 0, ',', '.') }}</strong></div>
                    <div class="flex justify-between"><span class="text-gray-500">Despacho</span><strong id="valor-despacho"
                        data-retiro="0"
                        data-despacho="{{ (float) config('services.cotizaciones.costo_despacho', 0) }}"
                        data-productos="{{ (float) $version->neto + (float) $version->iva }}"
                        data-metodo-inicial="{{ $version->metodo_entrega }}"
                        data-costo-inicial="{{ (float) $version->costo_despacho }}">$ {{ number_format($version->costo_despacho, 0, ',', '.') }}</strong></div>
                    <div class="flex justify-between border-t-2 border-[#241817] pt-3 text-lg"><span class="font-black">Total</span><strong id="total-cotizacion" class="text-[#b52f25]">$ {{ number_format($version->total, 0, ',', '.') }}</strong></div>
                </div>
            </section>
            <aside class="space-y-6">
                <form method="POST" enctype="multipart/form-data" action="{{ route('cotizaciones.responder', [$version->id_version, $token]) }}" class="space-y-6">
                    @csrf
                <section class="rounded-xl bg-white p-6 shadow-sm">
                    <h2 class="font-black">Datos del cliente</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-gray-500">Razón social / nombre</dt><dd class="mt-1 font-semibold">{{ $cliente->nombre }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wide text-gray-500">RUT</dt><dd class="mt-1">{{ $cliente->rut }}</dd></div>
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wide text-gray-500">Entrega</dt>
                            <dd class="mt-1">
                                <label class="sr-only" for="metodo_entrega">Modalidad de entrega</label>
                                <select id="metodo_entrega" name="metodo_entrega" required class="mt-1 w-full rounded-md border border-[#d9aaa3] bg-white p-3 focus:border-[#b52f25] focus:outline-none">
                                    <option value="retiro" @selected(old('metodo_entrega', $version->metodo_entrega) === 'retiro')>Retiro en tienda</option>
                                    <option value="despacho" @selected(old('metodo_entrega', $version->metodo_entrega) === 'despacho')>Delivery a domicilio</option>
                                </select>
                            </dd>
                        </div>
                    </dl>
                    <div id="datos-despacho" class="mt-4 hidden space-y-3 border-t border-[#ead8d5] pt-4">
                        <p class="text-xs leading-5 text-gray-600">Ingresa dónde debemos entregar el pedido.</p>
                        <label class="block text-sm font-bold" for="calle">Calle
                            <input id="calle" name="calle" maxlength="255" value="{{ old('calle', $version->calle) }}" class="mt-1 w-full rounded-md border border-[#d9aaa3] p-2">
                        </label>
                        <label class="block text-sm font-bold" for="numero">Número
                            <input id="numero" name="numero" maxlength="50" value="{{ old('numero', $version->numero) }}" class="mt-1 w-full rounded-md border border-[#d9aaa3] p-2">
                        </label>
                        <label class="block text-sm font-bold" for="comuna">Comuna
                            <input id="comuna" name="comuna" maxlength="100" value="{{ old('comuna', $version->comuna) }}" class="mt-1 w-full rounded-md border border-[#d9aaa3] p-2">
                        </label>
                        <label class="block text-sm font-bold" for="ciudad">Ciudad
                            <input id="ciudad" name="ciudad" maxlength="100" value="{{ old('ciudad', $version->ciudad) }}" class="mt-1 w-full rounded-md border border-[#d9aaa3] p-2">
                        </label>
                        <label class="block text-sm font-bold" for="region">Región
                            <input id="region" name="region" maxlength="100" value="{{ old('region', $version->region) }}" class="mt-1 w-full rounded-md border border-[#d9aaa3] p-2">
                        </label>
                        <label class="block text-sm font-bold" for="referencias">Referencias <span class="font-normal text-gray-500">(opcional)</span>
                            <textarea id="referencias" name="referencias" maxlength="1000" rows="2" class="mt-1 w-full rounded-md border border-[#d9aaa3] p-2">{{ old('referencias', $version->referencias) }}</textarea>
                        </label>
                    </div>
                </section>
                <section class="rounded-xl bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black">Tu respuesta</h2>
                    <p class="mt-1 text-sm leading-6 text-gray-600">Confirma el teléfono registrado para identificarte y elegir cómo continuar.</p>
                    <label class="mt-5 block text-sm font-bold" for="telefono">Teléfono registrado
                        <input id="telefono" name="telefono" type="tel" required autocomplete="tel" value="{{ old('telefono') }}" placeholder="+56 9 1234 5678" class="mt-2 w-full rounded-md border border-[#d9aaa3] p-3 focus:border-[#b52f25] focus:outline-none">
                    </label>
                    <label class="mt-4 block text-sm font-bold" for="decision">Decisión
                        <select id="decision" name="decision" required class="mt-2 w-full rounded-md border border-[#d9aaa3] bg-white p-3 focus:border-[#b52f25] focus:outline-none">
                            <option value="aceptar" @selected(old('decision', 'aceptar') === 'aceptar')>Aceptar cotización</option>
                            <option value="cambios" @selected(old('decision') === 'cambios')>Solicitar cambios</option>
                            <option value="rechazar" @selected(old('decision') === 'rechazar')>Rechazar cotización</option>
                        </select>
                    </label>
                    <label class="mt-4 block text-sm font-bold" for="comentarios">Comentarios <span class="font-normal text-gray-500">(opcional)</span>
                        <textarea id="comentarios" name="comentarios" rows="3" maxlength="2000" placeholder="Indica cualquier detalle que debamos considerar." class="mt-2 w-full rounded-md border border-[#d9aaa3] p-3 focus:border-[#b52f25] focus:outline-none">{{ old('comentarios') }}</textarea>
                    </label>
                    <fieldset id="orden-compra-opciones" class="mt-5 rounded-lg border border-[#ead8d5] p-4">
                        <legend class="px-2 text-sm font-black">Orden de compra</legend>
                        <p class="mb-3 text-xs leading-5 text-gray-600">Si aceptas, autoriza la OC generada por RESMAP o adjunta la OC emitida por tu empresa.</p>
                        <label class="mt-2 flex items-start gap-3 rounded-md p-2 text-sm hover:bg-[#fffaf9]">
                            <input type="radio" name="modalidad_oc" value="automatica" @checked(old('modalidad_oc') === 'automatica') class="mt-1 accent-[#b52f25]">
                            <span><strong>Autorizar OC de RESMAP</strong><span class="mt-1 block text-xs text-gray-500">Confirmo que autorizo generar una orden de compra con esta cotización.</span></span>
                        </label>
                        <label class="mt-2 flex items-start gap-3 rounded-md p-2 text-sm hover:bg-[#fffaf9]">
                            <input type="radio" name="modalidad_oc" value="propia" @checked(old('modalidad_oc') === 'propia') class="mt-1 accent-[#b52f25]">
                            <span><strong>Adjuntar OC de mi empresa</strong><span class="mt-1 block text-xs text-gray-500">Puedes indicar su folio y subir un PDF o imagen.</span></span>
                        </label>
                        <div id="campos-oc-propia" class="mt-3 hidden space-y-3 border-t border-[#ead8d5] pt-3">
                            <label class="block text-sm font-bold" for="numero_oc">Número de OC
                                <input id="numero_oc" name="numero_oc" maxlength="100" value="{{ old('numero_oc') }}" class="mt-1 w-full rounded-md border border-[#d9aaa3] p-2">
                            </label>
                            <label class="block text-sm font-bold" for="archivo_oc">Archivo de OC
                                <input id="archivo_oc" name="archivo_oc" type="file" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-md border border-[#d9aaa3] p-2 text-sm">
                            </label>
                        </div>
                    </fieldset>
                    <button type="submit" class="mt-6 w-full rounded-md bg-[#b52f25] px-5 py-3 font-black text-white shadow-sm transition hover:bg-[#8f241d]">Enviar respuesta segura</button>
                    <p class="mt-3 text-center text-xs leading-5 text-gray-500">Esta respuesta es comercial y no genera una factura ni un documento tributario.</p>
                </section>
                </form>
            </aside>
        </div>
        <footer class="mt-8 text-center text-sm text-gray-500">¿Necesitas ayuda? <a href="mailto:resmap.la@gmail.com" class="font-bold text-[#9f2f25] underline">Contacta a RESMAP</a></footer>
    </main>
    <script>
        const decisionField = document.getElementById('decision');
        const deliveryField = document.getElementById('metodo_entrega');
        const deliveryDetails = document.getElementById('datos-despacho');
        const deliveryCharge = document.getElementById('valor-despacho');
        const quoteTotal = document.getElementById('total-cotizacion');
        const orderFieldset = document.getElementById('orden-compra-opciones');
        const ownOrderFields = document.getElementById('campos-oc-propia');
        const orderModeFields = [...document.querySelectorAll('input[name="modalidad_oc"]')];
        const deliveryAddressFields = ['calle', 'numero', 'comuna', 'ciudad', 'region']
            .map(fieldId => document.getElementById(fieldId));

        function updateDeliveryFields() {
            const deliverySelected = deliveryField.value === 'despacho';
            const shippingCost = deliveryField.value === deliveryCharge.dataset.metodoInicial
                ? Number(deliveryCharge.dataset.costoInicial)
                : Number(deliverySelected ? deliveryCharge.dataset.despacho : deliveryCharge.dataset.retiro);
            const productTotal = Number(deliveryCharge.dataset.productos);
            const formatCurrency = amount => new Intl.NumberFormat('es-CL', {
                maximumFractionDigits: 0,
            }).format(amount);

            deliveryDetails.classList.toggle('hidden', !deliverySelected);
            deliveryAddressFields.forEach(field => {
                field.disabled = !deliverySelected;
                field.required = deliverySelected && decisionField.value === 'aceptar';
            });
            deliveryCharge.textContent = `$ ${formatCurrency(shippingCost)}`;
            quoteTotal.textContent = `$ ${formatCurrency(productTotal + shippingCost)}`;
        }

        function updateOrderFields() {
            const accepting = decisionField.value === 'aceptar';
            orderFieldset.classList.toggle('opacity-50', !accepting);
            orderModeFields.forEach(field => { field.disabled = !accepting; });
            document.getElementById('numero_oc').disabled = !accepting;
            document.getElementById('archivo_oc').disabled = !accepting;
            ownOrderFields.classList.toggle('hidden', !accepting || document.querySelector('input[name="modalidad_oc"]:checked')?.value !== 'propia');
        }

        decisionField.addEventListener('change', updateOrderFields);
        decisionField.addEventListener('change', updateDeliveryFields);
        deliveryField.addEventListener('change', updateDeliveryFields);
        orderModeFields.forEach(field => field.addEventListener('change', updateOrderFields));
        updateOrderFields();
        updateDeliveryFields();
    </script>
</body>
</html>
