<?php

/**
 * Página de Notas Adhesivas (Sticky Notes)
 * * Este script recupera y muestra notas adhesivas para el usuario conectado, permitiéndole añadir, editar y eliminar notas.
 * Utiliza AJAX para el envío de formularios y actualiza dinámicamente la página sin recargar.
 * */

// Iniciar o reanudar la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir el archivo de conexión a la base de datos
require 'dbcon.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit; // Salir para asegurar que no se ejecute más código
}

$currentUserId = $_SESSION['user_id']; // ID del usuario actual

// Para recuperar notas por cage_id (ID de la jaula)
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT notes.*, COALESCE(users.name, notes.user_id) AS user_name 
            FROM notes 
            LEFT JOIN users ON notes.user_id = users.id 
            WHERE notes.cage_id = ? 
            ORDER BY notes.created_at DESC";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $id);
} else {
    // Para recuperar notas sin cage_id específico
    $sql = "SELECT notes.*, COALESCE(users.name, notes.user_id) AS user_name 
            FROM notes 
            LEFT JOIN users ON notes.user_id = users.id 
            WHERE notes.cage_id IS NULL 
            ORDER BY notes.created_at DESC";
    $stmt = $con->prepare($sql);
}
$stmt->execute();
$result = $stmt->get_result();

$coloresPostit = ['', 'postit-rosa', 'postit-azul', 'postit-verde'];
$contadorColores = 0;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas Adhesivas</title>
    
    <style>
        .nt-container {
            width: 100%;
            padding: 0;
        }

        /* Contenedor Flex que agrupa todos los Post-its */
        .sticky-notes-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: flex-start;
            padding: 10px 0;
        }

        /* Estilo base del Post-it (Amarillo Pastel clásico) */
        .sticky-note {
            background-color: #fffb96;
            padding: 20px;
            margin-bottom: 5px;
            border-radius: 4px;
            position: relative;
            width: 220px;
            min-height: 220px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 5px 5px 12px rgba(0, 0, 0, 0.12);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            border-bottom-right-radius: 50px 4px; /* Simula la esquina levantada */
        }

        /* Otras variantes de colores para que se vea como un tablero real */
        .postit-rosa { background-color: #ffc2df !important; }
        .postit-azul { background-color: #c2e2ff !important; }
        .postit-verde { background-color: #c2ffc7 !important; }

        /* Rotación sutil aleatoria simulando que se pegaron a mano */
        .sticky-note:nth-child(even) {
            transform: rotate(1.5deg);
        }
        .sticky-note:nth-child(odd) {
            transform: rotate(-2deg);
        }
        .sticky-note:nth-child(3n) {
            transform: rotate(1deg);
        }

        /* Efecto al pasar el mouse por encima */
        .sticky-note:hover {
            box-shadow: 10px 10px 15px rgba(0, 0, 0, 0.18);
            transform: scale(1.05) rotate(0deg) !important;
            z-index: 10;
        }

        .sticky-note .note-content {
            font-size: 0.88rem;
            color: #2c3e50;
            line-height: 1.4;
            word-wrap: break-word;
            flex-grow: 1;
            margin-top: 5px;
            margin-bottom: 10px;
        }

        .sticky-note .timestamp {
            font-size: 0.75rem;
            color: #555;
            font-weight: 600;
            border-top: 1px solid rgba(0,0,0,0.06);
            padding-top: 5px;
            display: block;
        }

        .sticky-note .userid {
            display: block;
            font-size: 0.8rem;
            color: #4e73df;
            font-weight: bold;
            margin-bottom: 5px;
        }

        /* Botones de acción flotantes en las esquinas */
        .sticky-note .close-btn,
        .sticky-note .edit-btn {
            cursor: pointer;
            position: absolute;
            top: 8px;
            font-weight: bold;
            color: rgba(0,0,0,0.3);
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .sticky-note .close-btn {
            right: 8px;
        }

        .sticky-note .edit-btn {
            right: 28px;
        }

        .sticky-note .close-btn:hover {
            color: #e74a3b;
        }

        .sticky-note .edit-btn:hover {
            color: #f6c23e;
        }

        .add-note-btn {
            background-color: #4e73df;
            color: white;
            padding: 8px 16px;
            border: none;
            cursor: pointer;
            margin-bottom: 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: background-color 0.2s;
        }

        .add-note-btn:hover {
            background-color: #2e59d9;
        }

        /* Modales Pop-up para Crear y Editar */
        .popup,
        .edit-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 25px;
            background-color: #fff;
            border: none;
            border-radius: 12px;
            z-index: 1050;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 420px;
        }

        .popup .popup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #ebedf0;
            padding-bottom: 10px;
        }

        .popup-header h5 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
        }

        .popup .popup-close {
            cursor: pointer;
            font-size: 1.2rem;
            color: #aaa;
            transition: color 0.2s;
        }

        .popup .popup-close:hover {
            color: #333;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1040;
            backdrop-filter: blur(2px);
        }

        #addNoteForm,
        #editNoteForm {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        #note_text,
        #edit_note_text {
            height: 120px;
            margin-bottom: 10px;
            resize: none;
            background-color: #fffb96;
            padding: 10px;
            border: 1px solid #e6d381;
            border-radius: 6px;
            font-size: 0.9rem;
            color: #333;
        }

        #addNoteForm button,
        #editNoteForm button {
            background-color: #1cc88a;
            color: white;
            padding: 10px;
            border: none;
            cursor: pointer;
            border-radius: 6px;
            font-weight: bold;
            transition: background-color 0.2s;
        }

        #addNoteForm button:hover,
        #editNoteForm button:hover {
            background-color: #17a673;
        }

        .char-count {
            font-size: 0.75rem;
            color: #888;
            margin-bottom: 12px;
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="nt-container">
        <div id="message"></div> 
        
        <button class="add-note-btn" onclick="togglePopup('addNotePopup')">
            <i class="fas fa-plus"></i> Añadir Nota Adhesiva
        </button>

        <div class="popup" id="addNotePopup">
            <div class="popup-header">
                <h5>Nueva Nota Adhesiva</h5>
                <span class="popup-close" onclick="togglePopup('addNotePopup')">×</span>
            </div>
            <form id="addNoteForm" method="post">
                <?php if (isset($_GET['id'])) : ?>
                    <label class="small text-muted mb-2">Para Jaula ID: <strong><?= htmlspecialchars($_GET['id']); ?></strong></label>
                    <input type="hidden" id="cage_id" name="cage_id" value="<?= htmlspecialchars($_GET['id']); ?>">
                <?php endif; ?>
                <textarea id="note_text" name="note_text" placeholder="Escribe tu nota adhesiva aquí..." maxlength="250" required></textarea>
                <div class="char-count" id="charCount">250 caracteres restantes</div>
                <button type="submit" name="add_note">Guardar Nota</button>
            </form>
        </div>

        <div class="popup edit-popup" id="editNotePopup">
            <div class="popup-header">
                <h5>Editar Nota Adhesiva</h5>
                <span class="popup-close" onclick="togglePopup('editNotePopup')">×</span>
            </div>
            <form id="editNoteForm" method="post">
                <input type="hidden" id="edit_note_id" name="note_id">
                <textarea id="edit_note_text" name="note_text" placeholder="Edita tu nota adhesiva aquí..." maxlength="250" required></textarea>
                <div class="char-count" id="editCharCount">250 caracteres restantes</div>
                <button type="submit" name="edit_note">Guardar Cambios</button>
            </form>
        </div>

        <div class="overlay" id="overlay" onclick="closeAllPopups()"></div>

        <div class="sticky-notes-container">
            <?php while ($row = $result->fetch_assoc()) : ?>
                <?php 
                    // Alternamos entre 4 colores pasteles sutiles
                    $claseColor = $coloresPostit[$contadorColores % count($coloresPostit)];
                    $contadorColores++;
                ?>
                <div class="sticky-note <?= $claseColor; ?>" id="note-<?= $row['id']; ?>">
                    
                    <?php if ($currentUserId == $row['user_id'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) : ?>
                        <span class="edit-btn" onclick='editNote(<?php echo $row['id']; ?>, <?php echo json_encode($row['note_text'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="Editar"><i class="fas fa-pen"></i></span>
                        <span class="close-btn" onclick="removeNote(<?php echo $row['id']; ?>)" title="Eliminar">×</span>
                    <?php endif; ?>

                    <div>
                        <span class="userid">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($row['user_name']); ?>
                        </span>
                        <div class="note-content">
                            <?php echo nl2br(htmlspecialchars($row['note_text'])); ?>
                        </div>
                    </div>

                    <span class="timestamp">
                        <i class="far fa-calendar-alt me-1"></i> <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?>
                    </span>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
        function togglePopup(popupId) {
            var popup = document.getElementById(popupId);
            var overlay = document.getElementById("overlay");

            if (popup.style.display === "block") {
                popup.style.display = "none";
                overlay.style.display = "none";
            } else {
                popup.style.display = "block";
                overlay.style.display = "block";
            }
        }

        function closeAllPopups() {
            document.getElementById("addNotePopup").style.display = "none";
            document.getElementById("editNotePopup").style.display = "none";
            document.getElementById("overlay").style.display = "none";
        }

        // Envío de formulario de adición usando AJAX
        $('#addNoteForm').submit(function(e) {
            e.preventDefault();
            var formData = $(this).serialize();

            $.ajax({
                type: 'POST',
                url: 'nt_add.php',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    togglePopup('addNotePopup'); // Cierra el popup tras enviar con éxito
                    var messageDiv = $('#message');
                    if (response.success) {
                        messageDiv.html('<div class="alert alert-success">' + response.message + '</div>');
                    } else {
                        messageDiv.html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                    
                    setTimeout(function() {
                        location.reload(); // Recarga la página para visualizar la nueva nota
                    }, 1000); 
                },
                error: function(error) {
                    console.log('Error:', error);
                }
            });
        });

        // Envío de formulario de edición usando AJAX
        $('#editNoteForm').submit(function(e) {
            e.preventDefault();
            var formData = $(this).serialize();

            $.ajax({
                type: 'POST',
                url: 'nt_edit.php',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    togglePopup('editNotePopup'); 
                    var messageDiv = $('#message');
                    if (response.success) {
                        messageDiv.html('<div class="alert alert-success">' + response.message + '</div>');
                    } else {
                        messageDiv.html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                    
                    setTimeout(function() {
                        location.reload(); // Recarga la página para visualizar la nota editada
                    }, 1000); 
                },
                error: function(error) {
                    console.log('Error:', error);
                }
            });
        });

        // Eliminación de nota usando AJAX
        function removeNote(noteId) {
            if (!confirm('¿Estás seguro de que quieres eliminar esta nota?')) {
                return;
            }
            $.ajax({
                type: 'POST',
                url: 'nt_rmv.php',
                data: {
                    note_id: noteId
                },
                dataType: 'json',
                success: function(response) {
                    var messageDiv = $('#message');
                    if (response.success) {
                        messageDiv.html('<div class="alert alert-success">' + response.message + '</div>');
                        $('#note-' + noteId).remove(); // Borra el elemento del DOM
                        
                        setTimeout(function() {
                            location.reload(); 
                        }, 1000);
                    } else {
                        messageDiv.html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                },
                error: function(error) {
                    console.log('Error:', error);
                }
            });
        }

        function editNote(noteId, noteText) {
            $('#edit_note_id').val(noteId);
            $('#edit_note_text').val(noteText);
            $('#editCharCount').text(250 - noteText.length + ' caracteres restantes');
            togglePopup('editNotePopup');
        }

        // Conteo de caracteres restantes (inputs de adición y edición)
        $('#note_text').on('input', function() {
            var maxLength = 250;
            var currentLength = $(this).val().length;
            var remaining = maxLength - currentLength;
            $('#charCount').text(remaining + ' caracteres restantes');
        });

        $('#edit_note_text').on('input', function() {
            var maxLength = 250;
            var currentLength = $(this).val().length;
            var remaining = maxLength - currentLength;
            $('#editCharCount').text(remaining + ' caracteres restantes');
        });
    </script>
</body>

</html>

<?php
// Cerrar las conexiones abiertas
$stmt->close();
$con->close();
?>
