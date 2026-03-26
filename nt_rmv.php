<?php

/**
 * Script para Eliminar Notas
 * * Este script maneja la eliminación de una nota de la base de datos.
 * Espera una solicitud POST con el ID de la nota. La nota solo puede ser eliminada por el usuario que la creó (o un administrador).
 * La respuesta se devuelve como JSON.
 */

// Incluir el archivo de conexión a la base de datos
include_once("dbcon.php");

// Iniciar o reanudar la sesión si no ha sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Inicializar el array de respuesta por defecto
$response = ['success' => false, 'message' => 'Solicitud no válida.'];

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Debes iniciar sesión para realizar esta acción.';
    echo json_encode($response);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Verificar si la solicitud es un POST y el campo 'note_id' está configurado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_id'])) {
    
    $note_id = intval($_POST['note_id']);

    // Validar propiedad de la nota antes de borrarla (Seguridad)
    if (!$isAdmin) {
        $checkSql = "SELECT user_id FROM notes WHERE id = ?";
        $checkStmt = $con->prepare($checkSql);
        $checkStmt->bind_param("i", $note_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($row = $checkResult->fetch_assoc()) {
            if ($row['user_id'] != $currentUserId) {
                $response['message'] = 'No tienes permisos para eliminar esta nota.';
                echo json_encode($response);
                $checkStmt->close();
                exit;
            }
        }
        $checkStmt->close();
    }

    // Preparar la sentencia SQL para eliminar la nota
    $sql = "DELETE FROM notes WHERE id = ?";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        $response['message'] = 'Fallo en la preparación (Prepare): ' . htmlspecialchars($con->error);
        echo json_encode($response);
        exit;
    }

    $stmt->bind_param("i", $note_id);

    // Ejecutar la sentencia
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Nota eliminada con éxito.';
        } else {
            $response['message'] = 'No se encontró la nota o ya había sido eliminada.';
        }
    } else {
        $response['message'] = 'Fallo en la ejecución (Execute): ' . htmlspecialchars($stmt->error);
    }

    $stmt->close();
}

$con->close();

echo json_encode($response);
?>
