<?php
/**
 * Archivo de diagnóstico para solucionar problemas
 */

// Mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnóstico del Sistema</h1>";

// Información de PHP
echo "<h2>Información de PHP</h2>";
echo "<p>Versión de PHP: " . phpversion() . "</p>";
echo "<p>Extensiones cargadas:</p>";
echo "<ul>";
foreach (get_loaded_extensions() as $ext) {
    echo "<li>$ext</li>";
}
echo "</ul>";

// Verificar extensiones requeridas
$requiredExtensions = ['curl', 'json', 'session', 'openssl'];
echo "<h2>Verificación de extensiones requeridas</h2>";
echo "<ul>";
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<li style='color:green'>$ext: Disponible</li>";
    } else {
        echo "<li style='color:red'>$ext: No disponible</li>";
    }
}
echo "</ul>";

// Verificar permisos de archivos
echo "<h2>Verificación de permisos de archivos</h2>";
$filesToCheck = [
    '../config.php',
    '../php/api_proxy.php',
    'index.php',
    'login.php',
    'map.php'
];
echo "<ul>";
foreach ($filesToCheck as $file) {
    if (file_exists($file)) {
        echo "<li>$file: Existe";
        echo " (Permisos: " . substr(sprintf('%o', fileperms($file)), -4) . ")";
        echo "</li>";
    } else {
        echo "<li style='color:red'>$file: No existe</li>";
    }
}
echo "</ul>";

// Verificar directorios
echo "<h2>Verificación de directorios</h2>";
$dirsToCheck = [
    '../php',
    '../js',
    '../assets'
];
echo "<ul>";
foreach ($dirsToCheck as $dir) {
    if (is_dir($dir)) {
        echo "<li>$dir: Existe";
        echo " (Permisos: " . substr(sprintf('%o', fileperms($dir)), -4) . ")";
        echo "</li>";
    } else {
        echo "<li style='color:red'>$dir: No existe</li>";
    }
}
echo "</ul>";

// Verificar sesión
echo "<h2>Verificación de sesión</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['test'] = 'Prueba de sesión';
echo "<p>ID de sesión: " . session_id() . "</p>";
echo "<p>Datos de sesión: " . print_r($_SESSION, true) . "</p>";

// Verificar conexión a la API de Traccar
echo "<h2>Verificación de conexión a la API de Traccar</h2>";
try {
    require_once '../config.php';
    echo "<p>URL de la API: " . TRACCAR_API_URL . "</p>";
    
    $ch = curl_init(TRACCAR_API_URL . '/server');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p>Código de respuesta: $httpCode</p>";
    if ($httpCode >= 200 && $httpCode < 300) {
        echo "<p style='color:green'>Conexión exitosa</p>";
        echo "<p>Respuesta: " . htmlspecialchars($response) . "</p>";
    } else {
        echo "<p style='color:red'>Error de conexión</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Información del servidor
echo "<h2>Información del servidor</h2>";
echo "<p>Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Directorio raíz: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Directorio actual: " . getcwd() . "</p>";
echo "<p>URI solicitada: " . $_SERVER['REQUEST_URI'] . "</p>";

// Verificar variables de entorno
echo "<h2>Variables de entorno</h2>";
echo "<pre>";
print_r($_SERVER);
echo "</pre>";
