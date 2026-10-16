<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiqueta {{ $producto->codigo_barra }} | RESMAP</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <style>
        @page { size: 50mm 30mm; margin: 0; }
        * { box-sizing: border-box; }
        body { width: 50mm; height: 30mm; margin: 0; padding: 2mm; color: #241817; font-family: Arial, sans-serif; text-align: center; }
        .brand { color: #8f241d; font-size: 9px; font-weight: 800; letter-spacing: 2px; }
        .name { height: 8mm; margin-top: 1mm; overflow: hidden; font-size: 9px; font-weight: 700; line-height: 1.05; }
        #barcode { display: block; width: 44mm; height: 12mm; margin: 1mm auto 0; }
        .code { font-size: 7px; letter-spacing: .3px; }
        .no-print { margin-top: 10px; border: 0; background: #b52f25; color: #fff; padding: 8px 12px; font-weight: 700; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="brand">RESMAP</div>
    <div class="name">{{ $producto->nombre }}</div>
    <svg id="barcode" role="img" aria-label="Código {{ $producto->codigo_barra }}"></svg>
    <div class="code">{{ $producto->codigo_barra }}</div>
    @if($producto->codigo_origen && $producto->codigo_origen !== $producto->codigo_barra)
        <div class="code">Orig: {{ $producto->codigo_origen }}</div>
    @endif
    <button class="no-print" onclick="window.print()">Imprimir etiqueta</button>
    <script>
        JsBarcode('#barcode', @js($producto->codigo_barra), { format: 'CODE128', displayValue: false, margin: 0, width: 1.35, height: 42 });
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>