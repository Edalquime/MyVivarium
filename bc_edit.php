<?php

/**
 * Edit Breeding Cage Script - VERSIÓN COMPLETA CORREGIDA
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

$strainQuery = "SELECT str_id, str_name FROM strains";
$strainResult = $con->query($strainQuery);

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($con, $_GET['id']);
    $query = "SELECT * FROM cages WHERE id = '$id'";
    $query_run = mysqli_query($con, $query);

    if (mysqli_num_rows($query_run) > 0) {
        $row = mysqli_fetch_assoc($query_run);
        $cage_id = $row['cage_id'];

        $litterQuery = "SELECT * FROM litters WHERE cage_id = '$cage_id'";
        $litterResult = mysqli_query($con, $litterQuery);

        $maintenanceQuery = "SELECT * FROM maintenance WHERE cage_id = '$cage_id' ORDER BY date DESC";
        $maintenanceResult = mysqli_query($con, $maintenanceQuery);
    }
}

// --- LÓGICA DE ACTUALIZACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $con->begin_transaction();
    try {
        $protocol = $_POST['protocol'];
        $strain = $_POST['strain'];
        $mating_date = $_POST['mating_date'];
        $pi_id = $_POST['pi_id'];
        $user_id = $_POST['user_id'];
        $sexing_date = $_POST['sexing_date'];
        $status = $_POST['status'];
        $remarks_cage = $_POST['remarks'];

        // Actualizar Jaula
        $updateCage = $con->prepare("UPDATE cages SET protocol=?, strain=?, mating_date=?, pi_id=?, user_id=?, sexing_date=?, status=?, remarks=? WHERE id=?");
        $updateCage->bind_param("ssssssssi", $protocol, $strain, $mating_date, $pi_id, $user_id, $sexing_date, $status, $remarks_cage, $id);
        $updateCage->execute();

        // Procesar Camadas
        if (isset($_POST['litter_dob'])) {
            foreach ($_POST['litter_dob'] as $index => $dob) {
                if (empty($dob)) continue;

                $l_id = $_POST['litter_id'][$index];
                $alive = $_POST['pups_alive'][$index];
                $dead = $_POST['pups_dead'][$index];
                $male = $_POST['pups_male'][$index];
                $female = $_POST['pups_female'][$index];
                $remarks_l = $_POST['remarks_litter'][$index];

                if ($l_id === 'NEW') {
                    // CORRECCIÓN SQL: Se agrega 'dom' como NULL
                    $ins = $con->prepare("INSERT INTO litters (cage_id, dom, litter_dob, pups_alive, pups_dead, pups_male, pups_female, remarks) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");
                    $ins->bind_param("sssssss", $cage_id, $dob, $alive, $dead, $male, $female, $remarks_l);
                    $ins->execute();

                    // Tareas automáticas (sin emojis para evitar errores de charset)
                    $d21 = date('Y-m-d', strtotime($dob . ' + 21 days'));
                    $d30 = date('Y-m-d', strtotime($dob . ' + 30 days'));
                    $t1 = "Destete 21 dias - Jaula $cage_id";
                    $t2 = "LIMITE Destete 30 dias - Jaula $cage_id";
                    
                    $task = $con->prepare("INSERT INTO tasks (title, due_date, status, priority) VALUES (?, ?, 'Pending', 'High')");
                    $task->bind_param("ss", $t1, $d21); $task->execute();
                    $task->bind_param("ss", $t2, $d30); $task->execute();
                } else {
                    $upd = $con->prepare("UPDATE litters SET litter_dob=?, pups_alive=?, pups_dead=?, pups_male=?, pups_female=?, remarks=? WHERE id=?");
                    $upd->bind_param("ssssssi", $dob, $alive, $dead, $male, $female, $remarks_l, $l_id);
                    $upd->execute();
                }
            }
        }

        $con->commit();
        header("Location: bc_view.php?id=$id&msg=Success");
        exit;
    } catch (Exception $e) {
        $con->rollback();
        die("Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Jaula - Biocenter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary-blue: #0d6efd; --success-green: #198754; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border: none; border-radius: 15px; overflow: hidden; }
        .section-blue { border-start: 5px solid var(--primary-blue); }
        .section-green { border-start: 5px solid var(--success-green); }
        .litter-box { background: #fff; border: 1px solid #dee2e6; border-radius: 10px; transition: 0.2s; border-left: 4px solid var(--success-green); }
        .litter-box:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #495057; }
    </style>
</head>
<body>

<div class="container py-5">
    <form action="" method="POST">
        
        <div class="card shadow-sm mb-4 section-blue">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary"><i class="fas fa-dna me-2"></i>Información de Reproductores</h5>
                <span class="badge bg-primary">Jaula: <?= htmlspecialchars($cage_id) ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Protocolo</label>
                        <input type="text" name="protocol" class="form-control" value="<?= htmlspecialchars($row['protocol']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cepa (Strain)</label>
                        <select name="strain" class="form-select" required>
                            <?php $strainResult->data_seek(0); while($s = $strainResult->fetch_assoc()): ?>
                                <option value="<?= $s['str_id'] ?>" <?= ($row['strain']==$s['str_id'])?'selected':'' ?>><?= $s['str_name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha de Cruce</label>
                        <input type="date" name="mating_date" class="form-control" value="<?= $row['mating_date'] ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="Active" <?= ($row['status']=='Active')?'selected':'' ?>>Activa</option>
                            <option value="Retired" <?= ($row['status']=='Retired')?'selected':'' ?>>Retirada</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Investigador Principal</label>
                        <select name="pi_id" class="form-select">
                            <?php $result1->data_seek(0); while($u = $result1->fetch_assoc()): ?>
                                <option value="<?= $u['id'] ?>" <?= ($row['pi_id']==$u['id'])?'selected':'' ?>><?= $u['name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Responsable</label>
                        <select name="user_id" class="form-select">
                            <?php $userResult->data_seek(0); while($u = $userResult->fetch_assoc()): ?>
                                <option value="<?= $u['id'] ?>" <?= ($row['user_id']==$u['id'])?'selected':'' ?>><?= $u['name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha Sexado</label>
                        <input type="date" name="sexing_date" class="form-control" value="<?= $row['sexing_date'] ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas de la Jaula</label>
                        <textarea name="remarks" class="form-control" rows="2"><?= htmlspecialchars($row['remarks']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 section-green">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0"><i class="fas fa-baby-carriage me-2"></i>Registro de Camadas</h5>
                <button type="button" class="btn btn-light btn-sm fw-bold text-success" onclick="addLitter()">
                    <i class="fas fa-plus-circle me-1"></i> Nueva Camada
                </button>
            </div>
            <div class="card-body bg-light">
                <div id="litters-container">
                    <?php while ($l = mysqli_fetch_assoc($litterResult)): ?>
                        <div class="litter-box p-3 mb-3 shadow-sm">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-2">
                                    <label class="form-label text-success">Nacimiento</label>
                                    <input type="date" name="litter_dob[]" class="form-control form-control-sm" value="<?= $l['litter_dob'] ?>">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Vivos</label>
                                    <input type="number" name="pups_alive[]" class="form-control form-control-sm text-center" value="<?= $l['pups_alive'] ?>">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Muertos</label>
                                    <input type="number" name="pups_dead[]" class="form-control form-control-sm text-center" value="<?= $l['pups_dead'] ?>">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Machos</label>
                                    <input type="number" name="pups_male[]" class="form-control form-control-sm text-center" value="<?= $l['pups_male'] ?>">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label">Hembras</label>
                                    <input type="number" name="pups_female[]" class="form-control form-control-sm text-center" value="<?= $l['pups_female'] ?>">
                                </div>
                                <div class="col-md-5 text-start">
                                    <label class="form-label text-muted">Notas</label>
                                    <input type="text" name="remarks_litter[]" class="form-control form-control-sm" value="<?= htmlspecialchars($l['remarks']) ?>">
                                </div>
                                <div class="col-md-1">
                                    <input type="hidden" name="litter_id[]" value="<?= $l['id'] ?>">
                                    <button type="button" class="btn btn-outline-danger btn-sm mt-3" onclick="this.closest('.litter-box').remove()">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-3">
            <a href="bc_view.php?id=<?= $id ?>" class="btn btn-secondary px-4">Cancelar</a>
            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">GUARDAR CAMBIOS</button>
        </div>
    </form>
</div>

<script>
function addLitter() {
    const container = document.getElementById('litters-container');
    const div = document.createElement('div');
    div.className = 'litter-box p-3 mb-3 shadow-sm animate__animated animate__fadeIn';
    div.innerHTML = `
        <div class="row g-2 align-items-center">
            <div class="col-md-2"><label class="form-label text-success">Nacimiento</label><input type="date" name="litter_dob[]" class="form-control form-control-sm" required></div>
            <div class="col-md-1"><label class="form-label">Vivos</label><input type="number" name="pups_alive[]" class="form-control form-control-sm text-center" value="0"></div>
            <div class="col-md-1"><label class="form-label">Muertos</label><input type="number" name="pups_dead[]" class="form-control form-control-sm text-center" value="0"></div>
            <div class="col-md-1"><label class="form-label">Machos</label><input type="number" name="pups_male[]" class="form-control form-control-sm text-center" value="0"></div>
            <div class="col-md-1"><label class="form-label">Hembras</label><input type="number" name="pups_female[]" class="form-control form-control-sm text-center" value="0"></div>
            <div class="col-md-5 text-start"><label class="form-label text-muted">Notas</label><input type="text" name="remarks_litter[]" class="form-control form-control-sm" placeholder="Nueva camada..."></div>
            <div class="col-md-1">
                <input type="hidden" name="litter_id[]" value="NEW">
                <button type="button" class="btn btn-outline-danger btn-sm mt-3" onclick="this.closest('.litter-box').remove()"><i class="fas fa-trash"></i></button>
            </div>
        </div>`;
    container.appendChild(div);
}
</script>
</body>
</html>
