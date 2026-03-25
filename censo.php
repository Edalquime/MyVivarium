<?php
session_start();
require 'dbcon.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'header.php';

// 🔥 OBLIGAMOS A PHP A MOSTRAR EL ERROR DE SQL EN PANTALLA
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<div class="container mt-4"><h3>🔍 Diagnóstico del Censo</h3>';

// --- INTENTO DE CONSULTA 1 ---
$query_pi = "
    SELECT u.name AS pi_name, 
           COUNT(m.mouse_id) AS total_mice
    FROM users u
    LEFT JOIN cages c ON u.id = c.pi_name
    LEFT JOIN mice m ON c.cage_id = m.cage_id
    GROUP BY u.id, u.name
";

$res_pi = mysqli_query($con, $query_pi);

if (!$res_pi) {
    echo '<div class="alert alert-danger">❌ Error en Consulta 1 (Investigadores): ' . mysqli_error($con) . '</div>';
} else {
    echo '<div class="alert alert-success">✅ Consulta 1 ejecutada con éxito.</div>';
}


// --- INTENTO DE CONSULTA 2 ---
$query_cepa = "
    SELECT s.str_name AS strain_name, COUNT(m.mouse_id) AS total
    FROM mice m
    INNER JOIN strains s ON m.strain_id = s.str_id
    GROUP BY s.str_name
";

$res_cepa = mysqli_query($con, $query_cepa);

if (!$res_cepa) {
    echo '<div class="alert alert-danger">❌ Error en Consulta 2 (Cepas): ' . mysqli_error($con) . '</div>';
} else {
    echo '<div class="alert alert-success">✅ Consulta 2 ejecutada con éxito.</div>';
}

echo '</div>';

include 'footer.php';
mysqli_close($con);
?>
