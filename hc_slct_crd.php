<?php

/**
 * Seleccionar Jaulas de Mantenimiento para Imprimir
 * * Este script permite al usuario seleccionar hasta 4 IDs de jaulas de mantenimiento para imprimir sus tarjetas. 
 * Utiliza la biblioteca Select2 para una lista desplegable de selección múltiple mejorada y abre los IDs de 
 * jaula seleccionados en una pestaña nueva para su impresión.
 * */

// Iniciar una nueva sesión o reanudar la existente
session_start();

// Incluir el archivo de conexión a la base de datos
require 'dbcon.php';

// Verificar si el usuario no ha iniciado sesión, redirigirlo a index.php
if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit; // Salir para asegurar que no se ejecute más código
}

// Obtener todos los IDs de jaula distintos de la base de datos
$query = "SELECT DISTINCT `cage_id` FROM holding";
$result = mysqli_query($con, $query);

// Inicializar un array para almacenar los IDs de las jaulas
$cageIds = [];
while ($row = mysqli_fetch_assoc($result)) {
    $cageIds[] = $row['cage_id'];
}

// Incluir el archivo de cabecera
require 'header.php';
?>

<!doctype html>
<html lang="es">

<head>
    <title>Imprimir Tarjetas de Jaulas de Mantenimiento | <?php echo htmlspecialchars($labName); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        /* --- SOLUCIÓN FLEXBOX: Empuja el footer dinámicamente al fondo de la pantalla --- */
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
        /* ---------------------------------------------------------------------------------- */

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

        .select2-container--default .select2-selection--multiple {
            border: 1px solid #d1d3e2 !important;
            border-radius: 8px !important;
            min-height: 45px;
            padding: 5px;
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
        // Validar la selección de IDs de jaula
        function validateSelection() {
            var selectedIds = document.getElementById("cageIds").selectedOptions;
            if (selectedIds.length > 4) {
                alert("Solo puedes seleccionar hasta 4 IDs de jaula por impresión.");
                return false;
            }
            if (selectedIds.length === 0) {
                alert("Por favor, selecciona al menos un ID de jaula.");
                return false;
            }
            return true;
        }

        // Manejar el envío del formulario para abrir una nueva pestaña con los IDs de jaula seleccionados
        function handleSubmit(event, url) {
            event.preventDefault(); // Evitar el envío de formulario por defecto
            if (validateSelection()) {
                var selectedIds = document.getElementById("cageIds").selectedOptions;
                var ids = Array.from(selectedIds).map(option => option.value);
                var queryString = url + "?id=" + ids.join(",");
                window.open(queryString, '_blank'); // Abrir en una pestaña nueva
            }
        }

        // Inicializar Select2 para la lista desplegable de IDs de jaula
        $(document).ready(function() {
            $('#cageIds').select2({
                placeholder: "Selecciona una o varias jaulas...",
                allowClear: true,
                width: '100%'
            });
        });

        // Función para regresar a la página anterior
        function goBack() {
            window.history.back();
        }
    </script>
</head>

<body>
    <div class="page-content">
        <div class="container mt-5 mb-5" style="max-width: 800px;">
            
            <div class="card main-card">
                <div class="card-header bg-dark text-white p-3 d-flex align-items-center justify-content-between" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-print me-2"></i> Imprimir Tarjetas de Jaulas de Mantenimiento</h4>
                    <button type="button" class="btn btn-sm btn-light" onclick="goBack()"><i class="fas fa-arrow-left me-1"></i> Volver</button>
                </div>

                <div class="card-body bg-light p-4">
                    <form>
                        <div class="section-card">
                            <div class="section-title">
                                <i class="fas fa-th-list"></i> Selección de Jaulas
                            </div>
                            
                            <div class="form-group mb-0">
                                <label for="cageIds" class="form-label">IDs de Jaulas (Selecciona hasta 4): <span class="required-asterisk">*</span></label>
                                <select id="cageIds" name="id[]" class="form-control" multiple>
                                    <?php foreach ($cageIds as $cageId) : ?>
                                        <option value="<?= htmlspecialchars($cageId) ?>"><?= htmlspecialchars($cageId) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted mt-2">
                                    <i class="fas fa-info-circle me-1"></i> Puedes elegir múltiples jaulas. Para un diseño óptimo de impresión se limitan a 4 por lote.
                                </small>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-3 mt-4">
                            <button type="button" class="btn btn-outline-secondary btn-modern" onclick="goBack()">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary btn-modern" onclick="handleSubmit(event, 'hc_prnt_crd.php')">
                                <i class="fas fa-print me-1"></i> Imprimir Tarjetas
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
