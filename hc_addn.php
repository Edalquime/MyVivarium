<?php

/**
 * Script para Agregar Nueva Jaula de Mantenimiento (Holding Cage)
 * * Este script maneja la creación de nuevas jaulas de mantenimiento e incluye funcionalidades 
 * para agregar o quitar dinámicamente datos de ratones individuales.
 */

// Iniciar sesión
session_start();

// Incluir la conexión a la base de datos
require 'dbcon.php';

// Verificar si el usuario no ha iniciado sesión
if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit; 
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Consultas para selects
$userQuery = "SELECT id, initials, name FROM users WHERE status = 'approved'";
$userResult = $con->query($userQuery);

$piQuery = "SELECT id, initials, name FROM users WHERE position = 'Principal Investigator' AND status = 'approved'";
$piResult = $con->query($piQuery);

$iacucQuery = "SELECT iacuc_id, iacuc_title FROM iacuc";
$iacucResult = $con->query($iacucQuery);

$strainQuery = "SELECT str_id, str_name, str_aka FROM strains";
$strainResult = $con->query($strainQuery);

$strainOptions = [];

while ($strainRow = $strainResult->fetch_assoc()) {
    $str_id = htmlspecialchars($strainRow['str_id'] ?? 'Unknown');
    $str_name = htmlspecialchars($strainRow['str_name'] ?? 'Unnamed Strain');
    $str_aka = $strainRow['str_aka'] ? htmlspecialchars($strainRow['str_aka']) : '';

    $strainOptions[] = "$str_id | $str_name";

    if (!empty($str_aka)) {
        $akaNames = explode(', ', $str_aka);
        foreach ($akaNames as $aka) {
            $strainOptions[] = "$str_id | " . htmlspecialchars(trim($aka));
        }
    }
}

sort($strainOptions, SORT_STRING);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('La validación del token CSRF falló');
    }

    $cage_id = trim(mysqli_real_escape_string($con, $_POST['cage_id']));
    $pi_name = mysqli_real_escape_string($con, $_POST['pi_name']);
    $strain = mysqli_real_escape_string($con, $_POST['strain']);
    $iacuc = isset($_POST['iacuc']) ? $_POST['iacuc'] : [];
    $user = isset($_POST['user']) ? $_POST['user'] : [];
    $dob = mysqli_real_escape_string($con, $_POST['dob']);
    $sex = mysqli_real_escape_string($con, $_POST['sex']);
    $parent_cg = mysqli_real_escape_string($con, $_POST['parent_cg']);
    $remarks = mysqli_real_escape_string($con, $_POST['remarks']);
    $mouse_data = [];

    $mouse_ids = $_POST['mouse_id'] ?? [];
    $genotypes = $_POST['genotype'] ?? [];
    $notes = $_POST['notes'] ?? [];

    for ($i = 0; $i < count($mouse_ids); $i++) {
        $mouse_id = $mouse_ids[$i];
        $genotype = $genotypes[$i];
        $note = $notes[$i];

        if (!empty(trim($mouse_id))) {
            $mouse_data[] = [
                'mouse_id' => mysqli_real_escape_string($con, $mouse_id),
                'genotype' => mysqli_real_escape_string($con, $genotype),
                'notes' => mysqli_real_escape_string($con, $note)
            ];
        }
    }

    $qty = count($mouse_data); 

    $check_query = "SELECT * FROM cages WHERE cage_id = ?";
    $check_stmt = $con->prepare($check_query);
    $check_stmt->bind_param("s", $cage_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['message'] = "El ID de Jaula '$cage_id' ya existe. Por favor utiliza un ID diferente.";
    } else {
        mysqli_begin_transaction($con);

        try {
            $query1 = "INSERT INTO cages (cage_id, pi_name, quantity, remarks) VALUES (?, ?, ?, ?)";
            $stmt = $con->prepare($query1);
            $stmt->bind_param("siss", $cage_id, $pi_name, $qty, $remarks);
            $stmt->execute();

            $query2 = "INSERT INTO holding (cage_id, strain, dob, sex, parent_cg) VALUES (?, ?, ?, ?, ?)";
            $stmt2 = $con->prepare($query2);
            $stmt2->bind_param("sssss", $cage_id, $strain, $dob, $sex, $parent_cg);
            $stmt2->execute();

            foreach ($mouse_data as $mouse) {
                $query3 = "INSERT INTO mice (cage_id, mouse_id, genotype, notes) VALUES (?, ?, ?, ?)";
                $stmt3 = $con->prepare($query3);
                $stmt3->bind_param("ssss", $cage_id, $mouse['mouse_id'], $mouse['genotype'], $mouse['notes']);
                $stmt3->execute();
            }

            if (!empty($iacuc)) {
                foreach ($iacuc as $iacuc_code) {
                    $query4 = "INSERT INTO cage_iacuc (cage_id, iacuc_id) VALUES (?, ?)";
                    $stmt4 = $con->prepare($query4);
                    $stmt4->bind_param("ss", $cage_id, $iacuc_code);
                    $stmt4->execute();
                }
            }

            if (!empty($user)) {
                foreach ($user as $user_id) {
                    $query5 = "INSERT INTO cage_users (cage_id, user_id) VALUES (?, ?)";
                    $stmt5 = $con->prepare($query5);
                    $stmt5->bind_param("si", $cage_id, $user_id);
                    $stmt5->execute();
                }
            }

            mysqli_commit($con);
            $_SESSION['message'] = "Nueva jaula de mantenimiento agregada con éxito.";
        } catch (Exception $e) {
            mysqli_rollback($con);
            $_SESSION['message'] = "Error al agregar la jaula de mantenimiento.";
        }

        $stmt->close();
        if (isset($stmt2)) $stmt2->close();
        if (isset($stmt3)) $stmt3->close();
        if (isset($stmt4)) $stmt4->close();
        if (isset($stmt5)) $stmt5->close();
    }

    header("Location: hc_dash.php");
    exit();
}

require 'header.php';
?>

<!doctype html>
<html lang="es">

<head>
    <title>Agregar Jaula de Mantenimiento | <?php echo htmlspecialchars($labName); ?></title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-beta.1/js/select2.min.js"></script>

    <style>
        /* Sincronización Flexbox para pie de página y estética estándar */
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
            color: #e74a3b;
        }

        .warning-text {
            color: #e74a3b;
            font-size: 13px;
        }

        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #d1d3e2;
            border-radius: 6px;
            min-height: 38px;
        }

        .btn-modern {
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
        });

        function goBack() {
            window.history.back();
        }

        let mouseFieldCounter = 0;

        function addMouseField() {
            const mouseContainer = document.getElementById('mouse_fields_container');
            mouseFieldCounter++;

            const mouseFieldHTML = `
            <div id="mouse_fields_${mouseFieldCounter}" class="section-card border-left-primary bg-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-primary fw-bold mb-0"><i class="fas fa-mouse-pointer me-2"></i>Ratón #${mouseFieldCounter}</h6>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMouseField(${mouseFieldCounter})">
                        <i class="fas fa-trash-alt me-1"></i> Eliminar
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">ID del Ratón <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" name="mouse_id[]" required placeholder="Ej: R102">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Genotipo</label>
                        <input type="text" class="form-control" name="genotype[]" placeholder="Ej: WT">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Notas de Mantenimiento</label>
                        <textarea class="form-control" name="notes[]" rows="1" oninput="adjustTextareaHeight(this)" placeholder="Notas individuales del ratón..."></textarea>
                    </div>
                </div>
            </div>`;

            mouseContainer.insertAdjacentHTML('beforeend', mouseFieldHTML);
        }

        function removeMouseField(mouseIndex) {
            const mouseField = document.getElementById(`mouse_fields_${mouseIndex}`);
            if (mouseField) {
                mouseField.remove();
            }
        }

        function adjustTextareaHeight(element) {
            element.style.height = "auto";
            element.style.height = (element.scrollHeight) + "px";
        }

        $(document).ready(function() {
            $('#user').select2({ placeholder: "Seleccionar Usuario(s)", allowClear: true, width: '100%' });
            $('#strain').select2({ placeholder: "Seleccionar Cepa", allowClear: true, width: '100%' });
            $('#iacuc').select2({ placeholder: "Seleccionar Protocolos", allowClear: true, width: '100%' });
        });
    </script>
</head>

<body>
    <div class="page-content">
        <div class="container mt-4 mb-5">
            <div class="card main-card">
                
                <div class="card-header bg-dark text-white p-3 d-flex align-items-center justify-content-between" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-plus-circle me-2"></i> Agregar Nueva Jaula de Mantenimiento</h4>
                    <button type="button" class="btn btn-sm btn-light" onclick="goBack()"><i class="fas fa-arrow-left me-1"></i> Volver</button>
                </div>

                <div class="card-body bg-light p-4">
                    
                    <?php include('message.php'); ?>

                    <p class="warning-text mb-4">Los campos marcados con <span class="required-asterisk">*</span> son obligatorios.</p>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-cube"></i> Información de la Jaula
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="cage_id" class="form-label">ID de la Jaula <span class="required-asterisk">*</span></label>
                                    <input type="text" class="form-control" id="cage_id" name="cage_id" required placeholder="Ej: JM-05">
                                </div>

                                <div class="col-md-6">
                                    <label for="pi_name" class="form-label">Investigador Principal (PI) <span class="required-asterisk">*</span></label>
                                    <select class="form-control" id="pi_name" name="pi_name" required>
                                        <option value="" disabled selected>Seleccionar PI</option>
                                        <?php
                                        while ($row = $piResult->fetch_assoc()) {
                                            $pi_id = htmlspecialchars($row['id']);
                                            $pi_initials = htmlspecialchars($row['initials']);
                                            $pi_name = htmlspecialchars($row['name']);
                                            echo "<option value='$pi_id'>$pi_initials [$pi_name]</option>";
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
                                            echo "<option value='" . explode(" | ", $option)[0] . "'>$option</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="iacuc" class="form-label">Protocolos IACUC</label>
                                    <select class="form-control" id="iacuc" name="iacuc[]" multiple>
                                        <?php
                                        while ($iacucRow = $iacucResult->fetch_assoc()) {
                                            $iacuc_id = htmlspecialchars($iacucRow['iacuc_id']);
                                            $iacuc_title = htmlspecialchars($iacucRow['iacuc_title']);
                                            $truncated_title = strlen($iacuc_title) > 40 ? substr($iacuc_title, 0, 40) . '...' : $iacuc_title;
                                            echo "<option value='$iacuc_id' title='$iacuc_title'>$iacuc_id | $truncated_title</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="user" class="form-label">Usuarios Asignados <span class="required-asterisk">*</span></label>
                                    <select class="form-control" id="user" name="user[]" multiple required>
                                        <?php
                                        while ($userRow = $userResult->fetch_assoc()) {
                                            $user_id = htmlspecialchars($userRow['id']);
                                            $initials = htmlspecialchars($userRow['initials']);
                                            $name = htmlspecialchars($userRow['name']);
                                            echo "<option value='$user_id'>$initials [$name]</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="dob" class="form-label">Fecha de Nacimiento <span class="required-asterisk">*</span></label>
                                    <input type="date" class="form-control" id="dob" name="dob" required min="1900-01-01">
                                </div>

                                <div class="col-md-6">
                                    <label for="sex" class="form-label">Sexo <span class="required-asterisk">*</span></label>
                                    <select class="form-control" id="sex" name="sex" required>
                                        <option value="" disabled selected>Seleccionar Sexo</option>
                                        <option value="Male">Macho</option>
                                        <option value="Female">Hembra</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="parent_cg" class="form-label">Jaula Madre / Parental <span class="required-asterisk">*</span></label>
                                    <input type="text" class="form-control" id="parent_cg" name="parent_cg" required placeholder="Ej: JC-01">
                                </div>

                                <div class="col-md-12">
                                    <label for="remarks" class="form-label">Observaciones de la Jaula</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" oninput="adjustTextareaHeight(this)" placeholder="Anotaciones globales..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="section-card">
                            <div class="section-title d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-paw"></i> Información Individual de Ratones</span>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="addMouseField()">
                                    <i class="fas fa-plus me-1"></i> Añadir Ratón
                                </button>
                            </div>
                            
                            <div id="mouse_fields_container">
                                </div>
                        </div>

                        <div class="d-flex justify-content-end gap-3 mt-4">
                            <button type="button" class="btn btn-outline-secondary btn-modern" onclick="goBack()">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary btn-modern">
                                <i class="fas fa-save me-1"></i> Guardar Jaula
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="page-footer">
        <?php include 'footer.php'; ?>
    </div>
</body>

</html>
