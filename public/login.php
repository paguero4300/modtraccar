<?php
/**
 * Procesador de login
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/api_proxy.php';

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    header('Location: index.php?error=csrf');
    exit;
}

// Obtener credenciales
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Validar credenciales
if (empty($email) || empty($password)) {
    header('Location: index.php?error=empty');
    exit;
}

// Intentar login
$api = new TraccarAPI();
$result = $api->login($email, $password);

if ($result) {
    // Guardar datos de sesión
    $_SESSION['user'] = $result['user'];
    $_SESSION['JSESSIONID'] = $result['JSESSIONID'];
    
    // Redirigir al mapa
    header('Location: map.php');
    exit;
} else {
    // Error de autenticación
    header('Location: index.php?error=auth');
    exit;
}
