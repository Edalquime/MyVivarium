<?php
session_start();
require 'dbcon.php';

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

require 'header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reserva de Salas | Bioterio</title>
    
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

    <style>
        .booking-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        #calendar {
            max-width: 100%;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="booking-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📅 Reservas de Salas del Bioterio</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal">
            + Nueva Reserva
        </button>
    </div>

    <?php include('message.php'); ?>

    <div id="calendar"></div>
</div>

<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="booking_action.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="bookingModalLabel">Reservar Sala</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Sala/Área</label>
                        <select class="form-select" name="room_name" required>
                            <option value="Sala de Cirugía">Sala de Cirugía</option>
                            <option value="Sala de Comportamiento">Sala de Comportamiento</option>
                            <option value="Procedimientos Generales">Procedimientos Generales</option>
                            <option value="Cuarentena">Cuarentena</option>
                        </select>
                    </div>
                    <div class="mb-3">
    <label class="form-label font-weight-bold">🔍 Filtrar por Sala:</label>
    <select class="form-select" id="roomFilter" style="max-width: 300px;">
        <option value="">Todas las salas</option>
        <option value="Sala de Cirugía">Sala de Cirugía</option>
        <option value="Sala de Comportamiento">Sala de Comportamiento</option>
        <option value="Procedimientos Generales">Procedimientos Generales</option>
        <option value="Cuarentena">Cuarentena</option>
    </select>
</div>

<div id="calendar"></div>
                    <div class="mb-3">
                        <label class="form-label">Título/Descripción de uso</label>
                        <input type="text" class="form-control" name="title" placeholder="Ej: Eutanasia proyecto 32" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha y Hora de Inicio</label>
                        <input type="datetime-local" class="form-control" name="start_event" id="start_event" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha y Hora de Fin</label>
                        <input type="datetime-local" class="form-control" name="end_event" id="end_event" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" name="save_booking" class="btn btn-primary">Guardar Reserva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var roomFilter = document.getElementById('roomFilter');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        locale: 'es',
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        
        // 🔥 Modificado: Enviamos el parámetro 'room' al archivo PHP
        events: function(fetchInfo, successCallback, failureCallback) {
            $.ajax({
                url: 'booking_fetch.php',
                type: 'GET',
                data: {
                    room: roomFilter.value // Mandamos la sala seleccionada
                },
                success: function(data) {
                    successCallback(JSON.parse(data));
                },
                error: function() {
                    failureCallback();
                }
            });
        },
        selectable: true,

        eventClick: function(info) {
            if (confirm("¿Deseas CANCELAR la reserva: " + info.event.title + "?")) {
                $.ajax({
                    url: 'booking_action.php',
                    type: 'POST',
                    data: {
                        delete_booking: true,
                        booking_id: info.event.id
                    },
                    success: function(response) {
                        alert(response);
                        calendar.refetchEvents(); // Recarga
                    }
                });
            }
        }
    });

    calendar.render();

    // 🔥 Escuchar cuando el usuario cambia de sala en el desplegable
    roomFilter.addEventListener('change', function() {
        calendar.refetchEvents(); // Esto obliga a llamar de nuevo a booking_fetch.php con el nuevo filtro
    });

    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('start_event').min = now.toISOString().slice(0,16);
    document.getElementById('end_event').min = now.toISOString().slice(0,16);
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>
