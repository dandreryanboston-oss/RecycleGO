<?php
// api/config.php
// Configuración principal
ini_set('display_errors', 0);
date_default_timezone_set('America/Bogota');

define('DB_HOST', '127.0.0.1:3308');
define('DB_NAME', 'recyclego');
define('DB_USER', 'root');
define('DB_PASS', ''); // <- coloca tu contraseña si aplica

// CORS & Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // en producción limita el origen
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight
    http_response_code(204);
    exit;
}

// Inicia sesión (si no existe)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
