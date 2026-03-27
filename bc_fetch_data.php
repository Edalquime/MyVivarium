<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Jaulas de Cruce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fc;
        }
        .main-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.05);
            background-color: #fff;
        }
        .btn-icon {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 6px;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="card main-card">
            <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h4 class="mb-0 fs-5"><i class="fas fa-paw me-2"></i> Jaulas de Cruce (Breeding)</h4>
            </div>

            <div class="card-body p-4">
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Escribe el ID de la jaula para buscar..." autocomplete="off">
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID de Jaula</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-center mt-4">
                    <ul class="pagination mb-0" id="paginationLinks">
                        </ul>
                </div>

            </div>
        </div>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // --- ⚙️ MOTOR DE BÚSQUEDA EN TIEMPO REAL (DEBOUNCE) ---
        let timerDeBusqueda; 

        document.getElementById('searchInput').addEventListener('input', function() {
            const queryBusqueda = this.value;

            // Borramos el reloj previo para que no haga peticiones por cada letra tecleada
            clearTimeout(timerDeBusqueda);

            // Esperamos 400 milisegundos de inactividad de teclado antes de ir a buscar a la base de datos
            timerDeBusqueda = setTimeout(function() {
                cargarDatos(1, queryBusqueda);
            }, 400); 
        });

        // --- 📡 FUNCIÓN FETCH AJAX PARA LLAMAR AL PHP ---
        function cargarDatos(pagina, busqueda = '') {
            // Reemplaza "buscar_breeding.php" por el nombre real de tu archivo de paginación PHP
            const scriptPHP = 'buscar_breeding.php'; 
            const urlCompleta = `${scriptPHP}?page=${pagina}&search=${encodeURIComponent(busqueda)}`;

            fetch(urlCompleta)
                .then(respuesta => respuesta.json())
                .then(datosRecibidos => {
                    // Actualizamos la tabla visualmente sin recargar la web
                    document.getElementById('tableBody').innerHTML = datosRecibidos.tableRows;
                    document.getElementById('paginationLinks').innerHTML = datosRecibidos.paginationLinks;
                })
                .catch(error => {
                    console.error('Error procesando la búsqueda:', error);
                });
        }

        // Cargar los primeros datos apenas se abre la pantalla
        document.addEventListener("DOMContentLoaded", function() {
            cargarDatos(1);
        });

        // Función de confirmación en español para eliminar (Manejada en tu PHP)
        function confirmDeletion(cageId) {
            if (confirm("¿Estás seguro de que deseas eliminar permanentemente la jaula " + cageId + "?")) {
                window.location.href = "bc_delete.php?id=" + encodeURIComponent(cageId);
            }
        }
    </script>
</body>

</html>
