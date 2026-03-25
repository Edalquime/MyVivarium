<?php

/**
 * Script de Recuperación de Contraseña por Correo
 *
 * Maneja la funcionalidad de restablecimiento de contraseña para los usuarios.
 * Verifica si el correo existe, genera un token de restablecimiento, lo guarda 
 * con un tiempo de expiración y envía un correo con el enlace.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'dbcon.php';  // Conexión a la base de datos
require 'config.php';  // Archivo de configuración
require 'vendor/autoload.php';  // Carga automática de PHPMailer

// Consulta para obtener nombre del laboratorio, URL y llaves de Turnstile
$labQuery = "SELECT name, value FROM settings WHERE name IN ('lab_name', 'url', 'cf-turnstile-sitekey', 'cf-turnstile-secretKey')";
$labResult = mysqli_query($con, $labQuery);

// Valores por defecto
$labName = "Mi Vivario";
$url = "";
$turnstileSiteKey = "";
$turnstileSecretKey = "";

while ($row = mysqli_fetch_assoc($labResult)) {
    if ($row['name'] === 'lab_name') {
        $labName = $row['value'];
    } elseif ($row['name'] === 'url') {
        $url = $row['value'];
    } elseif ($row['name'] === 'cf-turnstile-sitekey') {
        $turnstileSiteKey = $row['value'];
    } elseif ($row['name'] === 'cf-turnstile-secretKey') {
        $turnstileSecretKey = $row['value'];
    }
}

$resultMessage = "";
$messageType = ""; // 'success' o 'danger' para pintar el cuadro de alerta

// Procesar el formulario de restablecimiento
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset'])) {
    $email = $_POST['email'];

    // Proceder con Turnstile solo si las llaves están configuradas
    if (!empty($turnstileSiteKey) && !empty($turnstileSecretKey)) {
        $turnstileResponse = $_POST['cf-turnstile-response'] ?? '';

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

        if (!$result['success']) {
            $resultMessage = "La verificación de Cloudflare Turnstile falló. Inténtalo de nuevo.";
            $messageType = "danger";
        } else {
            handlePasswordReset($con, $email, $url, $resultMessage, $messageType, $labName);
        }
    } else {
        handlePasswordReset($con, $email, $url, $resultMessage, $messageType, $labName);
    }
}

function handlePasswordReset($con, $email, $url, &$resultMessage, &$messageType, $labName) {
    $query = "SELECT * FROM users WHERE username = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $resetToken = bin2hex(random_bytes(32));
        $expirationTimeUnix = time() + 3600; // 1 hora de expiración
        $expirationTime = date('Y-m-d H:i:s', $expirationTimeUnix);

        $updateQuery = "UPDATE users SET reset_token = ?, reset_token_expiration = ?, login_attempts = 0, account_locked = NULL WHERE username = ?";
        $updateStmt = $con->prepare($updateQuery);
        $updateStmt->bind_param("sss", $resetToken, $expirationTime, $email);
        $updateStmt->execute();

        $resetLink = "https://" . $url . "/reset_password.php?token=$resetToken";

        $subject = 'Restablecimiento de Contraseña - ' . $labName;
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
            $mail->addAddress($email);
            $mail->isHTML(false);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->send();
            $resultMessage = "Las instrucciones de restablecimiento han sido enviadas a tu correo electrónico.";
            $messageType = "success";
        } catch (Exception $e) {
            $resultMessage = "El correo no pudo ser enviado. Error de PHPMailer: " . $mail->ErrorInfo;
            $messageType = "danger";
        }
        $updateStmt->close();
    } else {
        $resultMessage = "El correo electrónico no se encuentra en nuestros registros. Por favor, verifica e intenta de nuevo.";
        $messageType = "danger";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | <?php echo htmlspecialchars($labName); ?></title>

    <link rel="icon" href="/icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16x16.png">

    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        /* Clavar footer abajo de la pantalla */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #f4f6f9;
            font-family: 'Poppins', sans-serif;
            display: flex;
            flex-direction: column;
        }

        .page-content {
            flex: 1 0 auto;
        }

        .page-footer {
            flex-shrink: 0;
        }

        .main-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
            background-color: #ffffff;
        }

        .section-card {
            background-color: #ffffff;
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.03);
        }

        .section-title {
            color: #4e73df;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #eaecf4;
            padding-bottom: 8px;
        }

        .form-label {
            font-weight: 600;
            color: #343a40;
            font-size: 0.9rem;
        }

        .required-asterisk {
            color: #e74a3b;
        }

        .btn-modern {
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .header {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #343a40;
            color: white;
            padding: 1rem;
            text-align: center;
        }

        .header img.header-logo {
            width: 250px;
            height: auto;
        }

        .header h2 {
            margin-left: 15px;
            margin-bottom: 0;
            font-size: 2.5rem;
            font-weight: 500;
            white-space: nowrap;
        }

        @media (max-width: 576px) {
            .header {
                flex-direction: column;
            }
            .header h2 {
                font-size: 1.8rem;
                margin-left: 0;
                margin-top: 10px;
            }
            .header img.header-logo {
                width: 150px;
            }
        }
    </style>
</head>

<body>
    <div class="page-content">
        <?php if (isset($demo) && $demo === "yes") include('demo/demo-banner.php'); ?>

        <div class="header">
            <a href="index.php">
                <img src="images/logo1.jpg" alt="Logo" class="header-logo">
            </a>
            <h2><?php echo htmlspecialchars($labName); ?></h2>
        </div>

        <div class="container mt-5 mb-5" style="max-width: 600px;">
            <div class="card main-card">
                <div class="card-header bg-dark text-white p-3 d-flex align-items-center justify-content-between" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-lock-open me-2"></i> Recuperar Contraseña</h4>
                    <a href="index.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left me-1"></i> Volver</a>
                </div>

                <div class="card-body bg-light p-4">
                    <?php if (!empty($resultMessage)) : ?>
                        <div class="alert alert-<?= $messageType; ?> alert-dismissible fade show mb-4" role="alert">
                            <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                            <?= $resultMessage; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-envelope-open-text"></i> Datos de Solicitud
                            </div>
                            
                            <div class="form-group mb-0">
                                <label for="email" class="form-label">Correo Electrónico <span class="required-asterisk">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="ejemplo@correo.com">
                                <small class="form-text text-muted mt-2">
                                    <i class="fas fa-info-circle me-1"></i> Te enviaremos un enlace válido por una hora para restablecer tu cuenta.
                                </small>
                            </div>
                        </div>

                        <?php if (!empty($turnstileSiteKey)) { ?>
                            <div class="section-card text-center d-flex justify-content-center">
                                <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstileSiteKey); ?>"></div>
                                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                            </div>
                        <?php } ?>

                        <div class="d-flex justify-content-end gap-3 mt-4">
                            <a href="index.php" class="btn btn-outline-secondary btn-modern">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <button type="submit" name="reset" class="btn btn-primary btn-modern">
                                <i class="fas fa-paper-plane me-1"></i> Enviar Instrucciones
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="page-footer">
        <?php include 'footer.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
