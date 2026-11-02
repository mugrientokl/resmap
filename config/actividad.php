<?php

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\ReservaInventario;
use App\Models\SolicitudWeb;
use App\Models\User;
use App\Models\Venta;

return [
    /*
    | Modelos auditados. "ediciones" define qué cambios generan registro:
    | true = cualquier campo, array = solo si cambia alguno de esos campos.
    */
    'modelos' => [
        Categoria::class => ['etiqueta' => 'Categoría', 'ediciones' => true],
        Cliente::class => ['etiqueta' => 'Cliente', 'ediciones' => true],
        Producto::class => ['etiqueta' => 'Producto', 'ediciones' => true],
        SolicitudWeb::class => ['etiqueta' => 'Solicitud web', 'ediciones' => true],
        User::class => ['etiqueta' => 'Usuario', 'ediciones' => true],
        Venta::class => ['etiqueta' => 'Venta', 'ediciones' => true],
        Cotizacion::class => ['etiqueta' => 'Cotización', 'ediciones' => ['estado']],
        OrdenCompra::class => ['etiqueta' => 'Orden de compra', 'ediciones' => ['autorizada_at', 'autorizada_por']],
        ReservaInventario::class => ['etiqueta' => 'Reserva de inventario', 'ediciones' => ['estado']],
    ],

    'categorias' => [
        'datos' => 'Cambio de datos',
        'seguridad' => 'Seguridad',
        'importacion' => 'Importación',
        'exportacion' => 'Exportación',
        'sistema' => 'Sistema',
    ],

    // Campos que nunca se guardan en el registro.
    'campos_ocultos' => ['password', 'remember_token', 'token', 'api_token', 'secret'],

    // Cambios que, por sí solos, no justifican un registro.
    'campos_ignorados' => ['created_at', 'updated_at', 'remember_token'],

    // Meses de conservación por categoría antes de archivar y depurar.
    'retencion_meses' => [
        'datos' => 6,
        'importacion' => 6,
        'seguridad' => 12,
        'exportacion' => 12,
        'sistema' => 12,
    ],

    // Clave del HMAC que encadena los registros; por defecto usa APP_KEY.
    'clave_integridad' => env('ACTIVIDAD_HASH_KEY', env('APP_KEY')),

    'maximo_exportacion' => 5000,
    'maximo_pdf' => 500,
    'por_pagina' => 25,
];
