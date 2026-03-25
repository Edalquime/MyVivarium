<?php
session_start();
require 'dbcon.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'header.php';

// 🔥 Forzamos a PHP a que no oculte ningún error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<div class="container mt-4 mb-5">';
echo '<h2 class="mb-4"><i class="fas fa-search me-2"></i> Diagnóstico de Estructura de Base de Datos</h2>';

// Función mágica para inspeccionar cualquier tabla sin que falle
function inspeccionarTabla($con, $nombreTabla) {
    echo '<div class="card shadow-sm mb-4">';
    echo '<div class="card-header bg-dark text-white"><strong>📋 Columnas Reales de la Tabla: `' . $nombreTabla . '`</strong></div>';
    echo '<div class="card-body p-0">';
    
    $query = "SHOW COLUMNS FROM `$nombreTabla`";
    $result = mysqli_query($con, $query);

    if ($result) {
        echo '<ul class="list-group list-group-flush">';
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<li class="list-group-item">🔹 <strong>' . htmlspecialchars($row['Field']) . '</strong> (Tipo: ' . htmlspecialchars($row['Type']) . ')</li>';
        }
        echo '</ul>';
    } else {
        echo '<div class="alert alert-danger m-3">❌ No se pudo leer la tabla `' . $nombreTabla . '`. Error: ' . mysqli_error($con) . '</div>';
    }
    echo '</div></div>';
}

// 🕵️‍♂️ Inspeccionamos las 4 tablas críticas del censo
inspeccionarTabla($con, 'cages');
inspeccionarTabla($con, 'holding');
inspeccionarTabla($con, 'breeding');
inspeccionarTabla($con, 'litters');

echo '</div>';

include 'footer.php';
mysqli_close($con);
?>
