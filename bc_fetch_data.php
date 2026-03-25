<?php

/**
 * Breeding Cage Pagination and Search Script
 *
 * This script handles the pagination and search functionality for breeding cages.
 * It starts a session, includes the database connection, handles search filters,
 * fetches cage data with pagination, and generates HTML for table rows and pagination links.
 * The generated HTML is returned as a JSON response.
 *
 */

// Start a new session or resume the existing session
session_start();

// Disable error display in production (errors logged to server logs)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include the database connection
require 'dbcon.php';

// Start output buffering
ob_start();

// Check if the user is not logged in, redirect them to index.php with the current URL for redirection after login
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit; // Exit to ensure no further code is executed
}

// Fetch user role and ID from session
$userRole = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? '';

// Pagination variables
$limit = 10; // Number of entries to show in a page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page number, default to 1
$offset = ($page - 1) * $limit; // Offset for the SQL query

// Handle the search filter
$searchQuery = '';
if (isset($_GET['search'])) {
    $searchQuery = mysqli_real_escape_string($con, urldecode($_GET['search'])); // Decode and escape the search parameter
}

// Fetch the distinct cage IDs with pagination using prepared statements
if (!empty($searchQuery)) {
    $searchPattern = '%' . $searchQuery . '%';
    
    $totalQuery = "SELECT COUNT(DISTINCT cage_id) as total FROM breeding WHERE cage_id LIKE ?";
    $stmtTotal = $con->prepare($totalQuery);
    $stmtTotal->bind_param("s", $searchPattern);
    $stmtTotal->execute();
    $totalResult = $stmtTotal->get_result();
    $totalRecords = $totalResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRecords / $limit);
    $stmtTotal->close();

    $query = "SELECT DISTINCT cage_id FROM breeding WHERE cage_id LIKE ? LIMIT ? OFFSET ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("sii", $searchPattern, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $totalQuery = "SELECT COUNT(DISTINCT cage_id) as total FROM breeding";
    $totalResult = mysqli_query($con, $totalQuery);
    $totalRecords = mysqli_fetch_assoc($totalResult)['total'];
    $totalPages = ceil($totalRecords / $limit);

    $query = "SELECT DISTINCT cage_id FROM breeding LIMIT ? OFFSET ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Generate the table rows
$tableRows = '';

if ($result->num_rows > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $cageID = $row['cage_id']; 
        
        // Obtenemos los usuarios asignados para validar los permisos de edición/borrado
        $userCheckQuery = "SELECT user_id FROM cage_users WHERE cage_id = ?";
        $stmtUserCheck = $con->prepare($userCheckQuery);
        $stmtUserCheck->bind_param("s", $cageID);
        $stmtUserCheck->execute();
        $userCheckResult = $stmtUserCheck->get_result();
        
        $assignedUsers = [];
        while($uRow = $userCheckResult->fetch_assoc()){
            $assignedUsers[] = $uRow['user_id'];
        }
        $stmtUserCheck->close();

        $tableRows .= '<tr>';
        $tableRows .= '<td>' . htmlspecialchars($cageID) . '</td>';
        
        // --- COLUMNA DE ACCIONES (4 botones originales con espaciado correcto) ---
        $tableRows .= '<td style="width: 1%; white-space: nowrap; text-align: center;">';
        $tableRows .= '<div class="d-flex justify-content-center align-items-center" style="gap: 5px;">';

        // 1. Ver Jaula (View)
        $tableRows .= '<a href="bc_view.php?id=' . rawurlencode($cageID) . '&page=' . $page . '&search=' . urlencode($searchQuery) . '" class="btn btn-primary btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="View Cage"><i class="fas fa-eye"></i></a>';
        
        // 2. Gestionar Tareas (Manage Tasks)
        $tableRows .= '<a href="manage_tasks.php?id=' . rawurlencode($cageID) . '&page=' . $page . '&search=' . urlencode($searchQuery) . '" class="btn btn-secondary btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Manage Tasks"><i class="fas fa-tasks"></i></a>';

        // Validar Roles para edición y borrado
        if ($userRole === 'admin' || in_array($currentUserId, $assignedUsers)) {
            // 3. Editar Jaula (Edit)
            $tableRows .= '<a href="bc_edit.php?id=' . rawurlencode($cageID) . '&page=' . $page . '&search=' . urlencode($searchQuery) . '" class="btn btn-secondary btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Edit Cage"><i class="fas fa-edit"></i></a>';
            
            // 4. Borrar Jaula (Delete)
            $tableRows .= '<a href="#" onclick="confirmDeletion(\'' . htmlspecialchars($cageID) . '\')" class="btn btn-danger btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Delete Cage"><i class="fas fa-trash"></i></a>';
        }

        $tableRows .= '</div>';
        $tableRows .= '</td>';
        $tableRows .= '</tr>';
    }
} else {
    $tableRows .= '<tr><td colspan="2" class="text-center text-muted p-4">No se encontraron jaulas de cruce.</td></tr>';
}

if ($stmt) {
    $stmt->close();
}

// Generate the pagination links
$paginationLinks = '';
if ($totalPages > 1) {
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : ''; 
        $paginationLinks .= '<li class="page-item ' . $activeClass . '"><a class="page-link" href="javascript:void(0);" onclick="fetchData(' . $i . ', \'' . htmlspecialchars($searchQuery, ENT_QUOTES) . '\')">' . $i . '</a></li>';
    }
}

ob_end_clean();

header('Content-Type: application/json');
echo json_encode([
    'tableRows' => $tableRows,
    'paginationLinks' => $paginationLinks
]);
