<?php

/**
 * Script de Edición de Jaulas de Mantenimiento
 * * Este script maneja la edición de registros de jaulas de mantenimiento, incluyendo bitácoras de mantenimiento y datos de ratones.
 * */

// Iniciar una nueva sesión o reanudar la existente
session_start();

// Incluir el archivo de conexión a la base de datos
require 'dbcon.php';

// Desactivar visualización de errores en producción (se registran en los registros del servidor)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Verificar si el usuario no ha iniciado sesión, redirigirlo a index.php con la URL actual para redirección después del inicio de sesión
if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit; // Salir para asegurar que no se ejecute más código
}

// Generar un token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getCurrentUrlParams() {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $search = isset($_GET['search']) ? urlencode($_GET['search']) : '';
    return "page=$page&search=$search";
}

// Consulta para recuperar usuarios con iniciales y nombres
$userQuery = "SELECT id, initials, name FROM users WHERE status = 'approved'";
$userResult = $con->query($userQuery);

// Consulta para recuperar opciones donde el rol es 'Investigador Principal' (PI)
$piQuery = "SELECT id, initials, name FROM users WHERE position = 'Investigador Principal' AND status = 'approved'";
$piResult = $con->query($piQuery);

// Consulta para recuperar valores de IACUC
$iacucQuery = "SELECT iacuc_id, iacuc_title FROM iacuc";
$iacucResult = $con->query($iacucQuery);

// Consulta para recuperar detalles de cepas incluyendo nombres comunes
$strainQuery = "SELECT str_id, str_name, str_aka FROM strains";
$strainResult = $con->query($strainQuery);

$strainOptions = [];

while ($strainrow = $strainResult->fetch_assoc()) {
    $str_id = htmlspecialchars($strainrow['str_id'] ?? 'Desconocido');
    $str_name = htmlspecialchars($strainrow['str_name'] ?? 'Cepa Sin Nombre');
    $str_aka = $strainrow['str_aka'] ? htmlspecialchars($strainrow['str_aka']) : '';

    $strainOptions[] = "$str_id | $str_name";

    if (!empty($str_aka)) {
        $akaNames = explode(', ', $str_aka);
        foreach ($akaNames as $aka) {
            $strainOptions[] = "$str_id | " . htmlspecialchars(trim($aka));
        }
    }
}

sort($strainOptions, SORT_STRING);

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $query = "SELECT h.*, c.pi_name AS pi_name, c.quantity, c.remarks
              FROM holding h
              LEFT JOIN cages c ON h.cage_id = c.cage_id 
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

    $mouseQuery = "SELECT * FROM mice WHERE cage_id = ?";
    $stmtMouse = $con->prepare($mouseQuery);
    $stmtMouse->bind_param("s", $id);
    $stmtMouse->execute();
    $mouseResult = $stmtMouse->get_result();
    $mice = $mouseResult->fetch_all(MYSQLI_ASSOC);

    if ($result->num_rows === 1) {
        $holdingcage = $result->fetch_assoc();

        $selectedUsersQuery = "SELECT user_id FROM cage_users WHERE cage_id = ?";
        $stmtUsers = $con->prepare($selectedUsersQuery);
        $stmtUsers->bind_param("s", $id);
        $stmtUsers->execute();
        $usersResult = $stmtUsers->get_result();
        $selectedUsers = array_column($usersResult->fetch_all(MYSQLI_ASSOC), 'user_id');
        $stmtUsers->close();

        // SEGURIDAD: Validación de pertenencia a la jaula
        $currentUserId = $_SESSION['user_id'];
        $userRole = $_SESSION['role'];
        $cageUsers = $selectedUsers;

        if ($userRole !== 'admin' && !in_array($currentUserId, $cageUsers)) {
            $_SESSION['message'] = 'Acceso denegado. Solo el administrador o el usuario asignado pueden editar.';
            header("Location: hc_dash.php?" . getCurrentUrlParams());
            exit();
        }

        $selectedIacucs = [];
        $selectedIacucQuery = "SELECT iacuc_id FROM cage_iacuc WHERE cage_id = ?";
        $stmtSelectedIacuc = $con->prepare($selectedIacucQuery);
        $stmtSelectedIacuc->bind_param("s", $id);
        $stmtSelectedIacuc->execute();
        $resultSelectedIacuc = $stmtSelectedIacuc->get_result();
        while ($row = $resultSelectedIacuc->fetch_assoc()) {
            $selectedIacucs[] = $row['iacuc_id'];
        }
        $stmtSelectedIacuc->close();

        $selectedPiId = $holdingcage['pi_name'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                die('Fallo la validación del token CSRF');
            }

            $cage_id = trim(mysqli_real_escape_string($con, $_POST['cage_id']));
            $pi_name = mysqli_real_escape_string($con, $_POST['pi_name']);
            $strain = mysqli_real_escape_string($con, $_POST['strain']);
            $iacuc = isset($_POST['iacuc']) ? array_map(function ($value) use ($con) {
                return mysqli_real_escape_string($con, $value);
            }, $_POST['iacuc']) : [];
            $users = isset($_POST['user']) ? array_map(function ($user_id) use ($con) {
                return mysqli_real_escape_string($con, trim($user_id));
            }, $_POST['user']) : [];
            $dob = mysqli_real_escape_string($con, $_POST['dob']);
            $sex = mysqli_real_escape_string($con, $_POST['sex']);
            $parent_cg = mysqli_real_escape_string($con, $_POST['parent_cg']);
            $remarks = mysqli_real_escape_string($con, $_POST['remarks']);

            $updateQueryHolding = "UPDATE holding SET strain = ?, dob = ?, sex = ?, parent_cg = ? WHERE cage_id = ?";
            $stmtHolding = $con->prepare($updateQueryHolding);
            $stmtHolding->bind_param("sssss", $strain, $dob, $sex, $parent_cg, $cage_id);
            $stmtHolding->execute();
            $stmtHolding->close();

            $updateQueryCages = "UPDATE cages SET pi_name = ?, remarks = ? WHERE cage_id = ?";
            $stmtCages = $con->prepare($updateQueryCages);
            $stmtCages->bind_param("iss", $pi_name, $remarks, $cage_id);
            $stmtCages->execute();
            $stmtCages->close();

            $deleteUsersQuery = "DELETE FROM cage_users WHERE cage_id = ?";
            $stmtDeleteUsers = $con->prepare($deleteUsersQuery);
            $stmtDeleteUsers->bind_param("s", $cage_id);
            $stmtDeleteUsers->execute();
            $stmtDeleteUsers->close();

            $insertUsersQuery = "INSERT INTO cage_users (cage_id, user_id) VALUES (?, ?)";
            $stmtInsertUsers = $con->prepare($insertUsersQuery);
            foreach ($users as $user_id) {
                $stmtInsertUsers->bind_param("si", $cage_id, $user_id);
                $stmtInsertUsers->execute();
            }
            $stmtInsertUsers->close();

            $deleteIacucQuery = "DELETE FROM cage_iacuc WHERE cage_id = ?";
            $stmtDeleteIacuc = $con->prepare($deleteIacucQuery);
            $stmtDeleteIacuc->bind_param("s", $cage_id);
            $stmtDeleteIacuc->execute();
            $stmtDeleteIacuc->close();

            $insertIacucQuery = "INSERT INTO cage_iacuc (cage_id, iacuc_id) VALUES (?, ?)";
            $stmtInsertIacuc = $con->prepare($insertIacucQuery);
            foreach ($iacuc as $iacuc_id) {
                $stmtInsertIacuc->bind_param("ss", $cage_id, $iacuc_id);
                $stmtInsertIacuc->execute();
            }
            $stmtInsertIacuc->close();

            // Guardar ratones
            $mouse_ids = $_POST['mouse_id'] ?? [];
            $genotypes = $_POST['genotype'] ?? [];
            $notes = $_POST['notes'] ?? [];
            $existing_mouse_ids = $_POST['existing_mouse_id'] ?? [];

            for ($i = 0; $i < count($mouse_ids); $i++) {
                $mouse_id = mysqli_real_escape_string($con, $mouse_ids[$i]);
                $genotype = mysqli_real_escape_string($con, $genotypes[$i]);
                $note = mysqli_real_escape_string($con, $notes[$i]);
                $existing_mouse_id = isset($existing_mouse_ids[$i]) ? mysqli_real_escape_string($con, $existing_mouse_ids[$i]) : null;

                if (!empty($mouse_id)) {
                    if ($existing_mouse_id) {
                        $updateMouseQuery = "UPDATE mice SET mouse_id = ?, genotype = ?, notes = ? WHERE id = ?";
                        $stmtMouseUpdate = $con->prepare($updateMouseQuery);
                        $stmtMouseUpdate->bind_param("sssi", $mouse_id, $genotype, $note, $existing_mouse_id);
                        $stmtMouseUpdate->execute();
                        $stmtMouseUpdate->close();
                    } else {
                        $insertMouseQuery = "INSERT INTO mice (cage_id, mouse_id, genotype, notes) VALUES (?, ?, ?, ?)";
                        $stmtMouseInsert = $con->prepare($insertMouseQuery);
                        $stmtMouseInsert->bind_param("ssss", $cage_id, $mouse_id, $genotype, $note);
                        $stmtMouseInsert->execute();
                        $stmtMouseInsert->close();
                    }
                }
            }

            if (!empty($_POST['mice_to_delete'])) {
                $miceToDelete = explode(',', $_POST['mice_to_delete']);
                foreach ($miceToDelete as $mouseId) {
                    $deleteMouseQuery = "DELETE FROM mice WHERE id = ?";
                    $stmtMouseDelete = $con->prepare($deleteMouseQuery);
                    $stmtMouseDelete->bind_param("i", $mouseId);
                    $stmtMouseDelete->execute();
                    $stmtMouseDelete->close();
                }
            }

            // Actualizar la cantidad total de la jaula en base al censo de ratones
            $newQtyQuery = "SELECT COUNT(*) AS new_qty FROM mice WHERE cage_id = ?";
            $stmtNewQty = $con->prepare($newQtyQuery);
            $stmtNewQty->bind_param("s", $cage_id);
            $stmtNewQty->execute();
            $stmtNewQty->bind_result($newQty);
            $stmtNewQty->fetch();
            $stmtNewQty->close();

            $updateQtyQuery = "UPDATE cages SET quantity = ? WHERE cage_id = ?";
            $stmtUpdateQty = $con->prepare($updateQtyQuery);
            $stmtUpdateQty->bind_param("is", $newQty, $cage_id);
            $stmtUpdateQty->execute();
            $stmtUpdateQty->close();

            // Manejo de bitácora de mantenimiento
            if (isset($_POST['log_ids']) && isset($_POST['log_comments'])) {
                $logIds = $_POST['log_ids'];
                $logComments = $_POST['log_comments'];

                for ($i = 0; $i < count($logIds); $i++) {
                    $edit_log_id = intval($logIds[$i]);
                    $edit_comment = trim(mysqli_real_escape_string($con, $logComments[$i]));

                    $updateLogQuery = "UPDATE maintenance SET comments = ? WHERE id = ?";
                    $stmtUpdateLog = $con->prepare($updateLogQuery);
                    $stmtUpdateLog->bind_param("si", $edit_comment, $edit_log_id);
                    $stmtUpdateLog->execute();
                    $stmtUpdateLog->close();
                }

                $_SESSION['message'] = 'Registros de mantenimiento actualizados exitosamente.';
            }

            if (!empty($_POST['logs_to_delete'])) {
                $logsToDelete = explode(',', $_POST['logs_to_delete']);
                foreach ($logsToDelete as $logId) {
                    $deleteLogQuery = "DELETE FROM maintenance WHERE id = ?";
                    $stmtDeleteLog = $con->prepare($deleteLogQuery);
                    $stmtDeleteLog->bind_param("i", $logId);
                    $stmtDeleteLog->execute();
                    $stmtDeleteLog->close();
                }
            }

            // Manejo de subida de archivos
            if (isset($_FILES['fileUpload']) && $_FILES['fileUpload']['error'] == UPLOAD_ERR_OK) {
                $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
                $maxFileSize = 10 * 1024 * 1024;

                if ($_FILES['fileUpload']['size'] > $maxFileSize) {
                    $_SESSION['message'] .= " El tamaño del archivo excede el límite de 10 MB.";
                } else {
                    $fileExtension = strtolower(pathinfo($_FILES['fileUpload']['name'], PATHINFO_EXTENSION));

                    if (!in_array($fileExtension, $allowedExtensions)) {
                        $_SESSION['message'] .= " Tipo de archivo no válido.";
                    } else {
                        $originalFileName = preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($_FILES['fileUpload']['name']));
                        $targetDirectory = "uploads/$cage_id/";

                        if (!file_exists($targetDirectory)) {
                            mkdir($targetDirectory, 0777, true);
                        }

                        $targetFilePath = $targetDirectory . $originalFileName;

                        if (!file_exists($targetFilePath)) {
                            if (move_uploaded_file($_FILES['fileUpload']['tmp_name'], $targetFilePath)) {
                                $insert = $con->prepare("INSERT INTO files (file_name, file_path, cage_id) VALUES (?, ?, ?)");
                                $insert->bind_param("sss", $originalFileName, $targetFilePath, $cage_id);
                                $insert->execute();
                                $insert->close();
                                $_SESSION['message'] .= " Archivo subido exitosamente.";
                            }
                        } else {
                            $_SESSION['message'] .= " El archivo ya existe en el servidor.";
                        }
                    }
                }
            }

            header("Location: hc_dash.php?" . getCurrentUrlParams());
            exit();
        }
    }
}

// Función para recuperar detalles de usuario
function getUserDetailsByIds($con, $userIds)
{
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

$piDetails = getUserDetailsByIds($con, [$selectedPiId]);
$piDisplay = isset($piDetails[$selectedPiId]) ? $piDetails[$selectedPiId] : 'PI Desconocido';

require 'header.php';
?>

<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Jaula (Holding) | <?php echo htmlspecialchars($labName); ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css" rel="stylesheet">

    <style>
        /* --- ESTANDARIZACIÓN FLEXBOX PARA EL FOOTER AL FONDO --- */
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
            color: red;
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 0;
        }
    </style>
</head>

<body>
    <div class="page-content">
        <div class="container mt-4 mb-5" style="max-width: 900px;">

            <div class="card main-card">
                <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-edit me-2"></i> Editar Jaula (Holding)</h4>
                    <div class="d-flex gap-2">
                        <a href="javascript:void(0);" onclick="goBack()" class="btn btn-outline-light btn-icon" title="Volver atrás">
                            <i class="fas fa-arrow-circle-left"></i>
                        </a>
                        <button type="button" onclick="document.getElementById('editForm').submit();" class="btn btn-success btn-icon" title="Guardar Cambios">
                            <i class="fas fa-save"></i>
                        </button>
                    </div>
                </div>

                <div class="card-body bg-light p-4">
                    <form id="editForm" method="POST" action="hc_edit.php?id=<?= $id; ?>&<?= getCurrentUrlParams(); ?>" enctype="multipart/form-data">
                        <input type="hidden" id="mice_to_delete" name="mice_to_delete" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-id-badge"></i> Información General de la Jaula
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6 mb-3">
                                    <label for="cage_id" class="form-label">ID de Jaula <span class="required-asterisk">*</span></label>
                                    <input type="text" class="form-control bg-light" id="cage_id" name="cage_id" value="<?= htmlspecialchars($holdingcage['cage_id']); ?>" readonly required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="pi_name" class="form-label">Investigador Principal (PI) <span class="required-asterisk">*</span></label>
                                    <select class="form-control" id="pi_name" name="pi_name" required>
                                        <option value="" disabled>Seleccionar PI</option>
                                        <?php $piResult->data_seek(0); while ($row = $piResult->fetch_assoc()) : ?>
                                            <option value="<?= htmlspecialchars($row['id']); ?>" <?= ($row['id'] == $selectedPiId) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($row['initials']) . ' [' . htmlspecialchars($row['name']) . ']'; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="strain" class="form-label">Cepa <span class="required-asterisk">*</span></label>
                                    <select class="form-control" id="strain" name="strain" required style="width: 100%;">
                                        <option value="" disabled>Seleccionar Cepa</option>
                                        <?php foreach ($strainOptions as $option) : ?>
                                            <option value="<?= htmlspecialchars($option); ?>" <?= ($option == $holdingcage['strain']) ? 'selected' : ''; ?>>
                                                <?= htmlspecialchars($option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="iacuc" class="form-label">IACUC <span class="required-asterisk">*</span></label>
                                    <select class="form-control" id="iacuc" name="iacuc[]" multiple required style="width: 100%;">
                                        <?php
                                        if ($iacucResult->num_rows > 0) {
                                            $iacucResult->data_seek(0);
                                            while ($iacucRow = $iacucResult->fetch_assoc()) {
                                                $iacuc_id = htmlspecialchars($iacucRow['iacuc_id']);
                                                $iacuc_title = htmlspecialchars($iacucRow['iacuc_title']);
                                                $selected = in_array($iacuc_id, $selectedIacucs) ? 'selected' : '';
                                                $truncated_title = strlen($iacuc_title) > 50 ? substr($iacuc_title, 0, 50) . '...' : $iacuc_title;
                                                echo "<option value='$iacuc_id' title='$iacuc_title' $selected>$iacuc_id | $truncated_title</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="user" class="form-label">Usuario(s) <span class="required-asterisk">*</span></label>
                                    <select class="form-control" id="user" name="user[]" multiple required style="width: 100%;">
                                        <?php
                                        $userResult->data_seek(0);
                                        while ($userRow = $userResult->fetch_assoc()) {
                                            $user_id = htmlspecialchars($userRow['id']);
                                            $initials = htmlspecialchars($userRow['initials']);
                                            $name = htmlspecialchars($userRow['name']);
                                            $selected = in_array($user_id, $selectedUsers) ? 'selected' : '';
                                            echo "<option value='$user_id' $selected>$initials [$name]</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="dob" class="form-label">Fecha de Nacimiento (DOB) <span class="required-asterisk">*</span></label>
                                    <input type="date" class="form-control" id="dob" name="dob" value="<?= htmlspecialchars($holdingcage['dob']); ?>" required min="1900-01-01">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="sex" class="form-label">Sexo <span class="required-asterisk">*</span></label>
                                    <select class="form-control" id="sex" name="sex" required>
                                        <option value="" disabled <?= empty($holdingcage['sex']) ? 'selected' : ''; ?>>Seleccionar Sexo</option>
                                        <option value="Male" <?= $holdingcage['sex'] === 'Male' ? 'selected' : ''; ?>>Macho</option>
                                        <option value="Female" <?= $holdingcage['sex'] === 'Female' ? 'selected' : ''; ?>>Hembra</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="parent_cg" class="form-label">Jaula Materna (Parent Cage) <span class="required-asterisk">*</span></label>
                                    <input type="text" class="form-control" id="parent_cg" name="parent_cg" value="<?= htmlspecialchars($holdingcage['parent_cg']); ?>" required>
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="remarks" class="form-label">Observaciones</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3"><?= htmlspecialchars($holdingcage['remarks']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-paw"></i> Censo de Ratones (Total: <?= htmlspecialchars($holdingcage['quantity']); ?>)
                            </div>

                            <div id="mouse_fields_container">
                                <?php foreach ($mice as $index => $mouse) : ?>
                                    <div class="mb-3 border p-3 rounded" id="mouse_fields_<?= $index + 1; ?>">
                                        <h6>Ratón #<?= $index + 1; ?></h6>
                                        <input type="hidden" name="existing_mouse_id[]" value="<?= $mouse['id']; ?>">
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">ID del Ratón</label>
                                                <input type="text" class="form-control" name="mouse_id[]" value="<?= htmlspecialchars($mouse['mouse_id']); ?>">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">Genotipo</label>
                                                <input type="text" class="form-control" name="genotype[]" value="<?= htmlspecialchars($mouse['genotype']); ?>">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label">Notas</label>
                                                <input type="text" class="form-control" name="notes[]" value="<?= htmlspecialchars($mouse['notes']); ?>">
                                            </div>
                                            <div class="col-md-12 text-end">
                                                <button type="button" class="btn btn-danger btn-sm" onclick="markMouseForDeletion(<?= $mouse['id']; ?>, <?= $index + 1; ?>)">
                                                    <i class="fas fa-trash me-1"></i> Borrar Ratón
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary btn-sm" onclick="addMouseField()">
                                    <i class="fas fa-plus me-1"></i> Añadir Ratón
                                </button>
                            </div>
                        </div>

                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-clipboard-list"></i> Historial de Mantenimiento
                            </div>

                            <?php
                            $maintenanceQuery = "SELECT * FROM maintenance WHERE cage_id = ? ORDER BY date DESC";
                            $stmtMaint = $con->prepare($maintenanceQuery);
                            $stmtMaint->bind_param("s", $id);
                            $stmtMaint->execute();
                            $maintenanceLogs = $stmtMaint->get_result();

                            if ($maintenanceLogs->num_rows > 0) :
                            ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Comentarios</th>
                                                <th class="text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($log = $maintenanceLogs->fetch_assoc()) : ?>
                                                <tr id="log_row_<?= $log['id']; ?>">
                                                    <td><?= htmlspecialchars($log['date']); ?></td>
                                                    <td>
                                                        <input type="hidden" name="log_ids[]" value="<?= $log['id']; ?>">
                                                        <input type="text" class="form-control" name="log_comments[]" value="<?= htmlspecialchars($log['comments']); ?>">
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="markLogForDeletion(<?= $log['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else : ?>
                                <p class="text-muted mb-0">No hay bitácoras de mantenimiento para esta jaula.</p>
                            <?php endif; $stmtMaint->close(); ?>
                            <input type="hidden" id="logs_to_delete" name="logs_to_delete" value="">
                        </div>

                        <div class="section-card mb-0">
                            <div class="section-title">
                                <i class="fas fa-folder-open"></i> Documentos Adjuntos
                            </div>

                            <div class="mb-3">
                                <label for="fileUpload" class="form-label">Subir Archivo (Max 10MB)</label>
                                <input type="file" class="form-control" id="fileUpload" name="fileUpload">
                            </div>

                            <?php if ($files->num_rows > 0) : ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nombre del Archivo</th>
                                                <th class="text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($file = $files->fetch_assoc()) : ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($file['file_name']); ?></td>
                                                    <td class="text-center">
                                                        <a href="<?= htmlspecialchars($file['file_path']); ?>" download="<?= htmlspecialchars($file['file_name']); ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="fas fa-cloud-download-alt"></i></a>
                                                        <a href="delete_file.php?url=hc_edit&id=<?= intval($file['id']); ?>" class="btn btn-sm btn-outline-danger btn-icon" onclick="return confirm('¿Seguro que deseas eliminar este archivo?');"><i class="fas fa-trash-alt"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="page-footer">
        <?php include 'footer.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js"></script>

    <script>
        function goBack() {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 1;
            const search = urlParams.get('search') || '';
            window.location.href = 'hc_dash.php?page=' + page + '&search=' + encodeURIComponent(search);
        }

        function addMouseField() {
            const mouseContainer = document.getElementById('mouse_fields_container');
            const mouseCount = mouseContainer.childElementCount + 1;
            const mouseFieldHTML = `
                <div id="mouse_fields_${mouseCount}" class="mb-3 border p-3 rounded">
                    <h6>Ratón #${mouseCount}</h6>
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">ID del Ratón</label>
                            <input type="text" class="form-control" name="mouse_id[]" value="">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Genotipo</label>
                            <input type="text" class="form-control" name="genotype[]" value="">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Notas</label>
                            <input type="text" class="form-control" name="notes[]" value="">
                        </div>
                    </div>
                </div>`;
            mouseContainer.insertAdjacentHTML('beforeend', mouseFieldHTML);
        }

        function markMouseForDeletion(mouseId, elementId) {
            if (confirm('¿Estás seguro de eliminar este registro de ratón?')) {
                const deleteField = document.getElementById('mice_to_delete');
                deleteField.value += (deleteField.value ? ',' : '') + mouseId;
                document.getElementById('mouse_fields_' + elementId).remove();
            }
        }

        function markLogForDeletion(logId) {
            if (confirm('¿Estás seguro de eliminar este registro de mantenimiento?')) {
                const logsField = document.getElementById('logs_to_delete');
                logsField.value += (logsField.value ? ',' : '') + logId;
                document.getElementById('log_row_' + logId).remove();
            }
        }

        $(document).ready(function() {
            $('#user').select2({ placeholder: "Seleccionar Usuario(s)", allowClear: true });
            $('#strain').select2({ placeholder: "Seleccionar Cepa", allowClear: true });
            $('#iacuc').select2({ placeholder: "Seleccionar IACUC", allowClear: true });
        });
    </script>
</body>

</html>
