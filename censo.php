<?php
/**
 * Censo General de Animales
 * * Genera un recuento de animales totales sumando Holding y Breeding.
 * Accesible solo para usuarios con rol 'admin'.
 */

// Iniciamos sesión antes de cualquier salida de texto
session_start();

require 'dbcon.php';

// 🔐 SEGURIDAD: Solo Administradores (Estandarizado con manage_users.php)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Llamamos al header una vez pasada la prueba de seguridad
require 'header.php';

// --- 📊 CONSULTA 1: CENSO TOTAL POR P.I. (Investigador Principal) ---
$query_pi = "
    SELECT u.name AS pi_name, 
           SUM(COALESCE(h_counts.total_mice, 0)) AS total_holding,
           SUM(COALESCE(b_counts.total_breeding, 0)) AS total_breeding,
           (SUM(COALESCE(h_counts.total_mice, 0)) + SUM(COALESCE(b_counts.total_breeding, 0))) AS gran_total
    FROM users u
    LEFT JOIN (
        SELECT c.pi_name, COUNT(m.mouse_id) AS total_mice
        FROM cages c
        INNER JOIN mice m ON c.cage_id = m.cage_id
        GROUP BY c.pi_name
    ) h_counts ON u.id = h_counts.pi_name
    LEFT JOIN (
        SELECT c.pi_name, 
               SUM(COALESCE(b.male_n, 0) + COALESCE(b.female_n, 0) + COALESCE(l_counts.alive_pups, 0)) AS total_breeding
        FROM cages c
        INNER JOIN breeding b ON c.cage_id = b.cage_id
        LEFT JOIN (
            SELECT cage_id, SUM(pups_alive) AS alive_pups 
            FROM litters 
            GROUP BY cage_id
        ) l_counts ON b.cage_id = l_counts.cage_id
        GROUP BY c.pi_name
    ) b_counts ON u.id = b_counts.pi_name
    WHERE u.id IN (SELECT DISTINCT pi_name FROM cages)
    GROUP BY u.id, u.name
    ORDER BY gran_total DESC
";

$res_pi = mysqli_query($con, $query_pi);


// --- 🧬 CONSULTA 2: CENSO TOTAL POR CEPA ---
$query_cepa = "
    SELECT strain_name, SUM(total) as total_animales 
    FROM (
        SELECT s.str_name AS strain_name, COUNT(m.mouse_id) AS total
        FROM mice m
        INNER JOIN holding h ON m.cage_id = h.cage_id
        INNER JOIN strains s ON h.strain = s.str_id
        GROUP BY s.str_name
        
        UNION ALL
        
        SELECT s.str_name AS strain_name, (COALESCE(b.male_n, 0) + COALESCE(b.female_n, 0)) AS total
        FROM breeding b
        INNER JOIN strains s ON b.strain = s.str_id
        
        UNION ALL
        
        SELECT s.str_name AS strain_name, l.pups_alive AS total
        FROM litters l
        INNER JOIN breeding b ON l.cage_id = b.cage_id
        INNER JOIN strains s ON b.strain = s.str_id
    ) combined
    GROUP BY strain_name
    ORDER BY total_animales DESC
";

$res_cepa = mysqli_query($con, $query_cepa);
$total_general = 0;
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="font-weight-bold" style="color: #3c4043;"><i class="fas fa-poll me-2"></i> Censo General de Animales</h2>
        <div>
            <a href="descargar_censo.php" class="btn btn-success me-2">
                <i class="fas fa-file-csv me-1"></i> Descargar Excel (CSV)
            </a>
            <button onclick="window.print();" class="btn btn-secondary">
                <i class="fas fa-print me-1"></i> Imprimir Reporte
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white p-3">
                    <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i> Inventario por Investigador (PI)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Investigador Principal</th>
                                    <th class="text-center">Holding</th>
                                    <th class="text-center">Breeding (Adultos + Crías)</th>
                                    <th class="text-center font-weight-bold">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($res_pi) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($res_pi)): 
                                        $total_general += $row['gran_total']; ?>
                                        <tr>
                                            <td class="font-weight-bold"><?= htmlspecialchars($row['pi_name']) ?></td>
                                            <td class="text-center"><?= $row['total_holding'] ?></td>
                                            <td class="text-center"><?= $row['total_breeding'] ?></td>
                                            <td class="text-center table-primary font-weight-bold"><?= $row['gran_total'] ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted p-4">No se encontraron datos registrados de jaulas activas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <td>Gran Total de Animales Vivos</td>
                                    <td colspan="3" class="text-end font-weight-bold fs-5" style="padding-right: 2rem;"><?= $total_general ?> ratones</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white p-3">
                    <h5 class="mb-0"><i class="fas fa-dna me-2"></i> Por Cepa / Línea</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cepa</th>
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($res_cepa) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($res_cepa)): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['strain_name']) ?></td>
                                        <td class="text-center font-weight-bold"><?= $row['total_animales'] ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted p-4">No hay cepas con animales vivos.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Incluimos footer y cerramos conexión
include 'footer.php'; 
mysqli_close($con);
?>
