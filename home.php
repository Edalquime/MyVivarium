<?php

/**
 * Home Page
 * * This script serves as the home page for the web application. It displays a welcome message to the logged-in user, 
 * along with statistics on holding and breeding cages, and provides links to their respective dashboards. 
 * Additionally, it includes a section for general notes.
 * */

// Start a new session or resume the existing session
session_start();

// Include the database connection file
require 'dbcon.php';

// Check if the user is logged in, redirect to login page if not logged in
if (!isset($_SESSION['name'])) {
    header("Location: index.php"); // Redirect to login page if not logged in
    exit;
}


// --- LÓGICA DEL MONITOR DE SALAS DEL BIOTERIO ---
date_default_timezone_set('America/Santiago'); // Ajusta a tu zona horaria local
$ahora = date('Y-m-d H:i:s');

// Listado oficial de tus salas
$salas_bioterio = [
    "Sala 1", "Sala 4", "Sala 5", 
    "Sala de Cuarentena", "Sala de Procedimientos", 
    "Sala de Conducta", "Sala CFC/RotaRod"
];

$estados_salas = [];

foreach ($salas_bioterio as $sala) {
    // 1. Buscar si hay una reserva ACTIVA en este milisegundo
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

    // 2. Buscar la PRÓXIMA reserva (que empiece después de 'ahora')
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

    // Guardar la información consolidada de la sala
    $estados_salas[$sala] = [
        'ocupada' => $ocupada_ahora ? true : false,
        'uso_actual' => $ocupada_ahora ? $ocupada_ahora['title'] . " (" . $ocupada_ahora['name'] . ")" : null,
        'proxima' => $proxima_reserva ? date('d/m H:i', strtotime($proxima_reserva['start_event'])) . " - " . $proxima_reserva['name'] : "Sin reservas futuras"
    ];
}
// --- FIN LÓGICA DEL MONITOR ---


// Fetch the counts for holding and breeding cages
$holdingCountResult = $con->query("SELECT COUNT(*) AS count FROM holding");
$holdingCountRow = $holdingCountResult->fetch_assoc();
$holdingCount = $holdingCountRow['count'];

$matingCountResult = $con->query("SELECT COUNT(*) AS count FROM breeding");
$matingCountRow = $matingCountResult->fetch_assoc();
$matingCount = $matingCountRow['count'];

// Fetch the task stats for the logged-in user
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

// Set completedTasks, inProgressTasks, and pendingTasks to zero if totalTasks is zero
if ($totalTasks == 0) {
    $completedTasks = 0;
    $inProgressTasks = 0;
    $pendingTasks = 0;
}

// Include the header file
require 'header.php';
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Home | <?php echo htmlspecialchars($labName); ?></title>

    <style>
        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
        }

        .main-content {
            max-width: 1000px; /* Incrementé a 1000px para que la tabla respire mejor */
            margin: 0 auto;
            padding: 20px;
            box-sizing: border-box;
        }
    </style>

</head>

<body>
    <div class="main-content content">
        <?php include('message.php'); ?>

        <?php if ($_SESSION['username'] === 'admin@myvivarium.online' && $_SESSION['role'] === 'admin'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong><i class="fas fa-exclamation-triangle"></i> Security Warning:</strong> You are using the default admin account.
            For security reasons, please create a new admin user and delete this default account immediately.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <br>
        <div class="row align-items-center">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>
                    <span style="font-size: smaller; color: #555; border-bottom: 2px solid #ccc; padding: 0 5px;">
                        [<?php echo htmlspecialchars($_SESSION['position']); ?>]
                    </span>
                </h2>
            </div>
        </div>


        <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: none;">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h5 class="mb-0"><i class="fas fa-door-open me-2"></i> Estado de Salas en Tiempo Real</h5>
                <a href="booking.php" class="btn btn-sm btn-light" style="border-radius: 20px; font-weight: 500;">
                    Ver Calendario completo
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre de Sala</th>
                                <th class="text-center">Estado</th>
                                <th>Uso / Responsable Actual</th>
                                <th>Próxima Reserva Agendada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estados_salas as $nombre_sala => $data): ?>
                                <tr>
                                    <td class="font-weight-bold" style="color: #3c4043; font-weight: 600;"><?php echo $nombre_sala; ?></td>
                                    <td class="text-center">
                                        <?php if ($data['ocupada']): ?>
                                            <span class="badge bg-danger" style="border-radius: 12px; padding: 6px 12px;">
                                                🔴 Ocupado
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success" style="border-radius: 12px; padding: 6px 12px;">
                                                🟢 Disponible
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.9rem; color: #5f6368;">
                                        <?php echo $data['ocupada'] ? htmlspecialchars($data['uso_actual']) : "<em>Ninguno</em>"; ?>
                                    </td>
                                    <td style="font-size: 0.9rem; color: #5f6368;">
                                        <i class="far fa-clock me-1"></i> <?php echo htmlspecialchars($data['proxima']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <h2 class="mt-4">Cages Summary</h2>
        <div class="card">
            <div class="card-body">
                <div class="row mt-4">
                    <div class="col-md-6 mb-3">
                        <div class="card text-center">
                            <div class="card-header bg-primary text-white">
                                <a href="hc_dash.php" style="color: white; text-decoration: none;">Holding Cage</a>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $holdingCount; ?></h5>
                                <p class="card-text">Total Entries</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card text-center">
                            <div class="card-header bg-primary text-white">
                                <a href="bc_dash.php" style="color: white; text-decoration: none;">Breeding Cage</a>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $matingCount; ?></h5>
                                <p class="card-text">Total Entries</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h2 class="mt-4">Summary of Your Tasks</h2>
        <div class="card" style="margin-top: 20px;">
            <div class="card-body">
                <div class="row mt-4">
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-header bg-info text-white">
                                <a href="manage_tasks.php?filter=assigned_to_me" style="color: white; text-decoration: none;">Total Tasks</a>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $totalTasks; ?></h5>
                                <p class="card-text">Total</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-header bg-success text-white">
                                <a href="manage_tasks.php?search=completed&filter=assigned_to_me" style="color: white; text-decoration: none;">Completed</a>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $completedTasks; ?></h5>
                                <p class="card-text">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-header bg-warning text-white">
                                <a href="manage_tasks.php?search=in+progress&filter=assigned_to_me" style="color: white; text-decoration: none;">In Progress</a>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $inProgressTasks; ?></h5>
                                <p class="card-text">In Progress</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-header bg-danger text-white">
                                <a href="manage_tasks.php?search=pending&filter=assigned_to_me" style="color: white; text-decoration: none;">Pending</a>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $pendingTasks; ?></h5>
                                <p class="card-text">Pending</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 50px;">
            <h2><?php echo htmlspecialchars($labName); ?> - General Notes</h2>
            <?php include 'nt_app.php'; ?> </div>
    </div>
    
    <?php include 'footer.php'; ?>

    <script>
        function adjustFooter() {
            const footer = document.getElementById('footer');
            const container = document.querySelector('.main-content');
            const header = document.querySelector('.header');
            const navcontainer = document.querySelector('.nav-container');

            if (!footer) {
                console.warn("Footer element not found.");
                return;
            }

            if (!container) {
                console.warn("Top container element not found.");
                return;
            }

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
