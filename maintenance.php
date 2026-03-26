<?php

/**
 * Maintenance Management Script
 * * This script handles the addition of maintenance records, allowing for the selection of cages and optional comments.
 */

// Start a new session or resume the existing session
session_start();

// Include the database connection file
require 'dbcon.php';

// Disable error display in production (errors logged to server logs)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if the user is not logged in, redirect them to index.php with the current URL for redirection after login
if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit; // Exit to ensure no further code is executed
}

// Generate a CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Capture the 'from' query parameter for redirection purposes
$redirectFrom = isset($_GET['from']) ? $_GET['from'] : 'hc_dash';
$_SESSION['redirect_from'] = $redirectFrom;

// Query to retrieve cage IDs
$cageQuery = "SELECT cage_id FROM cages";
$cageResult = $con->query($cageQuery);

// Initialize an array to hold all cage options
$cageOptions = [];
while ($cageRow = $cageResult->fetch_assoc()) {
    $cageOptions[] = htmlspecialchars($cageRow['cage_id']);
}

// Process the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }

    // Retrieve and sanitize form data
    $selectedCages = $_POST['cage_id'];
    $comments = $_POST['comments'];
    $userId = $_SESSION['user_id']; // Assume user session is started and user_id is stored in session

    $stmt = $con->prepare("INSERT INTO maintenance (cage_id, user_id, comments) VALUES (?, ?, ?)");

    foreach ($selectedCages as $index => $cage_id) {
        $comment = !empty($comments[$index]) ? htmlspecialchars($comments[$index]) : null;
        $stmt->bind_param("sis", $cage_id, $userId, $comment);
        $stmt->execute();
    }
    $stmt->close();

    // Set success message and redirect based on the originating page
    $_SESSION['message'] = 'Maintenance records added successfully.';
    $redirectUrl = $redirectFrom === 'bc_dash' ? 'bc_dash.php' : 'hc_dash.php';
    header("Location: $redirectUrl");
    exit();
}

// Include the header file
require 'header.php';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento de Jaulas | <?php echo htmlspecialchars($labName); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

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

        /* Corregir integración Select2 con Bootstrap 5 */
        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
            border-radius: 6px;
            min-height: 45px;
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
        }
    </style>
</head>

<body>
    <div class="page-content">
        <div class="container mt-4 mb-5" style="max-width: 900px;">

            <div class="card main-card">
                <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-tools me-2"></i> Añadir Registro de Mantenimiento</h4>
                    
                    <div class="d-flex gap-2">
                        <a href="javascript:void(0);" onclick="goBack()" class="btn btn-outline-light btn-icon" data-bs-toggle="tooltip" title="Volver atrás">
                            <i class="fas fa-arrow-circle-left"></i>
                        </a>
                        <button type="button" onclick="document.getElementById('maintenanceForm').submit();" class="btn btn-success btn-icon" data-bs-toggle="tooltip" title="Guardar cambios">
                            <i class="fas fa-save"></i>
                        </button>
                    </div>
                </div>

                <div class="card-body bg-light p-4">
                    <form id="maintenanceForm" method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-cubes"></i> Selección de Jaulas
                            </div>

                            <div class="mb-3">
                                <label for="cage_id" class="form-label">Selecciona las Jaulas (Multiselección)</label>
                                <select id="cage_id" name="cage_id[]" multiple="multiple" class="form-control" style="width: 100%;" required>
                                    <?php foreach ($cageOptions as $cage_id) : ?>
                                        <option value="<?php echo $cage_id; ?>"><?php echo $cage_id; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div id="comments-section"></div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary btn-modern">
                                <i class="fas fa-check-circle"></i> Guardar Mantenimiento
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function goBack() {
            window.history.back();
        }

        $(document).ready(function() {
            // Activar Select2
            $('#cage_id').select2({
                placeholder: "Busca o selecciona las jaulas..."
            });

            // Activar tooltips de Bootstrap 5
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
              return new bootstrap.Tooltip(tooltipTriggerEl)
            })

            // Lógica para desplegar las cajas de comentarios dinámicas
            $('#cage_id').on('change', function() {
                let selectedCages = $(this).val();
                let commentsSection = $('#comments-section');
                commentsSection.empty();

                if (selectedCages && selectedCages.length > 0) {
                    
                    // Envoltura visual de la sección
                    commentsSection.append(`
                        <div class="section-card" id="dynamic-comments-card">
                            <div class="section-title">
                                <i class="fas fa-comment-dots"></i> Comentarios Opcionales
                            </div>
                            <div id="comments-list"></div>
                        </div>
                    `);

                    let listContainer = $('#comments-list');

                    selectedCages.forEach(function(cage_id, index) {
                        listContainer.append(`
                            <div class="mb-3">
                                <label for="comments[${index}]" class="form-label">Comentario para la Jaula: <strong>${cage_id}</strong></label>
                                <textarea name="comments[]" class="form-control" placeholder="Escribe un comentario opcional para esta jaula..." rows="2"></textarea>
                                <input type="hidden" name="cage_id_hidden[]" value="${cage_id}">
                            </div>
                        `);
                    });
                }
            });
        });
    </script>
</body>

</html>
<?php mysqli_close($con); ?>
