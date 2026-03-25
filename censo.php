<?php
session_start();
require 'dbcon.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'header.php';

// --- 📊 CONSULTA 1: CENSO POR INVESTIGADOR (PI) ---
$query_pi = "
    SELECT u.name AS pi_name, SUM(cantidad) AS total_animales
    FROM (
        SELECT c.pi_name AS user_id, COUNT(m.id) AS cantidad
        FROM mice m
        INNER JOIN cages c ON m.cage_id = c.cage_id
        GROUP BY c.pi_name

        UNION ALL

        SELECT c.pi_name AS user_id, SUM(COALESCE(b.male_n, 0) + COALESCE(b.female_n, 0)) AS cantidad
        FROM breeding b
        INNER JOIN cages c ON b.cage_id = c.cage_id
        GROUP BY c.pi_name

        UNION ALL

        SELECT c.pi_name AS user_id, SUM(COALESCE(l.pups_alive, 0)) AS cantidad
        FROM litters l
        INNER JOIN cages c ON l.cage_id = c.cage_id
        GROUP BY c.pi_name
    ) AS unificado
    INNER JOIN users u ON unificado.user_id = u.id
    GROUP BY u.id, u.name
    ORDER BY total_animales DESC
";

$res_pi = mysqli_query($con, $query_pi);


// --- 🧬 CONSULTA 2: CENSO POR CEPA (SEPARADO ADULTOS Y PUPS) ---
$query_strain = "
    SELECT s.str_name AS strain_name, 
           SUM(adultos) AS total_adultos, 
           SUM(pups) AS total_pups,
           (SUM(adultos) + SUM(pups)) AS total_cepa
    FROM (
        -- 1. Adultos en Holding (Contamos los ratones individuales de la tabla mice)
        SELECT h.strain AS str_id, COUNT(m.id) AS adultos, 0 AS pups
        FROM mice m
        INNER JOIN holding h ON m.cage_id = h.cage_id
        GROUP BY h.strain

        UNION ALL

        -- 2. Adultos en Breeding (Sumamos machos + hembras de la tabla breeding)
        SELECT b.cross AS str_id, SUM(COALESCE(b.male_n, 0) + COALESCE(b.female_n, 0)) AS adultos, 0 AS pups
        FROM breeding b
        GROUP BY b.cross

        UNION ALL

        -- 3. Crías en Litters (Sumamos pups_alive de la tabla litters)
        SELECT b.cross AS str_id, 0 AS adultos, SUM(COALESCE(l.pups_alive, 0)) AS pups
        FROM litters l
        INNER JOIN breeding b ON l.cage_id = b.cage_id
        GROUP BY b.cross
    ) AS unificado_cepa
    INNER JOIN strains s ON unificado_cepa.str_id = s.str_id
    GROUP BY s.str_id, s.str_name
    ORDER BY total_cepa DESC
";

$res_strain = mysqli_query($con, $query_strain);
$total_general = 0;
?>

<div class="container mt-4 mb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-secondary"><i class="fas fa-chart-pie me-2"></i> Censo General del Bioterio</h2>
        <div>
            <button onclick="window.print();" class="btn btn-secondary">
                <i class="fas fa-print me-1"></i> Imprimir Reporte
            </button>
        </div>
    </div>

    <div class="row">
        
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white p-3">
                    <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i> Por Investigador (PI)</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Investigador Principal</th>
                                <th class="text-center">Total Animales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_pi && mysqli_num_rows($res_pi) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($res_pi)): 
                                    $total_general += $row['total_animales']; ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($row['pi_name']) ?></td>
                                        <td class="text-center table-primary fw-bold"><?= number_format($row['total_animales'], 0) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted p-4">No se encontraron animales activos.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <td>Gran Total del Bioterio</td>
                                <td class="text-center fw-bold fs-5"><?= number_format($total_general, 0) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white p-3">
                    <h5 class="mb-0"><i class="fas fa-dna me-2"></i> Por Cepa (Adultos y Crías)</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cepa</th>
                                <th class="text-center">Adultos</th>
                                <th class="text-center">Crías (Pups)</th>
                                <th class="text-center fw-bold">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_strain && mysqli_num_rows($res_strain) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($res_strain)): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($row['strain_name']) ?></td>
                                        <td class="text-center"><?= number_format($row['total_adultos'], 0) ?></td>
                                        <td class="text-center"><?= number_format($row['total_pups'], 0) ?></td>
                                        <td class="text-center table-warning fw-bold"><?= number_format($row['total_cepa'], 0) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted p-4">No hay cepas activas computadas.</td>
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
include 'footer.php'; 
mysqli_close($con);
?>
