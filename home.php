<?php

/**
 * Página de Inicio
 * * Este script sirve como la página de inicio de la aplicación web. Muestra un mensaje de bienvenida al usuario logueado,
 * estadísticas de las jaulas de mantenimiento (Holding) y reproducción (Breeding), acceso directo a los paneles y un monitor de salas.
 * También incluye la sección de notas generales.
 * */

// Iniciar una nueva sesión o reanudar la existente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir el archivo de conexión a la base de datos
require 'dbcon.php';

// Verificar si el usuario ha iniciado sesión, si no, redirigir a la página de login
if (!isset($_SESSION['name'])) {
    header("Location: index.php"); 
    exit;
}


// --- LÓGICA DEL MONITOR DE SALAS DEL BIOTERIO ---
date_default_timezone_set('America/Santiago');
$ahora = date('Y-m-d H:i:s');

$salas_bioterio = [
    "Sala 1", "Sala 4", "Sala 5", 
    "Sala de Cuarentena", "Sala de Procedimientos", 
    "Sala de Conducta", "Sala CFC/RotaRod"
];

$estados_salas = [];

foreach ($salas_bioterio as $sala) {
    $query_actual = "SELECT b.title, u.name 
                     FROM bioterio_bookings b 
                     JOIN users u ON b.user_id = u.id 
                     WHERE b.room_name = ? AND ? BETWEEN b.start_event AND b.end_event 
                     LIMIT 1";
                     
    $stmt_actual = $con->prepare($query_actual);
    $stmt_actual->bind_param("ss", $sala, $ahora);
    $stmt_actual->execute();
    $res_actual = $stmt_actual->get_result();
    
    $ocupada_ahora = $res_actual->fetch_assoc();
    $stmt_actual->close();

    $query_proxima = "SELECT b.title, b.start_event, u.name 
                      FROM bioterio_bookings b 
                      JOIN users u ON b.user_id = u.id 
                      WHERE b.room_name = ? AND b.start_event > ? 
                      ORDER BY b.start_event ASC 
                      LIMIT 1";
                      
    $stmt_prox = $con->prepare($query_proxima);
    $stmt_prox->bind_param("ss", $sala, $ahora);
    $stmt_prox->execute();
    $res_prox = $stmt_prox->get_result();
    
    $proxima_reserva = $res_prox->fetch_assoc();
    $stmt_prox->close();

    $estados_salas[$sala] = [
        'ocupada' => $ocupada_ahora ? true : false,
        'uso_actual' => $ocupada_ahora ? $ocupada_ahora['title'] . " (" . $ocupada_ahora['name'] . ")" : null,
        'proxima' => $proxima_reserva ? date('d/m H:i', strtotime($proxima_reserva['start_event'])) . " - " . $proxima_reserva['name'] : "Sin reservas futuras"
    ];
}

// Conteo para jaulas
$holdingCountResult = $con->query("SELECT COUNT(*) AS count FROM holding");
$holdingCountRow = $holdingCountResult->fetch_assoc();
$holdingCount = $holdingCountRow['count'];

$matingCountResult = $con->query("SELECT COUNT(*) AS count FROM breeding");
$matingCountRow = $matingCountResult->fetch_assoc();
$matingCount = $matingCountRow['count'];

// Conteo de tareas
$userId = $_SESSION['user_id'];
$taskStatsQuery = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending
    FROM tasks
    WHERE assigned_to = ?
";
$stmtTaskStats = $con->prepare($taskStatsQuery);
$stmtTaskStats->bind_param("i", $userId);
$stmtTaskStats->execute();
$taskStatsResult = $stmtTaskStats->get_result();
$taskStatsRow = $taskStatsResult->fetch_assoc();
$totalTasks = $taskStatsRow['total'] ?? 0;
$completedTasks = $taskStatsRow['completed'] ?? 0;
$inProgressTasks = $taskStatsRow['in_progress'] ?? 0;
$pendingTasks = $taskStatsRow['pending'] ?? 0;

require 'header.php';
?>

<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inicio | <?php echo htmlspecialchars($labName); ?></title>

    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            background-color: #f4f6f9;
        }

        .main-content {
            max-width: 1440px; 
            margin: 0 auto;
            padding: 20px;
            box-sizing: border-box;
        }

        .section-title {
            font-weight: 600;
            color: #3c4043;
            font-size: 1.25rem;
        }

        .modern-card {
            border-radius: 12px !important;
            border: none !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
            background-color: #fff;
            display: flex;
            flex-direction: column;
        }

        .modern-card-header {
            border-top-left-radius: 12px !important;
            border-top-right-radius: 12px !important;
            border-bottom: 1px solid #ebedf0 !important;
        }

        .summary-stat-box {
            border-radius: 12px;
            border: 1px solid #dadce0;
            transition: all 0.2s ease;
            height: 100%;
            background-color: #fff;
        }

        .summary-stat-box:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .stat-link {
            text-decoration: none;
            color: inherit;
        }
    </style>
</head>

<body>
    <div class="main-content content">
        <?php include('message.php'); ?>

        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
            <h2 class="section-title mb-0">Bienvenido/a, <?php echo htmlspecialchars($_SESSION['name']); ?>
                <span style="font-size: smaller; color: #70757a; font-weight: 400;">
                    [<?php echo htmlspecialchars($_SESSION['position']); ?>]
                </span>
            </h2>
        </div>

        <div class="row g-4 mb-5">
            
            <div class="col-lg-4 col-md-12">
                <div class="card modern-card h-100">
                    <div class="card-header modern-card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fs-6"><i class="fas fa-door-open me-2"></i> Estado de Salas</h5>
                        <a href="booking.php" class="btn btn-sm btn-light" style="border-radius: 20px; font-size: 0.75rem;">Ver Todo</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                            <table class="table table-hover mb-0 align-middle" style="font-size: 0.85rem;">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Sala</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($estados_salas as $nombre_sala => $data): ?>
                                        <tr>
                                            <?php 
                                                $id_url = 'sala1';
                                                if ($nombre_sala === 'Sala 4') $id_url = 'sala4';
                                                if ($nombre_sala === 'Sala 5') $id_url = 'sala5';
                                                if ($nombre_sala === 'Sala de Cuarentena') $id_url = 'cuarentena';
                                                if ($nombre_sala === 'Sala de Procedimientos') $id_url = 'procedimientos';
                                                if ($nombre_sala === 'Sala de Conducta') $id_url = 'conducta';
                                                if ($nombre_sala === 'Sala CFC/RotaRod') $id_url = 'cfcrotarod';
                                            ?>
                                            <td>
                                                <a href="booking.php?sala=<?php echo $id_url; ?>" class="text-decoration-none fw-bold text-dark d-block">
                                                    <?php echo $nombre_sala; ?>
                                                </a>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    <?php echo $data['ocupada'] ? htmlspecialchars($data['uso_actual']) : "Próxima: " . htmlspecialchars($data['proxima']); ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($data['ocupada']): ?>
                                                    <span class="badge bg-danger rounded-pill px-2">Ocupada</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success rounded-pill px-2">Libre</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-12">
                <div class="d-flex flex-column h-100 justify-content-between gap-4">
                    
                    <div class="card modern-card flex-grow-1">
                        <div class="card-header modern-card-header bg-dark text-white p-3">
                            <h5 class="mb-0 fs-6"><i class="fas fa-boxes me-2"></i> Resumen de Jaulas</h5>
                        </div>
                        <div class="card-body bg-light d-flex flex-column justify-content-center p-3">
                            <div class="row g-2">
                                <div class="col-12">
                                    <a href="hc_dash.php" class="stat-link">
                                        <div class="summary-stat-box p-3 d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="text-secondary small fw-bold">Holding (Mantenimiento)</span>
                                                <h3 class="mb-0 mt-1 fw-bold text-dark"><?php echo $holdingCount; ?></h3>
                                            </div>
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-layer-group"></i>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-12">
                                    <a href="bc_dash.php" class="stat-link">
                                        <div class="summary-stat-box p-3 d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="text-secondary small fw-bold">Breeding (Reproducción)</span>
                                                <h3 class="mb-0 mt-1 fw-bold text-dark"><?php echo $matingCount; ?></h3>
                                            </div>
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-venus-mars"></i>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card modern-card flex-grow-1">
                        <div class="card-header modern-card-header bg-dark text-white p-3">
                            <h5 class="mb-0 fs-6"><i class="fas fa-tasks me-2"></i> Mis Tareas</h5>
                        </div>
                        <div class="card-body bg-light p-3 d-flex flex-column justify-content-center">
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="manage_tasks.php?filter=assigned_to_me" class="stat-link">
                                        <div class="summary-stat-box p-2 text-center">
                                            <span class="text-secondary small">Totales</span>
                                            <h4 class="mb-0 fw-bold text-info"><?php echo $totalTasks; ?></h4>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="manage_tasks.php?search=completed&filter=assigned_to_me" class="stat-link">
                                        <div class="summary-stat-box p-2 text-center">
                                            <span class="text-secondary small">Completas</span>
                                            <h4 class="mb-0 fw-bold text-success"><?php echo $completedTasks; ?></h4>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="manage_tasks.php?search=in+progress&filter=assigned_to_me" class="stat-link">
                                        <div class="summary-stat-box p-2 text-center">
                                            <span class="text-secondary small">En Progreso</span>
                                            <h4 class="mb-0 fw-bold text-warning"><?php echo $inProgressTasks; ?></h4>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="manage_tasks.php?search=pending&filter=assigned_to_me" class="stat-link">
                                        <div class="summary-stat-box p-2 text-center">
                                            <span class="text-secondary small">Pendientes</span>
                                            <h4 class="mb-0 fw-bold text-danger"><?php echo $pendingTasks; ?></h4>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="col-lg-4 col-md-12">
                <div class="card modern-card h-100">
                    <div class="card-header modern-card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fs-6"><i class="fas fa-sticky-note me-2"></i> Notas Generales</h5>
                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 8px;" data-bs-toggle="modal" data-bs-target="#noteModal" title="Añadir nueva nota">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="card-body bg-light p-3 d-flex flex-column h-100">
                        <div class="bg-white p-3 rounded border flex-grow-1 overflow-auto" style="max-height: 440px;">
                            <?php include 'nt_app.php'; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div> 

    <?php include 'footer.php'; ?>

    <script>
        function adjustFooter() {
            const footer = document.getElementById('footer');
            const container = document.querySelector('.main-content');
            const header = document.querySelector('.header');
            const navcontainer = document.querySelector('.nav-container');

            if (!footer || !container) return;

            footer.style.position = 'relative';
            footer.style.bottom = 'auto';
            footer.style.width = '100%';

            const headerHeight = header ? header.offsetHeight : 0;
            const navcontainerHeight = navcontainer ? navcontainer.offsetHeight : 0;
            const footerHeight = footer.offsetHeight;

            const availableSpace = window.innerHeight - headerHeight - navcontainerHeight;
            const contentHeight = container.scrollHeight + footerHeight;

            if (contentHeight < availableSpace) {
                footer.style.position = 'absolute';
                footer.style.bottom = '0';
            } else {
                footer.style.position = 'relative';
            }
        }

        window.addEventListener('load', adjustFooter);
        window.addEventListener('resize', adjustFooter);
    </script>
</body>
</html>
