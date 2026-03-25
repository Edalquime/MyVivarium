<?php

/**
 * Página de Inicio de Sesión
 * * Este script maneja el inicio de sesión del usuario, muestra errores de autenticación y redirige a los usuarios autenticados a su destino previsto o a la página de inicio. 
 * También muestra un carrusel de imágenes y destaca las características de la aplicación web.
 * */

// Desactivar la visualización de errores en producción (los errores se registran en los logs del servidor)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED);

// Iniciar una nueva sesión o reanudar la existente
session_start();

// Incluir el archivo de conexión a la base de datos
require 'dbcon.php';
mysqli_query($con, "INSERT IGNORE INTO settings (name, value) VALUES ('url', 'myvivarium-nel.up.railway.app')");
mysqli_query($con, "INSERT IGNORE INTO settings (name, value) VALUES ('lab_name', 'Mi Vivario')");
$demo = $_ENV['DEMO'] ?? 'no';
require 'config.php'; // Incluir archivo de configuración para detalles de SMTP
require 'vendor/autoload.php'; // Incluir archivo de carga automática de PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Consulta para obtener el nombre del laboratorio, URL y llaves de Turnstile
$labQuery = "SELECT name, value FROM settings WHERE name IN ('lab_name', 'url', 'cf-turnstile-secretKey', 'cf-turnstile-sitekey')";
$labResult = mysqli_query($con, $labQuery);

// Valores por defecto
$labName = "Mi Vivario";
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

// Función para enviar correo de confirmación
function sendConfirmationEmail($to, $token)
{
    global $url;
    $confirmLink = "https://" . $url . "/confirm_email.php?token=$token";
    $subject = 'Confirmación de Correo Electrónico';
    $message = "Por favor, haz clic en el siguiente enlace para confirmar tu dirección de correo electrónico:\n$confirmLink";

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
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
    } catch (Exception $e) {
        error_log("No se pudo enviar el mensaje. Error de PHPMailer: {$mail->ErrorInfo}");
    }
}

// Comprobar si el usuario ya ha iniciado sesión
if (isset($_SESSION['name'])) {
    if (isset($_GET['redirect'])) {
        $rurl = urldecode($_GET['redirect']);
        if (preg_match('/^[a-zA-Z0-9_\-\.\/\?=&]+\.php/', $rurl) && !preg_match('/^(https?:)?\/\//', $rurl)) {
            header("Location: $rurl");
            exit;
        }
    }
    header("Location: home.php");
    exit;
}

// Manejar el envío del formulario de inicio de sesión
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Verificación de Turnstile
    if (!empty($turnstileSiteKey) && !empty($turnstileSecretKey)) {
        $turnstileResponse = $_POST['cf-turnstile-response'];
        
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
            $_SESSION['error_message'] = "La verificación de Cloudflare Turnstile falló. Inténtalo de nuevo.";
            header("Location: " . htmlspecialchars($_SERVER["PHP_SELF"]));
            exit;
        }
    }

    $query = "SELECT * FROM users WHERE username=?";
    $statement = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($statement, "s", $username);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);

    if ($row = mysqli_fetch_assoc($result)) {
        if ($row['email_verified'] == 0) {
            if (empty($row['email_token'])) {
                $new_token = bin2hex(random_bytes(16));
                $update_token_query = "UPDATE users SET email_token = ? WHERE username = ?";
                $update_token_stmt = mysqli_prepare($con, $update_token_query);
                mysqli_stmt_bind_param($update_token_stmt, "ss", $new_token, $username);
                mysqli_stmt_execute($update_token_stmt);
                mysqli_stmt_close($update_token_stmt);

                $token = $new_token;
            } else {
                $token = $row['email_token'];
            }

            sendConfirmationEmail($username, $token);
            $error_message = "Tu correo electrónico no está verificado. Se ha enviado un nuevo correo de confirmación. Por favor, revísalo para verificar tu cuenta.";
        } else {
            if ($row['status'] != 'approved') {
                $error_message = "Tu cuenta está pendiente de aprobación por el administrador.";
            } else {
                if (!is_null($row['account_locked']) && new DateTime() < new DateTime($row['account_locked'])) {
                    $error_message = "La cuenta está bloqueada temporalmente. Inténtalo más tarde.";
                } else {
                    if (password_verify($password, $row['password'])) {
                        $_SESSION['name'] = $row['name'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['role'] = $row['role'];
                        $_SESSION['position'] = $row['position'];
                        $_SESSION['user_id'] = $row['id'];

                        session_regenerate_id(true);

                        $reset_attempts = "UPDATE users SET login_attempts = 0, account_locked = NULL WHERE username=?";
                        $reset_stmt = mysqli_prepare($con, $reset_attempts);
                        mysqli_stmt_bind_param($reset_stmt, "s", $username);
                        mysqli_stmt_execute($reset_stmt);

                        if (isset($_GET['redirect'])) {
                            $rurl = urldecode($_GET['redirect']);
                            if (preg_match('/^[a-zA-Z0-9_\-\.\/\?=&]+\.php/', $rurl) && !preg_match('/^(https?:)?\/\//', $rurl)) {
                                header("Location: $rurl");
                                exit;
                            }
                        }
                        header("Location: home.php");
                        exit;
                    } else {
                        $new_attempts = $row['login_attempts'] + 1;
                        if ($new_attempts >= 3) {
                            $lock_time = "UPDATE users SET account_locked = DATE_ADD(NOW(), INTERVAL 15 MINUTE), login_attempts = 3 WHERE username=?";
                            $lock_stmt = mysqli_prepare($con, $lock_time);
                            mysqli_stmt_bind_param($lock_stmt, "s", $username);
                            mysqli_stmt_execute($lock_stmt);
                            $error_message = "Cuenta bloqueada temporalmente por 15 minutos debido a demasiados intentos fallidos.";
                        } else {
                            $update_attempts = "UPDATE users SET login_attempts = ? WHERE username=?";
                            $update_stmt = mysqli_prepare($con, $update_attempts);
                            mysqli_stmt_bind_param($update_stmt, "is", $new_attempts, $username);
                            mysqli_stmt_execute($update_stmt);
                            $error_message = "Contraseña inválida. Inténtalo de nuevo.";
                        }
                    }
                }
            }
        }
    } else {
        $error_message = "No se encontró ningún usuario con ese correo electrónico.";
    }
    mysqli_stmt_close($statement);
}
mysqli_close($con);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | <?php echo htmlspecialchars($labName); ?></title>

    <link rel="icon" href="icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="icons/favicon-16x16.png">

    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <style>
        /* Clavar footer abajo de la pantalla de forma dinámica con Flexbox */
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

        /* Estilo unificado de tarjetas */
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

        .btn-modern {
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .carousel img {
            height: 390px;
            object-fit: cover;
            width: 100%;
            border-radius: 12px;
        }

        @media (max-width: 768px) {
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
        <?php if ($demo === "yes") include('demo/demo-banner.php'); ?>

        <div class="header">
            <a href="index.php">
                <img src="images/logo1.jpg" alt="Logo" class="header-logo">
            </a>
            <h2><?php echo htmlspecialchars($labName); ?></h2>
        </div>

        <div class="container mt-5 mb-5">
            <div class="row">
                <div class="col-md-6 mb-4 mb-md-0">
                    <div id="labCarousel" class="carousel slide shadow-sm" data-ride="carousel" style="border-radius: 12px; overflow: hidden;">
                        <div class="carousel-inner">
                            <div class="carousel-item active"> <img class="d-block w-100" src="images/DSC_0536.webp" alt="Imagen 1"> </div>
                            <div class="carousel-item"> <img class="d-block w-100" src="images/DSC_0537.webp" alt="Imagen 2"> </div>
                            <div class="carousel-item"> <img class="d-block w-100" src="images/DSC_0539.webp" alt="Imagen 3"> </div>
                            <div class="carousel-item"> <img class="d-block w-100" src="images/DSC_0540.webp" alt="Imagen 4"> </div>
                            <div class="carousel-item"> <img class="d-block w-100" src="images/DSC_0560.webp" alt="Imagen 7"> </div>
                            <div class="carousel-item"> <img class="d-block w-100" src="images/DSC_0562.webp" alt="Imagen 8"> </div>
                            <div class="carousel-item"> <img class="d-block w-100" src="images/DSC_0586.webp" alt="Imagen 11"> </div>
                            <div class="carousel-item"> <img class="d-block w-100" src="images/DSC_0593.webp" alt="Imagen 12"> </div>
                            <div class="carousel-item"> <img class="d-block w-100" src="images/DSC_0607.webp" alt="Imagen 13"> </div>
                            <div class="carousel-item"> <img class="d-block w-100" src="images/DSC_0623.webp" alt="Imagen 14"> </div>
                            <div class="carousel-item"> <img class="d-block w-100" src="images/DSC_0658.webp" alt="Imagen 15"> </div>
                            <div class="carousel-item"> <img class="d-block w-100" src="images/DSC_0665.webp" alt="Imagen 16"> </div>
                        </div>
                    </div>
                    <?php if ($demo === "yes") include('demo/demo-disclaimer.php'); ?>
                </div>

                <div class="col-md-6">
                    <div class="card main-card">
                        <div class="card-header bg-dark text-white p-3 d-flex align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                            <h4 class="mb-0 fs-5"><i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión</h4>
                        </div>

                        <div class="card-body bg-light p-4">
                            <?php if (isset($error_message)) { ?>
                                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i> <?= $error_message; ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            <?php } ?>

                            <form method="POST" action="">
                                <div class="section-card">
                                    <div class="section-title">
                                        <i class="fas fa-user-circle"></i> Credenciales
                                    </div>

                                    <div class="form-group">
                                        <label for="username" class="form-label">Correo Electrónico</label>
                                        <input type="email" class="form-control" id="username" name="username" required placeholder="nombre@correo.com">
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="password" class="form-label">Contraseña</label>
                                        <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                                    </div>
                                </div>

                                <?php if (!empty($turnstileSiteKey)) { ?>
                                    <div class="section-card d-flex justify-content-center text-center">
                                        <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstileSiteKey); ?>"></div>
                                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                                    </div>
                                <?php } ?>

                                <div class="d-flex flex-column gap-3 mt-4">
                                    <button type="submit" class="btn btn-primary btn-modern w-100" name="login">
                                        <i class="fas fa-sign-in-alt me-1"></i> Entrar
                                    </button>
                                    <div class="d-flex justify-content-between mt-3">
                                        <a href="forgot_password.php" class="text-secondary small"><i class="fas fa-question-circle me-1"></i> ¿Olvidaste tu contraseña?</a>
                                        <a href="register.php" class="text-primary small"><i class="fas fa-user-plus me-1"></i> Regístrate aquí</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php if ($demo === "yes") include('demo/demo-credentials.php'); ?>
                </div>
            </div>

            <div class="row mt-5">
                <div class="col-md-12">
                    <div class="section-card">
                        <h3 class="text-center text-primary font-weight-bold">¡Bienvenido a <?php echo htmlspecialchars($labName); ?>!</h3>
                        <p class="text-center text-muted italic mb-0">Eleva tus investigaciones con nuestro sistema de gestión de colonias automatizado e integrado con IoT.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-footer">
        <?php include 'footer.php'; ?>
    </div>
</body>

</html>
