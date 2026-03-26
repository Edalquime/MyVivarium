<?php

/**
 * Página de Gestión del Laboratorio
 * * Este script permite a los usuarios administradores ver y actualizar los detalles del laboratorio, incluyendo el nombre del laboratorio, URL, zona horaria, enlaces de sensores IoT para dos salas,
 * y las claves secreta y de sitio de Cloudflare Turnstile.
 */

// Iniciar una nueva sesión o reanudar la existente
session_start();

// Incluir el archivo de conexión a la base de datos
require 'dbcon.php';

// Verificar si el usuario ha iniciado sesión y tiene el rol de administrador
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirigir a usuarios no administradores a la página de inicio
    header("Location: index.php");
    exit; // Asegurar que no se ejecute más código
}

// Generación de token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener detalles del laboratorio de la base de datos
$query = "SELECT * FROM settings";
$result = mysqli_query($con, $query);
$labData = [];
while ($row = mysqli_fetch_assoc($result)) {
    $labData[$row['name']] = $row['value'];
}

// Proporcionar valores por defecto si no se encuentran datos
$defaultLabData = [
    'lab_name' => '',
    'url' => '',
    'timezone' => '',
    'r1_temp' => '',
    'r1_humi' => '',
    'r1_illu' => '',
    'r1_pres' => '',
    'r2_temp' => '',
    'r2_humi' => '',
    'r2_illu' => '',
    'r2_pres' => '',
    'cf-turnstile-secretKey' => '',
    'cf-turnstile-sitekey' => ''
];

$labData = array_merge($defaultLabData, $labData);

$updateMessage = '';
$messageType = '';

// Manejar el envío del formulario para la actualización de datos del laboratorio
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_lab'])) {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('La validación del token CSRF falló');
    }

    // Sanear y obtener entradas del formulario
    $inputFields = ['lab_name', 'url', 'timezone', 'r1_temp', 'r1_humi', 'r1_illu', 'r1_pres', 'r2_temp', 'r2_humi', 'r2_illu', 'r2_pres', 'cf-turnstile-secretKey', 'cf-turnstile-sitekey'];
    $inputData = [];
    foreach ($inputFields as $field) {
        $inputData[$field] = filter_input(INPUT_POST, $field, FILTER_SANITIZE_STRING);
    }

    // Actualizar o insertar nuevos datos
    foreach ($inputData as $name => $value) {
        $checkQuery = "SELECT COUNT(*) as count FROM settings WHERE name = ?";
        $checkStmt = $con->prepare($checkQuery);
        $checkStmt->bind_param("s", $name);
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();

        if ($count > 0) {
            // Actualizar configuración existente
            $updateQuery = "UPDATE settings SET value = ? WHERE name = ?";
            $updateStmt = $con->prepare($updateQuery);
            $updateStmt->bind_param("ss", $value, $name);
        } else {
            // Insertar nueva configuración
            $insertQuery = "INSERT INTO settings (name, value) VALUES (?, ?)";
            $updateStmt = $con->prepare($insertQuery);
            $updateStmt->bind_param("ss", $name, $value);
        }
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Refrescar datos del laboratorio
    $result = mysqli_query($con, $query);
    $labData = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $labData[$row['name']] = $row['value'];
    }

    $labData = array_merge($defaultLabData, $labData);

    $updateMessage = "La información del laboratorio se actualizó con éxito.";
    $messageType = "success";
}

// Incluir el archivo de cabecera
require 'header.php';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración del Laboratorio | <?php echo htmlspecialchars($labName); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        /* --- ESTANDARIZACIÓN FLEXBOX PARA EL FOOTER --- */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #f4f6f9;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
        }

        .page-content {
            flex: 1 0 auto;
        }

        .page-footer {
            flex-shrink: 0;
        }
        /* ----------------------------------------------- */

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
            gap: 5px;
        }
        
        textarea.form-control {
            resize: none;
            overflow: hidden;
        }
    </style>
</head>

<body>
    <div class="page-content">
        <div class="container mt-4 mb-5" style="max-width: 900px;">

            <?php if (!empty($updateMessage)) : ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $updateMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <div class="card main-card">
                <div class="card-header bg-dark text-white p-3 d-flex align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-tools me-2"></i> Ajustes de Gestión de Laboratorio</h4>
                </div>

                <div class="card-body bg-light p-4">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-id-card"></i> Información General del Sistema
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="lab_name" class="form-label">Nombre del Laboratorio / Plataforma</label>
                                    <input type="text" class="form-control" id="lab_name" name="lab_name" value="<?php echo htmlspecialchars($labData['lab_name']); ?>" placeholder="Ej: Mi Vivario">
                                </div>

                                <div class="col-md-6">
                                    <label for="url" class="form-label">Dominio (solo nombre del dominio, ej: midominio.com)</label>
                                    <input type="text" class="form-control" id="url" name="url" value="<?php echo htmlspecialchars($labData['url']); ?>" placeholder="midominio.com">
                                </div>

                                <div class="col-md-12">
                                    <label for="timezone" class="form-label">Zona Horaria (Timezone)</label>
                                    <input type="text" class="form-control" id="timezone" name="timezone" value="<?php echo htmlspecialchars($labData['timezone']); ?>" placeholder="Ej: America/Santiago">
                                    <small class="form-text text-muted mt-2">
                                        <i class="fas fa-info-circle me-1"></i> Puedes consultar la <a href="https://www.php.net/manual/es/timezones.php" target="_blank">lista de zonas horarias soportadas por PHP</a>.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-door-open"></i> Enlaces de Sensores - Sala 1
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="r1_temp" class="form-label">Sensor Temperatura Sala 1 (URL)</label>
                                    <textarea class="form-control" id="r1_temp" name="r1_temp" oninput="adjustTextareaHeight(this)"><?php echo htmlspecialchars($labData['r1_temp']); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="r1_humi" class="form-label">Sensor Humedad Sala 1 (URL)</label>
                                    <textarea class="form-control" id="r1_humi" name="r1_humi" oninput="adjustTextareaHeight(this)"><?php echo htmlspecialchars($labData['r1_humi']); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="r1_illu" class="form-label">Sensor Iluminación Sala 1 (URL)</label>
                                    <textarea class="form-control" id="r1_illu" name="r1_illu" oninput="adjustTextareaHeight(this)"><?php echo htmlspecialchars($labData['r1_illu']); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="r1_pres" class="form-label">Sensor Presión Sala 1 (URL)</label>
                                    <textarea class="form-control" id="r1_pres" name="r1_pres" oninput="adjustTextareaHeight(this)"><?php echo htmlspecialchars($labData['r1_pres']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-door-open"></i> Enlaces de Sensores - Sala 2
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="r2_temp" class="form-label">Sensor Temperatura Sala 2 (URL)</label>
                                    <textarea class="form-control" id="r2_temp" name="r2_temp" oninput="adjustTextareaHeight(this)"><?php echo htmlspecialchars($labData['r2_temp']); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="r2_humi" class="form-label">Sensor Humedad Sala 2 (URL)</label>
                                    <textarea class="form-control" id="r2_humi" name="r2_humi" oninput="adjustTextareaHeight(this)"><?php echo htmlspecialchars($labData['r2_humi']); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="r2_illu" class="form-label">Sensor Iluminación Sala 2 (URL)</label>
                                    <textarea class="form-control" id="r2_illu" name="r2_illu" oninput="adjustTextareaHeight(this)"><?php echo htmlspecialchars($labData['r2_illu']); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="r2_pres" class="form-label">Sensor Presión Sala 2 (URL)</label>
                                    <textarea class="form-control" id="r2_pres" name="r2_pres" oninput="adjustTextareaHeight(this)"><?php echo htmlspecialchars($labData['r2_pres']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-shield-alt"></i> Seguridad Cloudflare Turnstile
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="cf-turnstile-sitekey" class="form-label">Clave de Sitio (Site Key)</label>
                                    <input type="text" class="form-control" id="cf-turnstile-sitekey" name="cf-turnstile-sitekey" value="<?php echo htmlspecialchars($labData['cf-turnstile-sitekey']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="cf-turnstile-secretKey" class="form-label">Clave Secreta (Secret Key)</label>
                                    <input type="password" class="form-control" id="cf-turnstile-secretKey" name="cf-turnstile-secretKey" value="<?php echo htmlspecialchars($labData['cf-turnstile-secretKey']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-3 mt-4">
                            <button type="submit" class="btn btn-primary btn-modern" name="update_lab">
                                <i class="fas fa-save me-1"></i> Actualizar Información
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function adjustTextareaHeight(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('textarea').forEach(function(textarea) {
                adjustTextareaHeight(textarea);
            });
        });
    </script>
</body>

</html>

<?php mysqli_close($con); ?>
