<?php

/**
 * Breeding Cage Printable Card Script
 * Formato 2x2 en español blindado con grosores de línea unificados al milímetro.
 */

session_start();

ini_set('display_errors', 0); 

require 'dbcon.php';

if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit;
}

$labQuery = "SELECT value FROM settings WHERE name = 'url' LIMIT 1";
$labResult = mysqli_query($con, $labQuery);

$url = "";
if ($row = mysqli_fetch_assoc($labResult)) {
    $url = $row['value'];
}

if (isset($_GET['id'])) {
    $ids = explode(',', $_GET['id']);
    $breedingcages = [];

    foreach ($ids as $id) {
        $query = "SELECT b.*, c.remarks AS remarks, pi.name AS pi_name, s.str_name, s.str_aka
                  FROM breeding b
                  LEFT JOIN cages c ON b.cage_id = c.cage_id
                  LEFT JOIN users pi ON c.pi_name = pi.id
                  LEFT JOIN strains s ON b.strain = s.str_id
                  WHERE b.cage_id = ?";
        
        $stmt = $con->prepare($query);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $breedingcage = $result->fetch_assoc();

            $query1 = "SELECT * FROM litters WHERE cage_id = ? ORDER BY litter_dob DESC LIMIT 5";
            $stmt1 = $con->prepare($query1);
            $stmt1->bind_param("s", $id);
            $stmt1->execute();
            $result1 = $stmt1->get_result();
            $litters = [];
            while ($litter = $result1->fetch_assoc()) {
                $litters[] = $litter;
            }
            $breedingcage['litters'] = $litters;

            $contactData = getUsersContactByCageId($con, $id);
            $breedingcage['user_initials'] = $contactData['initials'];
            $breedingcage['contact_phone'] = $contactData['phones'];
            $breedingcage['contact_email'] = $contactData['emails'];

            $breedingcages[] = $breedingcage;
        }
    }
} else {
    header("Location: bc_dash.php");
    exit();
}

function getUsersContactByCageId($con, $cageId) {
    $query = "SELECT u.initials, u.username, u.phone 
              FROM users u 
              INNER JOIN cage_users cu ON u.id = cu.user_id 
              WHERE cu.cage_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $cageId);
    $stmt->execute();
    $result = $stmt->get_result();

    $initials = []; $emails = []; $phones = [];

    while ($row = $result->fetch_assoc()) {
        if (!empty($row['initials'])) $initials[] = htmlspecialchars($row['initials']);
        if (!empty($row['username'])) $emails[] = htmlspecialchars($row['username']);
        if (!empty($row['phone'])) $phones[] = htmlspecialchars($row['phone']);
    }
    $stmt->close();

    return [
        'initials' => !empty($initials) ? implode(', ', $initials) : 'N/A',
        'emails' => !empty($emails) ? implode(', ', $emails) : 'N/A',
        'phones' => !empty($phones) ? implode(', ', $phones) : 'N/A'
    ];
}

function getIacucIdsByCageId($con, $cageId) {
    $query = "SELECT i.iacuc_id FROM cage_iacuc ci
              LEFT JOIN iacuc i ON ci.iacuc_id = i.iacuc_id
              WHERE ci.cage_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $cageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $iacucIds = [];
    while ($row = $result->fetch_assoc()) {
        $iacucIds[] = $row['iacuc_id'];
    }
    $stmt->close();
    return implode(', ', $iacucIds);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Impresión de Tarjetas Exactas 2x2</title>
    <style>
        @page {
            size: letter landscape;
            margin: 0;
        }

        * {
            box-sizing: border-box !important;
        }

        body, html {
            margin: 0;
            padding: 0;
            width: 11in;
            height: 8.5in;
            font-family: Arial, Helvetica, sans-serif;
            background-color: #fff;
        }

        .page-container {
            display: flex;
            flex-wrap: wrap;
            width: 11in;
            height: 8.5in;
            align-content: flex-start;
        }

        .card-slot {
            width: 5.5in;
            height: 4.25in;
            border: 1px dashed #D3D3D3; 
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 0;
        }

        /* 📏 UNIFICACIÓN DE BORDES (Todo a 1px solid black) */
        .actual-card {
            width: 5in;
            height: 3in;
            border-collapse: collapse; /* Colapsa bordes dobles */
            table-layout: fixed;
            border: 1px solid black; /* Borde exterior idéntico al interior */
        }

        .actual-card td {
            border: 1px solid black; /* Bordes de celdas a 1px */
            padding: 3px;
            vertical-align: top;
            font-size: 8pt;
            word-wrap: break-word;
            overflow: hidden;
        }

        .title-span {
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
        }

        .label-span {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5pt;
        }

        .value-span {
            font-size: 8pt;
        }

        .litters-table {
            width: 100%;
            height: 1.5in;
            border-collapse: collapse; /* Colapsa bordes dobles de la tabla de partos */
            table-layout: fixed;
            border: 1px solid black; /* Cierra el marco exterior de la tabla de abajo */
            border-top: none; /* Evita encimar la línea con la tabla superior */
        }

        .litters-table td {
            border: 1px solid black; /* Bordes internos a 1px */
            text-align: center;
            vertical-align: middle;
            font-size: 7.5pt;
            height: calc(1.5in / 6);
            padding: 2px;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .card-slot {
                border: 1px dashed #ccc; 
            }
        }
    </style>
</head>
<body>

<div class="page-container">
    <?php foreach ($breedingcages as $breedingcage) : ?>
        <div class="card-slot">
            
            <div style="width: 5in; height: 3in; display: flex; flex-direction: column;">
                
                <table class="actual-card" style="height: 1.5in;">
                    <tr>
                        <td colspan="3" style="text-align:center; height: 25px; vertical-align:middle; background-color: #f2f2f2;">
                            <span class="title-span">JAULA DE CRUCE # <?= htmlspecialchars($breedingcage["cage_id"]) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 40%;">
                            <span class="label-span">PI:</span> 
                            <span class="value-span"><?= htmlspecialchars($breedingcage["pi_name"] ?? 'N/A'); ?></span>
                        </td>
                        <td style="width: 40%;">
                            <span class="label-span">Cepa:</span> 
                            <span class="value-span">
                                <?php 
                                    $strainDisplay = htmlspecialchars($breedingcage['str_name'] ?? 'N/A');
                                    if(!empty($breedingcage['str_aka'])) {
                                        $strainDisplay .= " [" . htmlspecialchars($breedingcage['str_aka']) . "]";
                                    }
                                    echo $strainDisplay;
                                ?>
                            </span>
                        </td>
                        <td rowspan="4" style="width: 20%; text-align:center; vertical-align:middle;">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=65x65&data=https://<?= $url ?>/bc_view.php?id=<?= $breedingcage["cage_id"] ?>&choe=UTF-8" alt="QR">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="label-span">IACUC:</span> 
                            <span class="value-span"><?= htmlspecialchars(getIacucIdsByCageId($con, $breedingcage['cage_id'])); ?></span>
                        </td>
                        <td>
                            <span class="label-span">Fecha Cruce:</span> 
                            <span class="value-span"><?= htmlspecialchars($breedingcage["cross"]); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="label-span">Teléfono:</span> 
                            <span class="value-span" style="font-size: 7pt;"><?= htmlspecialchars($breedingcage["contact_phone"]) ?></span>
                        </td>
                        <td>
                            <span class="label-span">Email:</span> 
                            <span class="value-span" style="font-size: 6.5pt; word-break: break-all;"><?= htmlspecialchars($breedingcage["contact_email"]) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="label-span">ID Macho (<?= $breedingcage["male_n"] ?? 1 ?>):</span> 
                            <span class="value-span"><?= htmlspecialchars($breedingcage["male_id"]) ?></span><br>
                            <span class="label-span">Nac:</span> <span class="value-span"><?= htmlspecialchars($breedingcage["male_dob"]) ?></span>
                        </td>
                        <td>
                            <span class="label-span">ID Hembra (<?= $breedingcage["female_n"] ?? 1 ?>):</span> 
                            <span class="value-span"><?= htmlspecialchars($breedingcage["female_id"]) ?></span><br>
                            <span class="label-span">Nac:</span> <span class="value-span"><?= htmlspecialchars($breedingcage["female_dob"]) ?></span>
                        </td>
                    </tr>
                </table>

                <table class="litters-table">
                    <tr style="background-color: #f2f2f2; font-weight: bold;">
                        <td style="width: 32%;"><span class="label-span">Fecha Nac.</span></td>
                        <td style="width: 17%;"><span class="label-span">Vivos</span></td>
                        <td style="width: 17%;"><span class="label-span">Muertos</span></td>
                        <td style="width: 17%;"><span class="label-span">Machos</span></td>
                        <td style="width: 17%;"><span class="label-span">Hembras</span></td>
                    </tr>
                    <?php for ($i = 0; $i < 5; $i++) : ?>
                        <tr>
                            <td><?= isset($breedingcage['litters'][$i]['litter_dob']) ? htmlspecialchars($breedingcage['litters'][$i]['litter_dob']) : '' ?></td>
                            <td><?= isset($breedingcage['litters'][$i]['pups_alive']) ? htmlspecialchars($breedingcage['litters'][$i]['pups_alive']) : '' ?></td>
                            <td><?= isset($breedingcage['litters'][$i]['pups_dead']) ? htmlspecialchars($breedingcage['litters'][$i]['pups_dead']) : '' ?></td>
                            <td><?= isset($breedingcage['litters'][$i]['pups_male']) ? htmlspecialchars($breedingcage['litters'][$i]['pups_male']) : '' ?></td>
                            <td><?= isset($breedingcage['litters'][$i]['pups_female']) ? htmlspecialchars($breedingcage['litters'][$i]['pups_female']) : '' ?></td>
                        </tr>
                    <?php endfor; ?>
                </table>

            </div>

        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
