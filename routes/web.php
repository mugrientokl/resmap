<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetalleVentaController;
use App\Http\Controllers\InventarioMovimientoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\SolicitudWebController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VentaController;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
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
    Route::get('/', [CatalogoController::class, 'home'])->name('home');
    Route::get('/catalogo', [CatalogoController::class, 'index'])->name('catalogo.index');
    Route::post('/catalogo/solicitudes', [CatalogoController::class, 'storeRequest'])->middleware('throttle:10,1')->name('catalogo.solicitudes.store');
    Route::get('/servicios', [ServicioController::class, 'index'])->name('servicios.index');
    Route::post('/servicios', [ServicioController::class, 'store'])->middleware('throttle:10,1')->name('servicios.store');
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/password/forgot', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/password/email', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/password/reset/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('password.update');
    Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'role:Administrador,Vendedor'])->name('dashboard');
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->middleware('auth')->name('notificaciones.index');
    Route::post('/notificaciones/{id}/leer', [NotificacionController::class, 'read'])->middleware('auth')->name('notificaciones.read');

    Route::get('/categorias', [CategoriaController::class, 'index'])->middleware(['auth', 'role:Administrador'])->name('categorias.index');
    Route::get('/categorias/crear', fn () => abort(404))->middleware(['auth', 'role:Administrador'])->name('categorias.create');
    Route::post('/categorias', [CategoriaController::class, 'store'])->middleware(['auth', 'role:Administrador'])->name('categorias.store');
    Route::get('/categorias/{id}/editar', fn () => abort(404))->middleware(['auth', 'role:Administrador'])->name('categorias.edit');
    Route::put('/categorias/{id}', [CategoriaController::class, 'update'])->middleware(['auth', 'role:Administrador'])->name('categorias.update');
    Route::delete('/categorias/{id}', [CategoriaController::class, 'destroy'])->middleware(['auth', 'role:Administrador'])->name('categorias.destroy');

    Route::get('/productos', [ProductoController::class, 'index'])->middleware(['auth', 'role:Administrador,Vendedor'])->name('productos.index');
    Route::get('/productos/exportar/excel', [ProductoController::class, 'exportarExcel'])->middleware(['auth', 'role:Administrador,Vendedor'])->name('productos.exportar.excel');
    Route::get('/productos/exportar/pdf', [ProductoController::class, 'exportarPdf'])->middleware(['auth', 'role:Administrador,Vendedor'])->name('productos.exportar.pdf');
    Route::get('/pos/producto-por-codigo', [ProductoController::class, 'buscarPorCodigo'])->middleware(['auth', 'role:Administrador,Vendedor'])->name('pos.producto.codigo');
    Route::get('/productos/{id}/etiqueta', [ProductoController::class, 'etiqueta'])->middleware(['auth', 'role:Administrador,Vendedor'])->name('productos.etiqueta');
    Route::get('/productos/crear', [ProductoController::class, 'create'])->middleware(['auth', 'role:Administrador'])->name('productos.create');
    Route::post('/productos', [ProductoController::class, 'store'])->middleware(['auth', 'role:Administrador'])->name('productos.store');
    Route::get('/productos/{id}/editar', [ProductoController::class, 'edit'])->middleware(['auth', 'role:Administrador'])->name('productos.edit');
    Route::put('/productos/{id}', [ProductoController::class, 'update'])->middleware(['auth', 'role:Administrador'])->name('productos.update');
    Route::delete('/productos/{id}', [ProductoController::class, 'destroy'])->middleware(['auth', 'role:Administrador'])->name('productos.destroy');

    Route::get('/clientes', [ClienteController::class, 'index'])->middleware(['auth', 'role:Administrador,Vendedor'])->name('clientes.index');
    Route::get('/clientes/{id}/editar', [ClienteController::class, 'edit'])->middleware(['auth', 'role:Administrador'])->name('clientes.edit');
    Route::put('/clientes/{id}', [ClienteController::class, 'update'])->middleware(['auth', 'role:Administrador'])->name('clientes.update');
    Route::delete('/clientes/{id}', [ClienteController::class, 'destroy'])->middleware(['auth', 'role:Administrador'])->name('clientes.destroy');

    Route::get('/pos', function (Request $request) {
        $categorias = Categoria::orderBy('nombre_categoria')->get();
        $productos = Producto::with('categoria')
            ->when($request->filled('nombre'), function ($query) use ($request): void {
                $nombre = $request->string('nombre')->toString();
                $query->where(function ($productQuery) use ($nombre): void {
                    $productQuery->where('nombre', 'like', "%{$nombre}%")
                        ->orWhere('codigo_barra', 'like', "%{$nombre}%")
                        ->orWhere('codigo_origen', 'like', "%{$nombre}%");
                });
            })
            ->when($request->filled('categoria'), function ($query) use ($request): void {
                $query->where('id_categoria', $request->integer('categoria'));
            })
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('pos.index', compact('productos', 'categorias'));
    })->middleware(['auth', 'role:Administrador,Vendedor'])->name('pos.index');
    Route::post('/ventas', [VentaController::class, 'store'])->middleware(['auth', 'role:Administrador,Vendedor', 'throttle:30,1']);
    Route::get('/inventario/movimientos', [InventarioMovimientoController::class, 'index'])->middleware(['auth', 'role:Administrador'])->name('inventario.movimientos');
    Route::get('/inventario/movimientos/{movimiento}', [InventarioMovimientoController::class, 'show'])->middleware(['auth', 'role:Administrador'])->name('inventario.movimientos.show');
    Route::post('/inventario/movimientos', [InventarioMovimientoController::class, 'store'])->middleware(['auth', 'role:Administrador'])->name('inventario.movimientos.store');
    Route::get('/reportes', [ReporteController::class, 'index'])->middleware(['auth', 'role:Administrador'])->name('reportes.index');
    Route::get('/reportes/exportar/{formato}', [ReporteController::class, 'exportar'])->middleware(['auth', 'role:Administrador'])->name('reportes.exportar');
    Route::get('/reportes/auditoria/exportar/{formato}', [ReporteController::class, 'exportarAuditoria'])->middleware(['auth', 'role:Administrador'])->name('reportes.auditoria.exportar');
    Route::get('/reportes/ventas/{id}', [ReporteController::class, 'detalleVenta'])->middleware(['auth', 'role:Administrador'])->name('reportes.ventas.detalle');
    Route::get('/reportes/ventas/{id}/factura', [ReporteController::class, 'facturaVenta'])->middleware(['auth', 'role:Administrador'])->name('reportes.ventas.factura');
    Route::get('/auditoria', [ReporteController::class, 'auditoria'])->middleware(['auth', 'role:Administrador'])->name('reportes.auditoria');
    Route::get('/backups', [BackupController::class, 'index'])->middleware(['auth', 'role:Administrador'])->name('backups.index');
    Route::get('/backups/{filename}', [BackupController::class, 'download'])->middleware(['auth', 'role:Administrador'])->name('backups.download');

    Route::get('/detalle-ventas', [DetalleVentaController::class, 'index'])->middleware(['auth', 'role:Administrador']);
    Route::get('/detalle-ventas/{id}', [DetalleVentaController::class, 'show'])->middleware(['auth', 'role:Administrador']);

    Route::get('/solicitudes-web', [SolicitudWebController::class, 'index'])->middleware(['auth', 'role:Administrador,Vendedor'])->name('solicitudes.index');
    Route::get('/solicitudes-web/{id}', [SolicitudWebController::class, 'show'])->middleware(['auth', 'role:Administrador,Vendedor'])->name('solicitudes.show');
    Route::get('/solicitudes-web/{id}/pdf', [SolicitudWebController::class, 'descargarSolicitudPdf'])->middleware(['auth', 'role:Administrador,Vendedor'])->name('solicitudes.pdf');
    Route::patch('/solicitudes-web/{id}/estado', [SolicitudWebController::class, 'actualizarEstado'])->middleware(['auth', 'role:Administrador,Vendedor'])->name('solicitudes.estado');

    Route::get('/usuarios', [UserController::class, 'index'])->middleware(['auth', 'role:Administrador'])->name('usuarios.index');
    Route::post('/usuarios', [UserController::class, 'store'])->middleware(['auth', 'role:Administrador'])->name('usuarios.store');
});
