<?php
session_start();
require 'dbcon.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'header.php';

echo '<div class="container mt-4"><h3>🔍 Inspección de la Tabla `mice`</h3>';

// Le pedimos a MySQL que nos diga qué columnas tiene la tabla 'mice'
$inspect_query = "SHOW COLUMNS FROM mice";
$result = mysqli_query($con, $inspect_query);

if ($result) {
    echo '<div class="alert alert-info">📋 Estas son las columnas reales de tu tabla de ratones:</div>';
    echo '<ul class="list-group mb-4">';
    while ($row = mysqli_fetch_assoc($result)) {
        // Imprime el nombre de cada columna
        echo '<li class="list-group-item"><strong>' . htmlspecialchars($row['Field']) . '</strong> (Tipo: ' . htmlspecialchars($row['Type']) . ')</li>';
    }
    echo '</ul>';
} else {
    echo '<div class="alert alert-danger">❌ No se pudo leer la tabla mice: ' . mysqli_error($con) . '</div>';
}

echo '</div>';

include 'footer.php';
mysqli_close($con);
?>
