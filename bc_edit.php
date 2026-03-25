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
            
            // CAPTURA ARRAYS DINÁMICOS
            $male_n = isset($_POST['male_n']) ? intval($_POST['male_n']) : 1;
            $female_n = isset($_POST['female_n']) ? intval($_POST['female_n']) : 1;

            $male_id_array = $_POST['male_id'] ?? [];
            $female_id_array = $_POST['female_id'] ?? [];
            $male_id = mysqli_real_escape_string($con, implode(', ', $male_id_array));
            $female_id = mysqli_real_escape_string($con, implode(', ', $female_id_array));

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

            // PROCESAMIENTO LITTERS SIN DOM
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
                        $updateLitterQuery = $con->prepare("UPDATE litters SET `litter_dob` = ?, `pups_alive` = ?, `pups_dead` = ?, `pups_male` = ?, `pups_female` = ?, `remarks` = ? WHERE `id` = ?");
                        $updateLitterQuery->bind_param("sssssss", $litter_dob_i, $pups_alive_i, $pups_dead_i, $pups_male_i, $pups_female_i, $remarks_litter_i, $litter_id_i);
                        $updateLitterQuery->execute();
                        $updateLitterQuery->close();
                    } else {
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
    }
} else {
    $_SESSION['message'] = 'ID parameter is missing.';
    header("Location: bc_dash.php?" . getCurrentUrlParams());
    exit();
}

require 'header.php';
?>
<!doctype html>
<html lang="en">

<head>
    <title>Edit Breeding Cage | <?php echo htmlspecialchars($labName); ?></title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js"></script>

    <style>
        .container { max-width: 800px; background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .form-label { font-weight: bold; }
        .required-asterisk { color: red; }
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

            // Leer datos del servidor
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
                        <div class="mb-2 p-3 border rounded bg-white">
                            <h6>Male #${i}</h6>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Male ID <span class="required-asterisk">*</span></label>
                                    <input type="text" class="form-control" name="male_id[]" required value="${savedId}">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Male DOB <span class="required-asterisk">*</span></label>
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
                        <div class="mb-2 p-3 border rounded bg-white">
                            <h6>Female #${i}</h6>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Female ID <span class="required-asterisk">*</span></label>
                                    <input type="text" class="form-control" name="female_id[]" required value="${savedId}">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Female DOB <span class="required-asterisk">*</span></label>
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

            // Select2 init
            $('#user').select2({ placeholder: "Select User(s)", allowClear: true });
            $('#iacuc').select2({ placeholder: "Select IACUC", allowClear: true });
        });

        function removeLitter(element) {
            const litterEntry = element.parentElement;
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

        function goBack() {
            window.history.back();
        }
    </script>
</head>

<body>
    <div class="container content mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4>Edit Breeding Cage (ID: <?= htmlspecialchars($breedingcage['cage_id']); ?>)</h4>
                <button type="button" class="btn btn-secondary btn-sm" onclick="goBack()">Go Back</button>
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="mb-3">
                        <label for="cage_id" class="form-label">Cage ID</label>
                        <input type="text" class="form-control" name="cage_id" id="cage_id" value="<?php echo htmlspecialchars($breedingcage['cage_id']); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="pi_name" class="form-label">PI Name <span class="required-asterisk">*</span></label>
                        <select class="form-control" id="pi_name" name="pi_name" required>
                            <?php
                            while ($row = $result1->fetch_assoc()) {
                                $selected = ($row['id'] == $breedingcage['pi_name']) ? 'selected' : '';
                                echo "<option value='{$row['id']}' $selected>{$row['initials']} [{$row['name']}]</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="cross" class="form-label">Cross <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="cross" id="cross" required value="<?php echo htmlspecialchars($breedingcage['cross']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="iacuc" class="form-label">IACUC</label>
                        <select class="form-control" id="iacuc" name="iacuc[]" multiple>
                            <?php
                            while ($iacucRow = $iacucResult->fetch_assoc()) {
                                $selected = in_array($iacucRow['iacuc_id'], $selectedIacucs) ? 'selected' : '';
                                echo "<option value='{$iacucRow['iacuc_id']}' $selected>{$iacucRow['iacuc_id']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="user" class="form-label">User <span class="required-asterisk">*</span></label>
                        <select class="form-control" id="user" name="user[]" multiple required>
                            <?php
                            while ($userRow = $userResult->fetch_assoc()) {
                                $selected = in_array($userRow['id'], $selectedUsers) ? 'selected' : '';
                                echo "<option value='{$userRow['id']}' $selected>{$userRow['initials']} [{$userRow['name']}]</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="male_n" class="form-label">Number of Males</label>
                        <input type="number" class="form-control" id="male_n" name="male_n" min="1" value="<?php echo $breedingcage['male_n'] ?? 1; ?>">
                    </div>
                    <div id="male_id_container" data-saved-id="<?= htmlspecialchars($breedingcage['male_id'] ?? ''); ?>" data-saved-dob="<?= htmlspecialchars($breedingcage['male_dob'] ?? ''); ?>"></div>

                    <div class="mb-3">
                        <label for="female_n" class="form-label">Number of Females</label>
                        <input type="number" class="form-control" id="female_n" name="female_n" min="1" value="<?php echo $breedingcage['female_n'] ?? 1; ?>">
                    </div>
                    <div id="female_id_container" data-saved-id="<?= htmlspecialchars($breedingcage['female_id'] ?? ''); ?>" data-saved-dob="<?= htmlspecialchars($breedingcage['female_dob'] ?? ''); ?>"></div>

                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" id="remarks"><?php echo htmlspecialchars($breedingcage['remarks']); ?></textarea>
                    </div>

                    <br>
                    <button type="submit" class="btn btn-primary">Update Cage</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>
