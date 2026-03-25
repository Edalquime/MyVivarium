<?php
// Activar errores para saber exactamente qué pasa
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Incluir conexión a BD
include 'dbcon.php';

if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit; 
}

if ($_SESSION['role'] != 'admin') {
    $_SESSION['message'] = "Access Denied. Contact Admin";
    header("Location: index.php");
    exit();
}

// Desactivar límite de tiempo de ejecución
set_time_limit(0);

// Crear archivo ZIP temporal en la carpeta del sistema
$zip = new ZipArchive();
$zipFilename = tempnam(sys_get_temp_dir(), 'db_export_');

if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("No se pudo crear o abrir el archivo ZIP temporal en el servidor. Verifica permisos de escritura del sistema.");
}

// Obtener todas las tablas
$tableQuery = "SHOW TABLES";
$tableResult = $con->query($tableQuery);

if (!$tableResult) {
    die("Error al leer las tablas: " . $con->error);
}

// Bucle para procesar cada tabla
while ($tableRow = $tableResult->fetch_row()) {
    $tableName = $tableRow[0];

    // Consulta de la tabla (con exclusión de seguridad para contraseñas de usuarios)
    $query = ($tableName == 'users') 
        ? "SELECT id, name, username, position, role, status FROM `$tableName`" 
        : "SELECT * FROM `$tableName`";

    $result = $con->query($query);

    if ($result) {
        // Usar memoria volátil para generar el CSV sin tocar disco duro intermedio
        $out = fopen('php://temp', 'r+');

        // Escribir cabeceras
        $fields = $result->fetch_fields();
        $headers = [];
        foreach ($fields as $field) {
            $headers[] = $field->name;
        }
        fputcsv($out, $headers);

        // Escribir filas
        while ($row = $result->fetch_assoc()) {
            fputcsv($out, $row);
        }

        // Leer el contenido del CSV en memoria
        rewind($out);
        $csvContent = stream_get_contents($out);
        fclose($out);

        // Meter al ZIP
        $zip->addFromString($tableName . '.csv', $csvContent);
        $result->free();
    }
}

$zip->close();

// Limpiar buffers de salida previos que puedan corromper el binario del ZIP
if (ob_get_level()) {
    ob_end_clean();
}

// Forzar la descarga del ZIP
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="exported_tables_data.zip"');
header('Content-Length: ' . filesize($zipFilename));
header('Pragma: no-cache');
header('Expires: 0');

readfile($zipFilename);

// Eliminar el archivo temporal del servidor
unlink($zipFilename);

$con->close();
exit();
