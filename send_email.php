#!/usr/bin/php
<?php

/**
 * Procesador de Cola de Correos Electrónicos
 *
 * Este script procesa una cola de correos electrónicos pendientes almacenados en la base de datos. 
 * Intenta enviar cada correo utilizando la biblioteca PHPMailer. Si un correo se envía con éxito, 
 * su estado se actualiza a 'sent' (enviado) en la base de datos. Si el envío falla, el estado se 
 * actualiza a 'failed' (fallido) y se registra un mensaje de error. El script está destinado a ser 
 * ejecutado desde la línea de comandos (CLI).
 */

require 'dbcon.php';  // Incluir archivo de conexión a la base de datos
require 'config.php';  // Incluir archivo de configuración de correo (SMTP)
require 'vendor/autoload.php'; // Cargar la biblioteca PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Función para enviar un correo electrónico usando PHPMailer
 * * @param mixed $recipients Destinatarios del correo, ya sea un string separado por comas o un array
 * @param string $subject Asunto del correo
 * @param string $body Cuerpo del correo
 * @return bool True si el correo se envió con éxito, false en caso contrario
 */
function sendEmail($recipients, $subject, $body)
{
    $mail = new PHPMailer(true);

    try {
        // Configuraciones del servidor SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;

        // Información del remitente
        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);

        // Manejar múltiples destinatarios
        if (is_array($recipients)) {
            foreach ($recipients as $recipient) {
                $mail->addAddress(trim($recipient));
            }
        } else {
            $recipientList = explode(',', $recipients);
            foreach ($recipientList as $recipient) {
                if (!empty(trim($recipient))) {
                    $mail->addAddress(trim($recipient)); // Caso de destinatario único o lista por comas
                }
            }
        }

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        // Enviar correo
        $mail->send();
        return true; 
    } catch (Exception $e) {
        // Registrar error en el log del servidor si falla el envío
        error_log('No se pudo enviar el correo. Error de PHPMailer: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Función para procesar y enviar correos pendientes de la cola
 */
function sendPendingEmails()
{
    global $con;

    // Obtener correos electrónicos pendientes de envío
    $stmt = $con->prepare("SELECT id, recipient, subject, body FROM outbox WHERE status = 'pending'");
    $stmt->execute();
    $stmt->store_result(); // Almacena el resultado para liberar la conexión para updates
    $stmt->bind_result($id, $recipient, $subject, $body);

    while ($stmt->fetch()) {
        // Intentar enviar el correo
        $result = sendEmail($recipient, $subject, $body);
        $sentAt = date('Y-m-d H:i:s');

        // Actualizar el estado del correo según el resultado
        if ($result) {
            $status = 'sent';
            $errorMessage = null;
        } else {
            $status = 'failed';
            $errorMessage = 'Error al enviar el correo electrónico'; // Se puede personalizar con información de error detallada
        }

        // Actualizar el estado del correo en la base de datos
        // Se abre y se cierra el updateStmt dentro del bucle para evitar el error "Commands out of sync" en MySQLi
        $updateStmt = $con->prepare("UPDATE outbox SET status = ?, sent_at = ?, error_message = ? WHERE id = ?");
        $updateStmt->bind_param("sssi", $status, $sentAt, $errorMessage, $id);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Cerrar la sentencia de selección
    $stmt->close();
}

// Ejecutar el script solo si se llama directamente desde la consola (CLI)
if (php_sapi_name() == "cli") {
    sendPendingEmails();
}

?>
