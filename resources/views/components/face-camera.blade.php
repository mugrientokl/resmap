<div data-face-modal class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="face-camera-title" aria-hidden="true">
    <section class="w-full max-w-xl overflow-hidden border border-[#e8c8c3] bg-white shadow-2xl">
        <header class="flex items-center justify-between bg-[#8f241d] px-5 py-4 text-white">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#ffd5c9]">RESMAP</p>
                <h2 id="face-camera-title" class="mt-1 text-lg font-black">Verificación facial</h2>
            </div>
            <button type="button" data-face-close class="flex h-10 w-10 items-center justify-center text-2xl text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white" aria-label="Cerrar cámara">×</button>
        </header>
        <div class="p-5 sm:p-6">
            <div class="overflow-hidden bg-[#241817]">
                <video data-face-video class="aspect-video w-full object-cover" autoplay playsinline muted aria-label="Vista previa de la cámara"></video>
            </div>
            <p data-face-modal-status class="mt-4 min-h-6 text-sm font-bold text-[#721d18]" role="status" aria-live="polite">Preparando la cámara…</p>
            <div class="mt-5 flex flex-wrap justify-end gap-3">
                <button type="button" data-face-close class="min-h-11 border border-gray-300 px-4 py-2 font-bold text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="button" data-face-capture class="min-h-11 bg-[#b52f25] px-5 py-2 font-bold text-white hover:bg-[#8f241d] disabled:cursor-not-allowed disabled:opacity-50" disabled>Capturar rostro</button>
            </div>
        </div>
    </section>
</div>