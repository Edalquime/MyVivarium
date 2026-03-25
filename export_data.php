<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'dbcon.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit; 
}

if ($_SESSION['role'] != 'admin') {
    $_SESSION['message'] = "Access Denied.";
    header("Location: index.php");
    exit();
}

// Configurar cabeceras para descargar un archivo CSV directo (No ZIP)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="database_export.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// Obtener todas las tablas
$tableQuery = "SHOW TABLES";
$tableResult = $con->query($tableQuery);

while ($tableRow = $tableResult->fetch_row()) {
    $tableName = $tableRow[0];

    // Separador visual en el CSV para saber dónde empieza cada tabla
    fputcsv($out, ["--- TABLA: $tableName ---"]);

    $query = ($tableName == 'users') 
        ? "SELECT id, name, username, position, role, status FROM `$tableName`" 
        : "SELECT * FROM `$tableName`";

    $result = $con->query($query);

    if ($result) {
        // Encabezados de la tabla
        $fields = $result->fetch_fields();
        $headers = [];
        foreach ($fields as $field) {
            $headers[] = $field->name;
        }
        fputcsv($out, $headers);

        // Datos de la tabla
        while ($row = $result->fetch_assoc()) {
            fputcsv($out, $row);
        }
        $result->free();
    }
    fputcsv($out, []); // Espacio en blanco entre tablas
}

fclose($out);
$con->close();
exit();
