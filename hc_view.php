<?php

/**
 * Ver Jaula de Mantenimiento
 * * Este script muestra información detallada sobre una jaula de mantenimiento específica, incluyendo archivos y notas relacionados. 
 * También proporciona opciones para ver, editar, imprimir la información de la jaula y generar un código QR.
 */

session_start();
require 'dbcon.php';

// Verificar si el usuario no ha iniciado sesión
if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit;
}

// Desactivar la visualización de errores en producción
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Obtener la URL del laboratorio
$labQuery = "SELECT value FROM settings WHERE name = 'url' LIMIT 1";
$labResult = mysqli_query($con, $labQuery);
$url = "";
if ($row = mysqli_fetch_assoc($labResult)) {
    $url = $row['value'];
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($con, $_GET['id']);

    $query = "SELECT h.*, pi.initials AS pi_initials, pi.name AS pi_name, s.*, c.quantity, c.remarks
              FROM holding h
              LEFT JOIN cages c ON h.cage_id = c.cage_id
              LEFT JOIN users pi ON c.pi_name = pi.id
              LEFT JOIN strains s ON h.strain = s.str_id
              WHERE h.cage_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    $query2 = "SELECT * FROM files WHERE cage_id = ?";
    $stmt2 = $con->prepare($query2);
    $stmt2->bind_param("s", $id);
    $stmt2->execute();
    $files = $stmt2->get_result();

    if (mysqli_num_rows($result) === 1) {
        $holdingcage = mysqli_fetch_assoc($result);

        if (is_null($holdingcage['str_name']) || empty($holdingcage['str_name'])) {
            $holdingcage['str_id'] = 'NA';
            $holdingcage['str_name'] = 'Cepa Desconocida';
            $holdingcage['str_url'] = '#';
        }

        if (is_null($holdingcage['pi_name'])) {
            $queryBasic = "SELECT * FROM holding WHERE `cage_id` = ?";
            $stmtBasic = $con->prepare($queryBasic);
            $stmtBasic->bind_param("s", $id);
            $stmtBasic->execute();
            $resultBasic = $stmtBasic->get_result();
            if (mysqli_num_rows($resultBasic) === 1) {
                $holdingcage = mysqli_fetch_assoc($resultBasic);
                $holdingcage['pi_initials'] = 'NA';
                $holdingcage['pi_name'] = 'NA';
            } else {
                $_SESSION['message'] = 'Error al obtener los detalles de la jaula.';
                header("Location: hc_dash.php");
                exit();
            }
        }

        $iacucQuery = "SELECT GROUP_CONCAT(iacuc_id SEPARATOR ', ') AS iacuc_ids FROM cage_iacuc WHERE cage_id = ?";
        $stmtIacuc = $con->prepare($iacucQuery);
        $stmtIacuc->bind_param("s", $id);
        $stmtIacuc->execute();
        $iacucResult = $stmtIacuc->get_result();
        $iacucRow = mysqli_fetch_assoc($iacucResult);
        $iacucCodes = [];
        if (!empty($iacucRow['iacuc_ids'])) {
            $iacucCodes = explode(',', $iacucRow['iacuc_ids']);
        }
        $stmtIacuc->close();

        $iacucLinks = [];
        foreach ($iacucCodes as $iacucCode) {
            $iacucCode = trim($iacucCode);
            $iacucQuery = "SELECT file_url FROM iacuc WHERE iacuc_id = ?";
            $stmtIacucFile = $con->prepare($iacucQuery);
            $stmtIacucFile->bind_param("s", $iacucCode);
            $stmtIacucFile->execute();
            $iacucResult = $stmtIacucFile->get_result();
            if ($iacucResult && mysqli_num_rows($iacucResult) === 1) {
                $iacucRow = mysqli_fetch_assoc($iacucResult);
                if (!empty($iacucRow['file_url'])) {
                    $iacucLinks[] = "<a href='" . htmlspecialchars($iacucRow['file_url']) . "' target='_blank'>" . htmlspecialchars($iacucCode) . "</a>";
                } else {
                    $iacucLinks[] = htmlspecialchars($iacucCode);
                }
            } else {
                $iacucLinks[] = htmlspecialchars($iacucCode);
            }
            $stmtIacucFile->close();
        }

        $iacucDisplayString = implode(', ', $iacucLinks);

        $userQuery = "SELECT user_id FROM cage_users WHERE cage_id = ?";
        $stmtUser = $con->prepare($userQuery);
        $stmtUser->bind_param("s", $id);
        $stmtUser->execute();
        $userResult = $stmtUser->get_result();
        $userIds = [];
        while ($userRow = mysqli_fetch_assoc($userResult)) {
            $userIds[] = $userRow['user_id'];
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
        $stmtUser->close();

        $mouseQuery = "SELECT * FROM mice WHERE cage_id = ?";
        $stmtMouse = $con->prepare($mouseQuery);
        $stmtMouse->bind_param("s", $id);
        $stmtMouse->execute();
        $mouseResult = $stmtMouse->get_result();
        $mice = mysqli_fetch_all($mouseResult, MYSQLI_ASSOC);

        // Calcular edad
        if (!empty($holdingcage['dob'])) {
            $dob = new DateTime($holdingcage['dob']);
            $now = new DateTime();
            $ageInterval = $dob->diff($now);

            $ageComponents = [];
            if ($ageInterval->y > 0) {
                $years = $ageInterval->y;
                $unit = ($years == 1) ? 'Año' : 'Años';
                $ageComponents[] = $years . ' ' . $unit;
            }
            if ($ageInterval->m > 0) {
                $months = $ageInterval->m;
                $unit = ($months == 1) ? 'Mes' : 'Meses';
                $ageComponents[] = $months . ' ' . $unit;
            }
            if ($ageInterval->d > 0) {
                $days = $ageInterval->d;
                $unit = ($days == 1) ? 'Día' : 'Días';
                $ageComponents[] = $days . ' ' . $unit;
            }
            if (empty($ageComponents)) {
                $ageComponents[] = '0 Días';
            }
            $ageString = implode(' ', $ageComponents);
        } else {
            $ageString = 'Desconocida';
        }

    } else {
        $_SESSION['message'] = 'ID inválido.';
        header("Location: hc_dash.php");
        exit();
    }
} else {
    $_SESSION['message'] = 'Falta el parámetro del ID.';
    header("Location: hc_dash.php");
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Jaula de Mantenimiento | <?php echo htmlspecialchars($labName); ?></title>

    <style>
        /* Empujar footer al final de la pantalla */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #f4f6f9 !important;
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

        .table-wrapper th,
        .table-wrapper td {
            border: 1px solid #e3e6f0;
            padding: 12px;
            text-align: left;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Proporción fija 30% / 70% */
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

        /* Ventana emergente (Modal Modernizado) */
        .popup-form {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            border: none;
            z-index: 1000;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            width: 90%;
            max-width: 600px;
        }

        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            backdrop-filter: blur(2px);
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="page-content">
        <div class="container mt-4 mb-5" style="max-width: 850px;">
            <div class="card main-card">
                
                <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-eye me-2"></i> Ver Jaula de Mantenimiento: <?= htmlspecialchars($holdingcage['cage_id']); ?></h4>
                    <div class="action-buttons">
                        <a href="javascript:void(0);" onclick="goBack()" class="btn btn-light btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Volver">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <a href="hc_edit.php?id=<?= rawurlencode($holdingcage['cage_id']); ?>" class="btn btn-warning btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Editar Jaula">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="manage_tasks.php?id=<?= rawurlencode($holdingcage['cage_id']); ?>" class="btn btn-info btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Gestionar Tareas">
                            <i class="fas fa-tasks"></i>
                        </a>
                        <a href="javascript:void(0);" onclick="showQrCodePopup('<?= rawurlencode($holdingcage['cage_id']); ?>')" class="btn btn-success btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Código QR">
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
                                    <th>Jaula #:</th>
                                    <td><?= htmlspecialchars($holdingcage['cage_id']); ?></td>
                                </tr>
                                <tr>
                                    <th>Investigador Principal (PI):</th>
                                    <td><?= htmlspecialchars($holdingcage['pi_initials'] . ' [' . $holdingcage['pi_name'] . ']'); ?></td>
                                </tr>
                                <tr>
                                    <th>Cepa:</th>
                                    <td>
                                        <a href="javascript:void(0);" class="fw-bold" onclick="viewStrainDetails(
                                            '<?= htmlspecialchars($holdingcage['str_id'] ?? 'NA'); ?>', 
                                            '<?= htmlspecialchars($holdingcage['str_name'] ?? 'Nombre Desconocido'); ?>', 
                                            '<?= htmlspecialchars($holdingcage['str_aka'] ?? ''); ?>', 
                                            '<?= htmlspecialchars($holdingcage['str_url'] ?? '#'); ?>', 
                                            '<?= htmlspecialchars($holdingcage['str_rrid'] ?? ''); ?>', 
                                            `<?= htmlspecialchars($holdingcage['str_notes'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE) ?>`)">
                                            <?= htmlspecialchars($holdingcage['str_id'] ?? 'NA'); ?> | <?= htmlspecialchars($holdingcage['str_name'] ?? 'Nombre Desconocido'); ?>
                                        </a>
                                    </td>
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
                                    <th>Cantidad de Ratones (Qty):</th>
                                    <td><?= htmlspecialchars($holdingcage['quantity']); ?></td>
                                </tr>
                                <tr>
                                    <th>Fecha de Nacimiento (DOB):</th>
                                    <td><?= htmlspecialchars($holdingcage['dob']); ?></td>
                                </tr>
                                <tr>
                                    <th>Edad:</th>
                                    <td><?= htmlspecialchars($ageString); ?></td>
                                </tr>
                                <tr>
                                    <th>Sexo:</th>
                                    <td><?= htmlspecialchars($holdingcage['sex'] === 'Male' ? 'Macho' : 'Hembra'); ?></td>
                                </tr>
                                <tr>
                                    <th>Jaula Parental:</th>
                                    <td><?= htmlspecialchars($holdingcage['parent_cg']); ?></td>
                                </tr>
                                <tr>
                                    <th>Observaciones:</th>
                                    <td class="remarks-column"><?= nl2br(htmlspecialchars($holdingcage['remarks'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>


                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-paw"></i> Información Individual de Ratones
                        </div>
                        <?php if (!empty($mice)) : ?>
                            <?php foreach ($mice as $index => $mouse) : ?>
                                <h6 class="text-primary fw-bold mt-2"><i class="fas fa-mouse-pointer me-1"></i> Ratón #<?= $index + 1; ?></h6>
                                <div class="table-wrapper mb-3">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>ID del Ratón</th>
                                                <th>Genotipo</th>
                                                <th style="width: 40%">Notas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?= htmlspecialchars($mouse['mouse_id']); ?></td>
                                                <td><?= htmlspecialchars($mouse['genotype']); ?></td>
                                                <td class="remarks-column"><?= nl2br(htmlspecialchars($mouse['notes'])); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="text-center text-muted mb-0">No hay datos individuales de ratones disponibles para esta jaula.</p>
                        <?php endif; ?>
                    </div>


                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-folder-open"></i> Archivos Adjuntos
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th style="background-color: #eaecf4; color: #4e73df;">Nombre del Archivo</th>
                                        <th style="background-color: #eaecf4; color: #4e73df; width: 15%; text-align:center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($files->num_rows > 0): ?>
                                        <?php while ($file = $files->fetch_assoc()) : ?>
                                            <tr>
                                                <td style="background-color: #fff;"><?= htmlspecialchars($file['file_name']); ?></td>
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


                    <div class="section-card mb-0">
                        <div class="section-title d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-clipboard-list"></i> Historial de Mantenimiento</span>
                            <a href="maintenance.php?from=hc_dash" class="btn btn-warning btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Añadir Registro de Mantenimiento">
                                <i class="fas fa-wrench"></i>
                            </a>
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
                                                    <td style="background-color: #fff;"><?= nl2br(htmlspecialchars($log['comments'])); ?></td>
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
    </div>

    <div class="page-footer">
        <?php include 'footer.php'; ?>
    </div>

    <div class="popup-overlay" id="viewPopupOverlay"></div>
    <div class="popup-form" id="viewPopupForm">
        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
            <h5 class="text-primary fw-bold mb-0" id="viewFormTitle"><i class="fas fa-dna me-2"></i>Detalles de la Cepa</h5>
            <button type="button" class="btn-close" onclick="closeViewForm()" style="background:none; border:none; font-size: 20px;">&times;</button>
        </div>
        <div class="row g-2">
            <div class="col-sm-6 mb-2">
                <span class="form-label d-block text-secondary">ID de la Cepa:</span>
                <strong id="view_strain_id" class="text-dark"></strong>
            </div>
            <div class="col-sm-6 mb-2">
                <span class="form-label d-block text-secondary">Nombre de la Cepa:</span>
                <strong id="view_strain_name" class="text-dark"></strong>
            </div>
            <div class="col-sm-6 mb-2">
                <span class="form-label d-block text-secondary">Nombres Comunes (Alias):</span>
                <strong id="view_strain_aka" class="text-dark"></strong>
            </div>
            <div class="col-sm-6 mb-2">
                <span class="form-label d-block text-secondary">RRID de la Cepa:</span>
                <strong id="view_strain_rrid" class="text-dark"></strong>
            </div>
            <div class="col-sm-12 mb-2">
                <span class="form-label d-block text-secondary">URL de la Cepa:</span>
                <a href="#" id="view_strain_url" target="_blank" class="fw-bold"></a>
            </div>
            <div class="col-sm-12 mb-2">
                <span class="form-label d-block text-secondary">Notas:</span>
                <p id="view_strain_notes" class="bg-light p-2 rounded text-dark mb-0" style="max-height: 150px; overflow-y: auto;"></p>
            </div>
        </div>
        <div class="d-flex justify-content-end mt-4">
            <button type="button" class="btn btn-secondary" onclick="closeViewForm()">Cerrar</button>
        </div>
    </div>

    <script>
        function showQrCodePopup(cageId) {
            var popup = window.open("", "Código QR de la Jaula " + cageId, "width=400,height=400");
            var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://' + <?php echo json_encode($url); ?> + '/hc_view.php?id=' + encodeURIComponent(cageId);

            var htmlContent = `
                <html>
                <head>
                    <title>Código QR de la Jaula ${cageId}</title>
                    <style>
                        body { font-family: Arial, sans-serif; text-align: center; padding-top: 40px; }
                        h1 { color: #333; font-size: 18px; }
                        img { margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <h1>Código QR de la Jaula ${cageId}</h1>
                    <img src="${qrUrl}" alt="Código QR de la Jaula ${cageId}" />
                </body>
                </html>
            `;

            popup.document.write(htmlContent);
            popup.document.close();
        }

        function viewStrainDetails(id, name, aka, url, rrid, notes) {
            document.getElementById('view_strain_id').innerText = id;
            document.getElementById('view_strain_name').innerText = name;
            document.getElementById('view_strain_aka').innerText = aka;
            document.getElementById('view_strain_url').innerText = url;
            document.getElementById('view_strain_rrid').innerText = rrid;
            document.getElementById('view_strain_notes').innerHTML = notes.replace(/\n/g, '<br>');
            document.getElementById('viewPopupOverlay').style.display = 'block';
            document.getElementById('viewPopupForm').style.display = 'block';
            document.getElementById('view_strain_url').href = url; 
        }

        function closeViewForm() {
            document.getElementById('viewPopupOverlay').style.display = 'none';
            document.getElementById('viewPopupForm').style.display = 'none';
        }
    </script>
</body>

</html>
