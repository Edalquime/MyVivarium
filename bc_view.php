<?php

/**
 * Ver Jaula de Cruce 
 *
 * Este script muestra información detallada sobre una jaula de cruce específica identificada por su ID.
 * Recupera datos de la base de datos, incluyendo información básica, archivos asociados y detalles de las camadas.
 * Asegura que solo los usuarios logueados accedan y proporciona opciones de edición, impresión y código QR.
 *
 */

session_start();

require 'dbcon.php';

if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit; 
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

$labQuery = "SELECT value FROM settings WHERE name = 'url' LIMIT 1";
$labResult = mysqli_query($con, $labQuery);

$url = "";
if ($row = mysqli_fetch_assoc($labResult)) {
    $url = $row['value'];
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($con, $_GET['id']);

    // MODIFICADO: También traemos datos de la cepa (nombre y alias)
    $query = "SELECT b.*, c.remarks AS remarks, pi.initials AS pi_initials, pi.name AS pi_name, s.str_name, s.str_aka
              FROM breeding b
              LEFT JOIN cages c ON b.cage_id = c.cage_id
              LEFT JOIN users pi ON c.pi_name = pi.id
              LEFT JOIN strains s ON b.strain = s.str_id
              WHERE b.cage_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    $query2 = "SELECT * FROM files WHERE cage_id = ?";
    $stmt2 = $con->prepare($query2);
    $stmt2->bind_param("s", $id);
    $stmt2->execute();
    $files = $stmt2->get_result();

    // Listar las camadas por Litter DOB descendente
    $query3 = "SELECT * FROM litters WHERE `cage_id` = ? ORDER BY `litter_dob` DESC";
    $stmt3 = $con->prepare($query3);
    $stmt3->bind_param("s", $id);
    $stmt3->execute();
    $litters = $stmt3->get_result();

    if (mysqli_num_rows($result) === 1) {
        $breedingcage = mysqli_fetch_assoc($result);

        // Preparamos la visualización de la Cepa
        $strainName = htmlspecialchars($breedingcage['str_name'] ?? 'Desconocida');
        $strainAka = !empty($breedingcage['str_aka']) ? ' <small class="text-muted">(Alias: ' . htmlspecialchars($breedingcage['str_aka']) . ')</small>' : '';
        $strainDisplay = $strainName . $strainAka;

        $iacucQuery = "SELECT ci.iacuc_id, i.file_url
                        FROM cage_iacuc ci
                        LEFT JOIN iacuc i ON ci.iacuc_id = i.iacuc_id
                        WHERE ci.cage_id = ?";
        $stmtIacuc = $con->prepare($iacucQuery);
        $stmtIacuc->bind_param("s", $id);
        $stmtIacuc->execute();
        $iacucResult = $stmtIacuc->get_result();
        $iacucLinks = [];
        while ($row = mysqli_fetch_assoc($iacucResult)) {
            if (!empty($row['file_url'])) {
                $iacucLinks[] = "<a href='" . htmlspecialchars($row['file_url']) . "' target='_blank'>" . htmlspecialchars($row['iacuc_id']) . "</a>";
            } else {
                $iacucLinks[] = htmlspecialchars($row['iacuc_id']);
            }
        }
        $iacucDisplayString = implode(', ', $iacucLinks);
    } else {
        $_SESSION['message'] = 'ID inválido.';
        header("Location: bc_dash.php");
        exit();
    }
} else {
    $_SESSION['message'] = 'Falta el parámetro del ID.';
    header("Location: bc_dash.php");
    exit();
}

function getUserDetailsByIds($con, $userIds)
{
    if (empty($userIds)) return [];
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $query = "SELECT id, initials, name FROM users WHERE id IN ($placeholders)";
    $stmt = $con->prepare($query);
    $stmt->bind_param(str_repeat('i', count($userIds)), ...$userIds);
    $stmt->execute();
    $result = $stmt->get_result();
    $userDetails = [];
    while ($row = $result->fetch_assoc()) {
        $userDetails[$row['id']] = htmlspecialchars($row['initials'] . ' [' . $row['name'] . ']');
    }
    $stmt->close();
    return $userDetails;
}

$userIdsQuery = "SELECT cu.user_id
                 FROM cage_users cu
                 WHERE cu.cage_id = ?";
$stmtUserIds = $con->prepare($userIdsQuery);
$stmtUserIds->bind_param("s", $id);
$stmtUserIds->execute();
$userIdsResult = $stmtUserIds->get_result();
$userIds = [];
while ($row = mysqli_fetch_assoc($userIdsResult)) {
    $userIds[] = $row['user_id'];
}

$userDetails = getUserDetailsByIds($con, $userIds);

$userDisplay = [];
foreach ($userIds as $userId) {
    if (isset($userDetails[$userId])) {
        $userDisplay[] = $userDetails[$userId];
    } else {
        $userDisplay[] = htmlspecialchars($userId);
    }
}
$userDisplayString = implode(', ', $userDisplay);

$maintenanceQuery = "
    SELECT m.timestamp, u.name AS user_name, m.comments 
    FROM maintenance m
    JOIN users u ON m.user_id = u.id
    WHERE m.cage_id = ?
    ORDER BY m.timestamp DESC";

$stmtMaintenance = $con->prepare($maintenanceQuery);
$stmtMaintenance->bind_param("s", $id);
$stmtMaintenance->execute();
$maintenanceLogs = $stmtMaintenance->get_result();

require 'header.php';
?>

<!doctype html>
<html lang="es">

<head>
    <title>Ver Jaula de Cruce | <?php echo htmlspecialchars($labName); ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        function showQrCodePopup(cageId) {
            var popup = window.open("", "Código QR para Jaula " + cageId, "width=400,height=400");
            var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://' + '<?= $url ?>' + '/bc_view.php?id=' + cageId;
            var htmlContent = `
            <html>
            <head>
                <title>Código QR para Jaula ${cageId}</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding-top: 40px; }
                    h1 { color: #333; font-size: 20px; }
                    img { margin-top: 20px; }
                </style>
            </head>
            <body>
                <h1>Código QR para Jaula ${cageId}</h1>
                <img src="${qrUrl}" alt="Código QR para Jaula ${cageId}" />
            </body>
            </html>
        `;
            popup.document.write(htmlContent);
            popup.document.close();
        }

        function goBack() {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 1;
            const search = urlParams.get('search') || '';
            window.location.href = 'bc_dash.php?page=' + page + '&search=' + encodeURIComponent(search);
        }
    </script>

    <style>
        body { 
            background-color: #f4f6f9 !important; 
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

        .table-wrapper table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 0;
        }

        .table-wrapper th, .table-wrapper td { 
            border: 1px solid #e3e6f0; 
            padding: 12px; 
            text-align: left; 
            word-wrap: break-word; 
            overflow-wrap: break-word; 
        }

        /* Proporción 30%-70% de la tabla principal */
        .table-wrapper th { 
            width: 30%; 
            background-color: #eaecf4; 
            color: #4e73df; 
            font-weight: 700;
        }

        .table-wrapper td { 
            width: 70%; 
            background-color: #fff;
        }

        .remarks-column { 
            max-width: 400px; 
            word-wrap: break-word; 
            overflow-wrap: break-word; 
        }

        .action-buttons { 
            display: flex; 
            gap: 8px; 
        }

        .btn-icon { 
            width: 38px; 
            height: 38px; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 8px;
            font-weight: 600;
            padding: 0; 
        }

        .btn-icon i { 
            font-size: 16px; 
            margin: 0; 
        }
        
        .note-app-container { 
            margin-top: 20px; 
            padding: 20px; 
            background-color: #ffffff; 
            border-radius: 12px; 
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.03);
            border: 1px solid #e3e6f0;
        }
    </style>
</head>

<body>

    <div class="container mt-4 mb-5" style="max-width: 850px;">
        <div class="card main-card">
            
            <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h4 class="mb-0 fs-5"><i class="fas fa-eye me-2"></i> Ver Jaula de Cruce: <?= htmlspecialchars($breedingcage['cage_id']); ?></h4>
                <div class="action-buttons">
                    <a href="javascript:void(0);" onclick="goBack()" class="btn btn-light btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Volver">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <a href="bc_edit.php?id=<?= rawurlencode($breedingcage['cage_id']); ?>" class="btn btn-warning btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Editar Jaula">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="manage_tasks.php?id=<?= rawurlencode($breedingcage['cage_id']); ?>" class="btn btn-info btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Gestionar Tareas">
                        <i class="fas fa-tasks"></i>
                    </a>
                    <a href="javascript:void(0);" onclick="showQrCodePopup('<?= rawurlencode($breedingcage['cage_id']); ?>')" class="btn btn-success btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Código QR">
                        <i class="fas fa-qrcode"></i>
                    </a>
                    <a href="javascript:void(0);" onclick="window.print()" class="btn btn-primary btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Imprimir Jaula">
                        <i class="fas fa-print"></i>
                    </a>
                </div>
            </div>

            <div class="card-body bg-light p-4">

                <div class="section-card">
                    <div class="section-title">
                        <i class="fas fa-cube"></i> Información de la Jaula
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <tr>
                                <th>ID de Jaula:</th>
                                <td><?= htmlspecialchars($breedingcage['cage_id']); ?></td>
                            </tr>
                            <tr>
                                <th>Nombre del PI:</th>
                                <td><?= htmlspecialchars($breedingcage['pi_initials'] . ' [' . $breedingcage['pi_name'] . ']'); ?></td>
                            </tr>
                            <tr>
                                <th>Cepa:</th>
                                <td><?= $strainDisplay; ?></td>
                            </tr>
                            <tr>
                                <th>Fecha Cruce:</th>
                                <td><?= htmlspecialchars($breedingcage['cross']); ?></td>
                            </tr>
                            <tr>
                                <th>Protocolos IACUC:</th>
                                <td><?= $iacucDisplayString; ?></td>
                            </tr>
                            <tr>
                                <th>Usuarios:</th>
                                <td><?= $userDisplayString; ?></td>
                            </tr>
                            <tr>
                                <th>ID Macho(s) [Cant: <?= htmlspecialchars($breedingcage['male_n'] ?? 1); ?>]:</th>
                                <td><?= htmlspecialchars($breedingcage['male_id']); ?></td>
                            </tr>
                            <tr>
                                <th>Fecha Nac. Macho(s):</th>
                                <td><?= htmlspecialchars($breedingcage['male_dob']); ?></td>
                            </tr>
                            <tr>
                                <th>ID Hembra(s) [Cant: <?= htmlspecialchars($breedingcage['female_n'] ?? 1); ?>]:</th>
                                <td><?= htmlspecialchars($breedingcage['female_id']); ?></td>
                            </tr>
                            <tr>
                                <th>Fecha Nac. Hembra(s):</th>
                                <td><?= htmlspecialchars($breedingcage['female_dob']); ?></td>
                            </tr>
                            <tr>
                                <th>Observaciones:</th>
                                <td class="remarks-column"><?= nl2br(htmlspecialchars($breedingcage['remarks'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>


                <div class="section-card">
                    <div class="section-title">
                        <i class="fas fa-folder-open"></i> Archivos Adjuntos
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="background-color: #eaecf4; color: #4e73df;">Nombre del Archivo</th>
                                    <th style="background-color: #eaecf4; color: #4e73df; width: 15%; text-align:center;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($files->num_rows > 0): ?>
                                    <?php while ($file = $files->fetch_assoc()) : ?>
                                        <tr>
                                            <td style="background-color: #fff; width: 85%;"><?= htmlspecialchars($file['file_name']); ?></td>
                                            <td style="background-color: #fff; text-align: center;">
                                                <a href="<?= htmlspecialchars($file['file_path']); ?>" download="<?= htmlspecialchars($file['file_name']); ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-cloud-download-alt"></i> Descargar
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center text-muted">No hay archivos vinculados a esta jaula.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>


                <div class="section-card">
                    <div class="section-title">
                        <i class="fas fa-baby"></i> Historial de Camadas (Nacimientos)
                    </div>

                    <?php if($litters->num_rows > 0): ?>
                        <?php while ($litter = mysqli_fetch_assoc($litters)) : ?>
                            <div class="table-wrapper mb-3">
                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <th>Fecha de Nac. Camada</th>
                                            <td><?= htmlspecialchars($litter['litter_dob'] ?? ''); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Crías Vivas</th>
                                            <td><?= htmlspecialchars($litter['pups_alive'] ?? '0'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Crías Muertas</th>
                                            <td><?= htmlspecialchars($litter['pups_dead'] ?? '0'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Crías Machos</th>
                                            <td><?= htmlspecialchars($litter['pups_male'] ?? '0'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Crías Hembras</th>
                                            <td><?= htmlspecialchars($litter['pups_female'] ?? '0'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Observaciones de Camada</th>
                                            <td class="remarks-column"><?= htmlspecialchars($litter['remarks'] ?? 'Sin observaciones'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">No se han registrado partos/camadas para este cruce aún.</p>
                    <?php endif; ?>
                </div>


                <div class="section-card mb-0">
                    <div class="section-title d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-clipboard-list"></i> Historial de Mantenimiento</span>
                        <div>
                            <a href="maintenance.php?from=bc_dash" class="btn btn-warning btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Añadir Registro de Mantenimiento">
                                <i class="fas fa-wrench"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($maintenanceLogs->num_rows > 0) : ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 25%; background-color: #eaecf4; color: #4e73df;">Fecha</th>
                                            <th style="width: 25%; background-color: #eaecf4; color: #4e73df;">Usuario</th>
                                            <th style="width: 50%; background-color: #eaecf4; color: #4e73df;">Comentario</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($log = $maintenanceLogs->fetch_assoc()) : ?>
                                            <tr>
                                                <td style="background-color: #fff;"><?= htmlspecialchars($log['timestamp'] ?? ''); ?></td>
                                                <td style="background-color: #fff;"><?= htmlspecialchars($log['user_name'] ?? 'Desconocido'); ?></td>
                                                <td style="background-color: #fff;"><?= nl2br(htmlspecialchars($log['comments'] ?? 'Sin comentarios')); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else : ?>
                            <p class="text-center text-muted mb-0 p-3">No se encontraron registros de mantenimiento para esta jaula.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <div class="note-app-container">
            <?php include 'nt_app.php'; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

</body>

</html>
