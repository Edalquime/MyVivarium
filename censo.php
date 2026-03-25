<?php
session_start();
require 'dbcon.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'header.php';

// --- 📊 CONSULTA 1: CENSO POR INVESTIGADOR (P.I.) ---
// Viajamos de users -> cages -> mice usando cage_id
$query_pi = "
    SELECT u.name AS pi_name, 
           COUNT(m.id) AS total_mice
    FROM users u
    LEFT JOIN cages c ON u.id = c.pi_name
    LEFT JOIN mice m ON c.cage_id = m.cage_id
    WHERE c.cage_id IS NOT NULL
    GROUP BY u.id, u.name
    ORDER BY total_mice DESC
";

$res_pi = mysqli_query($con, $query_pi);


// --- 🧬 CONSULTA 2: CENSO POR GENOTIPO / CEPA ---
// Como la tabla mice tiene 'genotype', agruparemos por genotipo de ratón.
$query_genotype = "
    SELECT genotype, COUNT(id) AS total
    FROM mice
    WHERE genotype IS NOT NULL AND genotype != ''
    GROUP BY genotype
    ORDER BY total DESC
";

$res_genotype = mysqli_query($con, $query_genotype);
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
                                    <th class="text-center font-weight-bold">Total Ratones en Mantenimiento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($res_pi) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($res_pi)): 
                                        $total_general += $row['total_mice']; ?>
                                        <tr>
                                            <td class="font-weight-bold"><?= htmlspecialchars($row['pi_name']) ?></td>
                                            <td class="text-center table-primary font-weight-bold"><?= $row['total_mice'] ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center text-muted p-4">No se encontraron datos de jaulas activas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <td>Gran Total de Ratones Contados</td>
                                    <td class="text-end font-weight-bold fs-5" style="padding-right: 2rem;"><?= $total_general ?> ratones</td>
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
                    <h5 class="mb-0"><i class="fas fa-dna me-2"></i> Por Genotipo</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Genotipo</th>
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($res_genotype) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($res_genotype)): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['genotype']) ?></td>
                                        <td class="text-center font-weight-bold"><?= $row['total'] ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted p-4">No hay genotipos registrados.</td>
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
