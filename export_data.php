<?php

/**
 * Database Table Exporter
 * * This PHP script allows administrators to export all database tables into CSV files 
 * and package them into a ZIP file for download.
 */

// Iniciamos almacenamiento en búfer para evitar que cualquier "espacio en blanco" accidental rompa el ZIP
ob_start();
session_start();

// Include the database connection file
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

// Desactivar límite de tiempo por si la base de datos es pesada
set_time_limit(0);

/**
 * Export a single table to CSV using temporary files (Low RAM consumption)
 */
function exportTableToCSV($con, $tableName)
{
    $query = ($tableName == 'users') 
        ? "SELECT id, name, username, position, role, status FROM `$tableName`" 
        : "SELECT * FROM `$tableName`";
        
    $result = $con->query($query);

    if (!$result) {
        return false;
    }

    // Creamos un archivo temporal en el disco (No en la RAM)
    $tempCsv = tempnam(sys_get_temp_dir(), 'csv_');
    $output = fopen($tempCsv, 'w');

    // Escribir encabezados
    $columns = $result->fetch_fields();
    $headers = [];
    foreach ($columns as $column) {
        $headers[] = $column->name;
    }
    fputcsv($output, $headers);

    // Escribir filas
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }

    fclose($output);
    $result->free(); // Liberar memoria del query de MySQL

    return $tempCsv; // Retornamos la ruta del archivo físico
}

$tableQuery = "SHOW TABLES";
$tableResult = $con->query($tableQuery);

if (!$tableResult) {
    die("Error retrieving tables: " . $con->error);
}

// Crear el archivo ZIP físico en el sistema
$zip = new ZipArchive();
$zipFilename = tempnam(sys_get_temp_dir(), 'export_zip_');

if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Cannot open ZIP file for writing");
}

$tempFilesToClean = [];

while ($tableRow = $tableResult->fetch_row()) {
    $tableName = $tableRow[0];
    $csvFile = exportTableToCSV($con, $tableName);
    
    if ($csvFile !== false) {
        // Añadir el archivo físico al ZIP
        $zip->addFile($csvFile, $tableName . '.csv');
        $tempFilesToClean[] = $csvFile; // Guardar ruta para borrarlo después
    }
}

$zip->close();

// Limpiamos CUALQUIER eco previo o espacio en blanco que se haya colado antes de disparar las cabeceras
ob_end_clean();

// Encabezados para forzar la descarga del ZIP real
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="exported_data.zip"');
header('Content-Length: ' . filesize($zipFilename));
header('Pragma: no-cache');
header('Expires: 0');

// Mandar el archivo al navegador
readfile($zipFilename);

// --- LIMPIEZA DEL DISCO DEL SERVIDOR ---
unlink($zipFilename); // Borrar el ZIP temporal
foreach ($tempFilesToClean as $file) {
    if (file_exists($file)) {
        unlink($file); // Borrar los CSV temporales
    }
}

$con->close();
exit();
