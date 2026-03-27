<?php

/**
 * Script de Paginación y Búsqueda de Jaulas de Cruce
 *
 * Este script maneja la paginación y la funcionalidad de búsqueda para las jaulas de cruce.
 * Devuelve un JSON limpio para que Javascript dibuje la tabla.
 */

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);

require 'dbcon.php';

// Limpiamos cualquier impresión previa para que no rompa el JSON
ob_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

$userRole = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? '';

$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; 
$offset = ($page - 1) * $limit; 

$searchQuery = '';
// CORRECCIÓN: Capturamos directamente el término enviado por JS
if (isset($_GET['search']) && $_GET['search'] !== '') {
    $searchQuery = $_GET['search']; // Ya viene codificado de JS, no aplicamos urldecode manual aquí
}

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

$tableRows = '';

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) { // fetch_assoc orientado a objetos es más estable con AJAX
        $cageID = $row['cage_id']; 
        
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
        $tableRows .= '<td style="width: 1%; white-space: nowrap; text-align: center;">';
        $tableRows .= '<div class="d-flex justify-content-center align-items-center" style="gap: 5px;">';

        $tableRows .= '<a href="bc_view.php?id=' . rawurlencode($cageID) . '&page=' . $page . '&search=' . urlencode($searchQuery) . '" class="btn btn-primary btn-sm btn-icon" data-toggle="tooltip" title="Ver Jaula"><i class="fas fa-eye"></i></a>';
        $tableRows .= '<a href="manage_tasks.php?id=' . rawurlencode($cageID) . '&page=' . $page . '&search=' . urlencode($searchQuery) . '" class="btn btn-secondary btn-sm btn-icon" data-toggle="tooltip" title="Gestionar Tareas"><i class="fas fa-tasks"></i></a>';

        if ($userRole === 'admin' || in_array($currentUserId, $assignedUsers)) {
            $tableRows .= '<a href="bc_edit.php?id=' . rawurlencode($cageID) . '&page=' . $page . '&search=' . urlencode($searchQuery) . '" class="btn btn-secondary btn-sm btn-icon" data-toggle="tooltip" title="Editar Jaula"><i class="fas fa-edit"></i></a>';
            $tableRows .= '<a href="#" onclick="confirmDeletion(\'' . htmlspecialchars($cageID) . '\')" class="btn btn-danger btn-sm btn-icon" data-toggle="tooltip" title="Eliminar Jaula"><i class="fas fa-trash"></i></a>';
        }

        $tableRows .= '</div>';
        $tableRows .= '</td>';
        $tableRows .= '</tr>';
    }
} else {
    $tableRows .= '<tr><td colspan="2" class="text-center text-muted p-4">No se encontraron jaulas.</td></tr>';
}

if (isset($stmt)) {
    $stmt->close();
}

$paginationLinks = '';
if ($totalPages > 1) {
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : ''; 
        // El onclick llama a la función de JS con el término de búsqueda actual
        $paginationLinks .= '<li class="page-item ' . $activeClass . '"><a class="page-link" href="javascript:void(0);" onclick="fetchData(' . $i . ', \'' . htmlspecialchars($searchQuery, ENT_QUOTES) . '\')">' . $i . '</a></li>';
    }
}

// Limpiamos el búfer para asegurarnos que no haya ningún texto raro antes del JSON
ob_end_clean();

header('Content-Type: application/json');
echo json_encode([
    'tableRows' => $tableRows,
    'paginationLinks' => $paginationLinks
]);
exit;
