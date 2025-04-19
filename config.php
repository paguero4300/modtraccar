<?php
/**
 * Configuración del Sistema de Monitoreo de Flota GPS
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de la API de Traccar
define('TRACCAR_API_URL', 'https://monitoreo.transporteurbanogps.click/api');
define('TRACCAR_WS_URL', 'wss://monitoreo.transporteurbanogps.click/api/socket');

// Configuración del mapa
define('MAP_DEFAULT_LAT', '19.4326'); // Ciudad de México
define('MAP_DEFAULT_LON', '-99.1332'); // Ciudad de México
define('MAP_DEFAULT_ZOOM', '12'); // Nivel de zoom

// Configuración de la aplicación
define('APP_NAME', 'Monitoreo de Flota GPS');
define('APP_VERSION', '1.0.0');

// Configuración de seguridad
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hora en segundos

// Configuración de la interfaz
define('REFRESH_INTERVAL', 15000); // 15 segundos para el polling fallback
define('MARKER_CLUSTER_THRESHOLD', 100); // Umbral para la clusterización de marcadores

// Credenciales por defecto (solo para desarrollo)
define('DEFAULT_EMAIL', 'demo@traccar.org');
define('DEFAULT_PASSWORD', 'demo');

// Cabeceras de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdn.tailwindcss.com cdn.jsdelivr.net unpkg.com; style-src \'self\' \'unsafe-inline\' cdn.tailwindcss.com cdn.jsdelivr.net unpkg.com; img-src \'self\' data: unpkg.com tile.openstreetmap.org *.tile.openstreetmap.org *.locationiq.com; connect-src \'self\' localhost monitoreo.transporteurbanogps.click wss://monitoreo.transporteurbanogps.click *.locationiq.com');

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

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['user']) && isset($_SESSION['JSESSIONID']);
}

// Función para redirigir si no está autenticado
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: index.php');
        exit;
    }
}


