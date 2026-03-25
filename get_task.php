<?php
/**
 * Obtener Detalles de Tarea
 * * Este script obtiene los detalles de una tarea específica de la base de datos basándose en el ID de tarea proporcionado.
 * Primero verifica si el usuario ha iniciado sesión, luego recupera los detalles de la tarea de la base de datos y, finalmente,
 * devuelve la información de la tarea como una respuesta JSON.
 * */

// Iniciar la sesión para usar variables de sesión
session_start();

// Incluir el archivo de conexión a la base de datos
require 'dbcon.php';

// Inicializar el array de respuesta
$response = [];

// Verificar si se proporciona un ID de tarea válido a través de la solicitud GET
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Validar y sanear el ID de la tarea
    $taskId = intval($_GET['id']);

    // Preparar la sentencia SQL para prevenir inyecciones SQL
    $stmt = $con->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $taskId);

    // Ejecutar la consulta y obtener el resultado
    if ($stmt->execute()) {
        $result = $stmt->get_result();

        // Verificar si se encuentra una tarea coincidente
        if ($result->num_rows > 0) {
            // Obtener los datos de la tarea si se encuentra una coincidencia
            $task = $result->fetch_assoc();

            // Poblar el array de respuesta con los datos de la tarea
            $response = [
                'id' => $task['id'],
                'title' => $task['title'],
                'description' => $task['description'],
                'assigned_by' => $task['assigned_by'],
                'assigned_to' => $task['assigned_to'],
                'status' => $task['status'],
                'completion_date' => $task['completion_date'],
                'creation_date' => $task['creation_date'],
                'cage_id' => $task['cage_id']
            ];
        } else {
            // Establecer respuesta de error si no se encuentra la tarea coincidente
            $response = ['error' => 'Tarea no encontrada.'];
        }

        // Cerrar la sentencia
        $stmt->close();
    } else {
        // Establecer respuesta de error si la ejecución de la consulta falla
        $response = ['error' => 'Error al ejecutar la consulta: ' . $stmt->error];
    }
} else {
    // Establecer respuesta de error si el ID de la tarea no es válido
    $response = ['error' => 'ID de tarea inválido.'];
}

// Imprimir la respuesta como JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
