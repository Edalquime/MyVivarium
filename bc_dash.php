<?php

/**
 * Breeding Cage Dashboard Script
 *
 * This script displays a dashboard for managing breeding cages. It starts a session, checks if the user is logged in,
 * and includes the necessary header and database connection files. The HTML part of the script includes the structure
 * for displaying breeding cages, search functionality, and actions such as adding a new cage or printing cage cards.
 * The script uses JavaScript for handling search, pagination, and confirmation dialogs.
 *
 */

// Start a new session or resume the existing session
session_start();

// Include the database connection file
require 'dbcon.php';

// Check if the user is not logged in, redirect them to index.php with the current URL for redirection after login
if (!isset($_SESSION['username'])) {
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: index.php?redirect=$currentUrl");
    exit; // Exit to ensure no further code is executed
}

// Include the header file
require 'header.php';
?>

<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        // Initialize tooltips when the document is ready
        $(document).ready(function() {
            $('body').tooltip({
                selector: '[data-toggle="tooltip"]'
            });
        });

        // Confirm deletion function with a dialog
        function confirmDeletion(id) {
            var confirmDelete = confirm("Are you sure you want to delete cage - '" + id + "' and related mouse data?");
            if (confirmDelete) {
                window.location.href = "bc_drop.php?id=" + id + "&confirm=true"; // Redirect to deletion script
            }
        }

        // Fetch data function to load data dynamically
        function fetchData(page = 1, search = '') {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'bc_fetch_data.php?page=' + page + '&search=' + encodeURIComponent(search), true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.tableRows && response.paginationLinks) {
                            document.getElementById('tableBody').innerHTML = response.tableRows; // Insert table rows
                            document.getElementById('paginationLinks').innerHTML = response.paginationLinks; // Insert pagination links
                            document.getElementById('searchInput').value = search; // Preserve search input

                            // Update the URL with the current page and search query
                            const newUrl = new URL(window.location.href);
                            newUrl.searchParams.set('page', page);
                            newUrl.searchParams.set('search', search);
                            window.history.replaceState({
                                path: newUrl.href
                            }, '', newUrl.href);
                        } else {
                            console.error('Invalid response format:', response);
                        }
                    } catch (e) {
                        console.error('Error parsing JSON response:', e);
                    }
                } else {
                    console.error('Request failed. Status:', xhr.status);
                }
            };
            xhr.onerror = function() {
                console.error('Request failed. An error occurred during the transaction.');
            };
            xhr.send();
        }

        // Search function to initiate data fetch based on search query
        function searchCages() {
            var searchQuery = document.getElementById('searchInput').value;
            fetchData(1, searchQuery);
        }

        // Fetch initial data when the DOM content is loaded
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page') || 1;
            const search = urlParams.get('search') || '';
            fetchData(page, search);
        });
    </script>


    <title>Dashboard Breeding Cage | <?php echo htmlspecialchars($labName); ?></title>

    <style>
        body {
            background-color: #f4f6f9;
            font-family: Arial, sans-serif;
        }

        /* Alineando dimensiones al estándar de los formularios previos */
        .main-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);
            background-color: #ffffff;
        }

        /* Caja de búsqueda modernizada */
        .search-card {
            background-color: #ffffff;
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.03);
        }

        .table-wrapper {
            margin-bottom: 30px;
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e3e6f0;
        }

        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        /* Estilización de cabeceras de tabla */
        .table-wrapper th {
            background-color: #eaecf4;
            color: #4e73df;
            font-weight: 700;
            border: 1px solid #e3e6f0;
            padding: 12px 15px;
            text-align: left;
        }

        .table-wrapper td {
            border: 1px solid #e3e6f0;
            padding: 12px 15px;
            vertical-align: middle;
            background-color: #fff;
        }

        /* Botonera estandarizada */
        .btn-modern {
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-icon {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            padding: 0;
            font-weight: 600;
        }

        .btn-icon i {
            font-size: 16px;
        }

        .action-icons {
            display: flex;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .table-wrapper th,
            .table-wrapper td {
                padding: 12px 8px;
                text-align: center;
            }
            .action-icons {
                justify-content: center;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container mt-4 mb-5">
        <?php include('message.php'); ?>

        <div class="card main-card">
            <div class="card-header bg-dark text-white p-3 d-flex flex-column flex-md-row justify-content-between align-items-center" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h4 class="mb-0 fs-5"><i class="fas fa-layer-group me-2"></i> Breeding Cage Dashboard</h4>
                
                <div class="action-icons mt-3 mt-md-0">
                    <a href="bc_addn.php" class="btn btn-primary btn-icon" data-toggle="tooltip" data-placement="top" title="Add New Cage">
                        <i class="fas fa-plus"></i>
                    </a>
                    <a href="bc_slct_crd.php" class="btn btn-success btn-icon" data-toggle="tooltip" data-placement="top" title="Print Cage Card">
                        <i class="fas fa-print"></i>
                    </a>
                    <a href="maintenance.php?from=bc_dash" class="btn btn-warning btn-icon" data-toggle="tooltip" data-placement="top" title="Cage Maintenance">
                        <i class="fas fa-wrench"></i>
                    </a>
                </div>
            </div>

            <div class="card-body bg-light p-4">
                
                <div class="search-card">
                    <div class="input-group">
                        <input type="text" id="searchInput" class="form-control" style="height: calc(1.5em + 1rem + 2px); border-radius: 8px 0 0 8px;" placeholder="Search by Cage ID, Strain or PI..." onkeyup="searchCages()">
                        <div class="input-group-append">
                            <button class="btn btn-primary btn-modern" style="border-radius: 0 8px 8px 0;" type="button" onclick="searchCages()">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-wrapper" id="tableContainer">
                    <table class="table table-hover" id="mouseTable">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Cage ID</th>
                                <th style="width: 50%;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center" id="paginationLinks">
                        </ul>
                </nav>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>

</html>
