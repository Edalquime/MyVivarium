<?php
// Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

require 'dbcon.php';

$query = "SELECT * FROM settings";
$result = mysqli_query($con, $query);

$labName = "My Vivarium";
$r1_temp = $r1_humi = $r1_illu = $r1_pres = $r2_temp = $r2_humi = $r2_illu = $r2_pres = "";

$settings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['name']] = $row['value'];
}

if (isset($settings['lab_name'])) { $labName = $settings['lab_name']; }
if (isset($settings['r1_temp'])) { $r1_temp = $settings['r1_temp']; }
if (isset($settings['r1_humi'])) { $r1_humi = $settings['r1_humi']; }
if (isset($settings['r1_illu'])) { $r1_illu = $settings['r1_illu']; }
if (isset($settings['r1_pres'])) { $r1_pres = $settings['r1_pres']; }
if (isset($settings['r2_temp'])) { $r2_temp = $settings['r2_temp']; }
if (isset($settings['r2_humi'])) { $r2_humi = $settings['r2_humi']; }
if (isset($settings['r2_illu'])) { $r2_illu = $settings['r2_illu']; }
if (isset($settings['r2_pres'])) { $r2_pres = $settings['r2_pres']; }
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href="./icons/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="./icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="./icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./icons/favicon-16x16.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        /* Nav principal unificado */
        .modern-navbar {
            background-color: #343a40;
            padding: 0.8rem 2rem;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 500;
            color: white !important;
            white-space: nowrap;
        }

        .logo-container {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .logo-container img {
            height: 45px;
            width: auto;
            border-radius: 4px;
            transition: transform 0.2s;
        }

        .logo-container img:hover {
            transform: scale(1.05);
        }

        .nav-link-btn {
            margin: 0 4px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Corregir desfases de visualización en pantallas móviles */
        @media (max-width: 991px) {
            .logo-container {
                margin: 1rem 0;
                justify-content: center;
            }
            .navbar-nav {
                text-align: center;
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <?php if (isset($demo) && $demo === "yes") include('demo/demo-banner.php'); ?>

    <nav class="navbar navbar-expand-lg navbar-dark modern-navbar">
        <div class="container-fluid">
            
            <a class="navbar-brand" href="home.php">
                <?php echo htmlspecialchars($labName); ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                
                <div class="logo-container mx-auto">
                    <a href="home.php">
                        <img src="images/logo1.jpg" alt="Logo Laboratorio">
                    </a>
                    <a href="https://ejemplo.com/icb" target="_blank">
                        <img src="images/logo_ICB_2019_2.jpg" alt="Logo ICB">
                    </a>
                    <a href="https://ejemplo.com/cprl" target="_blank">
                        <img src="images/logo_CPRL.jpeg" alt="Logo CPRL">
                    </a>
                </div>

                <div class="navbar-nav ms-auto">
                    
                    <a href="home.php" class="btn btn-outline-light nav-link-btn">
                        <i class="fas fa-home"></i> Home
                    </a>

                    <a href="booking.php" class="btn btn-outline-light nav-link-btn">
                        <i class="fas fa-calendar-alt"></i> Reservas
                    </a>

                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle nav-link-btn" type="button" id="dashMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-tachometer-alt"></i> Dashboards
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dashMenu">
                            <li><a class="dropdown-item" href="hc_dash.php">Holding Cage</a></li>
                            <li><a class="dropdown-item" href="bc_dash.php">Breeding Cage</a></li>
                            <?php if (!empty($r1_temp) || !empty($r1_humi) || !empty($r1_illu) || !empty($r1_pres) || !empty($r2_temp) || !empty($r2_humi) || !empty($r2_illu) || !empty($r2_pres)): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="iot_sensors.php"><i class="fas fa-microchip me-1"></i> IOT Sensors</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle nav-link-btn" type="button" id="setMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cog"></i> Ajustes
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="setMenu">
                            <li><a class="dropdown-item" href="user_profile.php"><i class="fas fa-user-circle me-1"></i> Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="manage_tasks.php"><i class="fas fa-tasks me-1"></i> Tareas</a></li>
                            
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li class="dropdown-header">Administración</li>
                                <li><a class="dropdown-item" href="manage_users.php">Usuarios</a></li>
                                <li><a class="dropdown-item" href="manage_iacuc.php">IACUC</a></li>
                                <li><a class="dropdown-item" href="manage_strain.php">Cepas</a></li>
                                <li><a class="dropdown-item" href="manage_lab.php">Ajustes del Lab</a></li>
                                <li><a class="dropdown-item" href="export_data.php">Exportar CSV</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Salir</a></li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </nav>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
