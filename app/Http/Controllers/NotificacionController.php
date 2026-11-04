<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\User;
use App\Notifications\ProductoStockCritico;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $this->notifyCriticalProducts($request->user());

        $notificaciones = $request->user()->notifications()->latest()->paginate(20)->withQueryString();

        return view('notificaciones.index', compact('notificaciones'));
    }

    public function read(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->update(['read_at' => now()]);

        return back();
    }

    private function notifyCriticalProducts(User $usuario): void
    {
        $yaNotificados = $usuario->notifications()
            ->where('type', ProductoStockCritico::class)
            ->whereNull('read_at')
            ->pluck('data')
            ->pluck('producto_id')
            ->flip();

        Producto::whereColumn('stock', '<=', 'stock_critico')
            ->get()
            ->reject(fn (Producto $producto): bool => $yaNotificados->has($producto->id_producto))
            ->each(fn (Producto $producto) => $usuario->notify(new ProductoStockCritico($producto)));
    }
}
