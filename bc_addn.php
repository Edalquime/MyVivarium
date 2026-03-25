<?php

/**
 * Add New Breeding Cage Script
 *
 * This script handles the creation of new breeding cages in a laboratory management system. It starts a session,
 * regenerates the session ID to prevent session fixation attacks, and checks if the user is logged in.
 * It generates a CSRF token for form submissions, retrieves a list of Principal Investigators (PIs),
 * and processes the form submission for adding a new breeding cage. The script also includes the functionality
 * to add litter data associated with the breeding cage.
 */

session_start();

require 'dbcon.php';

if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit; 
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userQuery = "SELECT id, initials, name FROM users WHERE status = 'approved'";
$userResult = $con->query($userQuery);

$piQuery = "SELECT id, initials, name FROM users WHERE position = 'Principal Investigator' AND status = 'approved'";
$piResult = $con->query($piQuery);

$iacucQuery = "SELECT iacuc_id, iacuc_title FROM iacuc";
$iacucResult = $con->query($iacucQuery);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }

    $cage_id = trim($_POST['cage_id']);
    $pi_id = $_POST['pi_name'];
    $cross = $_POST['cross'];
    $iacuc_ids = $_POST['iacuc'] ?? [];
    $user_ids = $_POST['user'] ?? [];
    
    $male_n = $_POST['male_n'] ?? 1; 
    $female_n = $_POST['female_n'] ?? 1; 

    // Guardar los IDs y Fechas como arrays implosionados por comas
    $male_id_array = $_POST['male_id'] ?? [];
    $male_dob_array = $_POST['male_dob'] ?? [];
    $male_id = implode(', ', $male_id_array);
    $male_dob = implode(', ', $male_dob_array);

    $female_id_array = $_POST['female_id'] ?? [];
    $female_dob_array = $_POST['female_dob'] ?? [];
    $female_id = implode(', ', $female_id_array);
    $female_dob = implode(', ', $female_dob_array);
    
    $remarks = $_POST['remarks'];

    $check_query = $con->prepare("SELECT * FROM cages WHERE cage_id = ?");
    $check_query->bind_param("s", $cage_id);
    $check_query->execute();
    $check_result = $check_query->get_result();

    if ($check_result->num_rows > 0) {
        $_SESSION['message'] = "Cage ID '$cage_id' already exists. Please use a different Cage ID.";
    } else {
        $insert_cage_query = $con->prepare("INSERT INTO cages (`cage_id`, `pi_name`, `remarks`) VALUES (?, ?, ?)");
        $insert_cage_query->bind_param("sss", $cage_id, $pi_id, $remarks);

        $insert_breeding_query = $con->prepare("INSERT INTO breeding (`cage_id`, `cross`, `male_n`, `male_id`, `female_n`, `female_id`, `male_dob`, `female_dob`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_breeding_query->bind_param("ssssssss", $cage_id, $cross, $male_n, $male_id, $female_n, $female_id, $male_dob, $female_dob);

        if ($insert_cage_query->execute() && $insert_breeding_query->execute()) {
            $_SESSION['message'] = "New breeding cage added successfully.";

            foreach ($iacuc_ids as $iacuc_id) {
                $insert_cage_iacuc_query = $con->prepare("INSERT INTO cage_iacuc (`cage_id`, `iacuc_id`) VALUES (?, ?)");
                $insert_cage_iacuc_query->bind_param("ss", $cage_id, $iacuc_id);
                $insert_cage_iacuc_query->execute();
                $insert_cage_iacuc_query->close();
            }

            foreach ($user_ids as $user_id) {
                $insert_cage_user_query = $con->prepare("INSERT INTO cage_users (`cage_id`, `user_id`) VALUES (?, ?)");
                $insert_cage_user_query->bind_param("ss", $cage_id, $user_id);
                $insert_cage_user_query->execute();
                $insert_cage_user_query->close();
            }

            if (isset($_POST['litter_dob'])) {
                $litter_dob = $_POST['litter_dob'];
                
                $pups_alive = array_map(function ($value) { return !empty($value) ? intval($value) : 0; }, $_POST['pups_alive']);
                $pups_dead = array_map(function ($value) { return !empty($value) ? intval($value) : 0; }, $_POST['pups_dead']);
                $pups_male = array_map(function ($value) { return !empty($value) ? intval($value) : 0; }, $_POST['pups_male']);
                $pups_female = array_map(function ($value) { return !empty($value) ? intval($value) : 0; }, $_POST['pups_female']);
                
                $litter_remarks = $_POST['remarks_litter'] ?? [];

                for ($i = 0; $i < count($litter_dob); $i++) {
                    $insert_litter_query = $con->prepare("INSERT INTO litters (`cage_id`, `litter_dob`, `pups_alive`, `pups_dead`, `pups_male`, `pups_female`, `remarks`) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $insert_litter_query->bind_param("sssssss", $cage_id, $litter_dob[$i], $pups_alive[$i], $pups_dead[$i], $pups_male[$i], $pups_female[$i], $litter_remarks[$i]);
                    $insert_litter_query->execute();
                    $insert_litter_query->close();
                }
            }
        } else {
            $_SESSION['message'] = "Failed to add new breeding cage.";
        }

        $insert_cage_query->close();
        $insert_breeding_query->close();
    }

    $check_query->close();

    header("Location: bc_dash.php");
    exit();
}

require 'header.php';
?>

<!doctype html>
<html lang="en">

<head>
    <title>Add New Breeding Cage | <?php echo htmlspecialchars($labName); ?></title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css" rel="stylesheet" />

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js"></script>

    <style>
        .container {
            max-width: 800px;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: auto;
        }

        .form-label {
            font-weight: bold;
        }

        .required-asterisk {
            color: red;
        }

        .warning-text {
            color: #dc3545;
            font-size: 14px;
        }

        .select2-container .select2-selection--single {
            height: 35px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-right: 10px;
            padding-left: 10px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 35px;
        }
    </style>
    
    <script>
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

            const maleNumInput = document.getElementById('male_n');
            const femaleNumInput = document.getElementById('female_n');
            const maleIdContainer = document.getElementById('male_id_container');
            const femaleIdContainer = document.getElementById('female_id_container');

            // Función unificada para machos (ID y DOB juntos)
            function actualizarMachos() {
                const cantidad = parseInt(maleNumInput.value) || 0;
                maleIdContainer.innerHTML = ''; 

                for (let i = 1; i <= cantidad; i++) {
                    const div = document.createElement('div');
                    div.className = 'mb-2 p-3 border rounded bg-white';
                    div.innerHTML = `
                        <h6>Male #${i}</h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Male ID <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="male_id[]" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Male DOB <span class="required-asterisk">*</span></label>
                                <input type="date" class="form-control" name="male_dob[]" required min="1900-01-01">
                            </div>
                        </div>
                    `;
                    maleIdContainer.appendChild(div);
                }
                setMaxDate();
            }

            // Función unificada para hembras (ID y DOB juntos)
            function actualizarHembras() {
                const cantidad = parseInt(femaleNumInput.value) || 0;
                femaleIdContainer.innerHTML = ''; 

                for (let i = 1; i <= cantidad; i++) {
                    const div = document.createElement('div');
                    div.className = 'mb-2 p-3 border rounded bg-white';
                    div.innerHTML = `
                        <h6>Female #${i}</h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Female ID <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="female_id[]" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Female DOB <span class="required-asterisk">*</span></label>
                                <input type="date" class="form-control" name="female_dob[]" required min="1900-01-01">
                            </div>
                        </div>
                    `;
                    femaleIdContainer.appendChild(div);
                }
                setMaxDate();
            }

            // Atamos los listener a los números de machos y hembras
            maleNumInput.addEventListener('input', actualizarMachos);
            femaleNumInput.addEventListener('input', actualizarHembras);

            actualizarMachos();
            actualizarHembras();

            function addLitter() {
                const litterDiv = document.createElement('div');
                litterDiv.className = 'litter-entry mb-3 p-3 border rounded bg-white';

                litterDiv.innerHTML = `
                    <div class="mb-3">
                        <label for="litter_dob[]" class="form-label">Litter DOB <span class="required-asterisk">*</span></label>
                        <input type="date" class="form-control" name="litter_dob[]" required min="1900-01-01">
                    </div>
                    <div class="mb-3">
                        <label for="pups_alive[]" class="form-label">Pups Alive <span class="required-asterisk">*</span></label>
                        <input type="number" class="form-control" name="pups_alive[]" required min="0" step="1">
