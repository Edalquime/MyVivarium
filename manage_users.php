<?php

/**
 * Página de Gestión de Usuarios
 * * Este script proporciona funcionalidad para que un administrador gestione usuarios, incluyendo aprobar, poner en pendiente, eliminar usuarios
 * y cambiar roles de usuario. También incluye protección CSRF y mejoras de seguridad de sesión.
 */

// Iniciar una nueva sesión o reanudar la existente
session_start();

// Incluir el archivo de conexión a la base de datos
require 'dbcon.php';

// Verificar si el usuario ha iniciado sesión y tiene el rol de administrador
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirigir a usuarios no administradores a la página de inicio
    header("Location: index.php");
    exit; // Asegurar que no se ejecute más código
}

// Generación de token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Manejar solicitudes POST para actualizaciones de estado y rol de usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('La validación del token CSRF falló');
    }

    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

    // Inicializar variables de consulta
    $query = "";

    // Determinar la acción a tomar: aprobar, poner en pendiente, eliminar usuario, establecer rol como administrador o usuario
    switch ($action) {
        case 'approve':
            $query = "UPDATE users SET status='approved' WHERE username=?";
            break;
        case 'pending':
            $query = "UPDATE users SET status='pending' WHERE username=?";
            break;
        case 'delete':
            $query = "DELETE FROM users WHERE username=?";
            break;
        case 'admin':
            $query = "UPDATE users SET role='admin' WHERE username=?";
            break;
        case 'user':
            $query = "UPDATE users SET role='user' WHERE username=?";
            break;
        default:
            die('Acción inválida');
    }

    // Ejecutar la sentencia preparada si se establece una acción válida
    if (!empty($query)) {
        $statement = mysqli_prepare($con, $query);
        if ($statement) {
            mysqli_stmt_bind_param($statement, "s", $username);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);
            $_SESSION['message'] = "¡Usuario actualizado correctamente!";
        } else {
            // Registrar error y manejarlo con gracia
            error_log("Error de base de datos: " . mysqli_error($con));
            die('Error de base de datos');
        }
    }
}

// Obtener todos los usuarios de la base de datos
$userquery = "SELECT * FROM users";
$userresult = mysqli_query($con, $userquery);

// Incluir el archivo de cabecera
require 'header.php';
mysqli_close($con);
?>

<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de Usuarios | <?php echo htmlspecialchars($labName); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

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
        /* ----------------------------------------------------- */

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

        .table-responsive {
            border-radius: 8px;
            border: 1px solid #e3e6f0;
        }

        .table-hover tbody tr:hover {
            background-color: #f8f9fc;
        }

        .table th {
            background-color: #eaecf4;
            color: #4e73df;
            font-weight: 700;
            border-bottom: 2px solid #e3e6f0;
            text-align: center;
            vertical-align: middle;
        }

        .table td {
            vertical-align: middle;
            background-color: #ffffff;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
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
            font-size: 14px;
        }

        @media (max-width: 576px) {
            .table th,
            .table td {
                display: block;
                width: 100%;
            }

            .table thead {
                display: none;
            }

            .table tr {
                margin-bottom: 15px;
                border-bottom: 1px solid #dee2e6;
            }

            .table td {
                text-align: right;
                position: relative;
                padding-left: 50%;
            }

            .table td::before {
                content: attr(data-label);
                font-weight: bold;
                text-transform: uppercase;
                margin-bottom: 5px;
                display: block;
                position: absolute;
                left: 15px;
                top: 50%;
                transform: translateY(-50%);
            }

            .action-buttons {
                justify-content: flex-end;
            }
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
                <div class="card-header bg-dark text-white p-3 d-flex align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-users-cog me-2"></i> Gestión de Usuarios del Sistema</h4>
                </div>

                <div class="card-body bg-light p-4">
                    <div class="section-card mb-0">
                        <div class="section-title">
                            <i class="fas fa-list-ul"></i> Lista de Cuentas Registradas
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Correo Electrónico</th>
                                        <th style="width: 10%; text-align: center;">Estado</th>
                                        <th style="width: 10%; text-align: center;">Rol</th>
                                        <th style="width: 1%; white-space: nowrap; text-align: center;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($userresult)) { ?>
                                        <tr>
                                            <td style="background-color: #fff;" data-label="Nombre"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td style="background-color: #fff;" data-label="Correo"><?php echo htmlspecialchars($row['username']); ?></td>
                                            <td style="background-color: #fff; text-align: center;" data-label="Estado">
                                                <span class="badge bg-<?= $row['status'] === 'approved' ? 'success' : 'secondary'; ?>">
                                                    <?= $row['status'] === 'approved' ? 'Aprobado' : 'Pendiente'; ?>
                                                </span>
                                            </td>
                                            <td style="background-color: #fff; text-align: center;" data-label="Rol">
                                                <span class="badge bg-<?= $row['role'] === 'admin' ? 'danger' : 'info'; ?> text-dark">
                                                    <?= htmlspecialchars($row['role']); ?>
                                                </span>
                                            </td>
                                            <td style="background-color: #fff;" data-label="Acciones">
                                                <form action="manage_users.php" method="post" class="action-buttons" onsubmit="return confirmAdminAction('<?php echo htmlspecialchars($row['username']); ?>')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($row['username']); ?>">

                                                    <?php if ($row['status'] === 'pending') { ?>
                                                        <button type="submit" class="btn btn-success btn-sm btn-icon" name="action" value="approve" title="Aprobar Usuario" data-bs-toggle="tooltip">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php } elseif ($row['status'] === 'approved') { ?>
                                                        <button type="submit" class="btn btn-secondary btn-sm btn-icon" name="action" value="pending" title="Suspender Usuario" data-bs-toggle="tooltip">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <?php if ($row['role'] === 'user') { ?>
                                                        <button type="submit" class="btn btn-warning btn-sm btn-icon" name="action" value="admin" title="Hacer Administrador" data-bs-toggle="tooltip">
                                                            <i class="fas fa-user-shield"></i>
                                                        </button>
                                                    <?php } elseif ($row['role'] == 'admin') { ?>
                                                        <button type="submit" class="btn btn-info btn-sm btn-icon" name="action" value="user" title="Hacer Usuario Estándar" data-bs-toggle="tooltip">
                                                            <i class="fas fa-user"></i>
                                                        </button>
                                                    <?php } ?>

                                                    <button type="submit" class="btn btn-danger btn-sm btn-icon" name="action" value="delete" title="Eliminar Usuario" onclick="return confirm('¿Estás seguro de que deseas eliminar permanentemente a este usuario?');" data-bs-toggle="tooltip">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-footer">
        <?php include 'footer.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Inicializar tooltips interactivos de Bootstrap 5
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        var currentAdminUsername = "<?php echo htmlspecialchars($_SESSION['username']); ?>";

        function confirmAdminAction(username) {
            if (username === currentAdminUsername) {
                return confirm("¿Estás seguro de que deseas cambiar la configuración de tu propia cuenta?");
            }
            return true;
        }
    </script>
</body>

</html>
