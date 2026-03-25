<?php
require 'dbcon.php';

// Detectar si mandaron una sala por el filtro GET
$room_filter = isset($_GET['room']) ? $_GET['room'] : '';

$query = "SELECT b.id, b.title, b.start_event as start, b.end_event as end, b.room_name, u.name as user_name 
          FROM bioterio_bookings b
          JOIN users u ON b.user_id = u.id";

// Si se seleccionó una sala específica, filtramos el Query de SQL
if (!empty($room_filter)) {
    $query .= " WHERE b.room_name = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $room_filter);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $con->query($query);
}

$events = [];

while($row = $result->fetch_assoc()) {
    
    // Asignación de colores por sala para que sea visualmente intuitivo
    $color = '#0d6efd'; // Azul por defecto (Procedimientos)
    if ($row['room_name'] === 'Sala de Cirugía') $color = '#dc3545'; // Rojo
    if ($row['room_name'] === 'Sala de Comportamiento') $color = '#198754'; // Verde
    if ($row['room_name'] === 'Cuarentena') $color = '#ffc107'; // Amarillo

    $events[] = [
        'id' => $row['id'],
        'title' => $row['title'] . " (" . $row['user_name'] . ")",
        'start' => $row['start'],
        'end' => $row['end'],
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => ($row['room_name'] === 'Cuarentena') ? '#000' : '#fff' // Letra negra para fondo amarillo
    ];
}

echo json_encode($events);
?>
