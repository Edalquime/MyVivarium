<?php
session_start();
require 'dbcon.php';

// 🔐 SEGURIDAD: Solo Administradores
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require 'header.php';

// --- 📊 CONSULTA 1: CENSO TOTAL POR INVESTIGADOR (PI) ---
$query_pi = "
    SELECT pi_name, SUM(cantidad) as total_animales
    FROM (
        -- 1. Ratones en Holding (Mantenimiento)
        SELECT c.pi_name, COUNT(m.id) AS cantidad
        FROM mice m
        INNER JOIN cages c ON m.cage_id = c.cage_id
        GROUP BY c.pi_name

        UNION ALL

        -- 2. Padres en Breeding (Macho + Hembra)
        SELECT c.pi_name, SUM(COALESCE(b.male_n, 0) + COALESCE(b.female_n, 0)) AS cantidad
        FROM breeding b
        INNER JOIN cages c ON b.cage_id = c.cage_id
        GROUP BY c.pi_name

        UNION ALL

        -- 3. Crías vivas en Litters (Bebés)
        SELECT c.pi_name, SUM(COALESCE(l.pups_alive, 0)) AS cantidad
        FROM litters l
        INNER JOIN cages c ON l.cage_id = c.cage_id
        GROUP BY c.pi_name
    ) AS sub_pi
    GROUP BY pi_name
    ORDER BY total_animales DESC
";

$res_pi = mysqli_query($con, $query_pi);


// --- 🧬 CONSULTA 2: CENSO TOTAL POR CEPA (STRAIN / GENOTYPE) ---
$query_strain = "
    SELECT strain_name, SUM(cantidad) as total_animales
    FROM (
        -- 1. Cepas de Ratones en Holding (Usamos Genotype de la tabla mice)
        SELECT m.genotype AS strain_name, COUNT(m.id) AS cantidad
        FROM mice m
        WHERE m.genotype IS NOT NULL AND m.genotype != ''
        GROUP BY m.genotype

        UNION ALL

        -- 2. Cepas en Breeding (Se busca la cepa de la jaula en la tabla cages o breeding)
        SELECT b.strain AS strain_name, SUM(COALESCE(b.male_n, 0) + COALESCE(b.female_n, 0)) AS cantidad
        FROM breeding b
        WHERE b.strain IS NOT NULL AND b.strain != ''
        GROUP BY b.strain

        UNION ALL

        -- 3. Cepas de los Bebés (Litters heredan la cepa de la jaula de cría en breeding)
        SELECT b.strain AS strain_name, SUM(COALESCE(l.pups_alive, 0)) AS cantidad
        FROM litters l
        INNER JOIN breeding b ON l.cage_id = b.cage_id
        WHERE b.strain IS NOT NULL AND b.strain != ''
        GROUP BY b.strain
    ) AS sub_strain
    GROUP BY strain_name
    ORDER BY total_animales DESC
";

$res_strain = mysqli_query($con, $query_strain);
$total_general = 0;
?>

<div class="container mt-4 mb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="font-weight-bold" style="color: #3c4043;"><i class="fas fa-poll me-2"></i> Censo General del Bioterio</h2>
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
        
        <div class="col-lg-7">
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
                                    <th class="text-center font-weight-bold">Animales Totales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($res_pi && mysqli_num_rows($res_pi) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($res_pi)): 
                                        $total_general += $row['total_animales']; ?>
                                        <tr>
                                            <td class="font-weight-bold"><?= htmlspecialchars($row['pi_name']) ?></td>
                                            <td class="text-center table-primary font-weight-bold fs-5"><?= number_format($row['total_animales'], 0) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center text-muted p-4">No hay datos de animales registrados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <td>Gran Total de Animales Vivos en el Bioterio</td>
                                    <td class="text-center font-weight-bold fs-4"><?= number_format($total_general, 0) ?> ratones</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white p-3">
                    <h5 class="mb-0"><i class="fas fa-dna me-2"></i> Inventario por Cepa / Genotipo</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cepa / Genotipo</th>
                                <th class="text-center font-weight-bold">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_strain && mysqli_num_rows($res_strain) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($res_strain)): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['strain_name']) ?></td>
                                        <td class="text-center font-weight-bold"><?= number_format($row['total_animales'], 0) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted p-4">No hay datos de genotipos activos.</td>
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
