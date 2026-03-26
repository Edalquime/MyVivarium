<?php

/**
 * Script para Añadir Nota
 * * Este script maneja la adición de una nota a la base de datos. Verifica si el usuario ha iniciado sesión,
 * procesa el envío del formulario e inserta la nota en la tabla 'notes'. La respuesta se devuelve como JSON.
 * */

// Incluir el archivo de conexión a la base de datos
include_once("dbcon.php");

// Iniciar o reanudar la sesión si no ha sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para añadir una nota.']);
    exit;
}

// Inicializar la respuesta por defecto
$response = ['success' => false, 'message' => 'Solicitud no válida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_text'])) {
    $note_text = htmlspecialchars($_POST['note_text']); // Saneamiento básico
    $user_id = $_SESSION['user_id']; 
    $cage = isset($_POST['cage_id']) ? $_POST['cage_id'] : null;

    // Preparar la sentencia SQL para prevenir inyecciones SQL
    $sql = "INSERT INTO notes (user_id, note_text, cage_id) VALUES (?, ?, ?)";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        $response['message'] = 'Fallo en la preparación (Prepare): ' . htmlspecialchars($con->error);
        echo json_encode($response);
        exit;
    }

    // Vincular parámetros
    $stmt->bind_param("sss", $user_id, $note_text, $cage);

    // Ejecutar la sentencia
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Nota añadida con éxito.';
    } else {
        $response['message'] = 'Fallo en la ejecución (Execute): ' . htmlspecialchars($stmt->error);
    }

    // Cerrar la sentencia
    $stmt->close();
}

// Cerrar la conexión a la base de datos
$con->close();

// Devolver la respuesta como JSON
header('Content-Type: application/json');
echo json_encode($response);
