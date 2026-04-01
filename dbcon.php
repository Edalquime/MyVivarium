<?php
// Cargar el autoload de Composer
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Cargar variables del .env si existe (útil para local)
// En Railway, las variables se inyectan directamente al entorno
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Recuperar credenciales (buscando primero en el sistema y luego en el .env)
$servername = $_ENV['DB_HOST'] ?? 'localhost'; 
$username   = $_ENV['DB_USERNAME'] ?? 'root';
$password   = $_ENV['DB_PASSWORD'] ?? '';
$dbname     = $_ENV['DB_DATABASE'] ?? 'railway';
$port       = $_ENV['DB_PORT'] ?? 3306; // Plan B: puerto 3306 si no hay variable

// Crear la conexión usando el puerto (importante para Railway)
$con = new mysqli($servername, $username, $password, $dbname, (int)$port);

// Verificar la conexión
if ($con->connect_error) {
    error_log('Error de Conexión: ' . $con->connect_error);
    die('Error de conexión a la base de datos. Verifica tus variables en Railway.');
}

// Configurar charset a utf8 para evitar problemas con tildes o Ñ
$con->set_charset("utf8mb4");
