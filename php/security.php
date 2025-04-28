<?php
/**
 * Archivo de seguridad centralizado
 * Este archivo debe ser incluido al principio de cada archivo PHP público
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de seguridad
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
}

// Cabeceras de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdn.tailwindcss.com cdn.jsdelivr.net unpkg.com; style-src \'self\' \'unsafe-inline\' cdn.tailwindcss.com cdn.jsdelivr.net unpkg.com; img-src \'self\' data: unpkg.com tile.openstreetmap.org *.tile.openstreetmap.org *.locationiq.com; connect-src \'self\' localhost monitoreo.transporteurbanogps.click wss://monitoreo.transporteurbanogps.click *.locationiq.com');

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['user']) && isset($_SESSION['JSESSIONID']);
}

// Función para redirigir si no está autenticado
function requireAuth() {
    if (!isAuthenticated()) {
        // Determinar la ruta relativa correcta para la redirección
        $script_name = $_SERVER['SCRIPT_NAME'];
        $base_dir = substr($script_name, 0, strrpos($script_name, '/'));
        $base_dir = str_replace('/public', '', $base_dir);

        // Redirigir a la página de inicio
        header('Location: ' . $base_dir . '/index.php');
        exit;
    }
}

// Función para generar token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Función para validar token CSRF
function validateCSRFToken($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || $token !== $_SESSION[CSRF_TOKEN_NAME]) {
        http_response_code(403);
        die('Error de validación CSRF');
    }
    return true;
}
