<?php
// HU-03: Exportación del inventario completo a CSV para Excel.
// Solo el administrador autenticado puede descargar este archivo.
require_once __DIR__ . '/funciones.php';
requiere_login();

require_once __DIR__ . '/conexion.php';

// Consultamos el inventario completo, sin modificar ningún registro.
$stmt = $pdo->query(
    'SELECT codigo, nombre, marca, categoria, precio, stock
     FROM productos ORDER BY id DESC'
);
$productos = $stmt->fetchAll();

// Evitamos que valores de texto comiencen con caracteres que Excel
// podría interpretar como una fórmula.
function texto_csv_seguro($valor): string
{
    $texto = (string)$valor;
    if (preg_match('/^[\x00-\x20]*[=+\-@]/', $texto)) {
        return "'" . $texto;
    }
    return $texto;
}

// Preparamos el archivo que se enviará directamente al navegador.
$salida = fopen('php://output', 'wb');
if ($salida === false) {
    http_response_code(500);
    exit('No se pudo generar el archivo CSV.');
}

$nombreArchivo = 'tecnistore_inventario_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');

// BOM UTF-8 para que Excel reconozca tildes y caracteres especiales.
fwrite($salida, "\xEF\xBB\xBF");

// Usamos punto y coma como separador y coma decimal para Excel en español.
fputcsv($salida, ['Código', 'Nombre', 'Marca', 'Categoría', 'Precio (COP)', 'Stock'], ';', '"', '');

foreach ($productos as $producto) {
    fputcsv($salida, [
        texto_csv_seguro($producto['codigo']),
        texto_csv_seguro($producto['nombre']),
        texto_csv_seguro($producto['marca']),
        texto_csv_seguro($producto['categoria']),
        number_format((float)$producto['precio'], 2, ',', ''),
        (int)$producto['stock'],
    ], ';', '"', '');
}

fclose($salida);
exit;
