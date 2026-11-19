window.inicializarBusquedaClientePorRut = function (form, url, tipoSolicitud) {
    const rut = form.querySelector('input[name="rut"]');
    const mensaje = document.getElementById('mensaje-cliente-solicitud');
    const campos = {
        nombre: form.querySelector('input[name="nombre"]'),
        contacto: form.querySelector('input[name="contacto"]'),
        correo: form.querySelector('input[name="correo"]'),
        direccion: form.querySelector('input[name="direccion"]'),
        telefono: form.querySelector('#telefono'),
        prefijo: form.querySelector('#prefijo_telefono'),
        region: form.querySelector('#region_solicitud'),
        comuna: form.querySelector('#comuna_solicitud')
    };
    let temporizador;
    let controlador;
    let numeroBusqueda = 0;
    let ultimoRutConsultado = '';

    const normalizarRut = valor => valor.replace(/[^0-9kK]/g, '').toUpperCase();
    const mostrarMensaje = (texto, estado = 'info') => {
        mensaje.textContent = texto;
        mensaje.className = estado === 'error'
            ? 'mt-1 block text-xs font-medium text-red-700'
            : estado === 'success'
                ? 'mt-1 block rounded-md border border-green-200 bg-green-50 px-3 py-2 text-xs font-semibold text-green-800'
                : 'mt-1 block text-xs text-gray-600';
    };
    const limpiarDatos = () => {
        campos.nombre.value = '';
        campos.contacto.value = '';
        campos.correo.value = '';
        campos.direccion.value = '';
        campos.telefono.value = '';
        campos.prefijo.value = '+569';
        campos.prefijo.dispatchEvent(new Event('change'));
        campos.region.value = '';
        campos.region.dispatchEvent(new Event('change'));
        campos.comuna.value = '';
    };
    const buscar = async () => {
        clearTimeout(temporizador);
        const rutNormalizado = normalizarRut(rut.value);
        if (rutNormalizado.length < 8 || rutNormalizado.length > 9) {
            mostrarMensaje('Ingresa el RUT completo.');
            return;
        }
        if (rutNormalizado === ultimoRutConsultado) return;

        controlador?.abort();
        controlador = new AbortController();
        const numeroConsulta = ++numeroBusqueda;
        mostrarMensaje('Buscando cliente...');
        try {
            const parametros = new URLSearchParams({ rut: rut.value.trim() });
            const respuesta = await fetch(url + '?' + parametros.toString(), {
                headers: { 'Accept': 'application/json' },
                signal: controlador.signal
            });
            const datos = await respuesta.json();
            if (numeroConsulta !== numeroBusqueda) return;
            if (!respuesta.ok) {
                const errores = Object.values(datos.errors || {}).flat().join(' ');
                throw new Error(errores || 'No se pudieron consultar los datos del cliente.');
            }
            ultimoRutConsultado = rutNormalizado;
            if (!datos.encontrado) {
                mostrarMensaje('RUT no registrado. Completa tus datos para solicitar ' + tipoSolicitud + '.');
                return;
            }
            const cliente = datos.cliente;
            campos.nombre.value = cliente.nombre || '';
            campos.contacto.value = cliente.contacto || '';
            campos.correo.value = cliente.correo || '';
            campos.direccion.value = cliente.direccion || '';
            const digitosTelefono = String(cliente.telefono || '').replace(/\D/g, '');
            campos.telefono.value = digitosTelefono.length >= 8 ? digitosTelefono.slice(-8) : '';
            if (digitosTelefono.length > 8) {
                const prefijo = '+' + digitosTelefono.slice(0, -8);
                if (Array.from(campos.prefijo.options).some(opcion => opcion.value === prefijo)) {
                    campos.prefijo.value = prefijo;
                    campos.prefijo.dispatchEvent(new Event('change'));
                }
            }
            campos.region.value = cliente.region || '';
            campos.region.dispatchEvent(new Event('change'));
            campos.comuna.value = cliente.comuna || '';
            mostrarMensaje('RUT ya registrado. Se autocompletaron los datos del cliente.', 'success');
        } catch (error) {
            if (error.name === 'AbortError' || numeroConsulta !== numeroBusqueda) return;
            ultimoRutConsultado = '';
            mostrarMensaje(error.message, 'error');
        }
    };

    rut.addEventListener('input', () => {
        clearTimeout(temporizador);
        controlador?.abort();
        numeroBusqueda++;
        ultimoRutConsultado = '';
        limpiarDatos();
        mostrarMensaje('');
        const rutNormalizado = normalizarRut(rut.value);
        if (rutNormalizado.length >= 8 && rutNormalizado.length <= 9) {
            temporizador = setTimeout(buscar, 600);
        }
    });
    rut.addEventListener('blur', buscar);
    rut.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            buscar();
        }
    });
};
