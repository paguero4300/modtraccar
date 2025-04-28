<?php
/**
 * Script de prueba para la API externa
 * Este archivo hace una llamada directa a la API externa sin pasar por el resto del sistema
 */

// Establecer encabezados para JSON
header('Content-Type: application/json');

// Detalles de la API externa
$externalApiUrl = 'http://161.132.50.106:3001/vehiculo/despachos';
$apiKey = 'JYc6Dqs{bBg!HtWLFmPN9SjKCh#7a24M';
$apiKeyHeader = 'x-api-key';

// Inicializar cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $externalApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    $apiKeyHeader . ': ' . $apiKey,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30 segundos
curl_setopt($ch, CURLOPT_VERBOSE, true); // Habilitar modo verbose

// Capturar salida verbose para diagnóstico
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// Ejecutar la petición
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$curlInfo = curl_getinfo($ch);

// Preparar datos para la respuesta
$result = [
    'success' => false,
    'http_code' => $httpCode,
    'curl_error' => $error,
    'curl_info' => $curlInfo
];

// Si no hay error, añadir los datos
if (!$error && $httpCode >= 200 && $httpCode < 300) {
    $data = json_decode($response, true);
    if ($data !== null) {
        $result['success'] = true;
        $result['data'] = $data;
    } else {
        $result['json_error'] = json_last_error_msg();
        $result['raw_response'] = $response;
    }
} else {
    // Recuperar información verbose para diagnóstico
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    $result['verbose_log'] = $verboseLog;
    
    // Si hay respuesta, incluirla aunque haya error
    if ($response) {
        $result['raw_response'] = $response;
    }
}

// Cerrar curl
curl_close($ch);

// Devolver resultado
echo json_encode($result, JSON_PRETTY_PRINT);
