// --- NUEVA LÓGICA DE CANCELACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    $booking_id = mysqli_real_escape_string($con, $_POST['booking_id']);
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    // Primero verificamos si la reserva le pertenece al usuario actual (o si es admin)
    $check_ownership = "SELECT user_id FROM bioterio_bookings WHERE id = ?";
    $stmt_owner = $con->prepare($check_ownership);
    $stmt_owner->bind_param("i", $booking_id);
    $stmt_owner->execute();
    $result_owner = $stmt_owner->get_result();

    if ($result_owner->num_rows > 0) {
        $row = $result_owner->fetch_assoc();
        
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
