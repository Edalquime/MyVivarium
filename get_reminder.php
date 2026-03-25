<?php

/**
 * Obtener Recordatorio
 * * Este script maneja solicitudes AJAX para obtener los datos de un único recordatorio basado en su ID.
 */

require 'dbcon.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $stmt = $con->prepare("SELECT * FROM reminders WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $reminder = $result->fetch_assoc();

        if ($reminder) {
            // Devolver los detalles del recordatorio como JSON
            echo json_encode($reminder);
        } else {
            echo json_encode(['error' => 'Recordatorio no encontrado']);
        }
    } else {
        echo json_encode(['error' => 'Error al ejecutar la consulta']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['error' => 'Solicitud inválida']);
}
?>
