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
            max-width: 1200px; /* Un poco más ancho para que quepan las 7 pestañas */
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: bold;
            cursor: pointer;
            font-size: 0.9rem; /* Un poco más pequeña la letra para que quepan bien */
        }
        .nav-tabs .nav-link.active {
            color: #0d6efd !important;
            border-bottom-color: #fff;
        }
        .calendar-box {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-top: none;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            background: #fff;
        }
        .fc-toolbar-title {
            font-size: 1.1rem !important;
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

    <ul class="nav nav-tabs" id="roomTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="sala1-tab" data-bs-toggle="tab" data-bs-target="#sala1" type="button" role="tab">Sala 1</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="sala4-tab" data-bs-toggle="tab" data-bs-target="#sala4" type="button" role="tab">Sala 4</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="sala5-tab" data-bs-toggle="tab" data-bs-target="#sala5" type="button" role="tab">Sala 5</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="cuarentena-tab" data-bs-toggle="tab" data-bs-target="#cuarentena" type="button" role="tab">Sala de Cuarentena</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="procedimientos-tab" data-bs-toggle="tab" data-bs-target="#procedimientos" type="button" role="tab">Sala de Procedimientos</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="conducta-tab" data-bs-toggle="tab" data-bs-target="#conducta" type="button" role="tab">Sala de Conducta</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="cfcrotarod-tab" data-bs-toggle="tab" data-bs-target="#cfcrotarod" type="button" role="tab">Sala CFC/RotaRod</button>
        </li>
    </ul>

    <div class="tab-content" id="roomTabsContent">
        
        <div class="tab-pane fade show active" id="sala1" role="tabpanel">
            <div class="calendar-box"> <div id="calendar-sala1"></div> </div>
        </div>

        <div class="tab-pane fade" id="sala4" role="tabpanel">
            <div class="calendar-box"> <div id="calendar-sala4"></div> </div>
        </div>

        <div class="tab-pane fade" id="sala5" role="tabpanel">
            <div class="calendar-box"> <div id="calendar-sala5"></div> </div>
        </div>

        <div class="tab-pane fade" id="cuarentena" role="tabpanel">
            <div class="calendar-box"> <div id="calendar-cuarentena"></div> </div>
        </div>

        <div class="tab-pane fade" id="procedimientos" role="tabpanel">
            <div class="calendar-box"> <div id="calendar-procedimientos"></div> </div>
        </div>

        <div class="tab-pane fade" id="conducta" role="tabpanel">
            <div class="calendar-box"> <div id="calendar-conducta"></div> </div>
        </div>

        <div class="tab-pane fade" id="cfcrotarod" role="tabpanel">
            <div class="calendar-box"> <div id="calendar-cfcrotarod"></div> </div>
        </div>

    </div>
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
                        <label class="form-label">Selecciona la Sala/Área</label>
                        <select class="form-select" name="room_name" required>
                            <option value="Sala 1">Sala 1</option>
                            <option value="Sala 4">Sala 4</option>
                            <option value="Sala 5">Sala 5</option>
                            <option value="Sala de Cuarentena">Sala de Cuarentena</option>
                            <option value="Sala de Procedimientos">Sala de Procedimientos</option>
                            <option value="Sala de Conducta">Sala de Conducta</option>
                            <option value="Sala CFC/RotaRod">Sala CFC/RotaRod</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Título/Descripción de uso</label>
                        <input type="text" class="form-control" name="title" placeholder="Ej: Comportamiento ratones Grupo A" required>
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
    
    const calendars = {};

    function initCalendar(elementId, roomName) {
        var calendarEl = document.getElementById(elementId);
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            locale: 'es',
            slotMinTime: '07:00:00',
            slotMaxTime: '21:00:00', // Modifiqué el horario de fin un poco más tarde
            events: function(fetchInfo, successCallback, failureCallback) {
                $.ajax({
                    url: 'booking_fetch.php',
                    type: 'GET',
                    data: { room: roomName },
                    success: function(data) {
                        successCallback(JSON.parse(data));
                    }
                });
            },
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
                            calendar.refetchEvents();
                        }
                    });
                }
            }
        });
        calendar.render();
        return calendar;
    }

    // Inicializamos la pestaña activa por defecto (Sala 1)
    calendars['sala1'] = initCalendar('calendar-sala1', 'Sala 1');

    const tabMapping = {
        'sala1-tab': { calendarId: 'calendar-sala1', room: 'Sala 1', key: 'sala1' },
        'sala4-tab': { calendarId: 'calendar-sala4', room: 'Sala 4', key: 'sala4' },
        'sala5-tab': { calendarId: 'calendar-sala5', room: 'Sala 5', key: 'sala5' },
        'cuarentena-tab': { calendarId: 'calendar-cuarentena', room: 'Sala de Cuarentena', key: 'cuarentena' },
        'procedimientos-tab': { calendarId: 'calendar-procedimientos', room: 'Sala de Procedimientos', key: 'procedimientos' },
        'conducta-tab': { calendarId: 'calendar-conducta', room: 'Sala de Conducta', key: 'conducta' },
        'cfcrotarod-tab': { calendarId: 'calendar-cfcrotarod', room: 'Sala CFC/RotaRod', key: 'cfcrotarod' }
    };

    var triggerTabList = [].slice.call(document.querySelectorAll('#roomTabs button'));
    triggerTabList.forEach(function (triggerEl) {
        triggerEl.addEventListener('shown.bs.tab', function (event) {
            const activeTabId = event.target.id;
            const map = tabMapping[activeTabId];

            if (!calendars[map.key]) {
                calendars[map.key] = initCalendar(map.calendarId, map.room);
            } else {
                calendars[map.key].updateSize();
            }
        });
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
