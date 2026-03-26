<?php

/**
 * Procesar Recordatorios
 * * Este script está diseñado para ejecutarse como una tarea programada (Cron Job) cada 5 minutos.
 * Lee los recordatorios activos de la base de datos y crea tareas basadas en sus configuraciones de recurrencia.
 * También programa correos electrónicos insertando registros en la tabla de bandeja de salida (outbox).
 */

require 'dbcon.php';

// Obtener la zona horaria de la tabla de configuraciones
$timezoneQuery = "SELECT value FROM settings WHERE name = 'timezone'";
$timezoneResult = mysqli_query($con, $timezoneQuery);
$timezoneRow = mysqli_fetch_assoc($timezoneResult);
$timezone = $timezoneRow['value'] ?? 'America/New_York';

// Establecer la zona horaria por defecto
date_default_timezone_set($timezone);

// Obtener la fecha y hora actuales
$now = new DateTime();

// Obtener recordatorios activos
$reminderQuery = "SELECT * FROM reminders WHERE status = 'active'";
$reminderResult = $con->query($reminderQuery);

if ($reminderResult) {
    while ($reminder = $reminderResult->fetch_assoc()) {
        $shouldTrigger = false;

        // Calcular la hora del recordatorio y la hora de creación de la tarea
        $reminderTime = new DateTime($reminder['time_of_day']);
        $taskCreationTime = clone $reminderTime;
        $taskCreationTime->modify('-1 day');

        // Ajustar la hora del recordatorio según la recurrencia
        if ($reminder['recurrence_type'] == 'daily') {
            // Establecer a la fecha de hoy
            $reminderTime->setDate($now->format('Y'), $now->format('m'), $now->format('d'));
            $taskCreationTime->setDate($now->format('Y'), $now->format('m'), $now->format('d'));
            $taskCreationTime->modify('-1 day');
        } elseif ($reminder['recurrence_type'] == 'weekly') {
            // Establecer a la próxima ocurrencia del día de la semana especificado
            $reminderTime->modify('next ' . $reminder['day_of_week']);
            $taskCreationTime = clone $reminderTime;
            $taskCreationTime->modify('-1 day');
        } elseif ($reminder['recurrence_type'] == 'monthly') {
            // Establecer al día especificado del mes
            $reminderDay = $reminder['day_of_month'];
            $reminderTime->setDate($now->format('Y'), $now->format('m'), $reminderDay);
            $taskCreationTime = clone $reminderTime;
            $taskCreationTime->modify('-1 day');
            
            // Si la fecha ya pasó, mover al próximo mes
            if ($reminderTime < $now) {
                $reminderTime->modify('+1 month');
                $taskCreationTime = clone $reminderTime;
                $taskCreationTime->modify('-1 day');
            }
        }

        // Verificar si debemos crear la tarea
        $lastTaskCreated = $reminder['last_task_created'] ? new DateTime($reminder['last_task_created']) : null;

        if ($now >= $taskCreationTime && $now < $reminderTime) {
            if (!$lastTaskCreated || $lastTaskCreated < $taskCreationTime) {
                $shouldTrigger = true;
            }
        } elseif ($now >= $reminderTime) {
            // Si ya pasó la hora del recordatorio y la tarea no se creó, crearla inmediatamente
            if (!$lastTaskCreated || $lastTaskCreated < $taskCreationTime) {
                $shouldTrigger = true;
            }
        }

        if ($shouldTrigger) {
            // Crear una nueva tarea
            $title = $reminder['title'];
            $description = $reminder['description'];
            $assignedBy = $reminder['assigned_by'];
            $assignedTo = $reminder['assigned_to'];
            $status = 'Pending'; // 'Pendiente' en el flujo interno de tu base de datos
            $cageId = $reminder['cage_id'];
            $dueDate = $reminderTime->format('Y-m-d H:i:s');

            $stmt = $con->prepare("INSERT INTO tasks (cage_id, title, description, assigned_by, assigned_to, completion_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssisss", $cageId, $title, $description, $assignedBy, $assignedTo, $dueDate, $status);
            $stmt->execute();
            $task_id = $stmt->insert_id;
            $stmt->close();

            // Actualizar last_task_created en la tabla de recordatorios
            $updateStmt = $con->prepare("UPDATE reminders SET last_task_created = ? WHERE id = ?");
            $currentTimeStr = $now->format('Y-m-d H:i:s');
            $updateStmt->bind_param("si", $currentTimeStr, $reminder['id']);
            $updateStmt->execute();
            $updateStmt->close();

            // Obtener correos electrónicos y nombres para assigned_by y assigned_to
            $emails = [];
            $assignedByName = '';
            $assignedToNames = [];

            // Obtener detalles de assigned_by (Asignado por)
            $userQuery = "SELECT name, username FROM users WHERE id = ?";
            $userStmt = $con->prepare($userQuery);
            $userStmt->bind_param("i", $assignedBy);
            $userStmt->execute();
            $userStmt->bind_result($assignedByName, $assignedByEmail);
            $userStmt->fetch();
            $userStmt->close();
            $emails[] = $assignedByEmail;

            // Obtener detalles de assigned_to (Asignado a)
            $assignedToArray = explode(',', $assignedTo);
            foreach ($assignedToArray as $assignedToUserId) {
                $userStmt = $con->prepare($userQuery);
                $userStmt->bind_param("i", $assignedToUserId);
                $userStmt->execute();
                $userStmt->bind_result($assignedToName, $assignedToEmail);
                if ($userStmt->fetch()) {
                    $assignedToNames[] = $assignedToName;
                    $emails[] = $assignedToEmail;
                }
                $userStmt->close();
            }
            $assignedToNamesString = implode(', ', $assignedToNames);

            // Preparar el contenido del correo electrónico en español
            $subject = "Nueva Tarea Creada desde Recordatorio: $title";
            $body = "Se ha creado una nueva tarea a partir de un recordatorio. Aquí están los detalles:<br><br>" .
                "<strong>Título:</strong> $title<br>" .
                "<strong>Descripción:</strong> $description<br>" .
                "<strong>Estado:</strong> Pendiente<br>" .
                "<strong>Fecha de Vencimiento / Realización:</strong> $dueDate<br>" .
                "<strong>Asignado por:</strong> $assignedByName<br>" .
                "<strong>Asignado a:</strong> $assignedToNamesString<br>";

            // Programar el envío del correo
            $scheduledAt = $now->format('Y-m-d H:i:s');
            $recipientList = implode(',', $emails);

            $emailStmt = $con->prepare("INSERT INTO outbox (task_id, recipient, subject, body, scheduled_at) VALUES (?, ?, ?, ?, ?)");
            $emailStmt->bind_param("issss", $task_id, $recipientList, $subject, $body, $scheduledAt);
            $emailStmt->execute();
            $emailStmt->close();
        }
    }
}
