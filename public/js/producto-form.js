const nombresProductos = JSON.parse(document.getElementById('nombres-productos').textContent);

function normalizarNombreProducto(nombre) {
    return nombre.normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .replace(/ß/g, 'ss')
        .trim()
        .replace(/\s+/gu, ' ');
}

function validateProductForm(form) {
    const errorBox = form.querySelector('.product-validation-error');
    const errorMessage = form.querySelector('.product-validation-message');
    const productName = form.querySelector('input[name="nombre"]').value.trim();
    const codeInput = form.querySelector('input[name="codigo_barra"]');
    const productCode = codeInput?.value.trim().toUpperCase() ?? '';
    let mensaje = '';

    if (!productName) {
        mensaje = 'El nombre del repuesto es obligatorio.';
    } else if (/^\d+$/.test(productName)) {
        mensaje = 'El nombre del repuesto no puede contener únicamente números; debe incluir al menos una letra.';
    } else if (form.dataset.productoNuevo === 'true' && (productName.match(/\p{L}/gu) || []).length < 3) {
        mensaje = 'El nombre del repuesto debe contener al menos tres letras.';
    } else if (nombresProductos.some(nombre => normalizarNombreProducto(nombre) === normalizarNombreProducto(productName))) {
        mensaje = 'Ya existe un repuesto con ese nombre.';
    } else if (form.dataset.codigoOriginal && productCode !== String(form.dataset.codigoOriginal).trim().toUpperCase()) {
        const prefijo = productName.normalize('NFD')
            .replace(/\p{M}/gu, '')
            .replace(/[^A-Za-z]/g, '')
            .toUpperCase()
            .slice(0, 3);

        if (!/^[A-Z]{3}\d{3,}$/.test(productCode)) {
            mensaje = 'El código debe tener tres letras seguidas de al menos tres números.';
        } else if (!productCode.startsWith(prefijo)) {
            mensaje = 'El código debe comenzar con las tres primeras letras del nombre.';
        }
    }

    errorMessage.textContent = mensaje;
    errorBox.classList.toggle('hidden', !mensaje);

    return !mensaje;
}
