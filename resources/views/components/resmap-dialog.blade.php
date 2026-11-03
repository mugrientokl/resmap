<div id="resmap-dialog" class="fixed inset-0 hidden items-center justify-center bg-[#241817]/60 p-4" style="z-index: 100" role="dialog" aria-modal="true" aria-labelledby="resmap-dialog-title" aria-describedby="resmap-dialog-message" aria-hidden="true">
    <section class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white shadow-2xl">
        <header class="flex items-center gap-3 bg-[#8f241d] px-6 py-5 text-white">
            <span id="resmap-dialog-icon" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-lg font-black" aria-hidden="true">!</span>
            <h2 id="resmap-dialog-title" class="text-lg font-black">RESMAP</h2>
        </header>
        <div class="p-6">
            <p id="resmap-dialog-message" class="whitespace-pre-line leading-6 text-gray-700"></p>
            <div class="mt-6 flex justify-end gap-3">
                <button id="resmap-dialog-cancel" type="button" class="hidden rounded-md border border-[#d9aaa3] px-5 py-2.5 font-bold text-[#8f241d] hover:bg-[#fffaf9]">Cancelar</button>
                <button id="resmap-dialog-confirm" type="button" class="rounded-md bg-[#b52f25] px-5 py-2.5 font-bold text-white hover:bg-[#8f241d]">Entendido</button>
            </div>
        </div>
    </section>
</div>
<script>
    const resmapDialog = document.getElementById('resmap-dialog');
    const resmapDialogTitle = document.getElementById('resmap-dialog-title');
    const resmapDialogMessage = document.getElementById('resmap-dialog-message');
    const resmapDialogConfirm = document.getElementById('resmap-dialog-confirm');
    const resmapDialogCancel = document.getElementById('resmap-dialog-cancel');

    function showResmapDialog(message, { title = 'Aviso', confirmText = 'Entendido', cancelText = null, type = 'info' } = {}) {
        if (!resmapDialog) {
            throw new Error('No se pudo cargar el diálogo de RESMAP.');
        }

        const previousFocus = document.activeElement;
        resmapDialogTitle.textContent = title;
        resmapDialogMessage.textContent = message;
        resmapDialogConfirm.textContent = confirmText;
        resmapDialogCancel.textContent = cancelText ?? 'Cancelar';
        resmapDialogCancel.classList.toggle('hidden', cancelText === null);
        document.getElementById('resmap-dialog-icon').textContent = type === 'success' ? '✓' : type === 'error' ? '!' : type === 'warning' ? '!' : 'i';

        resmapDialog.classList.remove('hidden');
        resmapDialog.classList.add('flex');
        resmapDialog.setAttribute('aria-hidden', 'false');
        (cancelText !== null ? resmapDialogCancel : resmapDialogConfirm).focus();

        return new Promise(resolve => {
            const finish = result => {
                resmapDialog.classList.add('hidden');
                resmapDialog.classList.remove('flex');
                resmapDialog.setAttribute('aria-hidden', 'true');
                resmapDialogConfirm.removeEventListener('click', accept);
                resmapDialogCancel.removeEventListener('click', cancel);
                resmapDialog.removeEventListener('click', clickOutside);
                document.removeEventListener('keydown', keydown);
                previousFocus?.focus();
                resolve(result);
            };
            const accept = () => finish(true);
            const cancel = () => finish(false);
            const focusableElements = () => [...resmapDialog.querySelectorAll('button:not([disabled])')]
                .filter(element => !element.classList.contains('hidden'));
            const clickOutside = event => {
                if (event.target === resmapDialog && cancelText !== null) {
                    cancel();
                }
            };
            const keydown = event => {
                if (event.key === 'Escape' && cancelText !== null) {
                    cancel();
                } else if (event.key === 'Enter' && cancelText === null) {
                    accept();
                } else if (event.key === 'Tab') {
                    const focusable = focusableElements();
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];

                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            };

            resmapDialogConfirm.addEventListener('click', accept);
            resmapDialogCancel.addEventListener('click', cancel);
            resmapDialog.addEventListener('click', clickOutside);
            document.addEventListener('keydown', keydown);
        });
    }

    window.resmapAlert = (message, type = 'info') => showResmapDialog(message, {
        title: type === 'success' ? 'Operación completada' : type === 'error' ? 'No se pudo completar' : type === 'warning' ? 'Revisa esta información' : 'Aviso RESMAP',
        type,
    });
    window.resmapConfirm = (message, { title = 'Confirma esta acción', confirmText = 'Sí, continuar' } = {}) => showResmapDialog(message, {
        title,
        confirmText,
        cancelText: 'Cancelar',
        type: 'warning',
    });

    const pendingConfirmations = new WeakSet();
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirmMessage) {
            return;
        }

        event.preventDefault();
        if (pendingConfirmations.has(form)) {
            return;
        }

        pendingConfirmations.add(form);
        window.resmapConfirm(form.dataset.confirmMessage, {
            title: form.dataset.confirmTitle || 'Confirma esta acción',
            confirmText: form.dataset.confirmText || 'Sí, continuar',
        }).then(confirmed => {
            if (confirmed) {
                form.dispatchEvent(new CustomEvent('resmap:confirmed'));
                HTMLFormElement.prototype.submit.call(form);
            } else {
                form.dispatchEvent(new CustomEvent('resmap:confirmation-cancelled'));
            }
        }).finally(() => pendingConfirmations.delete(form));
    });
</script>
