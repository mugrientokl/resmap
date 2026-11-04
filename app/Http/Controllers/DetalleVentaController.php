<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;

class DetalleVentaController extends Controller
{
    public function index()
    {
        $detalles = DetalleVenta::with(['venta', 'producto'])->paginate(100);

        return response()->json($detalles);
    }

    public function show($id)
    {
        $detalle = DetalleVenta::with(['venta', 'producto'])->findOrFail($id);

        return response()->json($detalle);
    }
}
