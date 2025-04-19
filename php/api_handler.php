<?php
/**
 * Manejador de API para pruebas
 *
 * Esta clase proporciona una interfaz simplificada para probar
 * las funcionalidades de la API de Traccar.
 */

class ApiHandler {
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        // La sesión ya debería estar iniciada en el script principal
        // pero verificamos por si acaso
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Obtener la cookie de sesión
        $sessionCookie = isset($_SESSION['JSESSIONID']) ? $_SESSION['JSESSIONID'] : null;

        if (!$sessionCookie) {
            throw new Exception("No hay sesión activa. Por favor, inicie sesión primero.");
        }

        // Crear instancia de la API
        require_once __DIR__ . '/api_proxy.php';
        $this->api = new TraccarAPI($sessionCookie);
    }

    /**
     * Maneja una solicitud a la API
     *
     * @param string $action Acción a realizar
     * @param array $params Parámetros de la solicitud
     * @return array Respuesta de la API
     */
    public function handleRequest($action, $params = []) {
        $result = null;

        switch ($action) {
            case 'getRoute':
                if (isset($params['deviceId']) && isset($params['from']) && isset($params['to'])) {
                    // Registrar los parámetros para depuración
                    error_log("[DEBUG] ApiHandler - getRoute - Parámetros validados:");
                    error_log("[DEBUG] ApiHandler - getRoute - deviceId: " . json_encode($params['deviceId']));
                    error_log("[DEBUG] ApiHandler - getRoute - from: {$params['from']}");
                    error_log("[DEBUG] ApiHandler - getRoute - to: {$params['to']}");

                    // Intentar obtener la ruta usando diferentes métodos
                    $result = [];
                    $methods = ['api', 'curl', 'direct'];

                    foreach ($methods as $method) {
                        error_log("[DEBUG] ApiHandler - getRoute - Intentando obtener ruta con método: {$method}");

                        try {
                            switch ($method) {
                                case 'api':
                                    // Método 1: Usar la API normal
                                    $startTime = microtime(true);
                                    $result = $this->api->getRoute($params['deviceId'], $params['from'], $params['to']);
                                    $endTime = microtime(true);
                                    $duration = round(($endTime - $startTime) * 1000, 2);
                                    error_log("[DEBUG] ApiHandler - getRoute - Método 'api' completado en {$duration}ms");
                                    break;

                                case 'curl':
                                    // Método 2: Usar curl directamente
                                    $startTime = microtime(true);
                                    $url = $this->api->getApiUrl() . "/reports/route?deviceId={$params['deviceId']}&from={$params['from']}&to={$params['to']}";
                                    $ch = curl_init($url);
                                    curl_setopt_array($ch, [
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_HEADER => false,
                                        CURLOPT_HTTPHEADER => ['Accept: application/json'],
                                        CURLOPT_COOKIE => 'JSESSIONID=' . $this->api->getSessionCookie(),
                                        CURLOPT_SSL_VERIFYPEER => false,
                                        CURLOPT_SSL_VERIFYHOST => false,
                                        CURLOPT_TIMEOUT => 30
                                    ]);

                                    $curlResult = curl_exec($ch);
                                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                                    curl_close($ch);

                                    $endTime = microtime(true);
                                    $duration = round(($endTime - $startTime) * 1000, 2);

                                    error_log("[DEBUG] ApiHandler - getRoute - Método 'curl' completado en {$duration}ms: HTTP {$httpCode}, Content-Type: {$contentType}");

                                    if ($curlResult !== false) {
                                        $jsonResult = json_decode($curlResult, true);
                                        if ($jsonResult !== null || json_last_error() === JSON_ERROR_NONE) {
                                            $result = $jsonResult;
                                            error_log("[DEBUG] ApiHandler - getRoute - Método 'curl' obtuvo JSON válido");
                                        } else {
                                            error_log("[DEBUG] ApiHandler - getRoute - Método 'curl' no obtuvo JSON válido: " . json_last_error_msg());
                                            error_log("[DEBUG] ApiHandler - getRoute - Primeros 500 bytes: " . substr($curlResult, 0, 500));
                                        }
                                    }
                                    break;

                                case 'direct':
                                    // Método 3: Intentar acceder directamente a la API
                                    $startTime = microtime(true);
                                    $url = $this->api->getApiUrl() . "/reports/route?deviceId={$params['deviceId']}&from={$params['from']}&to={$params['to']}";

                                    // Crear un contexto con las cabeceras necesarias
                                    $options = [
                                        'http' => [
                                            'method' => 'GET',
                                            'header' => [
                                                'Accept: application/json',
                                                'Cookie: JSESSIONID=' . $this->api->getSessionCookie()
                                            ],
                                            'ignore_errors' => true,
                                            'timeout' => 30
                                        ],
                                        'ssl' => [
                                            'verify_peer' => false,
                                            'verify_peer_name' => false
                                        ]
                                    ];

                                    $context = stream_context_create($options);
                                    $directResult = @file_get_contents($url, false, $context);

                                    $endTime = microtime(true);
                                    $duration = round(($endTime - $startTime) * 1000, 2);

                                    error_log("[DEBUG] ApiHandler - getRoute - Método 'direct' completado en {$duration}ms");

                                    if ($directResult !== false) {
                                        $jsonResult = json_decode($directResult, true);
                                        if ($jsonResult !== null || json_last_error() === JSON_ERROR_NONE) {
                                            $result = $jsonResult;
                                            error_log("[DEBUG] ApiHandler - getRoute - Método 'direct' obtuvo JSON válido");
                                        } else {
                                            error_log("[DEBUG] ApiHandler - getRoute - Método 'direct' no obtuvo JSON válido: " . json_last_error_msg());
                                            error_log("[DEBUG] ApiHandler - getRoute - Primeros 500 bytes: " . substr($directResult, 0, 500));
                                        }
                                    }
                                    break;
                            }

                            // Si obtuvimos resultados, salir del bucle
                            if (is_array($result) && count($result) > 0) {
                                error_log("[DEBUG] ApiHandler - getRoute - Método '{$method}' obtuvo " . count($result) . " puntos. Usando estos resultados.");
                                break;
                            }
                        } catch (Exception $e) {
                            error_log("[DEBUG] ApiHandler - getRoute - Error con método '{$method}': " . $e->getMessage());
                        }
                    }

                    // Registrar el resultado final
                    if (is_array($result)) {
                        error_log("[DEBUG] ApiHandler - getRoute - Resultado final: " . count($result) . " puntos");
                    } else {
                        error_log("[DEBUG] ApiHandler - getRoute - Resultado final no es un array: " . gettype($result));
                        $result = [];
                    }
                } else {
                    error_log("[DEBUG] ApiHandler - getRoute - Parámetros faltantes");
                    $result = [];
                }
                break;

            case 'getPositions':
                $result = $this->api->getPositions();
                break;

            case 'getDevices':
                $result = $this->api->getDevices();
                break;

            default:
                $result = ['error' => 'Acción no válida'];
                break;
        }

        return [
            'success' => ($result !== false),
            'data' => $result
        ];
    }
}
