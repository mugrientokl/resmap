(() => {
    const limpiar = valor =>
        String(valor ?? '').replace(/[^0-9kK]/g, '').toUpperCase();

    const normalizar = valor => {
        const limpio = limpiar(valor);

        if (limpio.length < 2) return limpio;

        return limpio.slice(0, -1) + '-' + limpio.slice(-1);
    };

    const formatear = valor => {
        const limpio = limpiar(valor).slice(0, 9);

        if (limpio.length < 2) return limpio;

        const cuerpo = limpio.slice(0, -1).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return cuerpo + '-' + limpio.slice(-1);
    };

    window.ResmapRut = { limpiar, normalizar, formatear };

    // En fase de captura para que el valor ya esté formateado
    // antes de que lo lean los demás listeners del campo.
    document.addEventListener('input', evento => {
        const campo = evento.target;

        if (!(campo instanceof HTMLInputElement) || !campo.hasAttribute('data-rut')) {
            return;
        }

        const formateado = formatear(campo.value);

        if (campo.value !== formateado) {
            campo.value = formateado;
        }
    }, true);

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('input[data-rut]').forEach(campo => {
            campo.value = formatear(campo.value);
        });
    });
})();
