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
        /* 🎨 DISEÑO BASE ESTILO GOOGLE */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            background-color: #f8f9fa; /* Fondo grisáceo claro de Google */
            font-family: 'Poppins', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        .booking-fullscreen-container {
            flex: 1 0 auto; 
            display: flex;
            flex-direction: column;
            padding: 24px;
            box-sizing: border-box;
            width: 100%;
        }

        /* Cabecera superior moderna */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-header h3 {
            font-weight: 600;
            color: #3c4043;
            font-size: 1.5rem;
            margin: 0;
        }

        .btn-google {
            background-color: #ffffff;
            color: #3c4043;
            border: 1px solid #dadce0;
            border-radius: 24px; /* Súper redondeado */
            padding: 10px 24px;
            font-weight: 500;
            font-size: 0.9rem;
            box-shadow: 0 1px 3px rgba(60,64,67,0.15);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-google:hover {
            background-color: #f1f3f4;
            box-shadow: 0 1px 3px rgba(60,64,67,0.3);
            color: #1a73e8;
            transform: translateY(-1px);
        }

        /* 📑 PESTAÑAS ESTILO HOJAS DE EXCEL REDONDEADAS */
        .nav-tabs {
            border-bottom: 1px solid #dadce0;
            gap: 4px;
        }

        .nav-tabs .nav-link {
            color: #5f6368;
            font-weight: 500;
            font-size: 0.88rem;
            background-color: transparent;
            border: none;
            border-radius: 16px 16px 0 0; /* Bordes superiores curvos */
            padding: 10px 20px;
            transition: all 0.2s;
        }

        .nav-tabs .nav-link:hover {
            background-color: #f1f3f4;
            border: none;
            color: #3c4043;
        }

        .nav-tabs .nav-link.active {
            color: #1a73e8 !important; /* Azul Google */
            background-color: #ffffff !important;
            border: 1px solid #dadce0 !important;
            border-bottom: 1px solid #ffffff !important;
            box-shadow: 0 -2px 6px rgba(0,0,0,0.03);
        }

        /* 📊 CAJA DEL CALENDARIO (TIPO CARD GOOGLE) */
        .tab-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            border: 1px solid #dadce0;
            border-top: none;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); /* Sombra suave flotante */
        }

        .tab-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .calendar-box {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 550px;
        }

        /* 🚀 SOBREESCRITURA DE FULLCALENDAR (PARA ESTILO GOOGLE) */
        .fc {
            flex: 1;
            height: 100%;
            --fc-border-color: #e8eaed;
            --fc-today-bg-color: #e8f0fe;
        }

        /* Botones superiores del calendario */
        .fc .fc-button {
            background-color: #ffffff;
            border: 1px solid #dadce0;
            color: #3c4043;
            font-weight: 500;
            text-transform: capitalize;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .fc .fc-button:hover {
            background-color: #f1f3f4;
            color: #3c4043;
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active, 
        .fc .fc-button-primary:not(:disabled):active {
            background-color: #e8f0fe;
            color: #1a73e8;
            border-color: #1a73e8;
        }

        /* Título del Mes / Semana */
        .fc-toolbar-title {
            font-weight: 500 !important;
            color: #3c4043 !important;
            font-size: 1.25rem !important;
        }

        /* Redondear las pastillas de los eventos dentro del calendario */
        .fc-event {
            border-radius: 6px !important;
            border: none !important;
            padding: 2px 4px;
            font-size: 0.85rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        /* Footer no se encoge y se queda abajo */
        footer, #footer {
            flex-shrink: 0;
            width: 100%;
        }

        /* Redondear Modales (Formularios) */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dadce0;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1a73e8;
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.2);
        }
    </style>
</head>
<body>

<div class="booking-fullscreen-container">
    
    <div class="page-header">
        <h3>📅 Reservas de Salas del Bioterio</h3>
        <button type="button" class="btn-google" data-bs-toggle="modal" data-bs-target="#bookingModal">
            <i class="fas fa-plus" style="color: #1a73e8;"></i> Crear reserva
        </button>
    </div>

    <?php include('message.php'); ?>

    <ul class="nav nav-tabs" id="roomTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="sala1-tab" data-bs-toggle="tab" data-bs-target="#sala1" type="button" role="tab">Sala 1</button></li>
        <li class="nav-item"><button class="nav-link" id="sala4-tab" data-bs-toggle="tab" data-bs-target="#sala4" type="button" role="tab">Sala 4</button></li>
        <li class="nav-item"><button class="nav-link" id="sala5-tab" data-bs-toggle="tab" data-bs-target="#sala5" type="button" role="tab">Sala 5</button></li>
        <li class="nav-item"><button class="nav-link" id="cuarentena-tab" data-bs-toggle="tab" data-bs-target="#cuarentena" type="button" role="tab">Sala de Cuarentena</button></li>
        <li class="nav-item"><button class="nav-link" id="procedimientos-tab" data-bs-toggle="tab" data-bs-target="#procedimientos" type="button" role="tab">Sala de Procedimientos</button></li>
        <li class="nav-item"><button class="nav-link" id="conducta-tab" data-bs-toggle="tab" data-bs-target="#conducta" type="button" role="tab">Sala de Conducta</button></li>
        <li class="nav-item"><button class="nav-link" id="cfcrotarod-tab" data-bs-toggle="tab" data-bs-target="#cfcrotarod" type="button" role="tab">Sala CFC/RotaRod</button></li>
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
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title font-weight-bold" id="bookingModalLabel" style="color: #3c4043;">Reservar Sala</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold" style="font-size: 0.85rem; color: #5f6368;">SALA/ÁREA</label>
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
                        <label class="form-label font-weight-bold" style="font-size: 0.85rem; color: #5f6368;">TÍTULO DE USO</label>
                        <input type="text" class="form-control" name="title" placeholder="Ej: Protocolo de habituación 4" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold" style="font-size: 0.85rem; color: #5f6368;">INICIO</label>
                        <input type="datetime-local" class="form-control" name="start_event" id="start_event" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold" style="font-size: 0.85rem; color: #5f6368;">TÉRMINO</label>
                        <input type="datetime-local" class="form-control" name="end_event" id="end_event" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 8px;">Cerrar</button>
                    <button type="submit" name="save_booking" class="btn btn-primary" style="border-radius: 8px; background-color: #1a73e8; border: none;">Guardar Reserva</button>
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
            height: 'auto', 
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            locale: 'es',
            slotMinTime: '07:00:00',
            slotMaxTime: '21:00:00',
            allDaySlot: false, // Quita la fila superior de todo el día para que se vea más limpio
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
