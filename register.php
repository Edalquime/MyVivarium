<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED);

/**
 * Página de Registro de Usuarios
 * * Este script maneja el registro de usuarios para el sistema del laboratorio.
 * Recopila la información, verifica spam (honeypot y Turnstile), revisa si el correo ya existe,
 * hashea la contraseña y guarda el registro con estado "pendiente" y teléfono obligatorio.
 * */

session_start();
require 'dbcon.php';  // Archivo de conexión a la base de datos
require 'config.php';  // Archivo de configuración (SMTP)
require 'vendor/autoload.php';  // Autoload de PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Traer configuración del laboratorio desde la base de datos
$labQuery = "SELECT name, value FROM settings WHERE name IN ('lab_name', 'url', 'cf-turnstile-secretKey', 'cf-turnstile-sitekey')";
$labResult = mysqli_query($con, $labQuery);

$labName = "My Vivarium";
$url = "";
$turnstileSecretKey = "";
$turnstileSiteKey = "";

while ($row = mysqli_fetch_assoc($labResult)) {
    if ($row['name'] === 'lab_name') {
        $labName = $row['value'];
    } elseif ($row['name'] === 'url') {
        $url = $row['value'];
    } elseif ($row['name'] === 'cf-turnstile-secretKey') {
        $turnstileSecretKey = $row['value'];
    } elseif ($row['name'] === 'cf-turnstile-sitekey') {
        $turnstileSiteKey = $row['value'];
    }
}

// Enviar correo de confirmación al usuario
function sendConfirmationEmail($to, $token)
{
    global $url;
    $confirmLink = "https://" . $url . "/confirm_email.php?token=$token";
    $subject = 'Confirmacion de correo electronico';
    $message = "Por favor haz clic en el siguiente enlace para confirmar tu correo electronico:\n$confirmLink";

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
    } catch (Exception $e) {
        error_log("No se pudo enviar el correo. Error: {$mail->ErrorInfo}");
    }
}

// Notificar a administradores sobre un nuevo registro (Incluye teléfono)
function notifyAdmins($newUserDetails)
{
    global $con;
    $adminQuery = "SELECT username FROM users WHERE role = 'admin'";
    $adminResult = mysqli_query($con, $adminQuery);

    if (mysqli_num_rows($adminResult) > 0) {
        $subject = 'Notificacion: Nuevo registro de usuario';
        $message = "Un nuevo usuario se ha registrado en el sistema. Aqui estan los detalles:\n\n";
        $message .= "Nombre: " . $newUserDetails['name'] . "\n";
        $message .= "Correo: " . $newUserDetails['email'] . "\n";
        $message .= "Telefono: " . $newUserDetails['phone'] . "\n"; // 📞 Teléfono añadido
        $message .= "Cargo: " . $newUserDetails['position'] . "\n";
        $message .= "Correo Verificado: " . ($newUserDetails['email_verified'] == 1 ? 'Si' : 'No') . "\n";

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

            while ($adminRow = mysqli_fetch_assoc($adminResult)) {
                $adminEmail = $adminRow['username'];
                $mail->addAddress($adminEmail);
            }

            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->send();
        } catch (Exception $e) {
            error_log("No se pudo enviar la notificacion al administrador. Error: {$mail->ErrorInfo}");
        }
    }
}

function verifyTurnstile($turnstileResponse, $turnstileSecretKey)
{
    $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $turnstileSecretKey,
        'response' => $turnstileResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $ch = curl_init($verifyUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    return $result['success'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['honeypot'])) {
        $_SESSION['resultMessage'] = "Spam detectado! Intentalo de nuevo.";
    } else {
        if (!empty($turnstileSiteKey) && !empty($turnstileSecretKey)) {
            $turnstileResponse = $_POST['cf-turnstile-response'];
            if (!verifyTurnstile($turnstileResponse, $turnstileSecretKey)) {
                $_SESSION['resultMessage'] = "La verificacion Turnstile fallo. Intentalo de nuevo.";
                $con->close();
                header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
                exit;
            }
        }

        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $username = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING); // 📞 Captura del teléfono
        $password = $_POST['password'];
        $position = filter_input(INPUT_POST, 'position', FILTER_SANITIZE_STRING);
        $role = "user";
        $status = "pending";
        $email_verified = 0;
        $email_token = bin2hex(random_bytes(16));

        $checkEmailQuery = "SELECT username FROM users WHERE username = ?";
        $checkEmailStmt = $con->prepare($checkEmailQuery);
        $checkEmailStmt->bind_param("s", $username);
        $checkEmailStmt->execute();
        $checkEmailStmt->store_result();

        if ($checkEmailStmt->num_rows > 0) {
            $_SESSION['resultMessage'] = "El correo electronico ya esta registrado. Intenta iniciar sesion o usa un correo diferente.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // ⚠️ NOTA: Asegúrate de que tu tabla `users` tenga la columna `phone` (VARCHAR)
            $stmt = $con->prepare("INSERT INTO users (name, username, phone, position, role, password, status, email_verified, email_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssis", $name, $username, $phone, $position, $role, $hashedPassword, $status, $email_verified, $email_token);

            if ($stmt->execute()) {
                sendConfirmationEmail($username, $email_token);

                $newUserDetails = [
                    'name' => $name,
                    'email' => $username,
                    'phone' => $phone, // Guardar para correo admin
                    'position' => $position,
                    'email_verified' => $email_verified
                ];
                notifyAdmins($newUserDetails);

                $_SESSION['resultMessage'] = "Registro exitoso. Revisa tu correo electronico para confirmar tu cuenta.";
            } else {
                $_SESSION['resultMessage'] = "El registro fallo. Intentalo de nuevo.";
            }
            $stmt->close();
        }
        $checkEmailStmt->close();
    }
    $con->close();
    header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
    exit;
}

$resultMessage = isset($_SESSION['resultMessage']) ? $_SESSION['resultMessage'] : "";
unset($_SESSION['resultMessage']);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuario | <?php echo htmlspecialchars($labName); ?></title>

    <link rel="icon" href="/icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16x16.png">

    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .container {
            max-width: 600px;
            margin-top: 50px; /* Reduje el margen superior para que no baje tanto */
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

        .btn-primary {
            background-color: #1a73e8;
            border: none;
            border-radius: 8px;
            padding: 11px;
            font-weight: 500;
        }
        .btn-secondary {
            border-radius: 8px;
            padding: 11px;
        }

        .note {
            color: #888;
            font-size: 12px;
        }

        .header {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            background-color: #343a40;
            color: white;
            padding: 1rem;
            text-align: center;
            margin: 0;
        }

        .header .logo-container img.header-logo {
            width: 150px;
            height: auto;
            display: block;
        }

        .header h2 {
            margin-left: 15px;
            margin-bottom: 0;
            font-size: 2rem;
            white-space: nowrap;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
        }

        @media (max-width: 576px) {
            .header h2 {
                font-size: 1.5rem;
                margin-top: 10px;
            }
            .container {
                max-width: 90%;
            }
        }
    </style>
</head>

<body>
    <?php if (isset($demo) && $demo === "yes") include('demo/demo-banner.php'); ?>
    <div class="header">
        <div class="logo-container">
            <a href="home.php">
                <img src="images/logo1.jpg" alt="Logo" class="header-logo">
            </a>
        </div>
        <h2><?php echo htmlspecialchars($labName); ?></h2>
    </div>
    
    <div class="container content">
        <h2 class="text-center font-weight-bold" style="color: #3c4043;">Registro de Usuario</h2>
        <hr>
        <br>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            
            <div style="display:none;">
                <label for="honeypot">Dejar vacío</label>
                <input type="text" id="honeypot" name="honeypot">
            </div>

            <div class="form-group">
                <label for="name" class="font-weight-bold">Nombre Completo</label>
                <input type="text" class="form-control" id="name" name="name" placeholder="Ej: Juan Perez" required>
            </div>

            <div class="form-group">
                <label for="position" class="font-weight-bold">Cargo / Posición</label>
                <select class="form-control" id="position" name="position" required>
                    <option value="" disabled selected>Selecciona tu cargo</option>
                    <option value="Investigador Principal">Investigador Principal</option>
                    <option value="Cientifico de Investigacion">Científico de Investigación</option>
                    <option value="Postdoc">Postdoctorado</option>
                    <option value="Estudiante Doctorado">Estudiante Doctorado</option>
                    <option value="Estudiante Magister">Estudiante Magíster</option>
                    <option value="Pregrado">Pregrado / Licenciatura</option>
                    <option value="Tecnico de Laboratorio">Técnico de Laboratorio</option>
                    <option value="Asociado de Investigacion">Asociado de Investigación</option>
                    <option value="Manager de Laboratorio">Manager de Laboratorio</option>
                    <option value="Tecnico Cuidado Animal">Técnico Cuidado Animal</option>
                    <option value="Pasante / Voluntario">Pasante o Voluntario</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email" class="font-weight-bold">Correo Electrónico <span class="note">(Sera tu usuario de ingreso)</span></label>
                <input type="email" class="form-control" id="email" name="email" placeholder="correo@ejemplo.com" required>
            </div>

            <div class="form-group">
                <label for="phone" class="font-weight-bold">Teléfono / WhatsApp de Contacto</label>
                <input type="tel" class="form-control" id="phone" name="phone" placeholder="Ej: +56912345678" required>
            </div>

            <div class="form-group">
                <label for="password" class="font-weight-bold">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <?php if (!empty($turnstileSiteKey)) { ?>
                <div class="cf-turnstile d-flex justify-content-center mb-3" data-sitekey="<?php echo htmlspecialchars($turnstileSiteKey); ?>"></div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            <?php } ?>

            <br>
            <button type="submit" class="btn btn-primary btn-block" name="signup">Registrarse</button>
            <a href="index.php" class="btn btn-secondary btn-block mt-2">Volver</a>
        </form>
        <br>

        <?php if (!empty($resultMessage)) {
            echo "<div class=\"alert alert-warning\" role=\"alert\">";
            echo $resultMessage;
            echo "</div>";
        } ?>
    </div>
    <br>
    <?php include 'footer.php'; ?>
</body>

</html>
