<?php

use App\Http\Controllers\ActividadController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetalleVentaController;
use App\Http\Controllers\InventarioMovimientoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\SolicitudWebController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    ValidateCsrfToken::class,
    SubstituteBindings::class,
])->group(function (): void {
    Route::get('/', [CatalogoController::class, 'home'])
        ->name('home');

    Route::get('/catalogo', [CatalogoController::class, 'index'])
        ->name('catalogo.index');

    Route::post('/catalogo/solicitudes', [CatalogoController::class, 'storeRequest'])
        ->middleware('request.limit:5,60,10,60')
        ->name('catalogo.solicitudes.store');

    Route::get('/cotizaciones/{version}/documento', [CotizacionController::class, 'pdfPublico'])
        ->middleware('signed')
        ->name('cotizaciones.pdf.publico');

    Route::get('/cotizaciones/{version}/documento-externo', [CotizacionController::class, 'pdfPublico'])
        ->middleware('signed:relative')
        ->name('cotizaciones.pdf.externo');

    Route::get('/cotizaciones/{version}/responder/{token}', fn () => abort(410, 'La respuesta se coordina directamente con RESMAP.'))
        ->where('version', '(?:\{\{1\}\})?\d+')
        ->name('cotizaciones.responder.form');

    Route::post('/cotizaciones/{version}/responder/{token}', fn () => abort(410, 'La respuesta se coordina directamente con RESMAP.'))
        ->middleware('throttle:10,1')
        ->name('cotizaciones.responder');

    Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verificar'])
        ->name('whatsapp.webhook.verificar');

    Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'recibir'])
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->name('whatsapp.webhook.recibir');

    Route::get('/servicios', [ServicioController::class, 'index'])
        ->name('servicios.index');

    Route::post('/servicios', [ServicioController::class, 'store'])
        ->middleware('request.limit:5,60')
        ->name('servicios.store');

    Route::get('/login', [AuthController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('login.store');

    Route::get('/password/forgot', [AuthController::class, 'showForgotPasswordForm'])
        ->name('password.request');

    Route::post('/password/email', [AuthController::class, 'sendResetLink'])
        ->middleware('throttle:3,60')
        ->name('password.email');

    Route::get('/password/reset/{token}', [AuthController::class, 'showResetPasswordForm'])
        ->name('password.reset');

    Route::post('/password/reset', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1')
        ->name('password.update');

    Route::post('/logout', [AuthController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware(['auth', 'permiso:dashboard.ver'])
        ->name('dashboard');

    Route::get('/perfil', [AuthController::class, 'showProfile'])
        ->middleware(['auth', 'role:Administrador,Vendedor'])
        ->name('perfil.show');

    Route::put('/perfil/contrasena', [AuthController::class, 'updatePassword'])
        ->middleware(['auth', 'role:Administrador,Vendedor', 'throttle:10,1'])
        ->name('perfil.contrasena.update');

    Route::put('/perfil/nombre', [AuthController::class, 'updateName'])
        ->middleware(['auth', 'role:Administrador,Vendedor', 'throttle:10,1'])
        ->name('perfil.nombre.update');

    Route::get('/dashboard/exportar/pdf', [DashboardController::class, 'exportarPdf'])
        ->middleware(['auth', 'permiso:dashboard.ver'])
        ->name('dashboard.exportar.pdf');

    Route::get('/notificaciones', [NotificacionController::class, 'index'])
        ->middleware('auth')
        ->name('notificaciones.index');

    Route::post('/notificaciones/{id}/leer', [NotificacionController::class, 'read'])
        ->middleware('auth')
        ->name('notificaciones.read');

    Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'readAll'])
        ->middleware('auth')
        ->name('notificaciones.read-all');

    Route::get('/categorias', [CategoriaController::class, 'index'])
        ->middleware(['auth', 'permiso:categorias.ver'])
        ->name('categorias.index');

    Route::get('/categorias/crear', fn () => abort(404))
        ->middleware(['auth', 'role:Administrador'])
        ->name('categorias.create');

    Route::post('/categorias', [CategoriaController::class, 'store'])
        ->middleware(['auth', 'permiso:categorias.crear'])
        ->name('categorias.store');

    Route::get('/categorias/{id}/editar', fn () => abort(404))
        ->middleware(['auth', 'role:Administrador'])
        ->name('categorias.edit');

    Route::put('/categorias/{id}', [CategoriaController::class, 'update'])
        ->middleware(['auth', 'permiso:categorias.editar'])
        ->name('categorias.update');

    Route::delete('/categorias/{id}', [CategoriaController::class, 'destroy'])
        ->middleware(['auth', 'permiso:categorias.eliminar'])
        ->name('categorias.destroy');

    Route::get('/proveedores', [ProveedorController::class, 'index'])
        ->middleware(['auth', 'permiso:proveedores.ver'])
        ->name('proveedores.index');

    Route::post('/proveedores', [ProveedorController::class, 'store'])
        ->middleware(['auth', 'permiso:proveedores.crear'])
        ->name('proveedores.store');

    Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])
        ->middleware(['auth', 'permiso:proveedores.editar'])
        ->name('proveedores.update');

    Route::get('/productos', [ProductoController::class, 'index'])
        ->middleware(['auth', 'permiso:productos.ver'])
        ->name('productos.index');

    Route::get('/productos/exportar/excel', [ProductoController::class, 'exportarExcel'])
        ->middleware(['auth', 'permiso:productos.ver'])
        ->name('productos.exportar.excel');

    Route::get('/productos/exportar/pdf', [ProductoController::class, 'exportarPdf'])
        ->middleware(['auth', 'permiso:productos.ver'])
        ->name('productos.exportar.pdf');

    Route::get('/pos/producto-por-codigo', [ProductoController::class, 'buscarPorCodigo'])
        ->middleware(['auth', 'permiso:ventas.ver'])
        ->name('pos.producto.codigo');

    Route::get('/pos/productos/buscar', [ProductoController::class, 'buscarParaPos'])
        ->middleware(['auth', 'permiso:ventas.ver'])
        ->name('pos.productos.buscar');

    Route::get('/pos/cliente-por-rut', [ClienteController::class, 'buscarPorRut'])
        ->middleware([
            'auth',
            'permiso:ventas.ver,cotizaciones.ver',
            'throttle:60,1',
        ])
        ->name('pos.cliente.rut');

    Route::get('/productos/{id}/etiqueta', [ProductoController::class, 'etiqueta'])
        ->middleware(['auth', 'permiso:productos.ver'])
        ->name('productos.etiqueta');

    Route::get('/productos/crear', [ProductoController::class, 'create'])
        ->middleware(['auth', 'permiso:productos.crear'])
        ->name('productos.create');

    Route::post('/productos', [ProductoController::class, 'store'])
        ->middleware(['auth', 'permiso:productos.crear'])
        ->name('productos.store');

    Route::get('/productos/{id}/editar', [ProductoController::class, 'edit'])
        ->middleware(['auth', 'permiso:productos.editar'])
        ->name('productos.edit');

    Route::put('/productos/{id}', [ProductoController::class, 'update'])
        ->middleware(['auth', 'permiso:productos.editar'])
        ->name('productos.update');

    Route::put('/productos/{id}/estado-oferta', [ProductoController::class, 'estadoOferta'])
        ->middleware(['auth', 'permiso:productos.editar'])
        ->name('productos.estado-oferta');

    Route::delete('/productos/{id}', [ProductoController::class, 'destroy'])
        ->middleware(['auth', 'permiso:productos.eliminar'])
        ->name('productos.destroy');

    Route::get('/clientes', [ClienteController::class, 'index'])
        ->middleware(['auth', 'permiso:clientes.ver'])
        ->name('clientes.index');

    Route::get('/clientes/sugerencias', [ClienteController::class, 'sugerencias'])
        ->middleware(['auth', 'permiso:clientes.ver'])
        ->name('clientes.sugerencias');

    Route::get('/clientes/{id}/historial', [ClienteController::class, 'historial'])
        ->middleware(['auth', 'permiso:clientes.ver'])
        ->name('clientes.historial');

    Route::get('/clientes/{id}/ventas/pdf', [ClienteController::class, 'ventasPdf'])
        ->middleware(['auth', 'permiso:clientes.ver'])
        ->name('clientes.ventas.pdf');

    Route::get('/clientes/{id}/ventas/{ventaId}/resumen', [ClienteController::class, 'resumenVenta'])
        ->middleware(['auth', 'permiso:clientes.ver'])
        ->name('clientes.ventas.resumen');

    Route::get('/clientes/{id}/editar', [ClienteController::class, 'edit'])
        ->middleware(['auth', 'permiso:clientes.editar'])
        ->name('clientes.edit');

    Route::put('/clientes/{id}', [ClienteController::class, 'update'])
        ->middleware(['auth', 'permiso:clientes.editar'])
        ->name('clientes.update');

    Route::delete('/clientes/{id}', [ClienteController::class, 'destroy'])
        ->middleware(['auth', 'permiso:clientes.eliminar'])
        ->name('clientes.destroy');

    Route::get('/pos', fn () => view('pos.index'))
        ->middleware(['auth', 'permiso:ventas.ver'])->name('pos.index');

    Route::post('/ventas', [VentaController::class, 'store'])
        ->middleware(['auth', 'permiso:ventas.crear', 'throttle:30,1']);

    Route::get('/ventas/{venta}/resumen-interno', [VentaController::class, 'resumenInterno'])
        ->middleware(['auth', 'permiso:ventas.ver,cotizaciones.ver'])
        ->whereNumber('venta')
        ->name('ventas.resumen-interno');

    Route::post('/ventas/{venta}/confirmar-comercial', fn () => abort(410, 'Esta etapa ya no forma parte del flujo de cotizaciones.'))
        ->middleware(['auth', 'role:Administrador,Vendedor'])
        ->name('ventas.confirmar-comercial');

    Route::post('/ventas/{venta}/registrar-pago-credito', fn () => abort(410, 'Esta etapa ya no forma parte del flujo de cotizaciones.'))
        ->middleware(['auth', 'role:Administrador,Vendedor'])
        ->name('ventas.registrar-pago-credito');

    Route::post('/ventas/{venta}/simular-facturacion', fn () => abort(410, 'Esta etapa ya no forma parte del flujo de cotizaciones.'))
        ->middleware(['auth', 'role:Administrador'])
        ->name('ventas.simular-facturacion');

    Route::post('/ventas/{venta}/registrar-entrega', fn () => abort(410, 'Esta etapa ya no forma parte del flujo de cotizaciones.'))
        ->middleware(['auth', 'role:Administrador,Vendedor'])
        ->name('ventas.registrar-entrega');

    Route::get('/cotizaciones/ordenes-compra/{ordenCompra}', [CotizacionController::class, 'descargarOrdenCompra'])
        ->middleware(['auth', 'permiso:cotizaciones.ver'])
        ->name('cotizaciones.orden-compra');

    Route::get('/cotizaciones/ordenes-compra/{ordenCompra}/ver', [CotizacionController::class, 'verOrdenCompra'])
        ->middleware(['auth', 'permiso:cotizaciones.ver'])
        ->name('cotizaciones.orden-compra.ver');

    Route::get('/cotizaciones/versiones/{version}/pdf', [CotizacionController::class, 'pdf'])
        ->middleware(['auth', 'permiso:cotizaciones.ver'])
        ->name('cotizaciones.pdf');

    Route::post('/cotizaciones/versiones/{version}/enviar', [CotizacionController::class, 'enviar'])
        ->middleware(['auth', 'permiso:cotizaciones.editar'])
        ->name('cotizaciones.enviar');

    Route::get('/cotizaciones/versiones/{version}/estado-envio', [CotizacionController::class, 'estadoEnvio'])
        ->middleware(['auth', 'permiso:cotizaciones.ver', 'throttle:30,1'])
        ->name('cotizaciones.estado-envio');

    Route::post('/cotizaciones/versiones/{version}/respuesta-interna', [CotizacionController::class, 'registrarRespuesta'])
        ->middleware(['auth', 'permiso:cotizaciones.editar'])
        ->name('cotizaciones.respuesta-interna');

    Route::post('/cotizaciones/versiones/{version}/convertir-venta', [CotizacionController::class, 'convertirEnVenta'])
        ->middleware(['auth', 'permiso:cotizaciones.editar', 'permiso:ventas.crear'])
        ->name('cotizaciones.convertir-venta');

    Route::post('/ventas/{venta}/confirmar-pago', [CotizacionController::class, 'confirmarPago'])
        ->middleware(['auth', 'permiso:cotizaciones.editar', 'permiso:ventas.crear'])
        ->whereNumber('venta')
        ->name('ventas.confirmar-pago');

    Route::post('/ventas/{venta}/generar-resumen', [CotizacionController::class, 'generarResumen'])
        ->middleware(['auth', 'permiso:cotizaciones.editar'])
        ->name('ventas.generar-resumen');

    Route::get('/inventario/movimientos', [InventarioMovimientoController::class, 'index'])
        ->middleware(['auth', 'permiso:movimientos.ver'])
        ->name('inventario.movimientos');

    Route::get('/inventario/movimientos/{movimiento}', [InventarioMovimientoController::class, 'show'])
        ->middleware(['auth', 'permiso:movimientos.ver'])
        ->name('inventario.movimientos.show');

    Route::post('/inventario/movimientos', [InventarioMovimientoController::class, 'store'])
        ->middleware(['auth', 'permiso:movimientos.crear'])
        ->name('inventario.movimientos.store');

    Route::get('/reportes', [ReporteController::class, 'index'])
        ->middleware(['auth', 'permiso:reportes.ver'])
        ->name('reportes.index');

    Route::get('/reportes/exportar/{formato}', [ReporteController::class, 'exportar'])
        ->middleware(['auth', 'permiso:reportes.ver'])
        ->name('reportes.exportar');

    Route::middleware(['auth', 'can:ver-actividad', 'throttle:60,1'])->prefix('actividad')->name('actividad.')->group(function (): void {
        Route::get('/', [ActividadController::class, 'index'])->name('index');
        Route::get('/exportar/{formato}', [ActividadController::class, 'exportar'])
            ->whereIn('formato', ['csv', 'pdf'])
            ->middleware('throttle:10,1')
            ->name('exportar');
        Route::get('/{auditoria}', [ActividadController::class, 'show'])->whereNumber('auditoria')->name('show');
    });

    Route::redirect('/reportes/auditoria/exportar/{formato}', '/actividad', 301);

    Route::get('/reportes/ventas/{id}', [ReporteController::class, 'detalleVenta'])
        ->middleware(['auth', 'permiso:reportes.ver'])
        ->name('reportes.ventas.detalle');

    Route::get('/reportes/ventas/{id}/factura', [ReporteController::class, 'facturaVenta'])
        ->middleware(['auth', 'permiso:reportes.ver'])
        ->name('reportes.ventas.factura');

    Route::get('/reportes/ventas/{id}/resumen-interno', [ReporteController::class, 'facturaVenta'])
        ->middleware(['auth', 'permiso:reportes.ver'])
        ->name('reportes.ventas.resumen');

    Route::get('/reportes/ordenes-compra/{ordenCompra}', [ReporteController::class, 'descargarOrdenCompra'])
        ->middleware(['auth', 'permiso:reportes.ver'])
        ->name('reportes.orden-compra');

    Route::get('/auditoria', fn () => redirect()->route('actividad.index', [], 301))
        ->middleware(['auth', 'can:ver-actividad']);

    Route::get('/backups', [BackupController::class, 'index'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('backups.index');

    Route::post('/backups/{filename}/restore', [BackupController::class, 'restore'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('backups.restore');

    Route::get('/backups/{filename}', [BackupController::class, 'download'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('backups.download');

    Route::get('/administracion', [ConfiguracionController::class, 'index'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('configuracion.index');

    Route::put('/administracion/fondo', [ConfiguracionController::class, 'actualizarFondo'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('configuracion.fondo.actualizar');

    Route::post('/administracion/fondos', [ConfiguracionController::class, 'subirFondo'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('configuracion.fondos.subir');

    Route::put('/administracion/logo', [ConfiguracionController::class, 'actualizarLogo'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('configuracion.logo.actualizar');

    Route::post('/administracion/logos', [ConfiguracionController::class, 'subirLogo'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('configuracion.logos.subir');

    Route::put('/administracion/fuente', [ConfiguracionController::class, 'actualizarFuente'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('configuracion.fuente.actualizar');

    Route::put('/administracion/notificaciones', [ConfiguracionController::class, 'actualizarNotificaciones'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('configuracion.notificaciones.actualizar');

    Route::delete('/administracion/fondos/{id}', [ConfiguracionController::class, 'eliminarFondo'])
        ->middleware(['auth', 'role:Administrador'])
        ->whereUuid('id')
        ->name('configuracion.fondos.eliminar');

    Route::get('/detalle-ventas', [DetalleVentaController::class, 'index'])
        ->middleware(['auth', 'permiso:reportes.ver']);

    Route::get('/detalle-ventas/{id}', [DetalleVentaController::class, 'show'])
        ->middleware(['auth', 'permiso:reportes.ver']);

    Route::get('/solicitudes-web', [SolicitudWebController::class, 'index'])
        ->middleware(['auth', 'permiso:cotizaciones.ver'])
        ->name('solicitudes.index');

    Route::get('/solicitudes-web/{id}', [SolicitudWebController::class, 'show'])
        ->middleware(['auth', 'permiso:cotizaciones.ver'])
        ->name('solicitudes.show');

    Route::get('/solicitudes-web/{id}/seguimiento', [SolicitudWebController::class, 'seguimiento'])
        ->middleware(['auth', 'permiso:cotizaciones.ver'])
        ->whereNumber('id')
        ->name('solicitudes.seguimiento');

    Route::get('/solicitudes-web/{id}/pdf', [SolicitudWebController::class, 'descargarSolicitudPdf'])
        ->middleware(['auth', 'permiso:cotizaciones.ver'])
        ->name('solicitudes.pdf');

    Route::patch('/solicitudes-web/{id}/estado', [SolicitudWebController::class, 'actualizarEstado'])
        ->middleware(['auth', 'permiso:cotizaciones.editar'])
        ->name('solicitudes.estado');

    Route::post('/solicitudes-web/{id}/registrar-servicio', [SolicitudWebController::class, 'registrarServicio'])
        ->middleware(['auth', 'permiso:cotizaciones.editar', 'permiso:ventas.crear'])
        ->name('solicitudes.registrar-servicio');

    Route::post('/solicitudes-web/{solicitud}/cotizacion', [CotizacionController::class, 'crearVersion'])
        ->middleware(['auth', 'permiso:cotizaciones.crear'])
        ->name('solicitudes.cotizacion.crear');

    Route::get('/usuarios', [UserController::class, 'index'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('usuarios.index');

    Route::post('/usuarios', [UserController::class, 'store'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('usuarios.store');

    Route::put('/usuarios/{user}/permisos', [UserController::class, 'actualizarPermisos'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('usuarios.permisos.update');

    Route::patch('/usuarios/{user}/estado', [UserController::class, 'actualizarEstado'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('usuarios.estado');

    Route::post('/usuarios/{user}/enlace-contrasena', [UserController::class, 'enviarEnlaceContrasena'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('usuarios.enlace-contrasena');

    Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('usuarios.destroy');

    Route::post('/usuarios/{user}/restaurar', [UserController::class, 'restaurar'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('usuarios.restaurar');

    Route::delete('/usuarios/{user}/eliminar-definitivamente', [UserController::class, 'eliminarDefinitivamente'])
        ->middleware(['auth', 'role:Administrador'])
        ->name('usuarios.eliminar-definitivamente');
});
