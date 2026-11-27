document.querySelectorAll('[data-estado-envio]').forEach(contenedor => {
    const correo = contenedor.querySelector('[data-envio-correo]');
    const whatsapp = contenedor.querySelector('[data-envio-whatsapp]');
    const aviso = contenedor.querySelector('[data-envio-aviso]');
    const boton = contenedor.querySelector('[data-envio-consultar]');
    let consultas = 0;
    let consultando = false;
    let temporizador;

    const consultar = async () => {
        if (consultando) return;
        consultando = true;
        boton.disabled = true;
        consultas++;

        try {
            const respuesta = await fetch(contenedor.dataset.envioUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
                redirect: 'error',
                signal: AbortSignal.timeout(10000),
            });
            if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);
            const estados = await respuesta.json();
            if (typeof estados.email_estado !== 'string'
                || typeof estados.whatsapp_estado !== 'string'
                || typeof estados.pendiente !== 'boolean') {
                throw new Error('Respuesta de estado de envío inválida');
            }

            correo.textContent = estados.email_estado.replaceAll('_', ' ');
            whatsapp.textContent = estados.whatsapp_estado.replaceAll('_', ' ');
            contenedor.dataset.envioPendiente = estados.pendiente ? '1' : '0';
            if (!estados.pendiente) {
                aviso.textContent = '· Estados de envío actualizados';
                boton.hidden = true;
            } else if (consultas >= 12) {
                aviso.textContent = '· El envío sigue en cola. Verifica que el worker esté activo o consulta nuevamente.';
                boton.hidden = false;
            } else {
                aviso.textContent = '· Consultando estado automáticamente, sin recargar la página';
                temporizador = setTimeout(consultar, 5000);
            }
        } catch (error) {
            console.error('No fue posible consultar el estado de envío de la cotización.', error);
            aviso.textContent = '· No se pudo consultar el estado. Se conservan los últimos estados conocidos; puedes reintentar.';
            boton.hidden = false;
        } finally {
            consultando = false;
            boton.disabled = false;
        }
    };

    boton.addEventListener('click', () => {
        clearTimeout(temporizador);
        consultas = 0;
        boton.hidden = true;
        consultar();
    });
    if (contenedor.dataset.envioPendiente === '1') {
        temporizador = setTimeout(consultar, 5000);
    }
});
