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
        /* 🔧 TRUCO FLEXBOX PARA PANTALLA COMPLETA Y FOOTER FIJO */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column; /* Apila Header, Contenido y Footer */
            background-color: #f4f6f9;
        }

        /* El contenedor principal se "estira" para empujar el footer al fondo */
        .booking-fullscreen-container {
            flex: 1 0 auto; 
            display: flex;
            flex-direction: column;
            padding: 15px;
            box-sizing: border-box;
            width: 100%;
        }

        /* Pestañas estilo Excel */
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            color: #495057;
            font-weight: bold;
            cursor: pointer;
            font-size: 0.9rem;
            background-color: #e9ecef;
            border: 1px solid #dee2e6;
            border-bottom: none;
            margin-right: 3px;
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd !important;
            background-color: #fff !important;
            border-bottom-color: #fff !important;
        }

        /* Caja de la Hoja activa que se estira al 100% del espacio disponible */
        .tab-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fff;
            border: 1px solid #dee2e6;
            border-top: none;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            padding: 15px;
        }

        .tab-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Estira el contenedor del calendario */
        .calendar-box {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 500px; /* Previene que colapse en pantallas muy pequeñas */
        }

        /* Estira FullCalendar internamente */
        .fc {
            flex: 1;
            height: 100%;
        }

        /* Footer no se encoge y se queda abajo */
        footer, #footer {
            flex-shrink: 0;
            width: 100%;
        }
    </style>
</head>
<body>

<div class="booking-fullscreen-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">📅 Reservas de Salas del Bioterio</h3>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal">
            + Nueva Reserva
        </button>
    </div>

    <?php include('message.php'); ?>

    <ul class="nav nav-tabs" id="roomTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="sala1-tab" data-bs-toggle="tab" data-bs-target="#sala1" type="button" role="tab">Sala 1</button></li>
        <li class="nav-item"><button class="nav-link" id="sala4-tab" data-bs-toggle="tab" data-bs-target="#sala4" type="button" role="tab">Sala 4</button></li>
        <li class="nav-item"><button class="nav-link" id="sala5-tab" data-bs-toggle="tab" data-bs-target="#sala5" type="button" role="tab">Sala 5</button></li>
        <li class="nav-item"><button class="nav-link" id="cuarentena-tab" data-bs-toggle="tab" data-bs-target="#cuarentena" type="button" role="tab">Cuarentena</button></li>
        <li class="nav-item"><button class="nav-link" id="procedimientos-tab" data-bs-toggle="tab" data-bs-target="#procedimientos" type="button" role="tab">Procedimientos</button></li>
        <li class="nav-item"><button class="nav-link" id="conducta-tab" data-bs-toggle="tab" data-bs-target="#conducta" type="button" role="tab">Conducta</button></li>
        <li class="nav-item"><button class="nav-link" id="cfcrotarod-tab" data-bs-toggle="tab" data-bs-target="#cfcrotarod" type="button" role="tab">CFC/RotaRod</button></li>
    </ul>

    <div class="tab-content" id="roomTabsContent">
        <div class="tab-pane fade show active" id="sala1" role="tabpanel"><div class="calendar-box"><div id="calendar-sala1"></div></div></div>
        <div class="tab-pane fade" id="sala4" role="tabpanel"><div class="calendar-box"><div id="calendar-sala4"></div></div></div>
        <div class="tab-pane fade" id="sala5" role="tabpanel"><div class="calendar-box"><div id="calendar-sala5"></div></div></div>
        <div class="tab-pane fade" id="cuarentena" role="tabpanel"><div class="calendar-box"><div id="calendar-cuarentena"></div></div></div>
        <div class="tab-pane fade" id="procedimientos" role="tabpanel"><div class="calendar-box"><div id="calendar-procedimientos"></div></div></div>
        <div class="tab-pane fade" id="conducta" role="tabpanel"><div class="calendar-box"><div id="calendar-conducta"></div></div></div>
        <div class="tab-pane fade" id="cfcrotarod" role="tabpanel"><div class="calendar-box"><div id="calendar-cfcrotarod"></div></div></div>
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
                        <input type="text" class="form-control" name="title" placeholder="Ej: Protocolo de habituación 4" required>
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
            height: 'auto', // 🚀 Truco de FullCalendar para ajustarse a su contenedor padre
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            locale: 'es',
            slotMinTime: '07:00:00',
            slotMaxTime: '21:00:00',
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

    // Inicializar primer calendario
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
                calendars[map.key].updateSize(); // Fuerza a que no se deforme al abrir la pestaña
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
