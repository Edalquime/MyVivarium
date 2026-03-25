<?php
// 🔥 ESTO MOSTRARÁ EL ERROR REAL EN PANTALLA
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * Breeding Cage Printable Card Script
 *
 * This script retrieves breeding cage data along with their latest litter records,
 * generates printable cards for each cage, and displays them in a 2x2 table format.
 * The script also includes QR codes for quick access to detailed views of each cage.
 */

// Start a new session or resume the existing session
session_start();

// Include the database connection file
require 'dbcon.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit; // Exit to ensure no further code is executed
}

// Query to get lab data (URL) from the settings table
$labQuery = "SELECT value FROM settings WHERE name = 'url' LIMIT 1";
$labResult = mysqli_query($con, $labQuery);

// Default value if the query fails or returns no result
$url = "";
if ($row = mysqli_fetch_assoc($labResult)) {
    $url = $row['value'];
}

// Check if the ID parameter is set in the URL
if (isset($_GET['id'])) {
    $ids = explode(',', $_GET['id']); // Split the IDs into an array
    $breedingcages = []; // Initialize an array to store breeding cage data

    foreach ($ids as $id) {
        // SQL MODIFICADO: Se asocia `cages.user_id` con `users` para obtener teléfono y correo
        $query = "SELECT b.*, c.remarks AS remarks, pi.name AS pi_name, u.username AS contact_email, u.phone AS contact_phone
        FROM breeding b
        LEFT JOIN cages c ON b.cage_id = c.cage_id
        LEFT JOIN users pi ON c.pi_name = pi.id
        LEFT JOIN users u ON c.user_id = u.id
        WHERE b.cage_id = ?";
        
        $stmt = $con->prepare($query);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $breedingcage = $result->fetch_assoc(); // Fetch the breeding cage data

            $query1 = "SELECT * FROM litters WHERE cage_id = ? ORDER BY litter_dob DESC LIMIT 5";
            $stmt1 = $con->prepare($query1);
            $stmt1->bind_param("s", $id);
            $stmt1->execute();
            $result1 = $stmt1->get_result();
            $litters = [];
            while ($litter = $result1->fetch_assoc()) {
                $litters[] = $litter; // Store each litter record
            }

            // Store the breeding cage and its litters
            $breedingcage['litters'] = $litters;

            // Fetch the user initials based on IDs from cage_users table
            $userInitials = getUserInitialsByCageId($con, $id);
            $userDisplayString = implode(', ', $userInitials);
            $breedingcage['user_initials'] = $userDisplayString;

            $breedingcages[] = $breedingcage;
        } else {
            // Set an error message and redirect if the ID is invalid
            $_SESSION['message'] = "Invalid ID: $id";
            header("Location: bc_dash.php");
            exit();
        }
    }
} else {
    // If the ID parameter is missing, set an error message and redirect to the dashboard
    $_SESSION['message'] = 'ID parameter is missing.';
    header("Location: bc_dash.php");
    exit();
}

function getUserInitialsByCageId($con, $cageId)
{
    $query = "SELECT u.initials 
              FROM users u 
              INNER JOIN cage_users cu ON u.id = cu.user_id 
              WHERE cu.cage_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $cageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userInitials = [];
    while ($row = $result->fetch_assoc()) {
        $userInitials[] = htmlspecialchars($row['initials']);
    }
    $stmt->close();
    return $userInitials;
}

function getIacucIdsByCageId($con, $cageId)
{
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
    <title>Impresión de Tarjetas 2x2</title>
    <style>
        @page {
            size: letter landscape;
            margin: 0;
            padding: 0;
        }

        /* Estilos de impresión */
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
        <?php foreach ($breedingcages as $index => $breedingcage) : ?>

            <?php if ($index % 2 === 0) : ?>
                <tr style="height: 3in; border: 1px dashed #D3D3D3; vertical-align:top;">
                <?php endif; ?>

                <td style="width: 5in; border: 1px dashed #D3D3D3;">
                    <table border="1" style="width: 5in; height: 1.5in;" id="cageA">
                        <tr>
                            <td colspan="3" style="width: 100%; text-align:center;">
                                <span style="font-weight: bold; font-size: 10pt; text-transform: uppercase; padding:3px;">
                                    Breeding Cage - # <?= $breedingcage["cage_id"] ?> </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="width:40%;">
                                <span style="font-weight: bold; text-transform: uppercase;">PI Name:</span>
                                <span><?= htmlspecialchars($breedingcage["pi_name"]); ?></span>
                            </td>
                            <td style="width:40%;">
                                <span style="font-weight: bold; text-transform: uppercase;">Cross:</span>
                                <span><?= $breedingcage["cross"] ?></span>
                            </td>
                            <td rowspan="4" style="width:20%; text-align:center; vertical-align:middle;">
                                <img src="<?php echo "https://api.qrserver.com/v1/create-qr-code/?size=70x70&data=https://" . $url . "/bc_view.php?id=" . $breedingcage["cage_id"] . "&choe=UTF-8"; ?>" alt="QR Code">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">IACUC:</span>
                                <span><?= htmlspecialchars(getIacucIdsByCageId($con, $breedingcage['cage_id'])); ?></span>
                            </td>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">User (Initials):</span>
                                <span><?= $breedingcage['user_initials']; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">Phone:</span>
                                <span style="font-size: 7.5pt;"><?= htmlspecialchars($breedingcage["contact_phone"] ?? 'N/A') ?></span>
                            </td>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">Email:</span>
                                <span style="font-size: 7pt; word-break: break-all;"><?= htmlspecialchars($breedingcage["contact_email"] ?? 'N/A') ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">Male ID (<?= $breedingcage["male_n"] ?? 1 ?>):</span>
                                <span><?= $breedingcage["male_id"] ?></span><br>
                                <span style="font-weight: bold; text-transform: uppercase;">DOB:</span>
                                <span><?= $breedingcage["male_dob"] ?></span>
                            </td>
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase;">Female ID (<?= $breedingcage["female_n"] ?? 1 ?>):</span>
                                <span><?= $breedingcage["female_id"] ?></span><br>
                                <span style="font-weight: bold; text-transform: uppercase;">DOB:</span>
                                <span><?= $breedingcage["female_dob"] ?></span>
                            </td>
                        </tr>
                    </table>
                    
                    <table border="1" style="width: 5in; height: 1.5in; border-top: none;" id="cageB">
                        <tr>
                            <td style="width:30%; text-align: center;">
                                <span style="font-weight: bold; text-transform: uppercase;">Litter DOB</span>
                            </td>
                            <td style="width:17.5%; text-align: center;">
                                <span style="font-weight: bold; text-transform: uppercase;">Alive</span>
                            </td>
                            <td style="width:17.5%; text-align: center;">
                                <span style="font-weight: bold; text-transform: uppercase;">Dead</span>
                            </td>
                            <td style="width:17.5%; text-align: center;">
                                <span style="font-weight: bold; text-transform: uppercase;">Male</span>
                            </td>
                            <td style="width:17.5%; text-align: center;">
                                <span style="font-weight: bold; text-transform: uppercase;">Female</span>
                            </td>
                        </tr>
                        <?php for ($i = 0; $i < 5; $i++) : ?>
                            <tr>
                                <td style="width:30%; padding:3px;">
                                    <span><?= isset($breedingcage['litters'][$i]['litter_dob']) ? $breedingcage['litters'][$i]['litter_dob'] : '' ?></span>
                                </td>
                                <td style="width:17.5%; padding:3px; text-align:center;">
                                    <span><?= isset($breedingcage['litters'][$i]['pups_alive']) ? $breedingcage['litters'][$i]['pups_alive'] : '' ?></span>
                                </td>
                                <td style="width:17.5%; padding:3px; text-align:center;">
                                    <span><?= isset($breedingcage['litters'][$i]['pups_dead']) ? $breedingcage['litters'][$i]['pups_dead'] : '' ?></span>
                                </td>
                                <td style="width:17.5%; padding:3px; text-align:center;">
                                    <span><?= isset($breedingcage['litters'][$i]['pups_male']) ? $breedingcage['litters'][$i]['pups_male'] : '' ?></span>
                                </td>
                                <td style="width:17.5%; padding:3px; text-align:center;">
                                    <span><?= isset($breedingcage['litters'][$i]['pups_female']) ? $breedingcage['litters'][$i]['pups_female'] : '' ?></span>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </table>
                </td>

                <?php if ($index % 2 === 1 || $index === count($breedingcages) - 1) : ?>
                </tr>
            <?php endif; ?>

        <?php endforeach; ?>
    </table>
</body>

</html>
