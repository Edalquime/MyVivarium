<?php

/**
 * Gestión del Perfil de Usuario
 *
 * Este script permite a los usuarios logueados actualizar su información de perfil, incluyendo su nombre, cargo,
 * dirección de correo electrónico e iniciales. También proporciona una opción para solicitar un cambio de contraseña.
 * */

session_start();
require 'dbcon.php'; // Conexión a la base de datos
require 'config.php'; // Archivo de configuración para ajustes de correo
require 'header.php'; // Incluir el archivo de cabecera
require 'vendor/autoload.php'; // Autoload de PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit;
}

// Generar token CSRF si no está establecido
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener detalles del usuario de la base de datos
$username = $_SESSION['username'];
$query = "SELECT * FROM users WHERE username = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$updateMessage = ''; // Inicializar mensaje de actualización de perfil

// Función para asegurar iniciales únicas
function ensureUniqueInitials($con, $initials, $currentUsername)
{
    $uniqueInitials = substr($initials, 0, 3);
    $suffix = 1;
    $maxLength = 10;

    $checkQuery = "SELECT initials FROM users WHERE initials = ? AND username != ?";
    $stmt = $con->prepare($checkQuery);

    if (!$stmt) {
        error_log("Fallo al preparar declaración: " . $con->error);
        return $initials;
    }

    do {
        $stmt->bind_param("ss", $uniqueInitials, $currentUsername);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $uniqueInitials = substr($initials, 0, 3) . $suffix;
            $suffix++;
        } else {
            break;
        }

        $stmt->free_result();

    } while (strlen($uniqueInitials) <= $maxLength);

    $stmt->close();

    if (strlen($uniqueInitials) > $maxLength) {
        $uniqueInitials = substr($uniqueInitials, 0, $maxLength);
    }

    return $uniqueInitials;
}

// Manejar envío de formulario para actualización de perfil
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Fallo de validación del token CSRF');
    }

    $newUsername = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_EMAIL);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $initials = filter_input(INPUT_POST, 'initials', FILTER_SANITIZE_STRING);
    $position = filter_input(INPUT_POST, 'position', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING); // 📱 Captura de teléfono

    $emailChanged = ($newUsername !== $username);

    $uniqueInitials = ensureUniqueInitials($con, $initials, $username);

    if ($uniqueInitials !== $initials) {
        $updateMessage = "Las iniciales '$initials' ya están siendo usadas por otro usuario. Por favor escoge iniciales diferentes.";
    } else {
        // 🛠️ Actualizar la consulta para incluir el teléfono
        $updateQuery = "UPDATE users SET username = ?, name = ?, position = ?, initials = ?, phone = ?";
        if ($emailChanged) {
            $updateQuery .= ", email_verified = 0";
        }
        $updateQuery .= " WHERE username = ?";
        $updateStmt = $con->prepare($updateQuery);

        $updateStmt->bind_param("ssssss", $newUsername, $name, $position, $uniqueInitials, $phone, $username);

        if ($updateStmt->execute()) {
            if ($emailChanged) {
                $_SESSION['username'] = $newUsername;
                $username = $newUsername;
                $updateMessage = "Información del perfil actualizada. Por favor cierra sesión e ingresa nuevamente para reflejar los cambios.";
            } else {
                $updateMessage = "Perfil actualizado exitosamente.";
            }
        } else {
            $updateMessage = "Ocurrió un error al actualizar el perfil. Por favor intenta nuevamente.";
        }

        $updateStmt->close();

        // Refrescar datos del usuario
        $stmt = $con->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    }
}

// Manejar envío de formulario para restablecimiento de contraseña
$resultMessage = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Fallo de validación del token CSRF');
    }

    $email = $username;

    $query = "SELECT * FROM users WHERE username = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $resetToken = bin2hex(random_bytes(32));
        $expirationTimeUnix = time() + 3600;
        $expirationTime = date('Y-m-d H:i:s', $expirationTimeUnix);

        $updateQuery = "UPDATE users SET reset_token = ?, reset_token_expiration = ?, login_attempts = 0, account_locked = NULL WHERE username = ?";
        $updateStmt = $con->prepare($updateQuery);
        $updateStmt->bind_param("sss", $resetToken, $expirationTime, $email);
        $updateStmt->execute();

        $resetLink = "https://" . $url . "/reset_password.php?token=$resetToken";
        $to = $email;
        $subject = 'Restablecimiento de Contraseña';
        $message = "Para restablecer tu contraseña, haz clic en el siguiente enlace:\n$resetLink";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = SMTP_PORT;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;

            $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
            $mail->addAddress($to);
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->send();
            $resultMessage = "Las instrucciones para restablecer la contraseña han sido enviadas a tu correo.";
        } catch (Exception $e) {
            $resultMessage = "No se pudo enviar el correo. Error: " . $mail->ErrorInfo;
        }
    } else {
        $resultMessage = "Dirección de correo no encontrada en nuestros registros.";
    }

    $stmt->close();
    if (isset($updateStmt)) {
        $updateStmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Perfil de Usuario</title>
    <style>
        .container {
            max-width: 800px;
            margin-top: 50px;
            margin-bottom: 50px;
            padding: 25px;
            border: none;
            border-radius: 12px;
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .btn1 {
            display: block;
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-primary {
            background-color: #1a73e8;
            color: white;
        }

        .btn-primary:hover {
            background-color: #155cb0;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        .result-message, .update-message {
            text-align: center;
            margin-top: 15px;
            padding: 12px;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            border-radius: 8px;
        }

        .note {
            font-size: 0.9em;
            color: #5f6368;
            text-align: center;
            margin-top: 10px;
        }

        .note1 {
            color: #70757a;
            font-size: 12px;
            display: block;
            margin-top: 4px;
        }
    </style>
</head>

<body>
    <div class="container content">
        <h2 class="font-weight-bold" style="color: #3c4043;">Perfil de Usuario</h2>
        <hr>
        <br>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="form-group">
                <label for="name" class="font-weight-bold">Nombre Completo</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="initials" class="font-weight-bold">Iniciales</label>
                <input type="text" class="form-control" id="initials" name="initials" value="<?php echo htmlspecialchars($user['initials']); ?>" maxlength="3" required>
                <span class="note1">(Tus Iniciales se mostrarán en las Tarjetas de Jaula)</span>
            </div>

            <div class="form-group">
                <label for="position" class="font-weight-bold">Cargo / Posición</label>
                <select class="form-control" id="position" name="position" required>
                    <option value="" disabled>Selecciona tu posición</option>
                    <option value="Principal Investigator" <?php echo ($user['position'] == 'Principal Investigator' || $user['position'] == 'Investigador Principal') ? 'selected' : ''; ?>>Investigador Principal</option>
                    <option value="Research Scientist" <?php echo ($user['position'] == 'Research Scientist' || $user['position'] == 'Cientifico de Investigacion') ? 'selected' : ''; ?>>Científico de Investigación</option>
                    <option value="Postdoctoral Researcher" <?php echo ($user['position'] == 'Postdoctoral Researcher' || $user['position'] == 'Postdoc') ? 'selected' : ''; ?>>Postdoctorado</option>
                    <option value="PhD Student" <?php echo ($user['position'] == 'PhD Student' || $user['position'] == 'Estudiante Doctorado') ? 'selected' : ''; ?>>Estudiante Doctorado</option>
                    <option value="Masters Student" <?php echo ($user['position'] == 'Masters Student' || $user['position'] == 'Estudiante Magister') ? 'selected' : ''; ?>>Estudiante Magíster</option>
                    <option value="Undergraduate" <?php echo ($user['position'] == 'Undergraduate' || $user['position'] == 'Pregrado') ? 'selected' : ''; ?>>Pregrado / Licenciatura</option>
                    <option value="Laboratory Technician" <?php echo ($user['position'] == 'Laboratory Technician' || $user['position'] == 'Tecnico de Laboratorio') ? 'selected' : ''; ?>>Técnico de Laboratorio</option>
                    <option value="Research Associate" <?php echo ($user['position'] == 'Research Associate' || $user['position'] == 'Asociado de Investigacion') ? 'selected' : ''; ?>>Asociado de Investigación</option>
                    <option value="Lab Manager" <?php echo ($user['position'] == 'Lab Manager' || $user['position'] == 'Manager de Laboratorio') ? 'selected' : ''; ?>>Manager de Laboratorio</option>
                    <option value="Animal Care Technician" <?php echo ($user['position'] == 'Animal Care Technician' || $user['position'] == 'Tecnico Cuidado Animal') ? 'selected' : ''; ?>>Técnico Cuidado Animal</option>
                    <option value="Interns and Volunteers" <?php echo ($user['position'] == 'Interns and Volunteers' || $user['position'] == 'Pasante / Voluntario') ? 'selected' : ''; ?>>Pasante o Voluntario</option>
                </select>
            </div>

            <div class="form-group">
                <label for="phone" class="font-weight-bold">Teléfono / WhatsApp de Contacto</label>
                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Ej: +56912345678" required>
            </div>

            <?php if ($demo !== "yes") : ?>
                <div class="form-group">
                    <label for="username" class="font-weight-bold">Correo Electrónico</label>
                    <input type="email" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
            <?php endif; ?>

            <br>
            <button type="submit" class="btn1 btn-primary" name="update_profile">Actualizar Perfil</button>
        </form>

        <?php if ($updateMessage) {
            echo "<p class='update-message'>$updateMessage</p>";
        } ?>

        <br>
        <hr>
        <br>

        <h3 class="font-weight-bold" style="color: #3c4043;">Solicitar Cambio de Contraseña</h3>
        <br>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <button type="submit" class="btn1 btn-warning" name="reset">Solicitar Cambio de Contraseña</button>
        </form>

        <?php if ($resultMessage) {
            echo "<p class='result-message'>$resultMessage</p>";
        } ?>

        <br>
        <p class="note"><i class="fas fa-info-circle me-1"></i> Para que todos los cambios se reflejen correctamente, te recomendamos cerrar sesión y volver a ingresar.</p>
    </div>

    <?php include 'footer.php'; ?>
</body>

</html>
<?php mysqli_close($con); ?>
