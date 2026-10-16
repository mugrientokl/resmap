<?php

namespace App\Http\Controllers;

use App\Models\DetalleVenta;
use Illuminate\Http\Request;

class DetalleVentaController extends Controller
{
    public function index()
    {
        $detalles = DetalleVenta::with(['venta', 'producto'])->get();
        return response()->json($detalles);
    }

    public function show($id)
    {
        $detalle = DetalleVenta::with(['venta', 'producto'])->findOrFail($id);
        return response()->json($detalle);
    }
}