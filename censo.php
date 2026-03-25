<?php 
session_start();
require 'dbcon.php';

// 1. Quitamos la seguridad temporalmente
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}
*/

// 2. COMENTAMOS EL HEADER temporalmente para ver si él es quien te bota
// require 'header.php'; 

echo "<h1>¡SI PUEDES VER ESTO, EL PROBLEMA ES EL HEADER.PHP!</h1>";
exit; // Forzamos a que se detenga aquí


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
        
        SELECT s.str_name AS strain_name, (b.male_n + b.female_n) AS total
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

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Censo de Animales | Bioterio</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <style>
        .census-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }

        .census-card {
            background: #fff;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .bg-dark-header {
            background-color: #343a40;
            color: white;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            padding: 15px;
        }
    </style>
</head>

<body class="bg-light">

    <div class="container census-container content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="font-weight-bold" style="color: #3c4043;"><i class="fas fa-poll me-2"></i> Censo General de Animales</h2>
            <button onclick="window.print();" class="btn btn-secondary"><i class="fas fa-print me-1"></i> Imprimir Reporte</button>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="census-card">
                    <div class="bg-dark-header">
                        <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i> Inventario por Investigador (PI)</h5>
                    </div>
                    <div class="p-0">
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
                                    <?php while ($row = mysqli_fetch_assoc($res_pi)):
                                        $total_general += $row['gran_total']; ?>
                                        <tr>
                                            <td class="font-weight-bold"><?= htmlspecialchars($row['pi_name']) ?></td>
                                            <td class="text-center"><?= $row['total_holding'] ?></td>
                                            <td class="text-center"><?= $row['total_breeding'] ?></td>
                                            <td class="text-center table-primary font-weight-bold"><?= $row['gran_total'] ?></td>
                                        </tr>
                                    <?php endwhile; ?>
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
                <div class="census-card">
                    <div class="bg-dark-header">
                        <h5 class="mb-0"><i class="fas fa-dna me-2"></i> Por Cepa / Línea</h5>
                    </div>
                    <div class="p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Cepa</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($res_cepa)): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['strain_name']) ?></td>
                                        <td class="text-center font-weight-bold"><?= $row['total_animales'] ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>

</html>
<?php mysqli_close($con); ?>
