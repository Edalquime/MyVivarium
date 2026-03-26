<?php

/**
 * Script del Panel de Control de Jaulas de Mantenimiento (Holding Cages)
 * * Este script muestra el panel de jaulas de mantenimiento para usuarios autenticados. Incluye funcionalidades
 * como agregar nuevas jaulas, imprimir tarjetas de jaulas, buscar jaulas y paginación. El contenido de la página
 * se carga dinámicamente utilizando JavaScript y AJAX.
 *
 */

// Iniciar una nueva sesión o reanudar la existente
session_start();

// Incluir el archivo de conexión a la base de datos
require 'dbcon.php';

// Verificar si el usuario no ha iniciado sesión, redirigirlo a index.php con la URL actual para redirección después del login
if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit; // Salir para asegurar que no se ejecute más código
}

// Incluir el archivo de cabecera
require 'header.php';
?>

<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        // Inicializar tooltips cuando el documento esté listo
        $(document).ready(function() {
            $('body').tooltip({
                selector: '[data-toggle="tooltip"]'
            });
        });

        // Función de confirmación de eliminación con un cuadro de diálogo
        function confirmDeletion(id) {
            var confirmDelete = confirm("¿Estás seguro de que deseas eliminar la jaula '" + id + "' y los datos de ratones relacionados?");
            if (confirmDelete) {
                window.location.href = "hc_drop.php?id=" + id + "&confirm=true"; // Redirigir al script de eliminación
            }
        }

        // Función para obtener datos dinámicamente vía AJAX
        function fetchData(page = 1, search = '') {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'hc_fetch_data.php?page=' + page + '&search=' + encodeURIComponent(search), true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.tableRows && response.paginationLinks) {
                            document.getElementById('tableBody').innerHTML = response.tableRows; // Insertar filas
                            document.getElementById('paginationLinks').innerHTML = response.paginationLinks; // Insertar paginación
                            document.getElementById('searchInput').value = search; // Preservar entrada de búsqueda

                            // Actualizar la URL con la página actual y la consulta de búsqueda sin recargar la página
                            const newUrl = new URL(window.location.href);
                            newUrl.searchParams.set('page', page);
                            newUrl.searchParams.set('search', search);
                            window.history.replaceState({
                                path: newUrl.href
                            }, '', newUrl.href);
                        } else {
                            console.error('Formato de respuesta inválido:', response);
                        }
                    } catch (e) {
                        console.error('Error parseando la respuesta JSON:', e);
                    }
                } else {
                    console.error('La solicitud falló. Estado:', xhr.status);
                }
            };
            xhr.onerror = function() {
                console.error('La solicitud falló. Ocurrió un error durante la transacción.');
            };
            xhr.send();
        }

        // Función de búsqueda para iniciar la obtención de datos basada en el texto ingresado
        function searchCages() {
            var searchQuery = document.getElementById('searchInput').value;
            fetchData(1, searchQuery);
        }

        // Obtener datos iniciales cuando el contenido del DOM esté cargado
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 1;
            const search = urlParams.get('search') || '';
            fetchData(page, search);
        });

    </script>


    <title>Panel de Jaulas de Mantenimiento | <?php echo htmlspecialchars($labName); ?></title>

    <style>
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

        .search-card {
            background-color: #ffffff;
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.03);
        }

        .table-wrapper {
            margin-bottom: 30px;
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e3e6f0;
        }

        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .table-wrapper th {
            background-color: #eaecf4;
            color: #4e73df;
            font-weight: 700;
            border: 1px solid #e3e6f0;
            padding: 12px 15px;
            text-align: left;
        }

        .table-wrapper td {
            border: 1px solid #e3e6f0;
            padding: 12px 15px;
            vertical-align: middle;
            background-color: #fff;
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

        .action-icons {
            display: flex;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .table-wrapper th,
            .table-wrapper td {
                padding: 12px 8px;
                text-align: center;
            }
            .action-icons {
                justify-content: center;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="page-content">
        <div class="container mt-4 mb-5">
            <?php include('message.php'); ?>

            <div class="card main-card">
                <div class="card-header bg-dark text-white p-3 d-flex flex-column flex-md-row justify-content-between align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <h4 class="mb-0 fs-5"><i class="fas fa-layer-group me-2"></i> Panel de Jaulas de Mantenimiento</h4>
                    
                    <div class="action-icons mt-3 mt-md-0">
                        <a href="hc_addn.php" class="btn btn-primary btn-icon" data-toggle="tooltip" data-placement="top" title="Añadir Nueva Jaula">
                            <i class="fas fa-plus"></i>
                        </a>
                        <a href="hc_slct_crd.php" class="btn btn-success btn-icon" data-toggle="tooltip" data-placement="top" title="Imprimir Tarjeta de Jaula">
                            <i class="fas fa-print"></i>
                        </a>
                        <a href="maintenance.php?from=hc_dash" class="btn btn-warning btn-icon" data-toggle="tooltip" data-placement="top" title="Mantenimiento de Jaulas">
                            <i class="fas fa-wrench"></i>
                        </a>
                    </div>
                </div>


                <div class="card-body bg-light p-4">
                    
                    <div class="search-card">
                        <div class="input-group">
                            <input type="text" id="searchInput" class="form-control" style="height: calc(1.5em + 1rem + 2px); border-radius: 8px 0 0 8px;" placeholder="Buscar por ID de Jaula..." onkeyup="searchCages()">
                            <div class="input-group-append">
                                <button class="btn btn-primary btn-modern" style="border-radius: 0 8px 8px 0;" type="button" onclick="searchCages()">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrapper" id="tableContainer">
                        <table class="table table-hover" id="mouseTable">
                            <thead>
                                <tr>
                                    <th>ID de la Jaula</th>
                                    <th style="width: 1%; white-space: nowrap; text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                </tbody>
                        </table>
                    </div>

                    <nav aria-label="Navegación de páginas">
                        <ul class="pagination justify-content-center" id="paginationLinks">
                            </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="page-footer">
        <?php include 'footer.php'; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>

</html>
