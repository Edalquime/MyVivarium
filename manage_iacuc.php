<?php

/**
 * Gestionar IACUC
 * * Este script proporciona funcionalidad para gestionar los registros de IACUC en una base de datos. Permite a los usuarios añadir nuevos registros de IACUC,
 * editar registros existentes y eliminarlos. La interfaz incluye un formulario emergente adaptable para la entrada de datos y 
 * una tabla para mostrar los registros existentes. El script utiliza sesiones de PHP para el manejo de mensajes e incluye desinfección básica 
 * de entradas para seguridad. Se incluye la funcionalidad de carga de archivos para los registros de IACUC.
 * */

session_start(); // Iniciar la sesión para usar variables de sesión
require 'dbcon.php'; // Incluir la conexión a la base de datos
require 'header.php'; // Incluir la cabecera para una estructura de página consistente

// Generar token CSRF si aún no está configurado
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Manejar la carga de archivos con validación
function handleFileUpload($file) {
    // Definir tipos de archivo permitidos y tamaño máximo
    $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx',
                          'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
    $maxFileSize = 10 * 1024 * 1024; // 10MB en bytes

    // Verificar si el archivo se subió sin errores
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Validar el tamaño del archivo
    if ($file['size'] > $maxFileSize) {
        $_SESSION['message'] = "El tamaño del archivo supera el límite de 10 MB.";
        return false;
    }

    // Obtener la extensión del archivo
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Validar la extensión del archivo
    if (!in_array($fileExtension, $allowedExtensions)) {
        $_SESSION['message'] = "Tipo de archivo inválido. Permitidos: pdf, doc, docx, txt, xls, xlsx, ppt, pptx, jpg, jpeg, png, gif, bmp, svg, webp";
        return false;
    }

    // Desinfectar el nombre del archivo para evitar el salto de directorio
    $safeFilename = preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($file["name"]));

    $targetDir = "uploads/iacuc/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true); // Crear el directorio si no existe
    }

    $targetFile = $targetDir . $safeFilename;

    // Mover el archivo subido
    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return $targetFile;
    } else {
        $_SESSION['message'] = "Error al subir el archivo.";
        return false;
    }
}

// Manejar el envío del formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar el token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('La validación del token CSRF falló');
    }

    if (isset($_POST['add'])) {
        // Añadir nuevo IACUC
        $iacucId = htmlspecialchars($_POST['iacuc_id']); // Desinfectar entrada
        $iacucTitle = htmlspecialchars($_POST['iacuc_title']); // Desinfectar entrada

        // Verificar si el ID de IACUC ya existe
        $checkQuery = $con->prepare("SELECT iacuc_id FROM iacuc WHERE iacuc_id = ?");
        $checkQuery->bind_param("s", $iacucId);
        $checkQuery->execute();
        $checkQuery->store_result();
        if ($checkQuery->num_rows > 0) {
            $_SESSION['message'] = "El ID de IACUC ya existe. Por favor utiliza un ID diferente.";
            $checkQuery->close();
        } else {
            $checkQuery->close();
            $fileUrl = !empty($_FILES['iacuc_file']['name']) ? handleFileUpload($_FILES['iacuc_file']) : null;
            if ($fileUrl !== false) {
                $stmt = $con->prepare("INSERT INTO iacuc (iacuc_id, iacuc_title, file_url) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $iacucId, $iacucTitle, $fileUrl);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Registro de IACUC añadido con éxito."; // Mensaje de éxito
                } else {
                    $_SESSION['message'] = "Error al añadir el registro de IACUC."; // Mensaje de error
                }
                $stmt->close(); // Cerrar la sentencia
            } else {
                $_SESSION['message'] = "Error al subir el archivo.";
            }
        }
    } elseif (isset($_POST['edit'])) {
        // Actualizar IACUC existente
        $iacucId = htmlspecialchars($_POST['iacuc_id']); // Desinfectar entrada
        $iacucTitle = htmlspecialchars($_POST['iacuc_title']); // Desinfectar entrada
        $fileUrl = !empty($_FILES['iacuc_file']['name']) ? handleFileUpload($_FILES['iacuc_file']) : htmlspecialchars($_POST['existing_file_url']);
        
        if ($fileUrl !== false) {
            $stmt = $con->prepare("UPDATE iacuc SET iacuc_title = ?, file_url = ? WHERE iacuc_id = ?");
            $stmt->bind_param("sss", $iacucTitle, $fileUrl, $iacucId);
            if ($stmt->execute()) {
                $_SESSION['message'] = "Registro de IACUC actualizado con éxito."; // Mensaje de éxito
            } else {
                $_SESSION['message'] = "Error al actualizar el registro de IACUC."; // Mensaje de error
            }
            $stmt->close(); // Cerrar la sentencia
        } else {
            $_SESSION['message'] = "Error al subir el archivo.";
        }
    } elseif (isset($_POST['delete'])) {
        // Eliminar IACUC
        $iacucId = htmlspecialchars($_POST['iacuc_id']); // Desinfectar entrada
        $stmt = $con->prepare("DELETE FROM iacuc WHERE iacuc_id = ?");
        $stmt->bind_param("s", $iacucId);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Registro de IACUC eliminado con éxito."; // Mensaje de éxito
        } else {
            $_SESSION['message'] = "Error al eliminar el registro de IACUC."; // Mensaje de error
        }
        $stmt->close(); // Cerrar la sentencia
    }
}

// Recuperar todos los registros de IACUC para mostrar
$iacucQuery = "SELECT * FROM iacuc";
$iacucResult = $con->query($iacucQuery);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar IACUC | <?php echo htmlspecialchars($labName); ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* --- ESTANDARIZACIÓN FLEXBOX PARA EL FOOTER --- */
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
        /* ----------------------------------------------- */

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

        .btn-modern {
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-icon {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 0;
            font-weight: 600;
        }

        .btn-icon i {
            font-size: 16px;
        }

        .table-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        /* Formulario emergente (Modal modernizado) */
        .popup-form {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 25px;
            border-radius: 12px;
            z-index: 1000;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            width: 90%;
            max-width: 600px;
        }

        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            backdrop-filter: blur(2px);
        }

        .required-asterisk {
            color: #e74a3b;
        }
    </style>
</head>

<body>
    <div class="page-content">
        <div class="container mt-4 mb-5" style="max-width: 900px;">
            
            <?php if (isset($_SESSION['message'])) : ?>
                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <?= $_SESSION['message']; ?>
                    <?php unset($_SESSION['message']); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="card main-card">
                <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-file-medical me-2"></i> Gestionar Protocolos IACUC</h4>
                    <button onclick="openForm()" class="btn btn-primary btn-modern"><i class="fas fa-plus"></i> Añadir IACUC</button>
                </div>

                <div class="card-body bg-light p-4">

                    <div class="popup-overlay" id="popupOverlay"></div>
                    <div class="popup-form" id="popupForm">
                        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                            <h5 class="text-primary fw-bold mb-0" id="formTitle"><i class="fas fa-plus-circle me-2"></i>Añadir Nuevo IACUC</h5>
                            <button type="button" class="btn-close" onclick="closeForm()" style="background:none; border:none; font-size: 20px;">&times;</button>
                        </div>
                        
                        <form action="manage_iacuc.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="form-group">
                                <label for="iacuc_id" class="form-label">ID de IACUC <span class="required-asterisk">*</span></label>
                                <input type="text" name="iacuc_id" id="iacuc_id" class="form-control" required placeholder="Ej: PROTO-2026">
                            </div>
                            <div class="form-group">
                                <label for="iacuc_title" class="form-label">Título del Protocolo <span class="required-asterisk">*</span></label>
                                <input type="text" name="iacuc_title" id="iacuc_title" class="form-control" required placeholder="Ej: Estudio sobre comportamiento murino">
                            </div>
                            <div class="form-group">
                                <label for="iacuc_file" class="form-label">Subir Archivo</label>
                                <input type="file" name="iacuc_file" id="iacuc_file" class="form-control-file p-1 border rounded" style="width:100%;">
                                <div id="existingFile" class="mt-2"></div>
                            </div>
                            <input type="hidden" name="existing_file_url" id="existing_file_url">

                            <div class="d-flex justify-content-end gap-3 mt-4">
                                <button type="button" class="btn btn-outline-secondary btn-modern" onclick="closeForm()">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </button>
                                <button type="submit" name="add" id="addButton" class="btn btn-primary btn-modern">
                                    <i class="fas fa-save me-1"></i> Guardar IACUC
                                </button>
                                <button type="submit" name="edit" id="editButton" class="btn btn-success btn-modern" style="display: none;">
                                    <i class="fas fa-sync-alt me-1"></i> Actualizar IACUC
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="section-card mb-0">
                        <div class="section-title">
                            <i class="fas fa-list"></i> Registros IACUC Existentes
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th style="background-color: #eaecf4; color: #4e73df;">ID</th>
                                        <th style="background-color: #eaecf4; color: #4e73df;">Título</th>
                                        <th style="background-color: #eaecf4; color: #4e73df; text-align:center;">Archivo</th>
                                        <th style="background-color: #eaecf4; color: #4e73df; text-align:center;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($iacucResult->num_rows > 0) : ?>
                                        <?php while ($row = $iacucResult->fetch_assoc()) : ?>
                                            <tr>
                                                <td style="background-color: #fff;" data-label="ID"><?= htmlspecialchars($row['iacuc_id']); ?></td>
                                                <td style="background-color: #fff;" data-label="Título"><?= htmlspecialchars($row['iacuc_title']); ?></td>
                                                <td style="background-color: #fff; text-align:center;" data-label="Archivo">
                                                    <?php if ($row['file_url']) : ?>
                                                        <a href="<?= htmlspecialchars($row['file_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-file-download me-1"></i> Ver/Descargar
                                                        </a>
                                                    <?php else : ?>
                                                        <span class="text-muted small">Sin archivo adjunto</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="background-color: #fff; text-align:center;" data-label="Acciones">
                                                    <div class="table-actions">
                                                        <button class="btn btn-warning btn-sm btn-icon" title="Editar" onclick="editIACUC('<?= $row['iacuc_id']; ?>', '<?= htmlspecialchars($row['iacuc_title']); ?>', '<?= htmlspecialchars($row['file_url']); ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form action="manage_iacuc.php" method="post" style="display:inline-block;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="iacuc_id" value="<?= $row['iacuc_id']; ?>">
                                                            <button type="submit" name="delete" class="btn btn-danger btn-sm btn-icon" title="Eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar este registro de IACUC?');">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else : ?>
                                        <tr><td colspan="4" class="text-center text-muted p-4">No se encontraron registros de IACUC cargados.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="page-footer">
        <?php require 'footer.php'; ?>
    </div>

    <script>
        function openForm() {
            document.getElementById('popupOverlay').style.display = 'block';
            document.getElementById('popupForm').style.display = 'block';
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Añadir Nuevo IACUC';
            document.getElementById('addButton').style.display = 'inline-flex';
            document.getElementById('editButton').style.display = 'none';
            document.getElementById('iacuc_id').readOnly = false;
            document.getElementById('iacuc_id').value = '';
            document.getElementById('iacuc_title').value = '';
            document.getElementById('iacuc_file').value = '';
            document.getElementById('existing_file_url').value = '';
            document.getElementById('existingFile').innerHTML = '';
        }

        function closeForm() {
            document.getElementById('popupOverlay').style.display = 'none';
            document.getElementById('popupForm').style.display = 'none';
        }

        function editIACUC(id, title, fileUrl) {
            openForm();
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Editar IACUC';
            document.getElementById('addButton').style.display = 'none';
            document.getElementById('editButton').style.display = 'inline-flex';
            document.getElementById('iacuc_id').readOnly = true;
            document.getElementById('iacuc_id').value = id;
            document.getElementById('iacuc_title').value = title;
            document.getElementById('existing_file_url').value = file
