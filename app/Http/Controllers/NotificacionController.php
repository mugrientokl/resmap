<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\User;
use App\Notifications\ProductoStockCritico;
use App\Support\ConfiguracionSistema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotificacionController extends Controller
{
    public function index(Request $request): View
    {
        if ($request->user()->tienePermiso('productos.ver')) {
            $this->notifyCriticalProducts($request->user());
        }

        DB::table('notifications')->where('created_at', '<', now()->subDays(180))->delete();
        $notificaciones = $request->user()->notificacionesVisibles()->latest()->paginate(20)->withQueryString();

        return view('notificaciones.index', compact('notificaciones'));
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $request->user()->notificacionesVisibles()->whereNull('deleted_at')->whereKey($id)->firstOrFail()->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->notificacionesVisibles()->unread()->whereNull('deleted_at')->update(['read_at' => now()]);

        return redirect()->route('notificaciones.index')->with('success', 'Todos los avisos se marcaron como leídos.');
    }

    private function notifyCriticalProducts(User $usuario): void
    {
        if (! ConfiguracionSistema::notificacionActiva('stock_critico')) {
            return;
        }
        $yaNotificados = $usuario->notifications()
            ->where('type', ProductoStockCritico::class)
            ->pluck('data')
            ->pluck('producto_id')
            ->flip();

        Producto::whereColumn('stock', '<=', 'stock_critico')
            ->get()
            ->reject(fn (Producto $producto): bool => $yaNotificados->has($producto->id_producto))
            ->each(fn (Producto $producto) => $usuario->notify(new ProductoStockCritico($producto)));
    }
}
