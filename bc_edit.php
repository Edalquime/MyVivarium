<?php

/**
 * Script para Editar Jaulas de Cruce
 * Interfaz modernizada en Español, con Cepa obligatoria y Fecha de Cruce. Se autogeneran tareas de destete al guardar nacimientos.
 */

session_start();

require 'dbcon.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getCurrentUrlParams() {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $search = isset($_GET['search']) ? urlencode($_GET['search']) : '';
    return "page=$page&search=$search";
}

$userQuery = "SELECT id, initials, name FROM users WHERE status = 'approved'";
$userResult = $con->query($userQuery);

$query1 = "SELECT id, initials, name FROM users WHERE position = 'Principal Investigator' AND status = 'approved'";
$result1 = $con->query($query1);

$strainQuery = "SELECT str_id, str_name, str_aka FROM strains";
$strainResult = $con->query($strainQuery);

$strainOptions = [];

while ($strainrow = $strainResult->fetch_assoc()) {
    $str_id = htmlspecialchars($strainrow['str_id'] ?? 'Unknown');
    $str_name = htmlspecialchars($strainrow['str_name'] ?? 'Unnamed Strain');
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
    $id = mysqli_real_escape_string($con, $_GET['id']);

    $query = "SELECT * FROM breeding WHERE cage_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    $query2 = "SELECT * FROM files WHERE cage_id = ?";
    $stmt2 = $con->prepare($query2);
    $stmt2->bind_param("s", $id);
    $stmt2->execute();
    $files = $stmt2->get_result();

    $query3 = "SELECT * FROM litters WHERE `cage_id` = ?";
    $stmt3 = $con->prepare($query3);
    $stmt3->bind_param("s", $id);
    $stmt3->execute();
    $litters = $stmt3->get_result();

    $iacucQuery = "SELECT iacuc_id, iacuc_title FROM iacuc";
    $iacucResult = $con->query($iacucQuery);

    if ($result && $result->num_rows === 1) {
        $breedingcage = $result->fetch_assoc();

        $selectedIacucsQuery = "SELECT i.iacuc_id, i.iacuc_title
                                FROM cage_iacuc ci
                                JOIN iacuc i ON ci.iacuc_id = i.iacuc_id
                                WHERE ci.cage_id = ?";
        $stmtIacucs = $con->prepare($selectedIacucsQuery);
        $stmtIacucs->bind_param("s", $id);
        $stmtIacucs->execute();
        $selectedIacucsResult = $stmtIacucs->get_result();
        $selectedIacucs = [];
        while ($row = $selectedIacucsResult->fetch_assoc()) {
            $selectedIacucs[] = $row['iacuc_id'];
        }
        $stmtIacucs->close();

        $selectedUsersQuery = "SELECT user_id FROM cage_users WHERE cage_id = ?";
        $stmtUsers = $con->prepare($selectedUsersQuery);
        $stmtUsers->bind_param("s", $id);
        $stmtUsers->execute();
        $selectedUsersResult = $stmtUsers->get_result();
        $selectedUsers = [];
        while ($row = $selectedUsersResult->fetch_assoc()) {
            $selectedUsers[] = $row['user_id'];
        }
        $stmtUsers->close();

        $currentUserId = $_SESSION['user_id'];
        $userRole = $_SESSION['role'];
        $cageUsers = $selectedUsers;

        if ($userRole !== 'admin' && !in_array($currentUserId, $cageUsers)) {
            $_SESSION['message'] = 'Acceso denegado. Solo administradores o usuarios asignados pueden editar.';
            header("Location: bc_dash.php?" . getCurrentUrlParams());
            exit();
        }

        $selectedPiId = isset($breedingcage['pi_name']) ? $breedingcage['pi_name'] : '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                die('Validación del token CSRF fallida');
            }

            $cage_id = trim(mysqli_real_escape_string($con, $_POST['cage_id']));
            $pi_name = mysqli_real_escape_string($con, $_POST['pi_name']);
            $pairing_date = mysqli_real_escape_string($con, $_POST['pairing_date']);
            $strain = mysqli_real_escape_string($con, $_POST['strain']);
            $iacuc = isset($_POST['iacuc']) ? $_POST['iacuc'] : [];
            $users = isset($_POST['user']) ? $_POST['user'] : [];

            $male_n = isset($_POST['male_n']) ? intval($_POST['male_n']) : 0;
            $female_n = isset($_POST['female_n']) ? intval($_POST['female_n']) : 0;

            $male_id_array = $_POST['male_id'] ?? [];
            $female_id_array = $_POST['female_id'] ?? [];
            $male_id = mysqli_real_escape_string($con, implode(', ', $male_id_array));
            $female_id = mysqli_real_escape_string($con, implode(', ', $female_id_array));

            $male_dob_array = $_POST['male_dob'] ?? [];
            $female_dob_array = $_POST['female_dob'] ?? [];
            $male_dob = mysqli_real_escape_string($con, implode(', ', $male_dob_array));
            $female_dob = mysqli_real_escape_string($con, implode(', ', $female_dob_array));

            $remarks = mysqli_real_escape_string($con, $_POST['remarks']);

            $delete_litter_ids = isset($_POST['delete_litter_ids']) ? $_POST['delete_litter_ids'] : [];

            $con->begin_transaction();

            try {
                $updateCageQuery = $con->prepare("UPDATE breeding SET pi_name = ? WHERE cage_id = ?");
                $updateCageQuery->bind_param("ss", $pi_name, $cage_id);
                $updateCageQuery->execute();
                $updateCageQuery->close();

                $updateBreedingQuery = $con->prepare("UPDATE breeding SET `cross` = ?, `strain` = ?, male_n = ?, male_id = ?, female_n = ?, female_id = ?, male_dob = ?, female_dob = ? WHERE cage_id = ?");
                $updateBreedingQuery->bind_param("sssssssss", $pairing_date, $strain, $male_n, $male_id, $female_n, $female_id, $male_dob, $female_dob, $cage_id);
                $updateBreedingQuery->execute();
                $updateBreedingQuery->close();

                $deleteIacucQuery = $con->prepare("DELETE FROM cage_iacuc WHERE cage_id = ?");
                $deleteIacucQuery->bind_param("s", $cage_id);
                $deleteIacucQuery->execute();
                $deleteIacucQuery->close();

                $insertIacucQuery = $con->prepare("INSERT INTO cage_iacuc (cage_id, iacuc_id) VALUES (?, ?)");
                foreach ($iacuc as $iacuc_id) {
                    $insertIacucQuery->bind_param("ss", $cage_id, $iacuc_id);
                    $insertIacucQuery->execute();
                }
                $insertIacucQuery->close();

                $deleteUsersQuery = $con->prepare("DELETE FROM cage_users WHERE cage_id = ?");
                $deleteUsersQuery->bind_param("s", $cage_id);
                $deleteUsersQuery->execute();
                $deleteUsersQuery->close();

                $insertUsersQuery = $con->prepare("INSERT INTO cage_users (cage_id, user_id) VALUES (?, ?)");
                foreach ($users as $user_id) {
                    $insertUsersQuery->bind_param("ss", $cage_id, $user_id);
                    $insertUsersQuery->execute();
                }
                $insertUsersQuery->close();

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

                if (!empty($delete_litter_ids)) {
                    foreach ($delete_litter_ids as $delete_litter_id) {
                        if (!empty($delete_litter_id)) {
                            $deleteLitterQuery = $con->prepare("DELETE FROM litters WHERE id = ?");
                            $deleteLitterQuery->bind_param("s", $delete_litter_id);
                            $deleteLitterQuery->execute();
                            $deleteLitterQuery->close();
                        }
                    }
                }

                // ===== 🐁 SECCIÓN CRÍTICA DE CAMADAS BLINDADA =====
                if (isset($_POST['litter_dob'])) {
                    for ($i = 0; $i < count($_POST['litter_dob']); $i++) {
                        $litter_dob_i = mysqli_real_escape_string($con, $_POST['litter_dob'][$i]);
                        
                        // Blindamos variables para que siempre envíen un 0 (numérico) y no "" (texto vacío)
                        $pups_alive_i = (isset($_POST['pups_alive'][$i]) && $_POST['pups_alive'][$i] !== '') ? intval($_POST['pups_alive'][$i]) : 0;
                        $pups_dead_i = (isset($_POST['pups_dead'][$i]) && $_POST['pups_dead'][$i] !== '') ? intval($_POST['pups_dead'][$i]) : 0;
                        
                        $remarks_litter_i = mysqli_real_escape_string($con, $_POST['remarks_litter'][$i]);
                        $litter_id_i = isset($_POST['litter_id'][$i]) ? mysqli_real_escape_string($con, $_POST['litter_id'][$i]) : '';

                        $isNewLitter = empty($litter_id_i);

                        if (!$isNewLitter) {
                            // Quitamos pups_male y pups_female de la consulta UPDATE y ajustamos types a "siisi"
                            $updateLitterQuery = $con->prepare("UPDATE litters SET `litter_dob` = ?, `pups_alive` = ?, `pups_dead` = ?, `remarks` = ? WHERE `id` = ?");
                            
                            if ($updateLitterQuery === false) {
                                throw new Exception("Error al preparar UPDATE en litters: " . $con->error);
                            }

                            $updateLitterQuery->bind_param("siisi", $litter_dob_i, $pups_alive_i, $pups_dead_i, $remarks_litter_i, $litter_id_i);
                            $updateLitterQuery->execute();
                            $updateLitterQuery->close();
                        } else {
                            // Quitamos pups_male y pups_female de la consulta INSERT y ajustamos types a "ssiis"
                            $insertLitterQuery = $con->prepare("INSERT INTO litters (`cage_id`, `litter_dob`, `pups_alive`, `pups_dead`, `remarks`) VALUES (?, ?, ?, ?, ?)");
                            
                            if ($insertLitterQuery === false) {
                                throw new Exception("Error al preparar INSERT en litters: " . $con->error);
                            }

                            $insertLitterQuery->bind_param("ssiis", $cage_id, $litter_dob_i, $pups_alive_i, $pups_dead_i, $remarks_litter_i);
                            $insertLitterQuery->execute();
                            $insertLitterQuery->close();
                        }

                        if ($isNewLitter && !empty($litter_dob_i)) {

                            $date21 = date('Y-m-d', strtotime($litter_dob_i . ' +21 days'));
                            $date30 = date('Y-m-d', strtotime($litter_dob_i . ' +30 days'));

                            $taskQuery = $con->prepare("INSERT INTO tasks (title, description, assigned_by, assigned_to, status, completion_date, cage_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $taskBy = $_SESSION['user_id'];
                            $taskStatus = 'Pending';

                            foreach ($users as $userId) {
                                $title21 = "🍼 Destete de 21 días - Jaula: $cage_id";
                                $desc21 = "Nacimiento registrado el $litter_dob_i. Por favor evaluar destete de crías.";
                                $taskQuery->bind_param("sssisss", $title21, $desc21, $taskBy, $userId, $taskStatus, $date21, $cage_id);
                                $taskQuery->execute();

                                $title30 = "🚨 Destete LÍMITE de 30 días - Jaula: $cage_id";
                                $desc30 = "Nacimiento registrado el $litter_dob_i. Las crías deben destetarse a la brevedad.";
                                $taskQuery->bind_param("sssisss", $title30, $desc30, $taskBy, $userId, $taskStatus, $date30, $cage_id);
                                $taskQuery->execute();
                            }
                            $taskQuery->close();
                        }
                    }
                }

                $con->commit();
                $_SESSION['message'] = 'Registro de jaula actualizado exitosamente.';
            } catch (Exception $e) {
                $con->rollback();
                $_SESSION['message'] = 'La actualización falló: ' . $e->getMessage();
            }

            if (isset($_FILES['fileUpload']) && $_FILES['fileUpload']['error'] == UPLOAD_ERR_OK) {
                $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
                $maxFileSize = 10 * 1024 * 1024;

                if ($_FILES['fileUpload']['size'] > $maxFileSize) {
                    $_SESSION['message'] .= " El archivo excede el límite de 10 MB.";
                } else {
                    $fileExtension = strtolower(pathinfo($_FILES['fileUpload']['name'], PATHINFO_EXTENSION));

                    if (!in_array($fileExtension, $allowedExtensions)) {
                        $_SESSION['message'] .= " Tipo de archivo inválido.";
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
                                $_SESSION['message'] .= " Archivo subido con éxito.";
                            }
                        }
                    }
                }
            }

            header("Location: bc_dash.php?" . getCurrentUrlParams());
            exit();
        }
    } else {
        die("Error: No se encontró la Jaula de Cruce con ID '$id' o la estructura de la base de datos está dañada. Error de MySql: " . $con->error);
    }
} else {
    $_SESSION['message'] = 'El parámetro ID es faltante.';
    header("Location: bc_dash.php?" . getCurrentUrlParams());
    exit();
}

require 'header.php';

$litterCount = $litters->num_rows;
$litters->data_seek(0);
?>
<!doctype html>
<html lang="es">

<head>
    <title>Editar Jaula de Cruce | <?php echo htmlspecialchars($labName); ?></title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js"></script>

    <style>
        body {
            background-color: #f4f6f9;
        }

        .main-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
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

        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #d1d3e2;
            border-radius: 6px;
            min-height: 38px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
            color: #6e707e;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px;
        }

        .btn-modern {
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
        }

        .litter-entry {
            background-color: #f8fff8;
            border: 1px solid #c3e6cb !important;
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .litter-entry h6 {
            color: #1e7e34;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            function getCurrentDate() {
                const today = new Date();
                return `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            }

            function setMaxDate() {
                const currentDate = getCurrentDate();
                document.querySelectorAll('input[type="date"]').forEach(field => field.setAttribute('max', currentDate));
            }

            setMaxDate();

            const maleNumInput = document.getElementById('male_n');
            const femaleNumInput = document.getElementById('female_n');
            const maleIdContainer = document.getElementById('male_id_container');
            const femaleIdContainer = document.getElementById('female_id_container');

            const maleDobSaved = maleIdContainer.dataset.savedDob ? maleIdContainer.dataset.savedDob.split(', ') : [];
            const maleIdsSaved = maleIdContainer.dataset.savedId ? maleIdContainer.dataset.savedId.split(', ') : [];
            const femaleDobSaved = femaleIdContainer.dataset.savedDob ? femaleIdContainer.dataset.savedDob.split(', ') : [];
            const femaleIdsSaved = femaleIdContainer.dataset.savedId ? femaleIdContainer.dataset.savedId.split(', ') : [];

            function actualizarMachos(isFirstLoad = false) {
                const cantidad = parseInt(maleNumInput.value) || 0;
                maleIdContainer.innerHTML = '';

                for (let i = 1; i <= cantidad; i++) {
                    const savedId = (isFirstLoad && maleIdsSaved[i-1]) ? maleIdsSaved[i-1] : '';
                    const savedDate = (isFirstLoad && maleDobSaved[i-1]) ? maleDobSaved[i-1] : '';

                    maleIdContainer.innerHTML += `
                        <div class="mb-3 p-3 border rounded-3 bg-white shadow-sm">
                            <h6 class="text-primary fw-bold"><i class="fas fa-mars me-2"></i>Macho #${i}</h6>
                            <hr class="my-2">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">ID del Macho <span class="required-asterisk">*</span></label>
                                    <input type="text" class="form-control" name="male_id[]" required value="${savedId}" placeholder="Ej: M123">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Fecha Nac. del Macho <span class="required-asterisk">*</span></label>
                                    <input type="date" class="form-control" name="male_dob[]" required min="1900-01-01" value="${savedDate}">
                                </div>
                            </div>
                        </div>`;
                }
                setMaxDate();
            }

            function actualizarHembras(isFirstLoad = false) {
                const cantidad = parseInt(femaleNumInput.value) || 0;
                femaleIdContainer.innerHTML = '';

                for (let i = 1; i <= cantidad; i++) {
                    const savedId = (isFirstLoad && femaleIdsSaved[i-1]) ? femaleIdsSaved[i-1] : '';
                    const savedDate = (isFirstLoad && femaleDobSaved[i-1]) ? femaleDobSaved[i-1] : '';

                    femaleIdContainer.innerHTML += `
                        <div class="mb-3 p-3 border rounded-3 bg-white shadow-sm">
                            <h6 class="text-danger fw-bold"><i class="fas fa-venus me-2"></i>Hembra #${i}</h6>
                            <hr class="my-2">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">ID de la Hembra <span class="required-asterisk">*</span></label>
                                    <input type="text" class="form-control" name="female_id[]" required value="${savedId}" placeholder="Ej: F123">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Fecha Nac. de la Hembra <span class="required-asterisk">*</span></label>
                                    <input type="date" class="form-control" name="female_dob[]" required min="1900-01-01" value="${savedDate}">
                                </div>
                            </div>
                        </div>`;
                }
                setMaxDate();
            }

            maleNumInput.addEventListener('input', () => actualizarMachos(false));
            femaleNumInput.addEventListener('input', () => actualizarHembras(false));

            actualizarMachos(true);
            actualizarHembras(true);

            $('#user').select2({ placeholder: "Seleccionar Usuario(s)", allowClear: true, width: '100%' });
            $('#iacuc').select2({ placeholder: "Seleccionar Protocolo IACUC", allowClear: true, width: '100%' });
            $('#strain').select2({ placeholder: "Seleccionar Cepa", allowClear: true, width: '100%' });
        });

        let litterCount = <?= $litterCount ?>;

        function addLitter() {
            litterCount++;
            const container = document.getElementById('litters-container');
            const today = new Date();
            const todayStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;

            // Modificado: Se retiraron pups_male y pups_female de Javascript
            const html = `
                <div class="litter-entry">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0">
                            <i class="fas fa-paw me-2"></i>Camada #${litterCount} <span class="badge bg-success ms-1" style="font-size:0.7rem;">Nueva</span>
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLitter(this)">
                            <i class="fas fa-trash me-1"></i>Eliminar
                        </button>
                    </div>
                    <hr class="my-2">
                    <input type="hidden" name="litter_id[]" value="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Fecha de Nacimiento <span class="required-asterisk">*</span></label>
                            <input type="date" class="form-control" name="litter_dob[]" max="${todayStr}" required min="1900-01-01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Crías Vivas</label>
                            <input type="number" class="form-control" name="pups_alive[]" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Crías Muertas</label>
                            <input type="number" class="form-control" name="pups_dead[]" min="0" value="0">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Observaciones de la Camada</label>
                            <textarea class="form-control" name="remarks_litter[]" rows="2" placeholder="Notas sobre esta camada..."></textarea>
                        </div>
                    </div>
                </div>`;

            container.insertAdjacentHTML('beforeend', html);

            const currentDate = new Date();
            const maxDate = `${currentDate.getFullYear()}-${String(currentDate.getMonth()+1).padStart(2,'0')}-${String(currentDate.getDate()).padStart(2,'0')}`;
            container.querySelectorAll('input[type="date"]').forEach(field => field.setAttribute('max', maxDate));
        }

        function removeLitter(element) {
            const litterEntry = element.closest('.litter-entry');
            const litterIdInput = litterEntry.querySelector('[name="litter_id[]"]');

            if (litterIdInput && litterIdInput.value) {
                const newInput = document.createElement('input');
                newInput.type = 'hidden';
                newInput.name = 'delete_litter_ids[]';
                newInput.value = litterIdInput.value;
                document.querySelector('form').appendChild(newInput);
            }
            litterEntry.remove();
        }

        function markLogForDeletion(logId) {
            if (confirm('¿Está seguro que desea eliminar este registro de mantenimiento?')) {
                const logIdsInput = document.getElementById('logs_to_delete');
                let logsToDelete = logIdsInput.value ? logIdsInput.value.split(',') : [];
                logsToDelete.push(logId);
                logIdsInput.value = logsToDelete.join(',');

                const logRow = document.getElementById(`log-row-${logId}`);
                if (logRow) {
                    logRow.style.display = 'none';
                }
            }
        }

        function goBack() {
            window.history.back();
        }
    </script>
</head>

<body>
    <div class="container mt-4 mb-5">
        <div class="card main-card">
            <div class="card-header bg-dark text-white p-3 d-flex align-items-center justify-content-between" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h4 class="mb-0 fs-5"><i class="fas fa-edit me-2"></i>Editar Jaula de Cruce (ID: <?= htmlspecialchars($breedingcage['cage_id']); ?>)</h4>
                <button type="button" class="btn btn-sm btn-light" onclick="goBack()"><i class="fas fa-arrow-left me-1"></i> Volver</button>
            </div>

            <div class="card-body bg-light p-4">
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" id="logs_to_delete" name="logs_to_delete" value="">

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-cube"></i> Información General de la Jaula
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="cage_id" class="form-label">ID de la Jaula</label>
                                <input type="text" class="form-control" name="cage_id" id="cage_id" value="<?php echo htmlspecialchars($breedingcage['cage_id']); ?>" readonly>
                            </div>

                            <div class="col-md-6">
                                <label for="pi_name" class="form-label">Investigador Principal (PI) <span class="required-asterisk">*</span></label>
                                <select class="form-control" id="pi_name" name="pi_name" required>
                                    <?php
                                    while ($row = $result1->fetch_assoc()) {
                                        $selected = ($row['id'] == $selectedPiId) ? 'selected' : '';
                                        echo "<option value='{$row['id']}' $selected>{$row['initials']} [{$row['name']}]</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="strain" class="form-label">Cepa <span class="required-asterisk">*</span></label>
                                <select class="form-control" id="strain" name="strain" required>
                                    <option value="" disabled <?= empty($breedingcage['strain']) ? 'selected' : ''; ?>>Seleccionar Cepa</option>
                                    <?php
                                    $strainExists = false;
                                    foreach ($strainOptions as $option) {
                                        $value = explode(" | ", $option)[0];
                                        $selected = ($value == $breedingcage['strain']) ? 'selected' : '';
                                        if ($value == $breedingcage['strain']) $strainExists = true;
                                        echo "<option value='$value' $selected>$option</option>";
                                    }
                                    if (!$strainExists && !empty($breedingcage['strain'])) {
                                        $currentStrainId = htmlspecialchars($breedingcage['strain']);
                                        echo "<option value='$currentStrainId' disabled selected>$currentStrainId | Cepa Desconocida</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="pairing_date" class="form-label">Fecha de Cruce/Armado <span class="required-asterisk">*</span></label>
                                <input type="date" class="form-control" name="pairing_date" id="pairing_date" required value="<?php echo htmlspecialchars($breedingcage['cross']); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="iacuc" class="form-label">Protocolo IACUC</label>
                                <select class="form-control" id="iacuc" name="iacuc[]" multiple>
                                    <?php
                                    while ($iacucRow = $iacucResult->fetch_assoc()) {
                                        $selected = in_array($iacucRow['iacuc_id'], $selectedIacucs) ? 'selected' : '';
                                        echo "<option value='{$iacucRow['iacuc_id']}' $selected>{$iacucRow['iacuc_id']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="user" class="form-label">Usuarios Asignados <span class="required-asterisk">*</span></label>
                                <select class="form-control" id="user" name="user[]" multiple required>
                                    <?php
                                    while ($userRow = $userResult->fetch_assoc()) {
                                        $selected = in_array($userRow['id'], $selectedUsers) ? 'selected' : '';
                                        echo "<option value='{$userRow['id']}' $selected>{$userRow['initials']} [{$userRow['name']}]</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-mars"></i> Machos Reproductores
                        </div>
                        <div class="mb-3">
                            <label for="male_n" class="form-label">Cantidad de Machos <span class="required-asterisk">*</span></label>
                            <input type="number" class="form-control" id="male_n" name="male_n" required min="0" value="<?php echo $breedingcage['male_n'] ?? 0; ?>" style="max-width: 200px;">
                        </div>
                        <div id="male_id_container" data-saved-id="<?= htmlspecialchars($breedingcage['male_id'] ?? ''); ?>" data-saved-dob="<?= htmlspecialchars($breedingcage['male_dob'] ?? ''); ?>"></div>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-venus"></i> Hembras Reproductoras
                        </div>
                        <div class="mb-3">
                            <label for="female_n" class="form-label">Cantidad de Hembras <span class="required-asterisk">*</span></label>
                            <input type="number" class="form-control" id="female_n" name="female_n" required min="0" value="<?php echo $breedingcage['female_n'] ?? 0; ?>" style="max-width: 200px;">
                        </div>
                        <div id="female_id_container" data-saved-id="<?= htmlspecialchars($breedingcage['female_id'] ?? ''); ?>" data-saved-dob="<?= htmlspecialchars($breedingcage['female_dob'] ?? ''); ?>"></div>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-baby"></i> Registro de Camadas (Crías)
                        </div>

                        <div id="litters-container">
                            <?php
                            $litterIndex = 0;
                            while ($litter = $litters->fetch_assoc()):
                                $litterIndex++;
                            ?>
                            <div class="litter-entry">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0">
                                        <i class="fas fa-paw me-2"></i>Camada #<?= $litterIndex ?>
                                    </h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLitter(this)">
                                        <i class="fas fa-trash me-1"></i>Eliminar
                                    </button>
                                </div>
                                <hr class="my-2">
                                <input type="hidden" name="litter_id[]" value="<?= htmlspecialchars($litter['id']) ?>">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Fecha de Nacimiento <span class="required-asterisk">*</span></label>
                                        <input type="date" class="form-control" name="litter_dob[]"
                                               value="<?= htmlspecialchars($litter['litter_dob']) ?>" required min="1900-01-01">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Crías Vivas</label>
                                        <input type="number" class="form-control" name="pups_alive[]" min="0"
                                               value="<?= htmlspecialchars($litter['pups_alive']) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Crías Muertas</label>
                                        <input type="number" class="form-control" name="pups_dead[]" min="0"
                                               value="<?= htmlspecialchars($litter['pups_dead']) ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Observaciones de la Camada</label>
                                        <textarea class="form-control" name="remarks_litter[]" rows="2"
                                                  placeholder="Notas sobre esta camada..."><?= htmlspecialchars($litter['remarks']) ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>

                        <button type="button" class="btn btn-outline-success mt-2" onclick="addLitter()">
                            <i class="fas fa-plus me-1"></i> Agregar Camada
                        </button>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-comment-dots"></i> Observaciones de la Jaula
                        </div>
                        <textarea class="form-control" name="remarks" id="remarks" rows="3" placeholder="Añade notas adicionales..."><?php echo htmlspecialchars($breedingcage['remarks'] ?? ''); ?></textarea>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-paperclip"></i> Archivos Adjuntos
                        </div>

                        <?php if ($files->num_rows > 0): ?>
                        <div class="table-responsive mb-3">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nombre del Archivo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($file = $files->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($file['file_name']) ?></td>
                                        <td>
                                            <a href="<?= htmlspecialchars($file['file_path']) ?>" download="<?= htmlspecialchars($file['file_name']) ?>" class="btn btn-sm btn-outline-primary me-1">
                                                <i class="fas fa-cloud-download-alt"></i>
                                            </a>
                                            <a href="delete_file.php?url=bc_edit&id=<?= intval($file['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este archivo?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <p class="text-muted small mb-3">No hay archivos adjuntos.</p>
                        <?php endif; ?>

                        <label class="form-label">Subir nuevo archivo</label>
                        <input type="file" class="form-control" id="fileUpload" name="fileUpload">
                        <small class="text-muted">Máximo 10 MB. Formatos: pdf, doc, docx, xls, xlsx, ppt, pptx, jpg, png, gif, svg, webp.</small>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-wrench"></i> Registro de Mantenimiento
                            <a href="maintenance.php?from=bc_dash" class="btn btn-sm btn-warning ms-auto">
                                <i class="fas fa-plus me-1"></i> Agregar
                            </a>
                        </div>

                        <?php
                        $maintenanceQuery = "
                            SELECT m.id, m.timestamp, u.name AS user_name, m.comments, m.user_id
                            FROM maintenance m
                            JOIN users u ON m.user_id = u.id
                            WHERE m.cage_id = ?
                            ORDER BY m.timestamp DESC";
                        
                        $stmtMaintenance = $con->prepare($maintenanceQuery);
                        $stmtMaintenance->bind_param("s", $id);
                        $stmtMaintenance->execute();
                        $maintenanceLogs = $stmtMaintenance->get_result();
                        ?>

                        <?php if ($maintenanceLogs && $maintenanceLogs->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:22%;">Fecha</th>
                                        <th style="width:22%;">Usuario</th>
                                        <th style="width:46%;">Comentario</th>
                                        <th style="width:10%;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($log = $maintenanceLogs->fetch_assoc()): ?>
                                    <tr id="log-row-<?= $log['id']; ?>">
                                        <td><?= htmlspecialchars($log['timestamp'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($log['user_name'] ?? 'Desconocido') ?></td>
                                        <td>
                                            <input type="hidden" name="log_ids[]" value="<?= htmlspecialchars($log['id']) ?>">
                                            <textarea name="log_comments[]" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($log['comments'] ?? '') ?></textarea>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="markLogForDeletion(<?= $log['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <p class="text-muted small">No hay registros de mantenimiento para esta jaula.</p>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button type="button" class="btn btn-outline-secondary btn-modern" onclick="goBack()">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary btn-modern">
                            <i class="fas fa-save me-1"></i> Guardar Cambios
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>

</html>
