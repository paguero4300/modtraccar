<?php
/**
 * Script independiente para obtener datos de ruta
 * Este script devuelve los datos en formato JSON para ser consumidos por aplicaciones cliente
 */

// Incluir archivos necesarios
require_once __DIR__ . '/../config.php';

// Definir constantes si no existen
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
}

// Funciones de autenticación
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

require_once __DIR__ . '/../php/api_proxy.php';

// Configurar cabeceras para JSON
header('Content-Type: application/json');

// Verificar autenticación
if (!checkAuth(false)) {
    echo json_encode([
        'success' => false,
        'error' => 'No autenticado'
    ]);
    exit;
}

// Verificar parámetros requeridos
if (!isset($_GET['deviceId']) || !isset($_GET['from']) || !isset($_GET['to'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Faltan parámetros requeridos (deviceId, from, to)'
    ]);
    exit;
}

// Obtener parámetros
$deviceId = intval($_GET['deviceId']);
$fromStr = $_GET['from'];
$toStr = $_GET['to'];

// Validar fechas
try {
    $fromDate = new DateTime($fromStr);
    $toDate = new DateTime($toStr);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Formato de fecha inválido'
    ]);
    exit;
}

// Formatear fechas para la API
$fromFormatted = $fromDate->format('Y-m-d\TH:i:s\Z');
$toFormatted = $toDate->format('Y-m-d\TH:i:s\Z');

// Crear instancia de la API
$api = new TraccarAPI($_SESSION['JSESSIONID']);

// Intentar obtener la ruta usando diferentes métodos
$result = null;
$error = null;
$method = isset($_GET['method']) ? $_GET['method'] : 'api';

try {
    // Método 1: API directa
    if ($method === 'api' || $method === 'all') {
        try {
            $result = $api->getRoute($deviceId, $fromFormatted, $toFormatted);
            if (is_array($result) && count($result) > 0) {
                echo json_encode([
                    'success' => true,
                    'method' => 'api',
                    'data' => $result,
                    'count' => count($result)
                ]);
                exit;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // Método 2: CURL
    if ($method === 'curl' || $method === 'all') {
        try {
            // Construir URL
            $url = TRACCAR_API_URL . "/reports/route?deviceId={$deviceId}&from={$fromFormatted}&to={$toFormatted}";

            // Inicializar CURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Cookie: JSESSIONID=' . $_SESSION['JSESSIONID']
            ]);

            // Ejecutar CURL
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode == 200 && !$curlError) {
                $curlResult = json_decode($response, true);
                if (is_array($curlResult) && count($curlResult) > 0) {
                    echo json_encode([
                        'success' => true,
                        'method' => 'curl',
                        'data' => $curlResult,
                        'count' => count($curlResult)
                    ]);
                    exit;
                }
            } else {
                $error = "CURL Error: " . ($curlError ?: "HTTP Code: {$httpCode}");
            }
        } catch (Exception $e) {
            $error = "CURL Exception: " . $e->getMessage();
        }
    }

    // Método 3: file_get_contents
    if ($method === 'file' || $method === 'all') {
        try {
            // Construir URL
            $url = TRACCAR_API_URL . "/reports/route?deviceId={$deviceId}&from={$fromFormatted}&to={$toFormatted}";

            // Configurar contexto
            $context = stream_context_create([
                'http' => [
                    'header' => "Accept: application/json\r\nCookie: JSESSIONID={$_SESSION['JSESSIONID']}\r\n"
                ]
            ]);

            // Obtener datos
            $response = @file_get_contents($url, false, $context);

            if ($response !== false) {
                $fileResult = json_decode($response, true);
                if (is_array($fileResult) && count($fileResult) > 0) {
                    echo json_encode([
                        'success' => true,
                        'method' => 'file',
                        'data' => $fileResult,
                        'count' => count($fileResult)
                    ]);
                    exit;
                }
            } else {
                $error = "file_get_contents Error: No se pudo obtener datos";
            }
        } catch (Exception $e) {
            $error = "file_get_contents Exception: " . $e->getMessage();
        }
    }

    // Si llegamos aquí, ningún método funcionó
    echo json_encode([
        'success' => false,
        'error' => $error ?: 'No se encontraron datos de ruta para el período seleccionado',
        'params' => [
            'deviceId' => $deviceId,
            'from' => $fromFormatted,
            'to' => $toFormatted
        ]
    ]);

} catch (Exception $e) {
    // Error general
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
