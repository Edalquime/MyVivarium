<?php
session_start();
require 'dbcon.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit; 
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($con, $_GET['id']);
    $query = "SELECT b.*, c.remarks FROM breeding b LEFT JOIN cages c ON b.cage_id = c.cage_id WHERE b.cage_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $breedingcage = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $male_n = intval($_POST['male_n'] ?? 1);
    $female_n = intval($_POST['female_n'] ?? 1);

    $male_id = implode(', ', $_POST['male_id'] ?? []);
    $female_id = implode(', ', $_POST['female_id'] ?? []);
    $male_dob = implode(', ', $_POST['male_dob'] ?? []);
    $female_dob = implode(', ', $_POST['female_dob'] ?? []);

    $updateBreeding = $con->prepare("UPDATE breeding SET male_n = ?, male_id = ?, female_n = ?, female_id = ?, male_dob = ?, female_dob = ? WHERE cage_id = ?");
    $updateBreeding->bind_param("isissss", $male_n, $male_id, $female_n, $female_id, $male_dob, $female_dob, $id);
    $updateBreeding->execute();

    header("Location: bc_dash.php");
    exit();
}

require 'header.php';
?>

<!doctype html>
<html lang="en">
<head>
    <title>Edit Cage</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const maleNumInput = document.getElementById('male_n');
            const femaleNumInput = document.getElementById('female_n');
            const maleContainer = document.getElementById('male_dates_container');
            const femaleContainer = document.getElementById('female_dates_container');

            const savedMaleIds = document.getElementById('saved_male_id').value.split(', ');
            const savedMaleDobs = document.getElementById('saved_male_dob').value.split(', ');
            const savedFemaleIds = document.getElementById('saved_female_id').value.split(', ');
            const savedFemaleDobs = document.getElementById('saved_female_dob').value.split(', ');

            function drawMales(firstLoad = false) {
                const qty = parseInt(maleNumInput.value) || 0;
                maleContainer.innerHTML = '';
                for (let i = 1; i <= qty; i++) {
                    const id = (firstLoad && savedMaleIds[i-1]) ? savedMaleIds[i-1] : '';
                    const dob = (firstLoad && savedMaleDobs[i-1]) ? savedMaleDobs[i-1] : '';
                    maleContainer.innerHTML += `
                        <div class="p-3 border rounded mb-2 bg-white">
                            <h6>Male #${i}</h6>
                            <input type="text" name="male_id[]" class="form-control mb-2" value="${id}" required>
                            <input type="date" name="male_dob[]" class="form-control" value="${dob}" required>
                        </div>`;
                }
            }

            function drawFemales(firstLoad = false) {
                const qty = parseInt(femaleNumInput.value) || 0;
                femaleContainer.innerHTML = '';
                for (let i = 1; i <= qty; i++) {
                    const id = (firstLoad && savedFemaleIds[i-1]) ? savedFemaleIds[i-1] : '';
                    const dob = (firstLoad && savedFemaleDobs[i-1]) ? savedFemaleDobs[i-1] : '';
                    femaleContainer.innerHTML += `
                        <div class="p-3 border rounded mb-2 bg-white">
                            <h6>Female #${i}</h6>
                            <input type="text" name="female_id[]" class="form-control mb-2" value="${id}" required>
                            <input type="date" name="female_dob[]" class="form-control" value="${dob}" required>
                        </div>`;
                }
            }

            maleNumInput.addEventListener('input', () => drawMales(false));
            femaleNumInput.addEventListener('input', () => drawFemales(false));

            drawMales(true);
            drawFemales(true);
        });
    </script>
</head>
<body>
    <div class="container mt-4">
        <h4>Edit Cage: <?= htmlspecialchars($id) ?></h4>
        <form method="POST">
            <input type="hidden" id="saved_male_id" value="<?= htmlspecialchars($breedingcage['male_id'] ?? '') ?>">
            <input type="hidden" id="saved_male_dob" value="<?= htmlspecialchars($breedingcage['male_dob'] ?? '') ?>">
            <input type="hidden" id="saved_female_id" value="<?= htmlspecialchars($breedingcage['female_id'] ?? '') ?>">
            <input type="hidden" id="saved_female_dob" value="<?= htmlspecialchars($breedingcage['female_dob'] ?? '') ?>">

            <div class="mb-3">
                <label>Number of Males</label>
                <input type="number" class="form-control" id="male_n" name="male_n" value="<?= htmlspecialchars($breedingcage['male_n'] ?? 1) ?>">
            </div>
            <div id="male_dates_container"></div>

            <div class="mb-3">
                <label>Number of Females</label>
                <input type="number" class="form-control" id="female_n" name="female_n" value="<?= htmlspecialchars($breedingcage['female_n'] ?? 1) ?>">
            </div>
            <div id="female_dates_container"></div>

            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>
</body>
</html>
