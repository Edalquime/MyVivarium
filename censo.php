<?php
session_start();
require 'dbcon.php';

// 🔐 Seguridad estándar de tu sitio
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'header.php';

// Forzamos a PHP a mostrar errores si algo falla
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<div class="container mt-4 mb-5">
    <div class="d-flex align-items-center mb-4">
        <h2 class="text-dark"><i class="fas fa-database me-2"></i> Diccionario de Columnas del Bioterio</h2>
    </div>

    <div class="alert alert-info shadow-sm">
        <i class="fas fa-info-circle me-2"></i> Este script lee directamente la estructura del motor MySQL. Despliega las pestañas para ver cómo se llaman las columnas de cada tabla.
    </div>

    <div class="row">
        <div class="col-md-12">

            <?php
            // Lista de tablas que me diste para auditar
            $tablas_a_revisar = [
                'mice', 'litters', 'holding', 'breeding', 'cages', 
                'users', 'usuarios', 'strains', 'iacuc', 'cage_iacuc', 
                'cage_users', 'tasks', 'settings'
            ];

            foreach ($tablas_a_revisar as $tabla) {
                // Consultamos las columnas de la tabla actual
                $query = "SHOW COLUMNS FROM `$tabla`";
                $result = mysqli_query($con, $query);

                if ($result) {
                    ?>
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-table me-2"></i> Tabla: <span class="badge bg-primary fs-6"><?= $tabla ?></span></h6>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover table-sm mb-0 align-middle">
                                <thead class="table-secondary">
                                    <tr>
                                        <th class="ps-3">Nombre de Columna (Field)</th>
                                        <th>Tipo de Dato (Type)</th>
                                        <th>¿Puede ser Nulo? (Null)</th>
                                        <th>Llave (Key)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td class="ps-3 font-monospace fw-bold text-secondary"><?= $row['Field'] ?></td>
                                            <td><code><?= $row['Type'] ?></code></td>
                                            <td><?= $row['Null'] ?></td>
                                            <td>
                                                <?php if ($row['Key'] === 'PRI'): ?>
                                                    <span class="badge bg-danger">Llave Primaria</span>
                                                <?php elseif ($row['Key'] === 'MUL'): ?>
                                                    <span class="badge bg-warning text-dark">Llave Foránea/Índice</span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> No se pudo leer la tabla <strong><?= $tabla ?></strong>. Error: <?= mysqli_error($con) ?>
                    </div>
                    <?php
                }
            }
            ?>

        </div>
    </div>
</div>

<?php 
include 'footer.php'; 
mysqli_close($con);
?>
