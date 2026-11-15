<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\User;
use App\Notifications\ProductoStockCritico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificacionController extends Controller
{
    public function index(Request $request): View
    {
        $this->notifyCriticalProducts($request->user());

        $notificaciones = $request->user()->notifications()->whereNull('deleted_at')->latest()->paginate(20)->withQueryString();
        $papelera = false;

        return view('notificaciones.index', compact('notificaciones', 'papelera'));
    }

    public function trash(Request $request): View
    {
        $notificaciones = $request->user()->notifications()->whereNotNull('deleted_at')->orderByDesc('deleted_at')->paginate(20)->withQueryString();
        $papelera = true;

        return view('notificaciones.index', compact('notificaciones', 'papelera'));
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereNull('deleted_at')->whereKey($id)->firstOrFail()->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->whereNull('deleted_at')->update(['read_at' => now()]);

        return redirect()->route('notificaciones.index')->with('success', 'Todos los avisos se marcaron como leídos.');
    }

    public function moveToTrash(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereNull('deleted_at')->whereKey($id)->firstOrFail()->update(['deleted_at' => now()]);

        return back()->with('success', 'Aviso movido a la papelera.');
    }

    public function moveReadToTrash(Request $request): RedirectResponse
    {
        $request->user()->readNotifications()->whereNull('deleted_at')->update(['deleted_at' => now()]);

        return redirect()->route('notificaciones.index')->with('success', 'Los avisos leídos se movieron a la papelera.');
    }

    public function restore(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereNotNull('deleted_at')->whereKey($id)->firstOrFail()->update(['deleted_at' => null]);

        return back()->with('success', 'Aviso restaurado.');
    }

    public function restoreAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->whereNotNull('deleted_at')->update(['deleted_at' => null]);

        return redirect()->route('notificaciones.trash')->with('success', 'Todos los avisos se restauraron.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereNotNull('deleted_at')->whereKey($id)->firstOrFail()->delete();

        return back()->with('success', 'Aviso eliminado definitivamente.');
    }

    private function notifyCriticalProducts(User $usuario): void
    {
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
