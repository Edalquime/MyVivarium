<?php

/**
 * Script para Editar Nota
 * * Este script maneja la actualización de una nota en la base de datos.
 * Espera una solicitud POST con el ID de la nota y el texto de la nota actualizado.
 * La respuesta se devuelve como JSON.
 * */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'dbcon.php'; // Incluir el archivo de conexión a la base de datos

    // Iniciar sesión para validar que el usuario está conectado (Seguridad adicional)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['username'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No has iniciado sesión.']);
        exit;
    }

    // Recuperar el ID de la nota y el texto de la nota actualizado de la solicitud POST
    $noteId = intval($_POST['note_id']);
    $noteText = htmlspecialchars($_POST['note_text']); // Saneamiento preventivo

    // Preparar la sentencia SQL para actualizar la nota en la base de datos
    $sql = "UPDATE notes SET note_text = ? WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param('si', $noteText, $noteId);

    header('Content-Type: application/json');

    // Ejecutar la sentencia y devolver la respuesta como JSON
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Nota actualizada con éxito.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la nota: ' . htmlspecialchars($stmt->error)]);
    }

    // Cerrar la sentencia y la conexión a la base de datos
    $stmt->close();
    $con->close();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método de solicitud no válido.']);
}
