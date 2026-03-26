<?php

/**
 * Gestión de Tareas
 * * Este script proporciona funcionalidad para gestionar tareas en una base de datos. Permite a los usuarios añadir nuevas tareas,
 * editar tareas existentes y eliminar tareas. La interfaz incluye un formulario emergente responsivo para el ingreso de datos y 
 * una tabla para mostrar las tareas existentes. El script utiliza sesiones de PHP para el manejo de mensajes e incluye desinfección 
 * básica de entradas por seguridad.
 * */

ob_start(); // Iniciar almacenamiento en búfer de salida
session_start(); // Iniciar la sesión para usar variables de sesión
require 'header.php'; // Incluir archivo de cabecera
require 'dbcon.php'; // Incluir conexión a la base de datos

// Verificar si el usuario ha iniciado sesión, redirigir a la página de inicio si no es así
if (!isset($_SESSION['name'])) {
    header("Location: index.php"); // Redirigir a la página de inicio de sesión si no ha iniciado sesión
    exit; // Salir del script
}

// Generar token CSRF si no está establecido
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener el ID y el nombre del usuario actual de la sesión
$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserName = $_SESSION['name'] ?? '';
$isAdmin = $_SESSION['role'] == 'admin'; // Verificar si el usuario es administrador

/**
 * Redirigir a manage_tasks.php con un mensaje y un ID de jaula opcional
 *
 * @param string $message El mensaje a mostrar
 * @param string|null $cageId El ID de la jaula a incluir en la URL
 */
function redirectToPage($message, $cageId = null)
{
    $_SESSION['message'] = $message;
    $location = 'manage_tasks.php';
    if ($cageId) {
        $location .= '?id=' . $cageId;
    }
    header('Location: ' . $location);
    exit();
}

/**
 * Programar un correo electrónico insertándolo en la tabla de outbox
 *
 * @param int $task_id El ID de la tarea asociada con el correo
 * @param mixed $recipients Los destinatarios del correo
 * @param string $subject El asunto del correo
 * @param string $body El cuerpo del correo
 * @param string $scheduledAt La hora programada para enviar el correo
 * @return bool True si el correo se programó con éxito, false de lo contrario
 */
function scheduleEmail($task_id, $recipients, $subject, $body, $scheduledAt)
{
    global $con;
    $stmt = $con->prepare("INSERT INTO outbox (task_id, recipient, subject, body, scheduled_at) VALUES (?, ?, ?, ?, ?)");
    $recipientList = is_array($recipients) ? implode(',', $recipients) : $recipients;
    $stmt->bind_param("issss", $task_id, $recipientList, $subject, $body, $scheduledAt);

    if ($stmt->execute()) {
        return true;
    } else {
        error_log("Error al programar el correo: " . $stmt->error);
        return false;
    }

    $stmt->close();
}

// Obtener usuarios para el menú desplegable
$userQuery = "SELECT id, name FROM users";
$userResult = $con->query($userQuery);
$users = $userResult ? array_column($userResult->fetch_all(MYSQLI_ASSOC), 'name', 'id') : [];

// Manejar el envío del formulario para agregar, editar o eliminar tareas
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('La validación del token CSRF falló');
    }

    $title = htmlspecialchars($_POST['title']);
    $description = htmlspecialchars($_POST['description']);
    $assignedBy = htmlspecialchars($_POST['assigned_by_id']);
    $assignedTo = htmlspecialchars(implode(',', $_POST['assigned_to'] ?? []));
    $status = htmlspecialchars($_POST['status']);
    $completionDate = !empty($_POST['completion_date']) ? htmlspecialchars($_POST['completion_date']) : NULL;
    $cageId = empty($_POST['cage_id']) ? NULL : htmlspecialchars($_POST['cage_id']);
    $taskAction = '';
    $task_id = null; // Inicializar variable task_id

    // Determinar la acción a realizar (agregar, editar o eliminar)
    if (isset($_POST['add'])) {
        $stmt = $con->prepare("INSERT INTO tasks (title, description, assigned_by, assigned_to, status, completion_date, cage_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $title, $description, $assignedBy, $assignedTo, $status, $completionDate, $cageId);
        if ($stmt->execute()) {
            $task_id = $stmt->insert_id; // Obtener el último ID de tarea insertado
            $taskAction = 'creada';
        } else {
            redirectToPage("Error: " . $stmt->error);
        }
    } elseif (isset($_POST['edit'])) {
        $id = htmlspecialchars($_POST['id']);
        $stmt = $con->prepare("UPDATE tasks SET title = ?, description = ?, assigned_by = ?, assigned_to = ?, status = ?, completion_date = ?, cage_id = ? WHERE id = ?");
        $stmt->bind_param("sssssssi", $title, $description, $assignedBy, $assignedTo, $status, $completionDate, $cageId, $id);
        if ($stmt->execute()) {
            $task_id = $id; // Usar el ID de la tarea que se está actualizando
            $taskAction = 'actualizada';
        } else {
            redirectToPage("Error: " . $stmt->error);
        }
    } elseif (isset($_POST['delete'])) {
        $id = htmlspecialchars($_POST['id']);

        // Obtener detalles de la tarea antes de la eliminación para la notificación por correo
        $taskQuery = $con->prepare("SELECT title, description, assigned_by, assigned_to, status, completion_date, cage_id FROM tasks WHERE id = ?");
        $taskQuery->bind_param("i", $id);
        $taskQuery->execute();
        $taskQuery->bind_result($title, $description, $assignedBy, $assignedTo, $status, $completionDate, $cageId);
        $taskQuery->fetch();
        $taskQuery->close();

        // Ahora eliminar la tarea
        $stmt = $con->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $task_id = NULL; // Establecer task_id en NULL para el caso de eliminación
            $taskAction = 'eliminada';
        } else {
            redirectToPage("Error: " . $stmt->error);
        }
    }

    // Obtener correos electrónicos de los usuarios asignadores y asignados
    $emails = [];
    $assignedByEmailQuery = "SELECT username FROM users WHERE id = ?";
    $assignedByEmailStmt = $con->prepare($assignedByEmailQuery);
    $assignedByEmailStmt->bind_param("i", $assignedBy);
    $assignedByEmailStmt->execute();
    $assignedByEmailStmt->bind_result($assignedByEmail);
    $assignedByEmailStmt->fetch();
    $assignedByEmailStmt->close();
    $emails[] = $assignedByEmail;

    $assignedToArray = explode(',', $assignedTo);
    foreach ($assignedToArray as $assignedToUserId) {
        $assignedToEmailQuery = "SELECT username FROM users WHERE id = ?";
        $assignedToEmailStmt = $con->prepare($assignedToEmailQuery);
        $assignedToEmailStmt->bind_param("i", $assignedToUserId);
        $assignedToEmailStmt->execute();
        $assignedToEmailStmt->bind_result($assignedToEmail);
        while ($assignedToEmailStmt->fetch()) {
            $emails[] = $assignedToEmail;
        }
        $assignedToEmailStmt->close();
    }

    // Obtener la zona horaria de la tabla de configuración
    $timezoneQuery = "SELECT value FROM settings WHERE name = 'timezone'";
    $timezoneResult = mysqli_query($con, $timezoneQuery);
    $timezoneRow = mysqli_fetch_assoc($timezoneResult);
    $timezone = $timezoneRow['value'] ?? 'America/New_York';

    // Establecer la zona horaria predeterminada
    date_default_timezone_set($timezone);

    // Obtener la fecha y hora actual
    $dateTime = date('Y-m-d H:i:s');

    // Convertir IDs de asignadores y asignados a nombres
    $assignedByName = isset($users[$assignedBy]) ? $users[$assignedBy] : 'Desconocido';
    $assignedToNames = array_map(function ($id) use ($users) {
        return isset($users[$id]) ? $users[$id] : 'Desconocido';
    }, explode(',', $assignedTo));
    $assignedToNamesStr = implode(', ', $assignedToNames);

    // Actualizar el asunto para incluir la fecha y la hora
    $subject = "Tarea $taskAction: $title el $dateTime";
    // Determinar el ID de tarea a usar en el cuerpo del correo
    $emailTaskId = is_null($task_id) ? $id : $task_id;

    $body = "La tarea con ID '$emailTaskId' ha sido $taskAction. Aquí están los detalles:<br><br>" .
        "<strong>Título:</strong> $title<br>" .
        "<strong>Descripción/Actualización:</strong> $description<br>" .
        "<strong>Estado:</strong> $status<br>" .
        "<strong>Asignado Por:</strong> $assignedByName<br>" .
        "<strong>Asignado A:</strong> $assignedToNamesStr<br>" .
        "<strong>Fecha de Finalización:</strong> $completionDate<br>" .
        "<strong>ID de Jaula:</strong> $cageId<br>";

    // Programar el correo electrónico
    $scheduledAt = date('Y-m-d H:i:s');
    $result = scheduleEmail($task_id, $emails, $subject, $body, $scheduledAt);

    if ($result) {
        error_log("Correo electrónico programado exitosamente.");
        redirectToPage("Tarea $taskAction exitosamente.");
    } else {
        error_log("Fallo al programar el correo electrónico.");
        redirectToPage("Tarea $taskAction, pero falló la programación del correo.");
    }

    $stmt->close();
}

// Obtener tareas para mostrar
$search = htmlspecialchars($_GET['search'] ?? '');
$cageIdFilter = htmlspecialchars($_GET['id'] ?? '');
$filter = htmlspecialchars($_GET['filter'] ?? '');

$taskQueries = "SELECT * FROM tasks WHERE 1";
if ($search) {
    $taskQueries .= " AND (title LIKE '%$search%' OR assigned_by LIKE '%$search%' OR assigned_to LIKE '%$search%' OR status LIKE '%$search%' OR cage_id LIKE '%$search%')";
}

if ($filter == 'assigned_by_me') {
    $taskQueries .= " AND assigned_by = '$currentUserId'";
} elseif ($filter == 'assigned_to_me') {
    $taskQueries .= " AND FIND_IN_SET('$currentUserId', assigned_to)";
} elseif (!$isAdmin) {
    $taskQueries .= " AND (assigned_by = '$currentUserId' OR FIND_IN_SET('$currentUserId', assigned_to))";
}

if ($cageIdFilter) {
    $taskQueries .= " AND cage_id = '$cageIdFilter'";
}

$taskResult = $con->query($taskQueries);

// Obtener jaulas para el menú desplegable
$cageQuery = "SELECT cage_id FROM cages";
$cageResult = $con->query($cageQuery);
$cages = $cageResult ? array_column($cageResult->fetch_all(MYSQLI_ASSOC), 'cage_id') : [];

ob_end_flush(); // Vaciar el búfer de salida
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tareas | <?php echo htmlspecialchars($labName); ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

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

        .table-responsive {
            border-radius: 8px;
            border: 1px solid #e3e6f0;
        }

        /* Ventanas emergentes modernas */
        .popup-form,
        .view-popup-form {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            z-index: 1000;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            width: 85%;
            max-width: 800px;
            overflow-y: auto;
            max-height: 85vh;
        }

        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 999;
            backdrop-filter: blur(2px);
        }

        .form-label {
            font-weight: 600;
            color: #343a40;
            font-size: 0.9rem;
        }

        .required-asterisk {
            color: red;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
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
        <div class="container mt-4 mb-5" style="max-width: 1100px;">

            <?php if (isset($_SESSION['message'])) : ?>
                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <?= $_SESSION['message']; ?>
                    <?php unset($_SESSION['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            <?php endif; ?>

            <div class="card main-card">
                <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-tasks me-2"></i> Tareas de Laboratorio <?= $cageIdFilter ? 'para la Jaula ' . htmlspecialchars($cageIdFilter) : ''; ?></h4>
                    <div class="d-flex gap-2">
                        <button id="addNewTaskButton" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                            <i class="fas fa-plus"></i> Añadir Tarea
                        </button>
                        <?php if ($cageIdFilter) : ?>
                            <a href="manage_tasks.php" class="btn btn-secondary btn-sm">Ver Todo</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body bg-light p-4">
                    <div class="mb-3 d-flex gap-2">
                        <a href="manage_tasks.php" class="btn btn-sm <?= empty($filter) ? 'btn-dark' : 'btn-outline-dark' ?>">Todas</a>
                        <a href="manage_tasks.php?filter=assigned_by_me" class="btn btn-sm <?= $filter === 'assigned_by_me' ? 'btn-dark' : 'btn-outline-dark' ?>">Creadas por mí</a>
                        <a href="manage_tasks.php?filter=assigned_to_me" class="btn btn-sm <?= $filter === 'assigned_to_me' ? 'btn-dark' : 'btn-outline-dark' ?>">Asignadas a mí</a>
                    </div>

                    <div class="section-card mb-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Título</th>
                                        <th style="width: 15%;" class="text-center">Estado</th>
                                        <th style="width: 15%;">Asignada A</th>
                                        <th style="width: 15%;">Finalización</th>
                                        <th style="width: 10%;" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $taskResult->fetch_assoc()) : ?>
                                        <?php
                                        $statusClass = 'bg-secondary';
                                        if ($row['status'] == 'In Progress') $statusClass = 'bg-warning text-dark';
                                        if ($row['status'] == 'Completed') $statusClass = 'bg-success';
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($row['title']); ?></strong></td>
                                            <td class="text-center"><span class="badge <?= $statusClass; ?>"><?= htmlspecialchars($row['status']); ?></span></td>
                                            <td>
                                                <?php
                                                $assignedToNames = array_map(function ($id) use ($users) {
                                                    return isset($users[$id]) ? $users[$id] : 'Desconocido';
                                                }, explode(',', $row['assigned_to']));
                                                echo htmlspecialchars(implode(', ', $assignedToNames));
                                                ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['completion_date'] ?? 'N/A'); ?></td>
                                            <td class="text-center">
                                                <div class="action-buttons">
                                                    <button type="button" class="btn btn-info btn-sm btn-icon viewButton" data-id="<?= $row['id']; ?>" title="Ver Tarea">
                                                        <i class="fas fa-eye text-white"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-warning btn-sm btn-icon editButton" data-id="<?= $row['id']; ?>" data-cage="<?= htmlspecialchars($row['cage_id']); ?>" title="Editar Tarea">
                                                        <i class="fas fa-edit text-white"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="popup-overlay" id="popupOverlay"></div>

    <div class="popup-form" id="popupForm">
        <h4 class="mb-4" id="formTitle">Añadir Nueva Tarea</h4>
        <form id="taskForm" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="id" id="id">

            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="title" class="form-label">Título <span class="required-asterisk">*</span></label>
                    <input type="text" name="title" id="title" class="form-control" maxlength="100" required>
                    <small id="titleCounter" class="form-text text-muted">0/100 caracteres usados</small>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="description" class="form-label">Descripción/Actualización <span class="required-asterisk">*</span></label>
                    <textarea name="description" id="description" class="form-control" rows="3" maxlength="500" required></textarea>
                    <small id="descriptionCounter" class="form-text text-muted">0/500 caracteres usados</small>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="assigned_by" class="form-label">Asignado Por <span class="required-asterisk">*</span></label>
                    <input type="text" name="assigned_by" id="assigned_by" class="form-control bg-light" readonly>
                    <input type="hidden" name="assigned_by_id" id="assigned_by_id">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="assigned_to" class="form-label">Asignado A <span class="required-asterisk">*</span></label>
                    <select name="assigned_to[]" id="assigned_to" class="form-control" style="width:100%;" multiple required>
                        <?php foreach ($users as $userId => $name) : ?>
                            <option value="<?= htmlspecialchars($userId); ?>"><?= htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado <span class="required-asterisk">*</span></label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="status" id="statusPending" value="Pending" required>
                            <label class="form-check-label" for="statusPending">Pendiente</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="status" id="statusInProgress" value="In Progress">
                            <label class="form-check-label" for="statusInProgress">En Progreso</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="status" id="statusCompleted" value="Completed">
                            <label class="form-check-label" for="statusCompleted">Completada</label>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="completion_date" class="form-label">Fecha de Finalización</label>
                    <input type="date" name="completion_date" id="completion_date" class="form-control">
                </div>

                <div class="col-md-12 mb-3">
                    <label for="cage_id_select" class="form-label">ID de Jaula</label>
                    <select name="cage_id" id="cage_id_select" class="form-control">
                        <option value="">Seleccionar Jaula (Opcional)</option>
                        <?php foreach ($cages as $cage) : ?>
                            <option value="<?= htmlspecialchars($cage); ?>"><?= htmlspecialchars($cage); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" id="cancelButton" class="btn btn-secondary">Cancelar</button>
                <button type="submit" name="add" id="addButton" class="btn btn-primary">Guardar Tarea</button>
                <button type="submit" name="edit" id="editButton" class="btn btn-primary" style="display:none;">Actualizar Tarea</button>
                <button type="submit" name="delete" id="deleteButton" class="btn btn-danger" style="display:none;" onclick="return confirm('¿Estás seguro de que deseas eliminar esta tarea?');">Eliminar Tarea</button>
            </div>
        </form>
    </div>

    <div class="popup-overlay" id="viewPopupOverlay"></div>
    <div class="view-popup-form" id="viewPopupForm">
        <h4 class="mb-4">Detalles de la Tarea</h4>
        <div id="viewTaskDetails" class="row"></div>
        <div class="d-flex justify-content-end mt-3">
            <button type="button" id="closeViewFormButton" class="btn btn-secondary">Cerrar</button>
        </div>
    </div>

    <div class="page-footer">
        <?php include 'footer.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        const users = <?= json_encode($users); ?>;
        const currentUserName = '<?= $currentUserName; ?>';
        const currentUserId = '<?= $currentUserId; ?>';
        const cageIdFilter = '<?= $cageIdFilter; ?>';

        $(document).ready(function() {
            $('#assigned_to').select2({
                placeholder: "Selecciona usuarios",
                allowClear: true
            });

            $('#popupOverlay, #viewPopupOverlay').on('click', function() {
                closeForm();
                closeViewForm();
            });

            $('.popup-form, .view-popup-form').on('click', function(e) {
                e.stopPropagation();
            });

            $('#addNewTaskButton').on('click', function() {
                openForm();
            });

            $('#cancelButton').on('click', function() {
                closeForm();
            });

            $('#closeViewFormButton').on('click', function() {
                closeViewForm();
            });

            $(document).on('click', '.editButton', function() {
                const id = $(this).data('id');
                const cageId = $(this).data('cage');
                fetchTaskData(id, 'edit', cageId);
            });

            $(document).on('click', '.viewButton', function() {
                const id = $(this).data('id');
                fetchTaskData(id, 'view');
            });

            $('#title').on('input', function() {
                $('#titleCounter').text(`${$(this).val().length}/100 caracteres usados`);
            });

            $('#description').on('input', function() {
                $('#descriptionCounter').text(`${$(this).val().length}/500 caracteres usados`);
            });
        });

        function openForm() {
            document.getElementById('popupForm').style.display = 'block';
            document.getElementById('popupOverlay').style.display = 'block';
            document.getElementById('formTitle').innerText = 'Añadir Nueva Tarea';
            document.getElementById('addButton').style.display = 'block';
            document.getElementById('editButton').style.display = 'none';
            document.getElementById('deleteButton').style.display = 'none';
            document.getElementById('taskForm').reset();
            $('#assigned_to').val(null).trigger('change');

            document.getElementById('assigned_by').value = currentUserName;
            document.getElementById('assigned_by_id').value = currentUserId;

            if (cageIdFilter) {
                document.getElementById('cage_id_select').value = cageIdFilter;
            }
        }

        function closeForm() {
            document.getElementById('popupForm').style.display = 'none';
            document.getElementById('popupOverlay').style.display = 'none';
        }

        function closeViewForm() {
            document.getElementById('viewPopupForm').style.display = 'none';
            document.getElementById('viewPopupOverlay').style.display = 'none';
        }

        function fetchTaskData(id, action, cageId = '') {
            $.ajax({
                url: 'get_task.php',
                type: 'GET',
                data: { id: id },
                success: function(response) {
                    if (action === 'edit') {
                        document.getElementById('popupForm').style.display = 'block';
                        document.getElementById('popupOverlay').style.display = 'block';
                        document.getElementById('formTitle').innerText = 'Editar Tarea';
                        document.getElementById('addButton').style.display = 'none';
                        document.getElementById('editButton').style.display = 'block';
                        document.getElementById('deleteButton').style.display = 'block';

                        document.getElementById('id').value = response.id;
                        document.getElementById('title').value = response.title;
                        document.getElementById('description').value = response.description;
                        document.getElementById('assigned_by').value = users[response.assigned_by] || response.assigned_by;
                        document.getElementById('assigned_by_id').value = response.assigned_by;

                        const assignedToArray = response.assigned_to.split(',');
                        $('#assigned_to').val(assignedToArray).trigger('change');

                        const statusRadios = document.getElementsByName('status');
                        statusRadios.forEach(radio => {
                            if (radio.value === response.status) {
                                radio.checked = true;
                            }
                        });

                        document.getElementById('completion_date').value = response.completion_date;
                        document.getElementById('cage_id_select').value = response.cage_id;
                    } else if (action === 'view') {
                        document.getElementById('viewPopupForm').style.display = 'block';
                        document.getElementById('viewPopupOverlay').style.display = 'block';

                        const assignedToNames = response.assigned_to.split(',').map(id => users[id] || 'Desconocido');

                        document.getElementById('viewTaskDetails').innerHTML = `
                            <div class="col-md-6 mb-2"><strong>Título:</strong> ${response.title}</div>
                            <div class="col-md-6 mb-2"><strong>Creado Por:</strong> ${users[response.assigned_by] || response.assigned_by}</div>
                            <div class="col-md-12 mb-2"><strong>Descripción:</strong> ${response.description}</div>
                            <div class="col-md-6 mb-2"><strong>Estado:</strong> ${response.status}</div>
                            <div class="col-md-6 mb-2"><strong>Asignado A:</strong> ${assignedToNames.join(', ')}</div>
                            <div class="col-md-6 mb-2"><strong>Jaula Relacionada:</strong> ${response.cage_id || 'N/A'}</div>
                            <div class="col-md-6 mb-2"><strong>Fecha Finalización:</strong> ${response.completion_date || 'N/A'}</div>
                        `;
                    }
                }
            });
        }
    </script>
</body>

</html>
