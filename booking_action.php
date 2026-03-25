<?php
// Forzar la zona horaria local del Bioterio (Ejemplo para Chile)
date_default_timezone_set('America/Santiago'); 

// Ejemplo para México: date_default_timezone_set('America/Mexico_City');
// Ejemplo para Colombia/Perú: date_default_timezone_set('America/Bogota');

/**
 * Booking Action Processor
 * Handles creation, validation, and collision prevention for room bookings.
 */

session_start();

// Include the database connection file
require 'dbcon.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// --- NUEVA LÓGICA DE CANCELACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    
    // Verificación de seguridad de sesión antes de procesar
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        echo "🚫 Sesión expirada. Por favor, recarga la página e inicia sesión nuevamente.";
        exit();
    }

    $booking_id = $_POST['booking_id']; // Tomamos el ID directo (bind_param se encarga de limpiarlo)
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    // 1. Verificamos si la reserva existe y a quién le pertenece
    $check_ownership = "SELECT user_id FROM bioterio_bookings WHERE id = ?";
    
    if ($stmt_owner = $con->prepare($check_ownership)) {
        $stmt_owner->bind_param("i", $booking_id);
        $stmt_owner->execute();
        $result_owner = $stmt_owner->get_result();

        if ($result_owner->num_rows > 0) {
            $row = $result_owner->fetch_assoc();
            
            // Regla: Solo el creador de la reserva o un Administrador pueden borrarla
            if ($row['user_id'] == $user_id || $user_role === 'admin') {
                
                $delete_query = "DELETE FROM bioterio_bookings WHERE id = ?";
                
                if ($stmt_del = $con->prepare($delete_query)) {
                    $stmt_del->bind_param("i", $booking_id);

                    if ($stmt_del->execute()) {
                        echo "✅ Reserva cancelada y liberada con éxito.";
                    } else {
                        echo "❌ Error al intentar borrar de la base de datos.";
                    }
                    $stmt_del->close();
                } else {
                    echo "❌ Error al preparar la consulta de eliminación.";
                }

            } else {
                echo "🚫 No tienes permisos para cancelar esta reserva (No eres el propietario).";
            }
        } else {
            echo "❓ La reserva que intentas eliminar ya no existe.";
        }
        $stmt_owner->close();
    } else {
        echo "❌ Error al preparar la consulta de verificación de propiedad.";
    }

    $con->close();
    exit(); // Detenemos el script aquí para la respuesta AJAX
}
// --- FIN LÓGICA DE CANCELACIÓN ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_booking'])) {
    
    $user_id = $_SESSION['user_id'];
    $room_name = mysqli_real_escape_string($con, $_POST['room_name']);
    $title = mysqli_real_escape_string($con, $_POST['title']);
    $start_event = $_POST['start_event'];
    $end_event = $_POST['end_event'];

    // 1. Validación básica: La fecha de fin no puede ser anterior o igual a la de inicio
    if (strtotime($start_event) >= strtotime($end_event)) {
        $_SESSION['message'] = "Error: La fecha de fin debe ser posterior a la fecha de inicio.";
        header("Location: booking.php");
        exit();
    }

    // 2. Validación básica: No se pueden hacer reservas en el pasado
    $now = date('Y-m-d H:i:s');
    if (strtotime($start_event) < strtotime($now)) {
        $_SESSION['message'] = "Error: No puedes reservar horarios que ya pasaron.";
        header("Location: booking.php");
        exit();
    }

    /* 3. VALIDACIÓN DE TRASLAPE (COLISIÓN DE HORARIOS):
     Buscamos si existe AL MENOS UNA reserva para la MISMA SALA donde:
     - El inicio de la nueva reserva sea antes del fin de una existente.
     - El fin de la nueva reserva sea después del inicio de una existente.
    */
    $collision_query = "
        SELECT id, title, start_event, end_event 
        FROM bioterio_bookings 
        WHERE room_name = ? 
          AND start_event < ? 
          AND end_event > ?
        LIMIT 1
    ";

    $stmt_check = $con->prepare($collision_query);
    // Vinculamos $end_event primero y luego $start_event según las condiciones del WHERE
    $stmt_check->bind_param("sss", $room_name, $end_event, $start_event);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $overlap = $result_check->fetch_assoc();
        $hora_inicio = date('H:i', strtotime($overlap['start_event']));
        $hora_fin = date('H:i', strtotime($overlap['end_event']));

        $_SESSION['message'] = "Error: La '$room_name' ya está ocupada en ese rango por la reserva '{$overlap['title']}' ($hora_inicio a $hora_fin).";
        $stmt_check->close();
        header("Location: booking.php");
        exit();
    }
    $stmt_check->close();

    // 4. Si pasa todas las validaciones, insertamos la reserva
    $insert_query = "INSERT INTO bioterio_bookings (room_name, user_id, title, start_event, end_event) VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $con->prepare($insert_query);
    $stmt_insert->bind_param("sisss", $room_name, $user_id, $title, $start_event, $end_event);

    if ($stmt_insert->execute()) {
        $_SESSION['message'] = "¡Éxito! Sala '$room_name' reservada correctamente.";
    } else {
        $_SESSION['message'] = "Error crítico: No se pudo registrar la reserva en la base de datos.";
    }

    $stmt_insert->close();
    $con->close();

    header("Location: booking.php");
    exit();

} else {
    // Si intentan acceder al archivo de acción directamente sin el formulario POST
    header("Location: booking.php");
    exit();
}
