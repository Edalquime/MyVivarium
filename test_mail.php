<?php
require 'vendor/autoload.php';
require 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
    $mail->addAddress(SMTP_USERNAME);
    $mail->Subject = 'Test MyVivarium';
    $mail->Body = 'Si recibes este correo, el SMTP funciona correctamente.';
    $mail->send();
    echo 'Correo enviado correctamente';
} catch (Exception $e) {
    echo 'Error: ' . $mail->ErrorInfo;
}
?>
