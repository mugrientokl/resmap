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
    let mensaje = '';

    if (!productName) {
        mensaje = 'El nombre del repuesto es obligatorio.';
    } else if (/^\d+$/.test(productName)) {
        mensaje = 'El nombre del repuesto no puede contener únicamente números; debe incluir al menos una letra.';
    } else if (form.dataset.productoNuevo === 'true' && (productName.match(/\p{L}/gu) || []).length < 3) {
        mensaje = 'El nombre del repuesto debe contener al menos tres letras.';
    } else if (nombresProductos.some(nombre => normalizarNombreProducto(nombre) === normalizarNombreProducto(productName))) {
        mensaje = 'Ya existe un repuesto con ese nombre.';
    }

    errorMessage.textContent = mensaje;
    errorBox.classList.toggle('hidden', !mensaje);

    return !mensaje;
}
