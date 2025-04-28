<?php
/**
 * Proxy para la API de Traccar (versión en carpeta public)
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user']) || !isset($_SESSION['JSESSIONID'])) {
    header('Location: index.php');
    exit;
}

// Incluir el archivo original
require_once __DIR__ . '/../php/api_proxy.php';
