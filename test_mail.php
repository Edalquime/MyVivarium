<?php
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);
require 'vendor/autoload.php';
require 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<pre>";
echo "SMTP_HOST: " . SMTP_HOST . "\n";
echo "SMTP_PORT: " . SMTP_PORT . "\n";
echo "SMTP_USERNAME: " . SMTP_USERNAME . "\n";
echo "SMTP_PASSWORD: " . substr(SMTP_PASSWORD, 0, 5) . "...\n";
echo "SMTP_ENCRYPTION: " . SMTP_ENCRYPTION . "\n";
echo "SENDER_EMAIL: " . SENDER_EMAIL . "\n";
echo "</pre>";

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USERNAME;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port = SMTP_PORT;
    $mail->SMTPDebug = 2;
    $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
    $mail->addAddress('eduardoquinonesmedina@gmail.com');
    $mail->Subject = 'Test MyVivarium';
    $mail->Body = 'Test de correo funcionando.';
    $mail->send();
    echo 'Correo enviado correctamente';
} catch (Exception $e) {
    echo 'Error: ' . $mail->ErrorInfo;
}
?>
