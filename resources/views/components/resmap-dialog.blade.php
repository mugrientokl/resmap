<div id="resmap-dialog" class="fixed inset-0 hidden items-center justify-center bg-[#241817]/60 p-4" style="z-index: 100" role="dialog" aria-modal="true" aria-labelledby="resmap-dialog-title" aria-describedby="resmap-dialog-message" aria-hidden="true">
    <section class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white shadow-2xl">
        <header id="resmap-dialog-header" class="flex items-center gap-3 bg-[#8f241d] px-6 py-5 text-white">
            <span id="resmap-dialog-icon" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-lg font-black" aria-hidden="true">!</span>
            <h2 id="resmap-dialog-title" class="text-lg font-black">RESMAP</h2>
        </header>
        <div class="p-6">
            <p id="resmap-dialog-message" class="whitespace-pre-line leading-6 text-gray-700"></p>
            <div id="resmap-dialog-input-wrap" class="mt-4 hidden">
                <label id="resmap-dialog-input-label" for="resmap-dialog-input" class="block text-sm font-bold text-[#721d18]">Dato requerido</label>
                <input id="resmap-dialog-input" type="text" class="mt-2 w-full rounded-md border border-gray-300 p-2.5 text-sm shadow-sm">
                <p id="resmap-dialog-input-error" class="mt-2 hidden text-sm font-medium text-red-700" role="alert"></p>
            </div>
            <div id="resmap-dialog-select-wrap" class="mt-4 hidden">
                <label id="resmap-dialog-select-label" for="resmap-dialog-select" class="block text-sm font-bold text-[#721d18]">Dato requerido</label>
                <select id="resmap-dialog-select" class="mt-2 w-full rounded-md border border-gray-300 p-2.5 text-sm shadow-sm"></select>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button id="resmap-dialog-cancel" type="button" class="hidden rounded-md border border-[#d9aaa3] px-5 py-2.5 font-bold text-[#8f241d] hover:bg-[#fffaf9]">Cancelar</button>
                <button id="resmap-dialog-confirm" type="button" class="rounded-md bg-[#b52f25] px-5 py-2.5 font-bold text-white hover:bg-[#8f241d]">Entendido</button>
            </div>
        </div>
    </section>
</div>
<script>
    const resmapDialog = document.getElementById('resmap-dialog');
    const resmapDialogHeader = document.getElementById('resmap-dialog-header');
    const resmapDialogTitle = document.getElementById('resmap-dialog-title');
    const resmapDialogMessage = document.getElementById('resmap-dialog-message');
    const resmapDialogConfirm = document.getElementById('resmap-dialog-confirm');
    const resmapDialogCancel = document.getElementById('resmap-dialog-cancel');
    const resmapDialogInputWrap = document.getElementById('resmap-dialog-input-wrap');
    const resmapDialogInput = document.getElementById('resmap-dialog-input');
    const resmapDialogInputLabel = document.getElementById('resmap-dialog-input-label');
    const resmapDialogInputError = document.getElementById('resmap-dialog-input-error');
    const resmapDialogSelectWrap = document.getElementById('resmap-dialog-select-wrap');
    const resmapDialogSelect = document.getElementById('resmap-dialog-select');
    const resmapDialogSelectLabel = document.getElementById('resmap-dialog-select-label');

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
        const colores = {
            success: ['bg-green-700', 'bg-green-700', 'hover:bg-green-800'],
            error: ['bg-red-700', 'bg-red-700', 'hover:bg-red-800'],
            warning: ['bg-amber-800', 'bg-amber-800', 'hover:bg-amber-900'],
            info: ['bg-[#8f241d]', 'bg-[#b52f25]', 'hover:bg-[#8f241d]'],
        };
        const clases = colores[type] || colores.info;
        Object.values(colores).forEach(([header, button, hover]) => {
            resmapDialogHeader.classList.remove(header);
            resmapDialogConfirm.classList.remove(button, hover);
        });
        resmapDialogHeader.classList.add(clases[0]);
        resmapDialogConfirm.classList.add(clases[1], clases[2]);

        resmapDialog.classList.remove('hidden');
        resmapDialog.classList.add('flex');
        resmapDialog.setAttribute('aria-hidden', 'false');
        (cancelText !== null ? resmapDialogCancel : resmapDialogConfirm).focus();

        return new Promise(resolve => {
            let timer;
            const finish = result => {
                window.clearTimeout(timer);
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
            if (cancelText === null) {
                timer = window.setTimeout(accept, 5000);
            }
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
    window.resmapPrompt = (message, {
        title = 'Ingresa un dato',
        confirmText = 'Continuar',
        label = 'Dato',
        placeholder = '',
        inputMode = 'text',
        validate = null,
    } = {}) => {
        resmapDialogTitle.textContent = title;
        resmapDialogMessage.textContent = message;
        resmapDialogConfirm.textContent = confirmText;
        resmapDialogCancel.textContent = 'Cancelar';
        resmapDialogCancel.classList.remove('hidden');
        resmapDialogInputWrap.classList.remove('hidden');
        resmapDialogInputLabel.textContent = label;
        resmapDialogInput.placeholder = placeholder;
        resmapDialogInput.inputMode = inputMode;
        resmapDialogInput.value = '';
        resmapDialogInputError.textContent = '';
        resmapDialogInputError.classList.add('hidden');
        document.getElementById('resmap-dialog-icon').textContent = '!';
        resmapDialogHeader.className = resmapDialogHeader.className
            .replace(/bg-(green|red|amber)-[0-9]+|bg-\[#8f241d\]/g, '')
            .trim();
        resmapDialogHeader.classList.add('bg-[#8f241d]');
        resmapDialogConfirm.className = resmapDialogConfirm.className
            .replace(/bg-(green|red|amber)-[0-9]+|bg-\[#b52f25\]|hover:bg-(green|red|amber)-[0-9]+|hover:bg-\[#8f241d\]/g, '')
            .trim();
        resmapDialogConfirm.classList.add('bg-[#b52f25]', 'hover:bg-[#8f241d]');

        const previousFocus = document.activeElement;
        resmapDialog.classList.remove('hidden');
        resmapDialog.classList.add('flex');
        resmapDialog.setAttribute('aria-hidden', 'false');
        resmapDialogInput.focus();

        return new Promise(resolve => {
            const finish = result => {
                resmapDialog.classList.add('hidden');
                resmapDialog.classList.remove('flex');
                resmapDialog.setAttribute('aria-hidden', 'true');
                resmapDialogInputWrap.classList.add('hidden');
                resmapDialogConfirm.removeEventListener('click', accept);
                resmapDialogCancel.removeEventListener('click', cancel);
                resmapDialog.removeEventListener('click', clickOutside);
                document.removeEventListener('keydown', keydown);
                previousFocus?.focus();
                resolve(result);
            };
            const accept = () => {
                const valor = resmapDialogInput.value.trim();
                if (typeof validate === 'function') {
                    const mensajeError = validate(valor);
                    if (mensajeError) {
                        resmapDialogInputError.textContent = mensajeError;
                        resmapDialogInputError.classList.remove('hidden');
                        resmapDialogInput.focus();
                        return;
                    }
                } else if (!valor) {
                    resmapDialogInputError.textContent = 'Ingresa este dato obligatorio.';
                    resmapDialogInputError.classList.remove('hidden');
                    resmapDialogInput.focus();
                    return;
                }
                finish(valor);
            };
            const cancel = () => finish(null);
            const clickOutside = event => {
                if (event.target === resmapDialog) cancel();
            };
            const keydown = event => {
                if (event.key === 'Escape') cancel();
                if (event.key === 'Enter') {
                    event.preventDefault();
                    accept();
                }
            };
            resmapDialogConfirm.addEventListener('click', accept);
            resmapDialogCancel.addEventListener('click', cancel);
            resmapDialog.addEventListener('click', clickOutside);
            document.addEventListener('keydown', keydown);
        });
    };
    window.resmapPromptWithSelect = (message, {
        title = 'Ingresa los datos',
        confirmText = 'Continuar',
        inputLabel = 'Dato',
        placeholder = '',
        inputMode = 'text',
        selectLabel = 'Selecciona una opción',
        selectOptions = [],
        validateInput = null,
    } = {}) => {
        resmapDialogTitle.textContent = title;
        resmapDialogMessage.textContent = message;
        resmapDialogConfirm.textContent = confirmText;
        resmapDialogCancel.textContent = 'Cancelar';
        resmapDialogCancel.classList.remove('hidden');
        resmapDialogInputWrap.classList.remove('hidden');
        resmapDialogSelectWrap.classList.remove('hidden');
        resmapDialogInputLabel.textContent = inputLabel;
        resmapDialogInput.placeholder = placeholder;
        resmapDialogInput.inputMode = inputMode;
        resmapDialogInput.value = '';
        resmapDialogSelectLabel.textContent = selectLabel;
        resmapDialogSelect.replaceChildren();
        selectOptions.forEach(option => {
            const elemento = document.createElement('option');
            elemento.value = option.value;
            elemento.textContent = option.label;
            resmapDialogSelect.append(elemento);
        });
        resmapDialogInputError.textContent = '';
        resmapDialogInputError.classList.add('hidden');
        document.getElementById('resmap-dialog-icon').textContent = '!';

        const previousFocus = document.activeElement;
        resmapDialog.classList.remove('hidden');
        resmapDialog.classList.add('flex');
        resmapDialog.setAttribute('aria-hidden', 'false');
        resmapDialogInput.focus();

        return new Promise(resolve => {
            const finish = result => {
                resmapDialog.classList.add('hidden');
                resmapDialog.classList.remove('flex');
                resmapDialog.setAttribute('aria-hidden', 'true');
                resmapDialogInputWrap.classList.add('hidden');
                resmapDialogSelectWrap.classList.add('hidden');
                resmapDialogConfirm.removeEventListener('click', accept);
                resmapDialogCancel.removeEventListener('click', cancel);
                resmapDialog.removeEventListener('click', clickOutside);
                document.removeEventListener('keydown', keydown);
                previousFocus?.focus();
                resolve(result);
            };
            const accept = () => {
                const valor = resmapDialogInput.value.trim();
                const mensajeError = typeof validateInput === 'function'
                    ? validateInput(valor)
                    : (!valor ? 'Ingresa este dato obligatorio.' : null);
                if (mensajeError) {
                    resmapDialogInputError.textContent = mensajeError;
                    resmapDialogInputError.classList.remove('hidden');
                    resmapDialogInput.focus();
                    return;
                }
                finish({
                    input: valor,
                    select: resmapDialogSelect.value,
                });
            };
            const cancel = () => finish(null);
            const clickOutside = event => {
                if (event.target === resmapDialog) cancel();
            };
            const keydown = event => {
                if (event.key === 'Escape') cancel();
                if (event.key === 'Enter') {
                    event.preventDefault();
                    accept();
                }
            };
            resmapDialogConfirm.addEventListener('click', accept);
            resmapDialogCancel.addEventListener('click', cancel);
            resmapDialog.addEventListener('click', clickOutside);
            document.addEventListener('keydown', keydown);
        });
    };

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
