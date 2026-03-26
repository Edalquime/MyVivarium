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
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas Adhesivas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }

        .sticky-notes-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
        }

        .sticky-note {
            background-color: #fff8b3;
            border: 1px solid #e6d381;
            padding: 20px 15px;
            margin-bottom: 15px;
            border-radius: 15px;
            position: relative;
            width: 300px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .sticky-note::before {
            content: '';
            width: 30px;
            height: 30px;
            background: url('images/pin-image.webp') no-repeat center center;
            background-size: contain;
            position: absolute;
            top: -10px;
            left: 2px;
        }

        .timestamp,
        .userid {
            display: block;
            font-size: 12px;
        }

        .timestamp {
            color: #888;
        }

        .userid {
            color: blue;
        }

        .close-btn,
        .edit-btn {
            cursor: pointer;
            position: absolute;
            top: 5px;
            font-weight: bold;
            color: #888;
        }

        .close-btn {
            right: 5px;
        }

        .edit-btn {
            right: 30px;
        }

        .close-btn:hover,
        .edit-btn:hover {
            color: #555;
        }

        .add-note-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border: none;
            cursor: pointer;
            margin-bottom: 25px;
            border-radius: 5px;
        }

        .popup,
        .edit-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 8px;
            z-index: 1000;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        #addNoteForm,
        #editNoteForm {
            display: flex;
            flex-direction: column;
            width: 380px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px;
            overflow-y: hidden;
        }

        #note_text,
        #edit_note_text {
            height: 100px;
            margin-bottom: 10px;
            resize: none;
            background-color: #fff8b3;
            padding: 5px;
        }

        #addNoteForm button,
        #editNoteForm button {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }

        #addNoteForm button:hover,
        #editNoteForm button:hover {
            background-color: #45a049;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }

        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }

        .char-count {
            font-size: 12px;
            color: #888;
            margin-bottom: 10px;
            text-align: right;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>

<body>
    <div class="nt-container" style="margin: 50px 0px;">
        <div id="message"></div> <button class="add-note-btn" onclick="togglePopup('addNotePopup')">Añadir Nota Adhesiva</button>
        <br>

        <div class="popup" id="addNotePopup">
            <span class="close-btn" onclick="togglePopup('addNotePopup')">X</span>
            <form id="addNoteForm" method="post">
                <?php if (isset($_GET['id'])) : ?>
                    <label for="cage_id">Para Jaula ID:
                        <?= htmlspecialchars($_GET['id']); ?>
                    </label>
                    <input type="hidden" id="cage_id" name="cage_id" value="<?= htmlspecialchars($_GET['id']); ?>">
                <?php endif; ?>
                <textarea id="note_text" name="note_text" placeholder="Escribe tu nota adhesiva aquí..." maxlength="250" required></textarea>
                <div class="char-count" id="charCount">250 caracteres restantes</div>
                <button type="submit" name="add_note">Añadir Nota</button>
            </form>
        </div>

        <div class="popup edit-popup" id="editNotePopup">
            <span class="close-btn" onclick="togglePopup('editNotePopup')">X</span>
            <form id="editNoteForm" method="post">
                <input type="hidden" id="edit_note_id" name="note_id">
                <textarea id="edit_note_text" name="note_text" placeholder="Edita tu nota adhesiva aquí..." maxlength="250" required></textarea>
                <div class="char-count" id="editCharCount">250 caracteres restantes</div>
                <button type="submit" name="edit_note">Editar Nota</button>
            </form>
        </div>

        <div class="overlay" id="overlay" onclick="closeAllPopups()"></div>

        <div class="sticky-notes-container">
            <?php while ($row = $result->fetch_assoc()) : ?>
                <div class="sticky-note" id="note-<?= $row['id']; ?>">
                    <?php if ($currentUserId == $row['user_id'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) : ?>
                        <span class="close-btn" onclick="removeNote(<?php echo $row['id']; ?>)">X</span>
                        <span class="edit-btn" onclick='editNote(<?php echo $row['id']; ?>, <?php echo json_encode($row['note_text'], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>✎</span>
                    <?php endif; ?>
                    <span class="userid">
                        <?php echo htmlspecialchars($row['user_name']); ?>
                    </span>
                    <p>
                        <?php echo nl2br(htmlspecialchars($row['note_text'])); ?>
                    </p>
                    <span class="timestamp">
                        <?php echo htmlspecialchars($row['created_at']); ?>
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
