<?php

/**
 * Printable Holding Cage Cards
 * Genera la vista de impresión 2x2 para jaulas de mantenimiento (Holding).
 * Ahora incluye los datos de contacto unificados y el diseño alineado.
 */

session_start();

// Cambiar a 1 temporalmente si necesitas depurar errores
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
    $holdingcages = [];

    foreach ($ids as $id) {
        $id = mysqli_real_escape_string($con, $id); 

        $query = "SELECT h.*, pi.name AS pi_name, c.quantity as qty, h.dob, h.sex, h.parent_cg, s.str_name
                  FROM holding h
                  LEFT JOIN cages c ON h.cage_id = c.cage_id
                  LEFT JOIN users pi ON c.pi_name = pi.id
                  LEFT JOIN strains s ON h.strain = s.str_id
                  WHERE h.cage_id = ?";
        
        $stmt = $con->prepare($query);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $holdingcage = $result->fetch_assoc();

            // Ratones (Mice)
            $mouseQuery = "SELECT mouse_id, genotype FROM mice WHERE cage_id = ? LIMIT 5";
            $stmtMouse = $con->prepare($mouseQuery);
            $stmtMouse->bind_param("s", $id);
            $stmtMouse->execute();
            $mouseResult = $stmtMouse->get_result();
            $mouseData = mysqli_fetch_all($mouseResult, MYSQLI_ASSOC);
            $stmtMouse->close();

            // IACUC
            $iacucQuery = "SELECT GROUP_CONCAT(i.iacuc_id SEPARATOR ', ') AS iacuc_ids
                           FROM cage_iacuc ci
                           JOIN iacuc i ON ci.iacuc_id = i.iacuc_id
                           WHERE ci.cage_id = ?";
            $stmtIacuc = $con->prepare($iacucQuery);
            $stmtIacuc->bind_param("s", $id);
            $stmtIacuc->execute();
            $iacucResult = $stmtIacuc->get_result();
            $iacucRow = mysqli_fetch_assoc($iacucResult);
            $holdingcage['iacuc'] = $iacucRow['iacuc_ids'] ?? 'N/A';
            $stmtIacuc->close();

            // Datos de contacto
            $contactData = getUsersContactByCageId($con, $id);
            $holdingcage['user_initials'] = $contactData['initials'];
            $holdingcage['contact_phone'] = $contactData['phones'];
            $holdingcage['contact_email'] = $contactData['emails'];

            $holdingcage['mice'] = $mouseData;
            $holdingcages[] = $holdingcage;

        } else {
            $_SESSION['message'] = "Invalid ID: $id";
            header("Location: hc_dash.php");
            exit();
        }
        $stmt->close(); // Cerrar el statement base dentro del bucle
    }
} else {
    $_SESSION['message'] = 'ID parameter is missing.';
    header("Location: hc_dash.php");
    exit();
}


function getUsersContactByCageId($con, $cageId)
{
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
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Impresión Tarjetas de Mantenimiento 2x2</title>
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

        /* 📏 TABLA ÚNICA DE 3.0"x5.0" */
        .actual-card {
            width: 5in;
            height: 3in;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid black;
        }

        .actual-card td {
            border: 1px solid black;
            padding: 4px;
            vertical-align: middle;
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
    <?php foreach ($holdingcages as $holdingcage) : ?>
        <div class="card-slot">
            
            <table class="actual-card">
                <tr>
                    <td colspan="5" style="text-align:center; height: 32px; background-color: #f2f2f2;">
                        <span class="title-span">Holding Cage # <?= htmlspecialchars($holdingcage["cage_id"]) ?></span>
                    </td>
                </tr>

                <tr>
                    <td colspan="2" style="width: 40%; height: 35px;">
                        <span class="label-span">PI Name:</span> 
                        <span class="value-span"><?= htmlspecialchars($holdingcage["pi_name"] ?? 'N/A'); ?></span>
                    </td>
                    <td colspan="2" style="width: 40%;">
                        <span class="label-span">Strain:</span> 
                        <span class="value-span"><?= htmlspecialchars($holdingcage["str_name"] ?? 'N/A'); ?></span>
                    </td>
                    <td rowspan="4" style="width: 20%; text-align:center; padding: 2px;">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=https://<?= $url ?>/hc_view.php?id=<?= $holdingcage["cage_id"] ?>&choe=UTF-8" alt="QR" style="display:block; margin: 0 auto; max-width: 90px; height: 90px;">
                    </td>
                </tr>

                <tr>
                    <td colspan="2" style="height: 35px;">
                        <span class="label-span">IACUC:</span> 
                        <span class="value-span"><?= htmlspecialchars($holdingcage["iacuc"]); ?></span>
                    </td>
                    <td colspan="2">
                        <span class="label-span">User (Initials):</span> 
                        <span class="value-span"><?= $holdingcage['user_initials']; ?></span>
                    </td>
                </tr>

                <tr>
                    <td colspan="2" style="height: 35px;">
                        <span class="label-span">User Phone:</span> 
                        <span class="value-span" style="font-size: 7.5pt;"><?= $holdingcage["contact_phone"] ?></span>
                    </td>
                    <td colspan="2">
                        <span class="label-span">User Email:</span> 
                        <span class="value-span" style="font-size: 6.5pt; word-break: break-all;"><?= $holdingcage["contact_email"] ?></span>
                    </td>
                </tr>

                <tr>
                    <td colspan="2" style="height: 35px;">
                        <span class="label-span">Sex:</span> 
                        <span class="value-span"><?= htmlspecialchars(ucfirst($holdingcage["sex"])); ?></span> |
                        <span class="label-span">Qty:</span> 
                        <span class="value-span"><?= htmlspecialchars($holdingcage["qty"]); ?></span>
                    </td>
                    <td colspan="2">
                        <span class="label-span">DOB:</span> 
                        <span class="value-span"><?= htmlspecialchars($holdingcage["dob"]); ?></span> |
                        <span class="label-span">Parent:</span> 
                        <span class="value-span"><?= htmlspecialchars($holdingcage["parent_cg"]); ?></span>
                    </td>
                </tr>

                <tr style="background-color: #f2f2f2; font-weight: bold; text-align: center; height: 25px;">
                    <td colspan="2" style="width: 40%;"><span class="label-span">Mouse ID</span></td>
                    <td colspan="3" style="width: 60%;"><span class="label-span">Genotype</span></td>
                </tr>

                <?php for ($i = 0; $i < 5; $i++) : ?>
                    <tr style="height: 23px;">
                        <td colspan="2" style="text-align: center;">
                            <span class="value-span"><?= isset($holdingcage['mice'][$i]['mouse_id']) ? htmlspecialchars($holdingcage['mice'][$i]['mouse_id']) : '' ?></span>
                        </td>
                        <td colspan="3" style="text-align: center;">
                            <span class="value-span"><?= isset($holdingcage['mice'][$i]['genotype']) ? htmlspecialchars($holdingcage['mice'][$i]['genotype']) : '' ?></span>
                        </td>
                    </tr>
                <?php endfor; ?>
            </table>

        </div>
    <?php endforeach; ?>
</div>

</body>

</html>
