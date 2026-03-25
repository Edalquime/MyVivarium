<?php

/**
 * Add New Breeding Cage Script
 * Modernized UI to match Home and Header
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

$strainQuery = "SELECT str_id, str_name FROM strains ORDER BY str_name ASC";
$strainResult = $con->query($strainQuery);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }

    $cage_id = trim($_POST['cage_id']);
    $pi_id = $_POST['pi_name'];
    $cross = $_POST['cross'];
    $strain = $_POST['strain']; 
    $iacuc_ids = $_POST['iacuc'] ?? [];
    $user_ids = $_POST['user'] ?? [];
    
    $male_n = $_POST['male_n'] ?? 1; 
    $female_n = $_POST['female_n'] ?? 1; 

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

        $insert_breeding_query = $con->prepare("INSERT INTO breeding (`cage_id`, `cross`, `strain`, `male_n`, `male_id`, `female_n`, `female_id`, `male_dob`, `female_dob`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_breeding_query->bind_param("sssisssss", $cage_id, $cross, $strain, $male_n, $male_id, $female_n, $female_id, $male_dob, $female_dob);

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
<html lang="es">

<head>
    <title>Add New Breeding Cage | <?php echo htmlspecialchars($labName); ?></title>

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

        /* Alineación limpia para Select2 */
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

            function actualizarMachos() {
                const cantidad = parseInt(maleNumInput.value) || 0;
                maleIdContainer.innerHTML = ''; 

                for (let i = 1; i <= cantidad; i++) {
                    const div = document.createElement('div');
                    div.className = 'mb-3 p-3 border rounded-3 bg-white shadow-sm';
                    div.innerHTML = `
                        <h6 class="text-primary fw-bold"><i class="fas fa-mars me-2"></i>Male #${i}</h6>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Male ID <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="male_id[]" required placeholder="Ex: M123">
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

            function actualizarHembras() {
                const cantidad = parseInt(femaleNumInput.value) || 0;
                femaleIdContainer.innerHTML = ''; 

                for (let i = 1; i <= cantidad; i++) {
                    const div = document.createElement('div');
                    div.className = 'mb-3 p-3 border rounded-3 bg-white shadow-sm';
                    div.innerHTML = `
                        <h6 class="text-danger fw-bold"><i class="fas fa-venus me-2"></i>Female #${i}</h6>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Female ID <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="female_id[]" required placeholder="Ex: F123">
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

            maleNumInput.addEventListener('input', actualizarMachos);
            femaleNumInput.addEventListener('input', actualizarHembras);

            actualizarMachos();
            actualizarHembras();

            function addLitter() {
                const litterDiv = document.createElement('div');
                litterDiv.className = 'litter-entry mb-3 p-3 border rounded-3 bg-white shadow-sm';

                litterDiv.innerHTML = `
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Litter DOB <span class="required-asterisk">*</span></label>
                            <input type="date" class="form-control" name="litter_dob[]" required min="1900-01-01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pups Alive <span class="required-asterisk">*</span></label>
                            <input type="number" class="form-control" name="pups_alive[]" required min="0" step="1" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pups Dead <span class="required-asterisk">*</span></label>
                            <input type="number" class="form-control" name="pups_dead[]" required min="0" step="1" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pups Male</label>
                            <input type="number" class="form-control" name="pups_male[]" min="0" step="1" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pups Female</label>
                            <input type="number" class="form-control" name="pups_female[]" min="0" step="1" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks_litter[]" rows="1"></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeLitter(this)">
                                <i class="fas fa-trash me-1"></i> Remove Litter
                            </button>
                        </div>
                    </div>
                `;

                document.getElementById('litterEntries').appendChild(litterDiv);
                setMaxDate();
            }

            function removeLitter(element) {
                // Sube hasta encontrar el contenedor .litter-entry
                element.closest('.litter-entry').remove();
            }

            window.addLitter = addLitter;
            window.removeLitter = removeLitter;

            $('#user').select2({
                placeholder: "Select User(s)",
                allowClear: true,
                width: '100%'
            });

            $('#iacuc').select2({
                placeholder: "Select IACUC",
                allowClear: true,
                width: '100%'
            });

            $('#strain').select2({
                placeholder: "Select Strain",
                allowClear: true,
                width: '100%'
            });
        });

        function goBack() {
            window.history.back();
        }
    </script>
</head>

<body>

    <div class="container mt-4 mb-5">

        <div class="card main-card">
            <div class="card-header bg-dark text-white p-3 d-flex align-items-center justify-content-between" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h4 class="mb-0 fs-5"><i class="fas fa-plus-circle me-2"></i> Add New Breeding Cage</h4>
                <span class="badge bg-light text-dark">Fields marked with <span class="text-danger">*</span> are required</span>
            </div>

            <div class="card-body bg-light p-4">

                <?php include('message.php'); ?>

                <form method="POST">

                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-cube"></i> General Cage Information
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="cage_id" class="form-label">Cage ID <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" id="cage_id" name="cage_id" required placeholder="Ex: BC-01">
                            </div>
                            <div class="col-md-6">
                                <label for="pi_name" class="form-label">PI Name <span class="required-asterisk">*</span></label>
                                <select class="form-control" id="pi_name" name="pi_name" required>
                                    <option value="" disabled selected>Select PI</option>
                                    <?php
                                    while ($row = $piResult->fetch_assoc()) {
                                        echo "<option value='{$row['id']}'>{$row['initials']} [{$row['name']}]</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="strain" class="form-label">Strain <span class="required-asterisk">*</span></label>
                                <select class="form-control" id="strain" name="strain" required>
                                    <option value="" disabled selected>Select Strain</option>
                                    <?php
                                    while ($row = $strainResult->fetch_assoc()) {
                                        echo "<option value='{$row['str_id']}'>{$row['str_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="cross" class="form-label">Cross <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" id="cross" name="cross" required placeholder="Ex: WT x KO">
                            </div>
                            <div class="col-md-6">
                                <label for="iacuc" class="form-label">IACUC Protocol</label>
                                <select class="form-control" id="iacuc" name="iacuc[]" multiple>
                                    <?php
                                    while ($iacucRow = $iacucResult->fetch_assoc()) {
                                        $truncated_title = strlen($iacucRow['iacuc_title']) > 40 ? substr($iacucRow['iacuc_title'], 0, 40) . '...' : $iacucRow['iacuc_title'];
                                        echo "<option value='{$iacucRow['iacuc_id']}' title='{$iacucRow['iacuc_title']}'>{$iacucRow['iacuc_id']} | {$truncated_title}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="user" class="form-label">Assigned Users <span class="required-asterisk">*</span></label>
                                <select class="form-control" id="user" name="user[]" multiple required>
                                    <?php
                                    while ($userRow = $userResult->fetch_assoc()) {
                                        echo "<option value='{$userRow['id']}'>{$userRow['initials']} [{$userRow['name']}]</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-mars"></i> Breeding Males
                        </div>
                        <div class="mb-3">
                            <label for="male_n" class="form-label">Number of Males <span class="required-asterisk">*</span></label>
                            <input type="number" class="form-control" id="male_n" name="male_n" required min="1" step="1" value="1" style="max-width: 200px;">
                        </div>
                        <div id="male_id_container"></div>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-venus"></i> Breeding Females
                        </div>
                        <div class="mb-3">
                            <label for="female_n" class="form-label">Number of Females <span class="required-asterisk">*</span></label>
                            <input type="number" class="form-control" id="female_n" name="female_n" required min="1" step="1" value="1" style="max-width: 200px;">
                        </div>
                        <div id="female_id_container"></div>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-comment-dots"></i> General Cage Remarks
                        </div>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Add any extra notes here..."></textarea>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-baby"></i> Initial Litter Data (Optional)
                        </div>
                        <div id="litterEntries"></div>
                        <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="addLitter()">
                            <i class="fas fa-plus me-1"></i> Add Litter Entry
                        </button>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button type="button" class="btn btn-outline-secondary btn-modern" onclick="goBack()">
                            <i class="fas fa-arrow-left me-1"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary btn-modern">
                            <i class="fas fa-save me-1"></i> Save Cage
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>

</html>
