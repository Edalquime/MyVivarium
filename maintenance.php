<?php

/**
 * Página de Mantenimiento / Historial
 * * Este archivo muestra el panel de mantenimiento, tareas o historial.
 * Adaptado a la plantilla unificada con Bootstrap 5 para que los menús no se rompan.
 */

// Iniciar o reanudar la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir la conexión a la base de datos
require 'dbcon.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

// Incluir el archivo de cabecera maestro
require 'header.php';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mantenimiento e Historial | <?php echo htmlspecialchars($labName); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- ESTANDARIZACIÓN FLEXBOX PARA EL FOOTER AL FONDO --- */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #f4f6f9;
            font-family: 'Poppins', sans-serif;
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
    </style>
</head>

<body>
    <div class="page-content">
        <div class="container mt-4 mb-5" style="max-width: 1000px;">
            
            <div class="card main-card">
                <div class="card-header bg-dark text-white p-3 d-flex align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-tools me-2"></i> Panel de Mantenimiento</h4>
                </div>

                <div class="card-body bg-light p-4">
                    <div class="section-card mb-0">
                        <div class="section-title">
                            <i class="fas fa-list-alt"></i> Registros de Mantenimiento e Historial
                        </div>
                        
                        <p class="text-muted">Desarrolla aquí el historial de mantenimiento originado desde <?php echo isset($_GET['from']) ? htmlspecialchars($_GET['from']) : 'el panel'; ?>...</p>
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
</body>

</html>
<?php mysqli_close($con); ?>
