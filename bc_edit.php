<?php

/**
 * Edit Breeding Cage Script
 */

// Start a new session or resume the existing session
session_start();

// Include the database connection
require 'dbcon.php';

// Disable error display in production (errors logged to server logs)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit; 
}

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getCurrentUrlParams() {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $search = isset($_GET['search']) ? urlencode($_GET['search']) : '';
    return "page=$page&search=$search";
}

// Query to retrieve users with initials and names
$userQuery = "SELECT id, initials, name FROM users WHERE status = 'approved'";
$userResult = $con->query($userQuery);

// Query to retrieve options where role is 'Principal Investigator'
$query1 = "SELECT id, initials, name FROM users WHERE position = 'Principal Investigator' AND status = 'approved'";
$result1 = $con->query($query1);

// Check if the ID parameter is set in the URL
if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($con, $_GET['id']);

    // Fetch the breeding cage record with the specified ID including PI name details
    $query = "SELECT b.*, c.remarks AS remarks, c.pi_name AS pi_name
          FROM breeding b
          LEFT JOIN cages c ON b.cage_id = c.cage_id
          WHERE b.cage_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch files associated with the specified cage ID
    $query2 = "SELECT * FROM files WHERE cage_id = ?";
    $stmt2 = $con->prepare($query2);
    $stmt2->bind_param("s", $id);
    $stmt2->execute();
    $files = $stmt2->get_result();

    // Fetch the breeding cage litter record with the specified ID
    $query3 = "SELECT * FROM litters WHERE `cage_id` = ?";
    $stmt3 = $con->prepare($query3);
    $stmt3->bind_param("s", $id);
    $stmt3->execute();
    $litters = $stmt3->get_result();

    // Query to retrieve IACUC values
    $iacucQuery = "SELECT iacuc_id, iacuc_title FROM iacuc";
    $iacucResult = $con->query($iacucQuery);

    if (mysqli_num_rows($result) === 1) {
        $breedingcage = mysqli_fetch_assoc($result);

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
            $_SESSION['message'] = 'Access denied. Only the admin or the assigned user can edit.';
            header("Location: bc_dash.php?" . getCurrentUrlParams());
            exit();
        }

        $selectedPiId = $breedingcage['pi_name'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                die('CSRF token validation failed');
            }

            $cage_id = trim(mysqli_real_escape_string($con, $_POST['cage_id']));
            $pi_name = mysqli_real_escape_string($con, $_POST['pi_name']);
            $cross = mysqli_real_escape_string($con, $_POST['cross']);
            $iacuc = isset($_POST['iacuc']) ? $_POST['iacuc'] : []; 
            $users = isset($_POST['user']) ? $_POST['user'] : []; 
            $male_id = mysqli_real_escape_string($con, $_POST['male_id']);
            $female_id = mysqli_real_escape_string($con, $_POST['female_id']);
            
            // MODIFICACIÓN: Captura de cantidad y fechas unidas por comas
            $male_n = isset($_POST['male_n']) ? intval($_POST['male_n']) : 1;
            $female_n = isset($_POST['female_n']) ? intval($_POST['female_n']) : 1;

            $male_dob_array = $_POST['male_dob'] ?? [];
            $female_dob_array = $_POST['female_dob'] ?? [];
            $male_dob = mysqli_real_escape_string($con, implode(', ', $male_dob_array));
            $female_dob = mysqli_real_escape_string($con, implode(', ', $female_dob_array));

            $remarks = mysqli_real_escape_string($con, $_POST['remarks']);

            $con->begin_transaction();

            try {
                $updateCageQuery = $con->prepare("UPDATE cages SET pi_name = ?, remarks = ? WHERE cage_id = ?");
                $updateCageQuery->bind_param("sss", $pi_name, $remarks, $cage_id);
                $updateCageQuery->execute();
                $updateCageQuery->close();

                // MODIFICACIÓN: Update de breeding incluyendo male_n y female_n
                $updateBreedingQuery = $con->prepare("UPDATE breeding SET `cross` = ?, male_n = ?, male_id = ?, female_n = ?, female_id = ?, male_dob = ?, female_dob = ? WHERE cage_id = ?");
                $updateBreedingQuery->bind_param("sissssss", $cross, $male_n, $male_id, $female_n, $female_id, $male_dob, $female_dob, $cage_id);
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

                $con->commit();
                $_SESSION['message'] = 'Entry updated successfully.';
            } catch (Exception $e) {
                $con->rollback();
                $_SESSION['message'] = 'Update failed: ' . $e->getMessage();
            }

            if (isset($_FILES['fileUpload']) && $_FILES['fileUpload']['error'] == UPLOAD_ERR_OK) {
                $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
                $maxFileSize = 10 * 1024 * 1024;

                if ($_FILES['fileUpload']['size'] > $maxFileSize) {
                    $_SESSION['message'] .= " File size exceeds 10MB limit.";
                } else {
                    $fileExtension = strtolower(pathinfo($_FILES['fileUpload']['name'], PATHINFO_EXTENSION));

                    if (!in_array($fileExtension, $allowedExtensions)) {
                        $_SESSION['message'] .= " Invalid file type.";
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
                                $_SESSION['message'] .= " File uploaded successfully.";
                            }
                        }
                    }
                }
            }

            // MODIFICACIÓN: Procesamiento de camadas (sin el DOM)
            $litter_dob = isset($_POST['litter_dob']) ? $_POST['litter_dob'] : [];
            $pups_alive = isset($_POST['pups_alive']) ? $_POST['pups_alive'] : [];
            $pups_dead = isset($_POST['pups_dead']) ? $_POST['pups_dead'] : [];
            $pups_male = isset($_POST['pups_male']) ? $_POST['pups_male'] : [];
            $pups_female = isset($_POST['pups_female']) ? $_POST['pups_female'] : [];
            $remarks_litter = isset($_POST['remarks_litter']) ? $_POST['remarks_litter'] : [];
            $litter_id = isset($_POST['litter_id']) ? $_POST['litter_id'] : [];
            $delete_litter_ids = isset($_POST['delete_litter_ids']) ? $_POST['delete_litter_ids'] : [];

            if (isset($_POST['litter_dob'])) {
                for ($i = 0; $i < count($_POST['litter_dob']); $i++) {
                    $litter_dob_i = mysqli_real_escape_string($con, $_POST['litter_dob'][$i]);
                    $pups_alive_i = !empty($_POST['pups_alive'][$i]) ? intval($_POST['pups_alive'][$i]) : 0;
                    $pups_dead_i = !empty($_POST['pups_dead'][$i]) ? intval($_POST['pups_dead'][$i]) : 0;
                    $pups_male_i = !empty($_POST['pups_male'][$i]) ? intval($_POST['pups_male'][$i]) : 0;
                    $pups_female_i = !empty($_POST['pups_female'][$i]) ? intval($_POST['pups_female'][$i]) : 0;
                    $remarks_litter_i = mysqli_real_escape_string($con, $_POST['remarks_litter'][$i]);
                    $litter_id_i = isset($_POST['litter_id'][$i]) ? mysqli_real_escape_string($con, $_POST['litter_id'][$i]) : '';

                    if (!empty($litter_id_i)) {
                        // SQL Update sin 'dom'
                        $updateLitterQuery = $con->prepare("UPDATE litters SET `litter_dob` = ?, `pups_alive` = ?, `pups_dead` = ?, `pups_male` = ?, `pups_female` = ?, `remarks` = ? WHERE `id` = ?");
                        $updateLitterQuery->bind_param("sssssss", $litter_dob_i, $pups_alive_i, $pups_dead_i, $pups_male_i, $pups_female_i, $remarks_litter_i, $litter_id_i);
                        $updateLitterQuery->execute();
                        $updateLitterQuery->close();
                    } else {
                        // SQL Insert sin 'dom'
                        $insertLitterQuery = $con->prepare("INSERT INTO litters (`cage_id`, `litter_dob`, `pups_alive`, `pups_dead`, `pups_male`, `pups_female`, `remarks`) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $insertLitterQuery->bind_param("sssssss", $cage_id, $litter_dob_i, $pups_alive_i, $pups_dead_i, $pups_male_i, $pups_female_i, $remarks_litter_i);
                        $insertLitterQuery->execute();
                        $insertLitterQuery->close();
                    }
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

            header("Location: bc_dash.php?" . getCurrentUrlParams());
            exit();
        }
    } else {
        $_SESSION['message'] = 'Invalid ID.';
        header("Location: bc_dash.php?" . getCurrentUrlParams());
        exit();
    }
} else {
    $_SESSION['message'] = 'ID parameter is missing.';
    header("Location: bc_dash.php?" . getCurrentUrlParams());
    exit();
}

function getUserDetailsByIds($con, $userIds) {
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

$piDetails = getUserDetailsByIds($con, [$selectedPiId]);
$piDisplay = isset($piDetails[$selectedPiId]) ? $piDetails[$selectedPiId] : 'Unknown PI';

require 'header.php';
?>

<!doctype html>
<html lang="en">

<head>
    <script>
        function goBack() {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 1;
            const search = urlParams.get('search') || '';
            window.location.href = 'bc_dash.php?page=' + page + '&search=' + encodeURIComponent(search);
        }

        document.addEventListener('DOMContentLoaded', function() {
            function getCurrentDate() {
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                return `${yyyy}-${mm}-${dd}`;
            }

            function setMaxDate() {
                const currentDate = getCurrentDate();
                const dateFields = document.querySelectorAll('input[type="date"]');
                dateFields.forEach(field => {
                    field.setAttribute('max', currentDate);
                });
            }

            setMaxDate();

            // MODIFICACIÓN: Fechas dinámicas de Macho/Hembra que respeta los valores cargados
            const maleNumInput = document.getElementById('male_n');
            const femaleNumInput = document.getElementById('female_n');
            const maleDatesContainer = document.getElementById('male_dates_container');
            const femaleDatesContainer = document.getElementById('female_dates_container');

            const maleDobSaved = maleDatesContainer.dataset.saved ? maleDatesContainer.dataset.saved.split(', ') : [];
            const femaleDobSaved = femaleDatesContainer.dataset.saved ? femaleDatesContainer.dataset.saved.split(', ') : [];

            function actualizarFechasMacho(isFirstLoad = false) {
                const cantidad = parseInt(maleNumInput.value) || 0;
                maleDatesContainer.innerHTML = ''; 

                for (let i = 1; i <= cantidad; i++) {
                    const savedDate = (isFirstLoad && maleDobSaved[i-1]) ? maleDobSaved[i-1] : '';
                    const div = document.createElement('div');
                    div.className = 'mb-2 p-2 border rounded bg-white';
                    div.innerHTML = `
                        <label class="form-label">Male #${i} DOB <span class="required-asterisk">*</span></label>
                        <input type="date" class="form-control" name="male_dob[]" required min="1900-01-01" value="${savedDate}">
                    `;
                    maleDatesContainer.appendChild(div);
                }
                setMaxDate();
            }

            function actualizarFechasHembra(isFirstLoad = false) {
                const cantidad = parseInt(femaleNumInput.value) || 0;
                femaleDatesContainer.innerHTML = ''; 

                for (let i = 1; i <= cantidad; i++) {
                    const savedDate = (isFirstLoad && femaleDobSaved[i-1]) ? femaleDobSaved[i-1] : '';
                    const div = document.createElement('div');
                    div.className = 'mb-2 p-2 border rounded bg-white';
                    div.innerHTML = `
                        <label class="form-label">Female #${i} DOB <span class="required-asterisk">*</span></label>
                        <input type="date" class="form-control" name="female_dob[]" required min="1900-01-01" value="${savedDate}">
                    `;
                    femaleDatesContainer.appendChild(div);
                }
                setMaxDate();
            }

            maleNumInput.addEventListener('input', () => actualizarFechasMacho(false));
            femaleNumInput.addEventListener('input', () => actualizarFechasHembra(false));

            actualizarFechasMacho(true);
            actualizarFechasHembra(true);


            // MODIFICACIÓN: addLitter sin DOM
            function addLitter() {
                const litterDiv = document.createElement('div');
                litterDiv.className = 'litter-entry';

                litterDiv.innerHTML = `
                    <hr>
                    <div class="mb-3">
                        <label for="litter_dob[]" class="form-label">Litter DOB </label>
                        <input type="date" class="form-control" name="litter_dob[]" min="1900-01-01">
                    </div>
                    <div class="mb-3">
                        <label for="pups_alive[]" class="form-label">Pups Alive <span class="required-asterisk">*</span></label>
                        <input type="number" class="form-control" name="pups_alive[]" required min="0" step="1">
                    </div>
                    <div class="mb-3">
                        <label for="pups_dead[]" class="form-label">Pups Dead <span class="required-asterisk">*</span></label>
                        <input type="number" class="form-control" name="pups_dead[]" required min="0" step="1">
                    </div>
                    <div class="mb-3">
                        <label for="pups_male[]" class="form-label">Pups Male</label>
                        <input type="number" class="form-control" name="pups_male[]" min="0" step="1">
                    </div>
                    <div class="mb-3">
                        <label for="pups_female[]" class="form-label">Pups Female</label>
                        <input type="number" class="form-control" name="pups_female[]" min="0" step="1">
                    </div>
                    <div class="mb-3">
                        <label for="remarks_litter[]" class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks_litter[]" oninput="adjustTextareaHeight(this)"></textarea>
                    </div>
                    <input type="hidden" name="litter_id[]" value="">
                    <button type="button" class="btn btn-danger" onclick="removeLitter(this)">Remove</button>
                `;

                document.getElementById('litterEntries').appendChild(litterDiv);
                setMaxDate();
            }

            function adjustTextareaHeight(element) {
                element.style.height = "auto";
                element.style.height = (element.scrollHeight) + "px";
            }

            window.addLitter = addLitter;
        });

        document.addEventListener('DOMContentLoaded', function() {
            function validateDate(dateString) {
                const regex = /^\d{4}-\d{2}-\d{2}$/;
                if (!dateString.match(regex)) return false;

                const date = new Date(dateString);
                const now = new Date();
                const year = date.getFullYear();

                return date && !isNaN(date) && year >= 1900 && date <= now;
            }

            function attachDateValidation() {
                const dateFields = document.querySelectorAll('input[type="date"]');
                dateFields.forEach(field => {
                    if (!field.dataset.validated) { 
                        const warningText = document.createElement('span');
                        warningText.style.color = 'red';
                        warningText.style.display = 'none';
                        field.parentNode.appendChild(warningText);

                        field.addEventListener('input', function() {
                            const dateValue = field.value;
                            const isValidDate = validateDate(dateValue);
                            if (!isValidDate) {
                                warningText.textContent = 'Invalid Date. Please enter a valid date.';
                                warningText.style.display = 'block';
                            } else {
                                warningText.textContent = '';
                                warningText.style.display = 'none';
                            }
                        });

                        field.dataset.validated = 'true';
                    }
                });
            }

            attachDateValidation();

            const form = document.querySelector('form');
            const observer = new MutationObserver(() => {
                attachDateValidation(); 
            });

            observer.observe(form, {
                childList: true,
                subtree: true
            });

            form.addEventListener('submit', function(event) {
                let isValid = true;
                const dateFields = document.querySelectorAll('input[type="date"]');
                dateFields.forEach(field => {
                    const dateValue = field.value;
                    const warningText = field.nextElementSibling;
                    if (!validateDate(dateValue)) {
                        warningText.textContent = 'Invalid Date. Please enter a valid date.';
                        warningText.style.display = 'block';
                        isValid = false;
                    }
                });
                if (!isValid) {
                    event.preventDefault(); 
                }
            });
        });

        function removeLitter(element) {
            const litterEntry = element.parentElement;
            const litterIdInput = litterEntry.querySelector('[name="litter_id[]"]');

            if (litterIdInput && litterIdInput.value) {
                const deleteLitterIdsInput = document.querySelector('[name="delete_litter_ids[]"]');
                const newInput = document.createElement('input');
                newInput.type = 'hidden';
                newInput.name = 'delete_litter_ids[]';
                newInput.value = litterIdInput.value;
                document.querySelector('form').appendChild(newInput);
            }

            litterEntry.remove();
        }

        $(document).ready(function() {
            $('#user').select2({
                placeholder: "Select User(s)",
                allowClear: true
            });

            $('#iacuc').select2({
                placeholder: "Select IACUC",
                allowClear: true,
                templateResult: function(data) {
                    if (!data.id) {
                        return data.text;
                    }
                    var $result = $('<span>' + data.text + '</span>');
                    $result.attr('title', data.element.title);
                    return $result;
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(event) {
                const piSelect = document.getElementById('pi_name');
                const selectedPiText = piSelect.options[piSelect.selectedIndex].text;

                if (selectedPiText.includes('Unknown PI')) {
                    event.preventDefault(); 
                    alert('Cannot proceed with "Unknown PI". Please select a valid PI.');
                }
            });
        });

        function markLogForDeletion(logId) {
            if (confirm('Are you sure you want to delete this maintenance record?')) {
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
    </script>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js"></script>

    <style>
        .container {
            max-width: 800px;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .form-label { font-weight: bold; }
        .btn-primary { margin-right: 10px; }
        .table-wrapper { margin-bottom: 50px; overflow-x: auto; }
        .table-wrapper table { width: 100%; border-collapse: collapse; }
        .table-wrapper th, .table-wrapper td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .required-asterisk { color: red; }
    </style>
</head>

<body>
    <div class="container content mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>Edit Breeding Cage</h4>
                        <div class="action-buttons">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="goBack()">Go Back</button>
                        </div>
                    </div>
                    <div class="card-body">

                        <form action="" method="POST" enctype="multipart/form-data">
<div class="card mb-4 border-start border-primary border-4 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-mouse me-2"></i>Información de Reproductores</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-bold">Cage ID</label>
                <input type="text" name="cage_id" value="<?= htmlspecialchars($row['cage_id']); ?>" class="form-control bg-light" readonly>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Protocol</label>
                <input type="text" name="protocol" value="<?= htmlspecialchars($row['protocol'] ?? ''); ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Strain (Cepa)</label>
                <select name="strain" class="form-select" required>
                    <?php while($s = $strainResult->fetch_assoc()): ?>
                        <option value="<?= $s['str_id']; ?>" <?= ($row['strain'] == $s['str_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($s['str_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Mating Date (Fecha Cruce)</label>
                <input type="date" name="mating_date" value="<?= $row['mating_date'] ?? ''; ?>" class="form-control">
            </div>
        </div>
    </div>
</div>

<div class="card mb-4 border-start border-success border-4 shadow-sm">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-baby-carriage me-2"></i>Nacimientos / Camadas</h5>
        <button type="button" class="btn btn-light btn-sm fw-bold" onclick="addLitter()">
            <i class="fas fa-plus me-1"></i> Agregar Camada
        </button>
    </div>
    <div class="card-body bg-light">
        <div id="litters-container">
            <?php 
            $litterResult->data_seek(0);
            while ($litter = $litterResult->fetch_assoc()): 
            ?>
                <div class="litter-entry bg-white p-3 mb-3 border rounded shadow-sm">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-success">F. Nacimiento (DOB)</label>
                            <input type="date" name="litter_dob[]" value="<?= $litter['litter_dob']; ?>" class="form-control border-success">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-center d-block">Vivos</label>
                            <input type="number" name="pups_alive[]" value="<?= $litter['pups_alive']; ?>" class="form-control text-center">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-center d-block">Muertos</label>
                            <input type="number" name="pups_dead[]" value="<?= $litter['pups_dead']; ?>" class="form-control text-center">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-center d-block">Macho</label>
                            <input type="number" name="pups_male[]" value="<?= $litter['pups_male']; ?>" class="form-control text-center">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-center d-block">Hembra</label>
                            <input type="number" name="pups_female[]" value="<?= $litter['pups_female']; ?>" class="form-control text-center">
                        </div>
                        <div class="col-md-1 d-flex align-items-end justify-content-center">
                            <input type="hidden" name="litter_id[]" value="<?= $litter['id']; ?>">
                            <button type="button" class="btn btn-outline-danger" onclick="removeLitter(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="col-12 mt-2">
                            <textarea name="remarks_litter[]" class="form-control form-control-sm" placeholder="Comentarios sobre esta camada..."><?= htmlspecialchars($litter['remarks']); ?></textarea>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
