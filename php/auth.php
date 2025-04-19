<?php
/**
 * Funciones de autenticación para el sistema
 */

// Verificar si el usuario está autenticado
function checkAuth($redirect = true) {
    // Iniciar sesión si no está activa
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar si existe la cookie de sesión
    if (!isset($_SESSION['JSESSIONID'])) {
        if ($redirect) {
            // Redirigir a la página de login
            header('Location: login.php');
            exit;
        }
        return false;
    }
    
    return true;
}

// Verificar CSRF token
function checkCSRF($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Generar CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION[CSRF_TOKEN_NAME];
}
