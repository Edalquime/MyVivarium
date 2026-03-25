<?php
require 'dbcon.php';

$query = "SELECT b.id, b.title, b.start_event as start, b.end_event as end, b.room_name, u.name as user_name 
          FROM bioterio_bookings b
          JOIN users u ON b.user_id = u.id";
$result = $con->query($query);

$events = [];

while($row = $result->fetch_assoc()) {
    $events[] = [
        'id' => $row['id'],
        'title' => "[" . $row['room_name'] . "] " . $row['title'] . " - " . $row['user_name'],
        'start' => $row['start'],
        'end' => $row['end'],
        'color' => ($row['room_name'] == 'Sala de Cirugía') ? '#dc3545' : '#0d6efd' // Puedes pintar de colores según la sala
    ];
}

echo json_encode($events);
?>
