<?php
/**
 * Procesador de logout
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/api_proxy.php';

// Verificar si el usuario está autenticado
if (isAuthenticated()) {
    // Intentar logout en la API
    $api = new TraccarAPI($_SESSION['JSESSIONID']);
    $api->logout();
    
    // Destruir sesión local
    session_destroy();
}

// Redirigir al login
header('Location: index.php');
exit;
