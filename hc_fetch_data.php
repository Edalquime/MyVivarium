<?php

/**
 * Script de Paginación y Búsqueda de Jaulas de Mantenimiento (Holding Cages)
 *
 * Este script maneja la funcionalidad de paginación y búsqueda para las jaulas de mantenimiento.
 * Inicia una sesión, incluye la conexión a la base de datos, maneja filtros de búsqueda,
 * obtiene datos de las jaulas con paginación y genera el HTML para las filas de la tabla y los enlaces de paginación.
 * El HTML generado se devuelve como una respuesta JSON.
 *
 */

// Iniciar una nueva sesión o reanudar la existente
session_start();

// Desactivar la visualización de errores directos en producción (los errores se registran en los logs del servidor)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Incluir la conexión a la base de datos
require 'dbcon.php';

// Iniciar el búfer de salida
ob_start();

// Verificar si el usuario no ha iniciado sesión, redirigirlo a index.php
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit; // Salir para asegurar que no se ejecute más código
}

// Obtener el rol y el ID del usuario de la sesión
$userRole = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? '';

// Variables de paginación
$limit = 10; // Número de entradas a mostrar en una página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Número de página actual, por defecto 1
$offset = ($page - 1) * $limit; // Desplazamiento para la consulta SQL

// Manejar el filtro de búsqueda
$searchQuery = '';
if (isset($_GET['search'])) {
    $searchQuery = mysqli_real_escape_string($con, urldecode($_GET['search'])); // Decodificar y escapar el parámetro de búsqueda
}

// Obtener los IDs de jaula distintos con paginación utilizando sentencias preparadas
if (!empty($searchQuery)) {
    $searchPattern = '%' . $searchQuery . '%';
    
    $totalQuery = "SELECT COUNT(DISTINCT cage_id) as total FROM holding WHERE cage_id LIKE ?";
    $stmtTotal = $con->prepare($totalQuery);
    $stmtTotal->bind_param("s", $searchPattern);
    $stmtTotal->execute();
    $totalResult = $stmtTotal->get_result();
    $totalRecords = $totalResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRecords / $limit);
    $stmtTotal->close();

    $query = "SELECT DISTINCT cage_id FROM holding WHERE cage_id LIKE ? LIMIT ? OFFSET ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("sii", $searchPattern, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $totalQuery = "SELECT COUNT(DISTINCT cage_id) as total FROM holding";
    $totalResult = mysqli_query($con, $totalQuery);
    $totalRecords = mysqli_fetch_assoc($totalResult)['total'];
    $totalPages = ceil($totalRecords / $limit);

    $query = "SELECT DISTINCT cage_id FROM holding LIMIT ? OFFSET ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Generar las filas de la tabla
$tableRows = '';

if ($result->num_rows > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $cageID = $row['cage_id']; // Obtener el ID de la jaula
        
        // Obtener los usuarios asignados para validar los permisos de edición/borrado
        $userCheckQuery = "SELECT user_id FROM cage_users WHERE cage_id = ?";
        $stmtUserCheck = $con->prepare($userCheckQuery);
        $stmtUserCheck->bind_param("s", $cageID);
        $stmtUserCheck->execute();
        $userCheckResult = $stmtUserCheck->get_result();
        
        $assignedUsers = [];
        while ($uRow = $userCheckResult->fetch_assoc()) {
            $assignedUsers[] = $uRow['user_id'];
        }
        $stmtUserCheck->close();

        $tableRows .= '<tr>';
        // El ID de jaula usa el resto del 100% disponible
        $tableRows .= '<td>' . htmlspecialchars($cageID) . '</td>';
        
        // Columna de Acciones ajustada al 1% y centrada para coincidir con la cabecera del Dashboard
        $tableRows .= '<td style="width: 1%; white-space: nowrap; text-align: center;">';
        $tableRows .= '<div class="d-flex justify-content-center align-items-center" style="gap: 5px;">';

        // 1. Ver Jaula (View Cage)
        $tableRows .= '<a href="hc_view.php?id=' . rawurlencode($cageID) . '&page=' . $page . '&search=' . urlencode($searchQuery) . '" class="btn btn-primary btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Ver Jaula"><i class="fas fa-eye"></i></a>';
        
        // 2. Gestionar Tareas (Manage Tasks)
        $tableRows .= '<a href="manage_tasks.php?id=' . rawurlencode($cageID) . '&page=' . $page . '&search=' . urlencode($searchQuery) . '" class="btn btn-secondary btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Gestionar Tareas"><i class="fas fa-tasks"></i></a>';

        // Validar Roles para edición y borrado
        if ($userRole === 'admin' || in_array($currentUserId, $assignedUsers)) {
            // 3. Editar Jaula (Edit Cage)
            $tableRows .= '<a href="hc_edit.php?id=' . rawurlencode($cageID) . '&page=' . $page . '&search=' . urlencode($searchQuery) . '" class="btn btn-secondary btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Editar Jaula"><i class="fas fa-edit"></i></a>';
            
            // 4. Eliminar Jaula (Delete Cage)
            $tableRows .= '<a href="#" onclick="confirmDeletion(\'' . htmlspecialchars($cageID) . '\')" class="btn btn-danger btn-sm btn-icon" data-toggle="tooltip" data-placement="top" title="Eliminar Jaula"><i class="fas fa-trash"></i></a>';
        }

        $tableRows .= '</div>';
        $tableRows .= '</td>';
        $tableRows .= '</tr>';
    }
} else {
    $tableRows .= '<tr><td colspan="2" class="text-center text-muted p-4">No se encontraron jaulas de mantenimiento.</td></tr>';
}

if ($stmt) {
    $stmt->close();
}

// Generar los enlaces de paginación
$paginationLinks = '';
if ($totalPages > 1) {
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $page) ? 'active' : ''; 
        $paginationLinks .= '<li class="page-item ' . $activeClass . '"><a class="page-link" href="javascript:void(0);" onclick="fetchData(' . $i . ', \'' . htmlspecialchars($searchQuery, ENT_QUOTES) . '\')">' . $i . '</a></li>';
    }
}

ob_end_clean();

// Devolver las filas generadas y enlaces de paginación como respuesta JSON
header('Content-Type: application/json');
echo json_encode([
    'tableRows' => $tableRows,
    'paginationLinks' => $paginationLinks
]);
