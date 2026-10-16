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
        $productos = Producto::whereColumn('stock', '<=', 'stock_critico')->get();

        foreach ($productos as $producto) {
            $existe = $usuario->notifications()->where('type', ProductoStockCritico::class)
                ->where('data->producto_id', $producto->id_producto)->whereNull('read_at')->exists();

            if (! $existe) {
                $usuario->notify(new ProductoStockCritico($producto));
            }
        }
    }
}
