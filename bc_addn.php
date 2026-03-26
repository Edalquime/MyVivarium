<?php

/**
 * Add New Breeding Cage Script
 * Interfaz moderna en Español con Fecha de Emparejamiento obligatoria.
 * Genera tareas automáticas de destete al registrar nacimientos.
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


// --- NUEVO CÓDIGO PARA CEPAS (IGUAL QUE HC_EDIT / BC_EDIT) ---
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
// -------------------------------------------------------------


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Validación de token CSRF fallida');
    }

    $cage_id = trim($_POST['cage_id']);
    $pi_id = $_POST['pi_name'];
    $pairing_date = $_POST['pairing_date']; 
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
        $_SESSION['message'] = "El ID de Jaula '$cage_id' ya existe. Por favor utiliza un ID diferente.";
    } else {
        $insert_cage_query = $con->prepare("INSERT INTO cages (`cage_id`, `pi_name`, `remarks`) VALUES (?, ?, ?)");
        $insert_cage_query->bind_param("sss", $cage_id, $pi_id, $remarks);

        $insert_breeding_query = $con->prepare("INSERT INTO breeding (`cage_id`, `cross`, `strain`, `male_n`, `male_id`, `female_n`, `female_id`, `male_dob`, `female_dob`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_breeding_query->bind_param("sssisssss", $cage_id, $pairing_date, $strain, $male_n, $male_id, $female_n, $female_id, $male_dob, $female_dob);

        if ($insert_cage_query->execute() && $insert_breeding_query->execute()) {
            $_SESSION['message'] = "Nueva jaula de cruce agregada exitosamente.";

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

            // ---------------------------------------------------------------------------------
            // COORDINACIÓN DE TAREAS AUTOMÁTICAS AL REGISTRAR NUEVOS NACIMIENTOS
            // ---------------------------------------------------------------------------------
            if (isset($_POST['litter_dob'])) {
                $litter_dob = $_POST['litter_dob'];
                
                $pups_alive = array_map(function ($value) { return !empty($value) ? intval($value) : 0; }, $_POST['pups_alive']);
                $pups_dead = array_map(function ($value) { return !empty($value) ? intval($value) : 0; }, $_POST['pups_dead']);
                $pups_male = array_map(function ($value) { return !empty($value) ? intval($value) : 0; }, $_POST['pups_male']);
                $pups_female = array_map(function ($value) { return !empty($value) ? intval($value) : 0; }, $_POST['pups_female']);
                
                $litter_remarks = $_POST['remarks_litter'] ?? [];

                for ($i = 0; $i < count($litter_dob); $i++) {
                    if (!empty($litter_dob[$i])) {
                        // 1. Guardar la Camada
                        $insert_litter_query = $con->prepare("INSERT INTO litters (`cage_id`, `litter_dob`, `pups_alive`, `pups_dead`, `pups_male`, `pups_female`, `remarks`) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $insert_litter_query->bind_param("sssssss", $cage_id, $litter_dob[$i], $pups_alive[$i], $pups_dead[$i], $pups_male[$i], $pups_female[$i], $litter_remarks[$i]);
                        $insert_litter_query->execute();
                        $insert_litter_query->close();

                        // 2. Calcular Fechas de Destete
                        $date21 = date('Y-m-d', strtotime($litter_dob[$i] . ' +21 days'));
                        $date30 = date('Y-m-d', strtotime($litter_dob[$i] . ' +30 days'));

                        // Preparar tarea
                        $taskQuery = $con->prepare("INSERT INTO tasks (title, description, assigned_by, assigned_to, status, completion_date, cage_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $taskBy = $_SESSION['user_id'];
                        $taskStatus = 'Pending';

                        // 3. Crear tarea para cada usuario asignado a la jaula
                        foreach ($user_ids as $userId) {
                            // Tarea Destete 21 días
                            $title21 = "🍼 Destete de 21 días - Jaula Cruce: $cage_id";
                            $desc21 = "Registro de nacimiento el {$litter_dob[$i]}. Por favor evaluar y proceder con el destete.";
                            $taskQuery->bind_param("sssisss", $title21, $desc21, $taskBy, $userId, $taskStatus, $date21, $cage_id);
                            $taskQuery->execute();

                            // Tarea Destete 30 días
                            $title30 = "🚨 Destete LÍMITE de 30 días - Jaula Cruce: $cage_id";
                            $desc30 = "Registro de nacimiento el {$litter_dob[$i]}. Las crías deben destetarse inmediatamente.";
                            $taskQuery->bind_param("sssisss", $title30, $desc30, $taskBy, $userId, $taskStatus, $date30, $cage_id);
                            $taskQuery->execute();
                        }
                        $taskQuery->close();
                    }
                }
            }
            // ---------------------------------------------------------------------------------
        } else {
            $_SESSION['message'] = "Error al agregar la nueva jaula de cruce.";
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
    <title>Agregar Jaula de Cruce | <?php echo htmlspecialchars($labName); ?></title>

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
                        <h6 class="text-primary fw-bold"><i class="fas fa-mars me-2"></i>Macho #${i}</h6>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">ID del Macho <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="male_id[]" required placeholder="Ej: M123">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Fecha Nac. del Macho <span class="required-asterisk">*</span></label>
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
                        <h6 class="text-danger fw-bold"><i class="fas fa-venus me-2"></i>Hembra #${i}</h6>
                        <hr class="my-2">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">ID de la Hembra <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" name="female_id[]" required placeholder="Ej: F123">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Fecha Nac. de la Hembra <span class="required-asterisk">*</span></label>
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
                            <label class="form-label">Fecha Nac. Camada <span class="required-asterisk">*</span></label>
                            <input type="date" class="form-control" name="litter_dob[]" required min="1900-01-01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Crías Vivas <span class="required-asterisk">*</span></label>
                            <input type="number" class="form-control" name="pups_alive[]" required min="0" step="1" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Crías Muertas <span class="required-asterisk">*</span></label>
                            <input type="number" class="form-control" name="pups_dead[]" required min="0" step="1" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Crías Machos</label>
                            <input type="number" class="form-control" name="pups_male[]" min="0" step="1" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Crías Hembras</label>
                            <input type="number" class="form-control" name="pups_female[]" min="0" step="1" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Observaciones Camada</label>
                            <textarea class="form-control" name="remarks_litter[]" rows="1" placeholder="Ej: Buen peso"></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeLitter(this)">
                                <i class="fas fa-trash me-1"></i> Eliminar Camada
                            </button>
                        </div>
                    </div>
                `;

                document.getElementById('litterEntries').appendChild(litterDiv);
                setMaxDate();
            }

            function removeLitter(element) {
                element.closest('.litter-entry').remove();
            }

            window.addLitter = addLitter;
            window.removeLitter = removeLitter;

            $('#user').select2({
                placeholder: "Seleccionar Usuario(s)",
                allowClear: true,
                width: '100%'
            });

            $('#iacuc').select2({
                placeholder: "Seleccionar Protocolo IACUC",
                allowClear: true,
                width: '100%'
            });

            $('#strain').select2({
                placeholder: "Seleccionar Cepa",
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
                <h4 class="mb-0 fs-5"><i class="fas fa-plus-circle me-2"></i> Agregar Nueva Jaula de Cruce</h4>
                <span class="badge bg-light text-dark">Los campos con <span class="text-danger">*</span> son obligatorios</span>
            </div>

            <div class="card-body bg-light p-4">

                <?php include('message.php'); ?>

                <form method="POST">

                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-cube"></i> Información General de la Jaula
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="cage_id" class="form-label">ID de la Jaula <span class="required-asterisk">*</span></label>
                                <input type="text" class="form-control" id="cage_id" name="cage_id" required placeholder="Ej: JC-01">
                            </div>
                            <div class="col-md-6">
                                <label for="pi_name" class="form-label">Nombre del Investigador Principal (PI) <span class="required-asterisk">*</span></label>
                                <select class="form-control" id="pi_name" name="pi_name" required>
                                    <option value="" disabled selected>Seleccionar PI</option>
                                    <?php
                                    while ($row = $piResult->fetch_assoc()) {
                                        echo "<option value='{$row['id']}'>{$row['initials']} [{$row['name']}]</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="strain" class="form-label">Cepa <span class="required-asterisk">*</span></label>
                                <select class="form-control" id="strain" name="strain" required>
                                    <option value="" disabled selected>Seleccionar Cepa</option>
                                    <?php
                                    foreach ($strainOptions as $option) {
                                        $value = explode(" | ", $option)[0]; // El id para la base de datos
                                        echo "<option value='$value'>$option</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="pairing_date" class="form-label">Fecha de Cruce/Armado <span class="required-asterisk">*</span></label>
                                <input type="date" class="form-control" id="pairing_date" name="pairing_date" required min="1900-01-01">
                            </div>

                            <div class="col-md-6">
                                <label for="iacuc" class="form-label">Protocolo IACUC</label>
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
                                <label for="user" class="form-label">Usuarios Asignados <span class="required-asterisk">*</span></label>
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
                            <i class="fas fa-mars"></i> Machos Reproductores
                        </div>
                        <div class="mb-3">
                            <label for="male_n" class="form-label">Cantidad de Machos <span class="required-asterisk">*</span></label>
                            <input type="number" class="form-control" id="male_n" name="male_n" required min="1" step="1" value="1" style="max-width: 200px;">
                        </div>
                        <div id="male_id_container"></div>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-venus"></i> Hembras Reproductoras
                        </div>
                        <div class="mb-3">
                            <label for="female_n" class="form-label">Cantidad de Hembras <span class="required-asterisk">*</span></label>
                            <input type="number" class="form-control" id="female_n" name="female_n" required min="1" step="1" value="1" style="max-width: 200px;">
                        </div>
                        <div id="female_id_container"></div>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-comment-dots"></i> Observaciones de la Jaula
                        </div>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Añade notas adicionales sobre la jaula aquí..."></textarea>
                    </div>

                    <div class="section-card">
                        <div class="section-title">
                            <i class="fas fa-baby"></i> Datos Iniciales de la Camada (Opcional)
                        </div>
                        <div id="litterEntries"></div>
                        <button type="button" class="btn btn-outline-success btn-sm mt-2" onclick="addLitter()">
                            <i class="fas fa-plus me-1"></i> Añadir Registro de Camada
                        </button>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button type="button" class="btn btn-outline-secondary btn-modern" onclick="goBack()">
                            <i class="fas fa-arrow-left me-1"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary btn-modern">
                            <i class="fas fa-save me-1"></i> Guardar Jaula
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>

</html>
