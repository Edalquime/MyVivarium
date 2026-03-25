<?php

/**
 * Printable Holding Cage Cards
 * * Genera la vista de impresión 2x2 para jaulas de mantenimiento (Holding).
 * Ahora incluye los datos de contacto (Teléfono y Correo) de los usuarios vinculados.
 */

session_start();

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

        // Consulta base sin user_id (lo sacamos aparte por seguridad de la estructura)
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

        if (mysqli_num_rows($result) === 1) {
            $holdingcage = mysqli_fetch_assoc($result);

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

            // 🔥 DATOS DE CONTACTO DE USUARIOS VINCULADOS
            $contactData = getUsersContactByCageId($con, $id);
            $holdingcage['user_initials'] = $contactData['initials'];
            $holdingcage['contact_phone'] = $contactData['phones'];
            $holdingcage['contact_email'] = $contactData['emails'];

            $holdingcage['mice'] = $mouseData;
            $holdingcages[] = $holdingcage;

            $stmt->close();
        } else {
            $_SESSION['message'] = "Invalid ID: $id";
            header("Location: hc_dash.php");
            exit();
        }
    }
} else {
    $_SESSION['message'] = 'ID parameter is missing.';
    header("Location: hc_dash.php");
    exit();
}


/**
 * Función centralizada para extraer información de contacto del personal de bioterio
 */
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

    $initials = [];
    $emails = [];
    $phones = [];

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
    <title>Printable 2x2 Card Table</title>
    <style>
        @page {
            size: letter landscape;
            margin: 0;
            padding: 0;
        }

        @media print {
            body {
                margin: 0;
                color: #000;
            }
        }

        body,
        html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
            display: grid;
            place-items: center;
            font-family: Arial, Helvetica, sans-serif;
        }

        span {
            font-size: 8pt;
            padding: 0px;
            line-height: 1.2;
            display: inline-block;
        }

        table {
            box-sizing: border-box;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
            border-spacing: 0;
        }

        table#cageA tr td,
        table#cageB tr td {
            border: 1px solid black;
            box-sizing: border-box;
            border-collapse: collapse;
            margin: 0;
            padding: 2px;
            border-spacing: 0;
        }

        table#cageB tr:first-child td {
            border-top: none;
        }
    </style>
</head>

<body>
    <table style="width: 10in; height: 6in; border-collapse: collapse; border: 1px dashed #D3D3D3;">
        <?php foreach ($holdingcages as $index => $holdingcage) : ?>

            <?php if ($index % 2 === 0) : ?>
                <tr style="height: 3in; border: 1px dashed #D3D3D3; vertical-align:top;">
                <?php endif; ?>

                <td style="width: 5in; border: 1px dashed #D3D3D3;">
                    <table border="1" style="width: 5in; height: 1.5in;" id="cageA">
                        <tr>
                            <td colspan="3" style="width: 100%; text-align:center;">
                                <span style="font-weight: bold; font-size: 10pt; text-transform: uppercase; padding:3px;">
                                    Holding Cage - # <?= $holdingcage["cage_id"] ?> </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="width:40%;">
                                <span style="font-weight: bold; text-transform: uppercase;">PI Name:</span>
                                <span><?= htmlspecialchars($holdingcage["pi_name"]); ?></span>
                            </td>
                            <td style="width:40%;">
                                <span style="font-weight: bold; text-transform: uppercase;">Strain:</span>
                                <span><?= htmlspecialchars($holdingcage["str_name"]); ?></span>
                            </td>
                            <td rowspan="4" style="width:20%; text-align:center; vertical-align:middle;">
                                <img src="<?php echo "https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=https://" . $url . "/hc_view.php?id=" . $holdingcage["cage_id"] . "&choe=UTF-8"; ?>" alt="QR Code">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">IACUC:</span>
                                <span><?= htmlspecialchars($holdingcage["iacuc"]); ?></span>
                            </td>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">User (Initials):</span>
                                <span><?= $holdingcage['user_initials']; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">User Phone:</span>
                                <span style="font-size: 7.5pt;"><?= $holdingcage["contact_phone"] ?></span>
                            </td>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">User Email:</span>
                                <span style="font-size: 7pt; word-break: break-all;"><?= $holdingcage["contact_email"] ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">Sex:</span>
                                <span><?= htmlspecialchars(ucfirst($holdingcage["sex"])); ?></span> |
                                <span style="font-weight: bold; text-transform: uppercase;">Qty:</span>
                                <span><?= htmlspecialchars($holdingcage["qty"]); ?></span>
                            </td>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">DOB:</span>
                                <span><?= htmlspecialchars($holdingcage["dob"]); ?></span> |
                                <span style="font-weight: bold; text-transform: uppercase;">Parent:</span>
                                <span><?= htmlspecialchars($holdingcage["parent_cg"]); ?></span>
                            </td>
                        </tr>
                    </table>

                    <table border="1" style="width: 5in; height: 1.5in; border-top: none;" id="cageB">
                        <tr>
                            <td style="width:40%; text-align:center;">
                                <span style="font-weight: bold; text-transform: uppercase;">Mouse ID</span>
                            </td>
                            <td style="width:60%; text-align:center;">
                                <span style="font-weight: bold; text-transform: uppercase;">Genotype</span>
                            </td>
                        </tr>
                        <?php foreach (range(1, 5) as $i) : ?>
                            <tr>
                                <td style="width:40%; padding:3px;">
                                    <span><?= isset($holdingcage['mice'][$i - 1]['mouse_id']) ? htmlspecialchars($holdingcage['mice'][$i - 1]['mouse_id']) : '' ?></span>
                                </td>
                                <td style="width:60%; padding:3px;">
                                    <span><?= isset($holdingcage['mice'][$i - 1]['genotype']) ? htmlspecialchars($holdingcage['mice'][$i - 1]['genotype']) : '' ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </td>

                <?php if ($index % 2 === 1 || $index === count($holdingcages) - 1) : ?>
                </tr>
            <?php endif; ?>

        <?php endforeach; ?>
    </table>
</body>

</html>
