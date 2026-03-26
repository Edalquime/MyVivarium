<?php

/**
 * Mostrar Mensajes de Sesión
 * * Este script verifica si hay un mensaje configurado en la sesión y lo muestra como una alerta de Bootstrap. 
 * El mensaje se elimina de la sesión después de ser mostrado para que no vuelva a aparecer al recargar la página.
 */

// Verificar si hay un mensaje configurado en la sesión
if (isset($_SESSION['message'])) :
?>

    <div class="alert alert-warning alert-dismissible fade show shadow-sm mb-4" role="alert" style="border-radius: 10px; border-left: 5px solid #f6c23e;">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-2" style="font-size: 1.2rem;"></i>
            <div>
                <strong>¡Hola <?= htmlspecialchars($_SESSION['name'] ?? 'Usuario'); ?>!</strong> 
                <?= htmlspecialchars($_SESSION['message']); ?>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>

<?php
    // Eliminar el mensaje después de mostrarlo
    unset($_SESSION['message']);
endif;
?>
