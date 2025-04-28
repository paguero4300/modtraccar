<?php
/**
 * Proxy genérico para la API de Traccar
 *
 * Este archivo maneja todas las solicitudes a la API de Traccar,
 * manteniendo la cookie de sesión y gestionando errores.
 */

require_once __DIR__ . '/../config.php';

class TraccarAPI {
    private $apiUrl;
    private $sessionCookie;

    /**
     * Obtiene la URL base de la API
     *
     * @return string URL base de la API
     */
    public function getApiUrl() {
        return $this->apiUrl;
    }

    /**
     * Obtiene la cookie de sesión actual
     *
     * @return string Cookie de sesión
     */
    public function getSessionCookie() {
        return $this->sessionCookie;
    }

    /**
     * Constructor
     *
     * @param string $sessionCookie Cookie JSESSIONID para autenticación
     */
    public function __construct($sessionCookie = null) {
        $this->apiUrl = TRACCAR_API_URL;
        $this->sessionCookie = $sessionCookie;
    }

    /**
     * Realiza login en la API de Traccar
     *
     * @param string $email Email del usuario
     * @param string $password Contraseña del usuario
     * @return array|false Datos del usuario o false si falla
     */
    public function login($email, $password) {
        $data = [
            'email' => $email,
            'password' => $password
        ];

        $response = $this->request('POST', '/session', $data, true);

        if ($response && isset($response['headers']['Set-Cookie'])) {
            // Extraer la cookie JSESSIONID
            preg_match('/JSESSIONID=([^;]+)/', $response['headers']['Set-Cookie'], $matches);
            if (isset($matches[1])) {
                $this->sessionCookie = $matches[1];
                return [
                    'user' => $response['body'],
                    'JSESSIONID' => $this->sessionCookie
                ];
            }
        }

        return false;
    }

    /**
     * Cierra la sesión en la API de Traccar
     *
     * @return bool Éxito de la operación
     */
    public function logout() {
        $response = $this->request('DELETE', '/session');
        return $response !== false;
    }

    /**
     * Obtiene los dispositivos del usuario
     *
     * @return array|false Lista de dispositivos o false si falla
     */
    /**
     * Obtiene los datos de los vehículos desde la API externa.
     *
     * @return array|false Datos de los vehículos externos o false si falla
     */
    public function getExternalVehicleData() {
        // Detalles de la API externa (podrían leerse de apivehiculos.txt dinámicamente)
        $externalApiUrl = 'http://161.132.50.106:3001/vehiculo/despachos';
        $apiKey = 'JYc6Dqs{bBg!HtWLFmPN9SjKCh#7a24M';
        $apiKeyHeader = 'x-api-key';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $externalApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            $apiKeyHeader . ': ' . $apiKey,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 segundos

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("[ERROR] TraccarAPI::getExternalVehicleData - Error cURL: " . $error);
            return false;
        }





        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            } else {
                error_log("[ERROR] TraccarAPI::getExternalVehicleData - Error decodificando JSON: " . json_last_error_msg());
                error_log("[ERROR] TraccarAPI::getExternalVehicleData - Respuesta recibida: " . $response);
                return false;
            }
        } else {
            error_log("[ERROR] TraccarAPI::getExternalVehicleData - Error HTTP: " . $httpCode);
            error_log("[ERROR] TraccarAPI::getExternalVehicleData - Respuesta recibida: " . $response);
            return false;
        }
    }

    /**
     * Obtiene los dispositivos del usuario combinados con datos externos.
     *
     * @return array|false Lista de dispositivos combinados o false si falla
     */
    public function getDevices() {
        $logFile = __DIR__ . "/traccar_devices.log";
        // 1. Obtener dispositivos de Traccar
        $traccarDevices = $this->request('GET', '/devices');
        if ($traccarDevices === false || !is_array($traccarDevices)) {
            error_log("[ERROR] TraccarAPI::getDevices - No se pudieron obtener los dispositivos de Traccar.");
            //return $traccarDevices; // Devolver el error original o false
        }
        
       
       //file_put_contents($logFile, date("Y-m-d H:i:s") . " TraccarAPI::getDevices: " . json_encode($traccarDevices) . PHP_EOL, FILE_APPEND);

        // 2. Obtener datos de la API externa
        $externalData = $this->getExternalVehicleData();
        if ($externalData === false || !is_array($externalData)) {
            error_log("[WARN] TraccarAPI::getDevices - No se pudieron obtener datos de la API externa o están vacíos. Devolviendo solo datos de Traccar.");
            // Si falla la API externa, devolvemos solo los de Traccar para no romper la funcionalidad
            //return $traccarDevices;
        }

        //file_put_contents($logFile, date("Y-m-d H:i:s") . " TraccarAPI::externalData: " . json_encode($externalData) . PHP_EOL, FILE_APPEND);


        // 3. Crear un mapa de búsqueda para los datos externos usando 'placa' como clave
        $externalDataMap = [];
        foreach ($externalData as $vehicle) {
            if (isset($vehicle['padron'])) {
                // Normalizar la placa (quitar espacios, convertir a mayúsculas) para mejorar la coincidencia
                $normalizedPlaca = strtoupper(trim($vehicle['padron']));
                $externalDataMap[$normalizedPlaca] = $vehicle;
            }
        }

        // 4. Combinar los datos
        $combinedDevices = [];
        foreach ($traccarDevices as $device) {
            $matched = false;
            // Intentar coincidir por uniqueId (IMEI/Identificador) o name (Nombre del dispositivo)
            // Asumimos que uno de estos campos contiene la placa
            $possibleKeys = [];
         
            if (isset($device['name'])) {
                // Manejo de caracteres escapados en JSON - el / se debe tratar como /
                $cleanName = str_replace('\/', '/', trim($device['name']));
                $nameParts = explode('/', $cleanName);
                $possibleKeys[] = strtoupper(trim($nameParts[0]));
            }


            foreach ($possibleKeys as $key) {
                if (isset($externalDataMap[$key])) {
                    // Verificar si attributes es un array
                    if (!isset($device['attributes']) || !is_array($device['attributes'])) {
                        $device['attributes'] = [];
                        file_put_contents($logFile, date("Y-m-d H:i:s") . " TraccarAPI::creatingAttributes: " . $key . PHP_EOL, FILE_APPEND);
                    }
                    
                    // Añadir los campos extra al dispositivo de Traccar
                    $device['attributes']['padron'] = $externalDataMap[$key]['padron'] ?? null;
                    $device['attributes']['terminal'] = $externalDataMap[$key]['terminal'] ?? null;
                    $device['attributes']['ultimo_despacho'] = $externalDataMap[$key]['ultimo_despacho'] ?? null;
                    
                    // Copiar los valores también a la raíz del objeto para compatibilidad con el frontend
                    $device['padron'] = $externalDataMap[$key]['padron'] ?? null;
                    $device['terminal'] = $externalDataMap[$key]['terminal'] ?? null;
                    $device['ultimo_despacho'] = $externalDataMap[$key]['ultimo_despacho'] ?? null;
                    
                    // Logear la asignación
                    file_put_contents($logFile, date("Y-m-d H:i:s") . " TraccarAPI::matchedDevice: " . $key . 
                        " - Padron: " . ($device['attributes']['padron'] ?? 'null') . PHP_EOL, FILE_APPEND);
                    
                    $matched = true;
                    break; // Salir del bucle de claves si se encuentra una coincidencia
                }
            }

            // Si no hubo coincidencia, añadir atributos vacíos o predeterminados si es necesario
            if (!$matched) {
                 $device['attributes']['padron'] = null;
                 $device['attributes']['terminal'] = null;
                 $device['attributes']['ultimo_despacho'] = null;
                 
                 // Copiar los valores nulos también a la raíz del objeto
                 $device['padron'] = null;
                 $device['terminal'] = null;
                 $device['ultimo_despacho'] = null;
            }

            $combinedDevices[] = $device;
        }

        return $combinedDevices;
    }

    /**
     * Obtiene las posiciones de los dispositivos
     *
     * @param int|null $deviceId ID del dispositivo (opcional)
     * @param string|null $from Fecha de inicio (opcional)
     * @param string|null $to Fecha de fin (opcional)
     * @return array|false Lista de posiciones o false si falla
     */
    public function getPositions($deviceId = null, $from = null, $to = null) {
        $params = [];

        if ($deviceId !== null) {
            $params['deviceId'] = $deviceId;
        }

        if ($from !== null && $to !== null) {
            $params['from'] = $from;
            $params['to'] = $to;
        }

        $url = '/positions';
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $this->request('GET', $url);
    }

    /**
     * Obtiene los tipos de comandos disponibles para un dispositivo
     *
     * @param int $deviceId ID del dispositivo
     * @return array|false Lista de tipos de comandos o false si falla
     */
    public function getCommandTypes($deviceId) {
        return $this->request('GET', '/commands/types?deviceId=' . urlencode($deviceId));
    }

    /**
     * Envía un comando a un dispositivo
     *
     * @param int $deviceId ID del dispositivo
     * @param string $type Tipo de comando
     * @param array $attributes Atributos adicionales del comando
     * @return array|false Respuesta del servidor o false si falla
     */
    public function sendCommand($deviceId, $type, $attributes = []) {
        $data = [
            'deviceId' => $deviceId,
            'type' => $type,
            'attributes' => $attributes
        ];

        return $this->request('POST', '/commands/send', $data);
    }

    /**
     * Obtiene el historial de ruta de un dispositivo
     *
     * @param int $deviceId ID del dispositivo
     * @param string $from Fecha de inicio (ISO 8601)
     * @param string $to Fecha de fin (ISO 8601)
     * @return array|false Lista de posiciones o false si falla
     */
    public function getRoute($deviceId, $from, $to) {
        // Usar exclusivamente el endpoint de reportes
        $url = "/reports/route?deviceId={$deviceId}&from={$from}&to={$to}";

        error_log("[DEBUG] TraccarAPI::getRoute - Solicitando ruta para dispositivo ID: {$deviceId}");
        error_log("[DEBUG] TraccarAPI::getRoute - Rango de fechas: {$from} a {$to}");
        error_log("[DEBUG] TraccarAPI::getRoute - URL completa: {$this->apiUrl}{$url}");

        // Verificar si las fechas son válidas
        $fromDate = new DateTime($from);
        $toDate = new DateTime($to);
        $now = new DateTime();

        error_log("[DEBUG] TraccarAPI::getRoute - Fecha actual: " . $now->format('Y-m-d\TH:i:s\Z'));
        error_log("[DEBUG] TraccarAPI::getRoute - Fecha 'from' parseada: " . $fromDate->format('Y-m-d\TH:i:s\Z'));
        error_log("[DEBUG] TraccarAPI::getRoute - Fecha 'to' parseada: " . $toDate->format('Y-m-d\TH:i:s\Z'));

        // Verificar si las fechas están en el futuro
        if ($fromDate > $now) {
            error_log("[DEBUG] TraccarAPI::getRoute - ADVERTENCIA: La fecha 'from' está en el futuro");
        }
        if ($toDate > $now) {
            error_log("[DEBUG] TraccarAPI::getRoute - ADVERTENCIA: La fecha 'to' está en el futuro");
        }

        // Probar con fechas alternativas si estamos en modo de depuración
        // Esto es solo para verificar si hay datos disponibles en el sistema
        $testPastDate = true; // Cambiar a false para desactivar esta prueba

        if ($testPastDate && ($fromDate > $now || $toDate > $now)) {
            // Crear fechas de prueba en el pasado (7 días atrás hasta hoy)
            $testFromDate = clone $now;
            $testFromDate->modify('-7 days');
            $testToDate = clone $now;

            $testFromStr = $testFromDate->format('Y-m-d\TH:i:s\Z');
            $testToStr = $testToDate->format('Y-m-d\TH:i:s\Z');

            error_log("[DEBUG] TraccarAPI::getRoute - PRUEBA: Intentando con fechas alternativas en el pasado");
            error_log("[DEBUG] TraccarAPI::getRoute - PRUEBA: Rango de fechas alternativo: {$testFromStr} a {$testToStr}");

            // Construir URL de prueba
            $testUrl = "/reports/route?deviceId={$deviceId}&from={$testFromStr}&to={$testToStr}";
            error_log("[DEBUG] TraccarAPI::getRoute - PRUEBA: URL alternativa: {$this->apiUrl}{$testUrl}");

            // Realizar solicitud de prueba
            try {
                $testResult = $this->request('GET', $testUrl);
                if (is_array($testResult)) {
                    error_log("[DEBUG] TraccarAPI::getRoute - PRUEBA: Resultado con fechas alternativas: " . count($testResult) . " puntos");
                } else {
                    error_log("[DEBUG] TraccarAPI::getRoute - PRUEBA: No se obtuvieron datos con fechas alternativas");
                }
            } catch (Exception $e) {
                error_log("[DEBUG] TraccarAPI::getRoute - PRUEBA: Error al probar con fechas alternativas: " . $e->getMessage());
            }
        }

        // Verificar el intervalo de tiempo
        $interval = $fromDate->diff($toDate);
        $intervalStr = $interval->format('%d días, %h horas, %i minutos');
        error_log("[DEBUG] TraccarAPI::getRoute - Intervalo de tiempo: {$intervalStr}");

        // Verificar si el dispositivo existe
        try {
            $deviceResponse = $this->request('GET', "/devices/{$deviceId}");
            if ($deviceResponse) {
                error_log("[DEBUG] TraccarAPI::getRoute - Dispositivo encontrado: " . json_encode($deviceResponse));
            } else {
                error_log("[DEBUG] TraccarAPI::getRoute - ADVERTENCIA: No se pudo verificar si el dispositivo existe");
            }
        } catch (Exception $e) {
            error_log("[DEBUG] TraccarAPI::getRoute - Error al verificar dispositivo: " . $e->getMessage());
        }

        // Crear opciones específicas para asegurar que se reciba JSON
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',  // Forzar respuesta en JSON
                    'Content-Type: application/json'
                ],
                'ignore_errors' => true,
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];

        // Imprimir la URL completa para depuración y prueba manual
        $fullUrl = $this->apiUrl . $url;
        error_log("[DEBUG] TraccarAPI::getRoute - URL completa para prueba manual: {$fullUrl}");
        error_log("[DEBUG] TraccarAPI::getRoute - Comando curl equivalente: curl -H 'Accept: application/json' -H 'Cookie: JSESSIONID={$this->sessionCookie}' '{$fullUrl}'");

        // Añadir cookie de sesión si existe
        if ($this->sessionCookie) {
            $options['http']['header'][] = 'Cookie: JSESSIONID=' . $this->sessionCookie;
        }

        error_log("[DEBUG] TraccarAPI::getRoute - Usando cabeceras específicas para forzar JSON: " . json_encode($options['http']['header']));

        $startTime = microtime(true);

        // Usar file_get_contents directamente para este endpoint específico
        $fullUrl = $this->apiUrl . $url;
        $context = stream_context_create($options);

        error_log("[DEBUG] TraccarAPI::getRoute - Realizando solicitud GET a {$fullUrl}");

        // Intentar con file_get_contents primero
        error_log("[DEBUG] TraccarAPI::getRoute - Intentando obtener ruta con file_get_contents");
        $rawResult = @file_get_contents($fullUrl, false, $context);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // en milisegundos

        if ($rawResult === false) {
            error_log("[DEBUG] TraccarAPI::getRoute - Error al obtener ruta con file_get_contents. Intentando con curl");

            // Intentar con curl como alternativa
            $ch = curl_init($fullUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_COOKIE => 'JSESSIONID=' . $this->sessionCookie,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30
            ]);

            error_log("[DEBUG] TraccarAPI::getRoute - Ejecutando curl con cabeceras: Accept: application/json, Cookie: JSESSIONID={$this->sessionCookie}");
            $curlResult = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            error_log("[DEBUG] TraccarAPI::getRoute - Respuesta curl: HTTP {$httpCode}, Content-Type: {$contentType}");

            if ($curlResult === false) {
                error_log("[DEBUG] TraccarAPI::getRoute - Error curl: {$error}");
                // Si falla curl, intentar con el método request normal
                $result = $this->request('GET', $url);
            } else {
                error_log("[DEBUG] TraccarAPI::getRoute - Respuesta curl recibida. Longitud: " . strlen($curlResult) . " bytes");

                // Verificar si es JSON válido
                $result = json_decode($curlResult, true);
                if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
                    error_log("[DEBUG] TraccarAPI::getRoute - Error al decodificar JSON de curl: " . json_last_error_msg());
                    error_log("[DEBUG] TraccarAPI::getRoute - Primeros 500 bytes de la respuesta curl: " . substr($curlResult, 0, 500));

                    // Si no es JSON válido pero es Excel, intentar convertirlo
                    if (strpos($contentType, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') !== false) {
                        error_log("[DEBUG] TraccarAPI::getRoute - La respuesta es un archivo Excel. Intentando procesar manualmente");

                        // Guardar el Excel temporalmente
                        $tempFile = tempnam(sys_get_temp_dir(), 'route_excel_');
                        file_put_contents($tempFile, $curlResult);
                        error_log("[DEBUG] TraccarAPI::getRoute - Excel guardado temporalmente en: {$tempFile}");

                        // Aquí podríamos implementar un parser de Excel si fuera necesario
                        // Por ahora, devolvemos un array vacío
                        $result = [];
                    } else {
                        // Si no es JSON ni Excel, intentar con el método request normal
                        $result = $this->request('GET', $url);
                    }
                } else {
                    error_log("[DEBUG] TraccarAPI::getRoute - JSON de curl decodificado correctamente");
                }
            }
        } else {
            error_log("[DEBUG] TraccarAPI::getRoute - Respuesta file_get_contents recibida. Longitud: " . strlen($rawResult) . " bytes");

            // Verificar si es JSON válido
            $result = json_decode($rawResult, true);
            if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log("[DEBUG] TraccarAPI::getRoute - Error al decodificar JSON de file_get_contents: " . json_last_error_msg());
                error_log("[DEBUG] TraccarAPI::getRoute - Primeros 500 bytes de la respuesta file_get_contents: " . substr($rawResult, 0, 500));

                // Intentar con curl como alternativa
                error_log("[DEBUG] TraccarAPI::getRoute - Intentando con curl como alternativa");

                $ch = curl_init($fullUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => false,
                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                    CURLOPT_COOKIE => 'JSESSIONID=' . $this->sessionCookie,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT => 30
                ]);

                $curlResult = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);

                error_log("[DEBUG] TraccarAPI::getRoute - Respuesta curl alternativa: HTTP {$httpCode}, Content-Type: {$contentType}");

                if ($curlResult !== false) {
                    $result = json_decode($curlResult, true);
                    if ($result !== null || json_last_error() === JSON_ERROR_NONE) {
                        error_log("[DEBUG] TraccarAPI::getRoute - JSON de curl alternativo decodificado correctamente");
                    } else {
                        error_log("[DEBUG] TraccarAPI::getRoute - Error al decodificar JSON de curl alternativo");
                        // Si todo falla, intentar con el método request normal
                        $result = $this->request('GET', $url);
                    }
                } else {
                    // Si todo falla, intentar con el método request normal
                    $result = $this->request('GET', $url);
                }
            } else {
                error_log("[DEBUG] TraccarAPI::getRoute - JSON de file_get_contents decodificado correctamente");
            }
        }

        if ($result === false) {
            error_log("[DEBUG] TraccarAPI::getRoute - Error al obtener ruta. La solicitud falló.");
            return false;
        }

        $count = is_array($result) ? count($result) : 0;
        error_log("[DEBUG] TraccarAPI::getRoute - Respuesta recibida en {$duration}ms con {$count} puntos");

        if ($count > 0 && is_array($result)) {
            // Verificar si los puntos pertenecen al dispositivo solicitado
            $deviceIds = array_unique(array_column($result, 'deviceId'));
            $deviceIdsStr = implode(', ', $deviceIds);
            error_log("[DEBUG] TraccarAPI::getRoute - IDs de dispositivos en la respuesta: {$deviceIdsStr}");

            // Verificar el primer y último punto
            $firstPoint = reset($result);
            $lastPoint = end($result);

            if ($firstPoint && $lastPoint) {
                error_log("[DEBUG] TraccarAPI::getRoute - Primer punto: ID={$firstPoint['id']}, deviceId={$firstPoint['deviceId']}, tiempo={$firstPoint['deviceTime']}");
                error_log("[DEBUG] TraccarAPI::getRoute - Último punto: ID={$lastPoint['id']}, deviceId={$lastPoint['deviceId']}, tiempo={$lastPoint['deviceTime']}");
            }
        } else {
            error_log("[DEBUG] TraccarAPI::getRoute - No se encontraron puntos para el dispositivo {$deviceId} en el rango especificado");
        }

        return $result;
    }

    /**
     * Realiza una solicitud a la API de Traccar
     *
     * @param string $method Método HTTP (GET, POST, PUT, DELETE)
     * @param string $endpoint Endpoint de la API
     * @param array|null $data Datos a enviar (opcional)
     * @param bool $returnHeaders Si se deben devolver las cabeceras
     * @return array|false Respuesta del servidor o false si falla
     */
    public function request($method, $endpoint, $data = null, $returnHeaders = false) {
        try {
            $url = $this->apiUrl . $endpoint;

            $options = [
                'http' => [
                    'method' => $method,
                    'header' => [
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ],
                    'ignore_errors' => true,
                    'timeout' => 30 // Aumentar el tiempo de espera a 30 segundos
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];

            // Asegurarse de que siempre se solicite JSON para el endpoint de reportes
            if (strpos($endpoint, '/reports/') === 0) {
                // Asegurarse de que Accept: application/json esté presente y sea la primera opción
                $hasAcceptHeader = false;
                foreach ($options['http']['header'] as $key => $header) {
                    if (strpos($header, 'Accept:') === 0) {
                        $options['http']['header'][$key] = 'Accept: application/json';
                        $hasAcceptHeader = true;
                        break;
                    }
                }
                if (!$hasAcceptHeader) {
                    $options['http']['header'][] = 'Accept: application/json';
                }
                error_log("[DEBUG] TraccarAPI::request - Forzando cabecera Accept: application/json para endpoint de reportes");
            }

            // Añadir cookie de sesión si existe
            if ($this->sessionCookie) {
                $options['http']['header'][] = 'Cookie: JSESSIONID=' . $this->sessionCookie;
            }

            // Añadir datos si existen
            if ($data !== null) {
                if ($endpoint === '/session' && $method === 'POST') {
                    // Para el login, usar application/x-www-form-urlencoded
                    $options['http']['header'][0] = 'Content-Type: application/x-www-form-urlencoded';
                    $options['http']['content'] = http_build_query($data);
                } else {
                    $options['http']['content'] = json_encode($data);
                }
            }

            $context = stream_context_create($options);

            // Registrar la solicitud para depuración
            error_log("[DEBUG] TraccarAPI::request - Iniciando solicitud $method $url");
            error_log("[DEBUG] TraccarAPI::request - Headers: " . json_encode($options['http']['header']));
            if (isset($options['http']['content'])) {
                error_log("[DEBUG] TraccarAPI::request - Content: " . substr($options['http']['content'], 0, 500) . (strlen($options['http']['content']) > 500 ? '...' : ''));
            }

            // Registrar la URL completa para depuración
            $urlParts = parse_url($url);
            error_log("[DEBUG] TraccarAPI::request - URL desglosada:");
            error_log("[DEBUG] TraccarAPI::request - Esquema: {$urlParts['scheme']}");
            error_log("[DEBUG] TraccarAPI::request - Host: {$urlParts['host']}");
            if (isset($urlParts['port'])) {
                error_log("[DEBUG] TraccarAPI::request - Puerto: {$urlParts['port']}");
            }
            error_log("[DEBUG] TraccarAPI::request - Ruta: {$urlParts['path']}");
            if (isset($urlParts['query'])) {
                error_log("[DEBUG] TraccarAPI::request - Query: {$urlParts['query']}");
            }

            // Registrar todas las cabeceras que se envían
            error_log("[DEBUG] TraccarAPI::request - Cabeceras completas: " . implode("\n", $options['http']['header']));

            // Intentar obtener el contenido con manejo de errores
            error_log("[DEBUG] TraccarAPI::request - Iniciando solicitud a {$url}");
            $result = @file_get_contents($url, false, $context);

            // Registrar todas las cabeceras de respuesta
            if (isset($http_response_header)) {
                error_log("[DEBUG] TraccarAPI::request - Cabeceras de respuesta completas: " . implode("\n", $http_response_header));
            }

            // Registrar la respuesta para depuración
            $endTime = microtime(true);
            $startTime = isset($startTime) ? $startTime : $endTime - 0.001; // Fallback si no se definió startTime
            $duration = round(($endTime - $startTime) * 1000, 2); // en milisegundos

            if ($result === false) {
                error_log("[DEBUG] TraccarAPI::request - Error en la solicitud a $url (después de {$duration}ms)");

                if (isset($http_response_header)) {
                    error_log("[DEBUG] TraccarAPI::request - Headers de respuesta: " . json_encode($http_response_header));

                    // Extraer código de estado HTTP
                    $statusLine = $http_response_header[0] ?? '';
                    if (preg_match('/HTTP\/\d\.\d\s+(\d+)\s+(.*)/', $statusLine, $matches)) {
                        $statusCode = $matches[1];
                        $statusText = $matches[2];
                        error_log("[DEBUG] TraccarAPI::request - Código de estado HTTP: {$statusCode} {$statusText}");
                    }
                } else {
                    error_log("[DEBUG] TraccarAPI::request - No hay headers de respuesta disponibles");
                }

                $error = error_get_last();
                if ($error) {
                    error_log("[DEBUG] TraccarAPI::request - Error PHP: " . json_encode($error));
                }

                // Si hay un error, devolver un array vacío en lugar de false
                if (strpos($endpoint, '/reports/route') === 0) {
                    error_log("[DEBUG] TraccarAPI::request - Devolviendo array vacío para ruta debido al error");
                    return [];
                }
                return false;
            } else {
                error_log("[DEBUG] TraccarAPI::request - Respuesta recibida de $url en {$duration}ms");

                if (isset($http_response_header)) {
                    // Extraer código de estado HTTP
                    $statusLine = $http_response_header[0] ?? '';
                    if (preg_match('/HTTP\/\d\.\d\s+(\d+)\s+(.*)/', $statusLine, $matches)) {
                        $statusCode = $matches[1];
                        $statusText = $matches[2];
                        error_log("[DEBUG] TraccarAPI::request - Código de estado HTTP: {$statusCode} {$statusText}");
                    }
                }

                error_log("[DEBUG] TraccarAPI::request - Longitud de la respuesta: " . strlen($result) . " bytes");

                // Verificar si la respuesta es JSON válido
                $isJson = json_decode($result) !== null;
                error_log("[DEBUG] TraccarAPI::request - La respuesta es JSON válido: " . ($isJson ? 'Sí' : 'No'));

                // Mostrar los primeros 500 caracteres de la respuesta
                error_log("[DEBUG] TraccarAPI::request - Primeros 500 caracteres: " . substr($result, 0, 500) . (strlen($result) > 500 ? '...' : ''));
            }

            if ($returnHeaders) {
                return [
                    'body' => $result !== false ? json_decode($result, true) : false,
                    'headers' => $http_response_header ? $this->parseHeaders($http_response_header) : []
                ];
            }

            $decoded = json_decode($result, true);
            return $decoded !== null ? $decoded : [];
        } catch (Exception $e) {
            // En caso de excepción, devolver un array vacío para rutas o false para otros endpoints
            if (strpos($endpoint, '/reports/route') === 0) {
                return [];
            }
            return false;
        }
    }

    /**
     * Parsea las cabeceras HTTP
     *
     * @param array $headers Cabeceras HTTP
     * @return array Cabeceras parseadas
     */
    private function parseHeaders($headers) {
        $result = [];

        foreach ($headers as $header) {
            if (strpos($header, ':') !== false) {
                list($key, $value) = explode(':', $header, 2);
                $result[trim($key)] = trim($value);
            } else {
                $result[] = $header;
            }
        }

        return $result;
    }
}

// Si se llama directamente a este archivo, actuar como API proxy
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Content-Type: application/json');

    // Verificar si es una solicitud POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        exit;
    }

    // Verificar token CSRF (excepto para getExternalVehicleData en modo DEBUG)
    $action = $_POST['action'] ?? '';
    if ($action !== 'getExternalVehicleData' && (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token']))) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido']);
        exit;
    }

    // Obtener datos de la solicitud
    $action = $_POST['action'] ?? '';
    $params = $_POST['params'] ?? [];

    // Verificar si el usuario está autenticado (excepto para login y getExternalVehicleData)
    if ($action !== 'login' && $action !== 'getExternalVehicleData' && !isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }

    // Crear instancia de la API
    $api = new TraccarAPI($_SESSION['JSESSIONID'] ?? null);

    // Procesar la acción
    $result = false;

    switch ($action) {
        case 'login':
            if (isset($params['email']) && isset($params['password'])) {
                $result = $api->login($params['email'], $params['password']);
                if ($result) {
                    $_SESSION['user'] = $result['user'];
                    $_SESSION['JSESSIONID'] = $result['JSESSIONID'];
                }
            }
            break;

        case 'logout':
            $result = $api->logout();
            if ($result) {
                session_destroy();
            }
            break;

        case 'getDevices':
            $result = $api->getDevices();
            break;

        case 'getPositions':
            $deviceId = $params['deviceId'] ?? null;
            $from = $params['from'] ?? null;
            $to = $params['to'] ?? null;
            $result = $api->getPositions($deviceId, $from, $to);
            break;

        case 'getCommandTypes':
            if (isset($params['deviceId'])) {
                $result = $api->getCommandTypes($params['deviceId']);
            }
            break;

        case 'sendCommand':
            if (isset($params['deviceId']) && isset($params['type'])) {
                $attributes = $params['attributes'] ?? [];
                $result = $api->sendCommand($params['deviceId'], $params['type'], $attributes);
            }
            break;

        case 'getExternalVehicleData':
            error_log("[DEBUG] API Proxy - Recibida solicitud getExternalVehicleData");
            try {
                $result = $api->getExternalVehicleData();
                if ($result === false) {
                    error_log("[DEBUG] API Proxy - getExternalVehicleData retornó false");
                    $result = ['success' => false, 'message' => 'Error al obtener datos de vehículos externos', 'error' => 'API externa no disponible'];
                } else {
                    error_log("[DEBUG] API Proxy - getExternalVehicleData ejecutado con éxito");
                    $result = ['success' => true, 'data' => $result];
                }
            } catch (Exception $e) {
                error_log("[DEBUG] API Proxy - Error en getExternalVehicleData: " . $e->getMessage());
                $result = ['success' => false, 'message' => 'Error al obtener datos de vehículos externos', 'error' => $e->getMessage()];
            }
            break;

        case 'getRoute':
            error_log("[DEBUG] API Proxy - Recibida solicitud getRoute");
            error_log("[DEBUG] API Proxy - Parámetros recibidos: " . json_encode($params));

            if (isset($params['deviceId']) && isset($params['from']) && isset($params['to'])) {
                // Registrar los parámetros para depuración
                error_log("[DEBUG] API Proxy - getRoute - Parámetros validados:");
                error_log("[DEBUG] API Proxy - getRoute - deviceId: " . json_encode($params['deviceId']));
                error_log("[DEBUG] API Proxy - getRoute - from: {$params['from']}");
                error_log("[DEBUG] API Proxy - getRoute - to: {$params['to']}");

                // Verificar formato de fechas
                $fromValid = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $params['from']);
                $toValid = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $params['to']);

                error_log("[DEBUG] API Proxy - getRoute - Formato de fecha 'from' válido: " . ($fromValid ? 'Sí' : 'No'));
                error_log("[DEBUG] API Proxy - getRoute - Formato de fecha 'to' válido: " . ($toValid ? 'Sí' : 'No'));

                // Calcular diferencia de tiempo
                try {
                    $fromDate = new DateTime($params['from']);
                    $toDate = new DateTime($params['to']);
                    $interval = $fromDate->diff($toDate);
                    $intervalStr = $interval->format('%d días, %h horas, %i minutos');
                    error_log("[DEBUG] API Proxy - getRoute - Intervalo de tiempo: {$intervalStr}");
                } catch (Exception $e) {
                    error_log("[DEBUG] API Proxy - getRoute - Error al calcular intervalo: " . $e->getMessage());
                }

                // Probar con un rango de fechas más amplio si las fechas están en el futuro
                $now = new DateTime();
                $fromDate = new DateTime($params['from']);
                $toDate = new DateTime($params['to']);

                // Si las fechas están en el futuro, usar un rango en el pasado
                if ($fromDate > $now || $toDate > $now) {
                    $alternativeFromDate = clone $now;
                    $alternativeFromDate->modify('-30 days'); // Probar con 30 días atrás
                    $alternativeToDate = clone $now;

                    $params['from'] = $alternativeFromDate->format('Y-m-d\TH:i:s\Z');
                    $params['to'] = $alternativeToDate->format('Y-m-d\TH:i:s\Z');

                    error_log("[DEBUG] API Proxy - getRoute - Usando rango de fechas alternativo (30 días): {$params['from']} a {$params['to']}");
                }

                // Intentar obtener la ruta usando diferentes métodos
                $result = [];
                $methods = ['api', 'curl', 'direct'];

                foreach ($methods as $method) {
                    error_log("[DEBUG] API Proxy - getRoute - Intentando obtener ruta con método: {$method}");

                    try {
                        switch ($method) {
                            case 'api':
                                // Método 1: Usar la API normal
                                $startTime = microtime(true);
                                $result = $api->getRoute($params['deviceId'], $params['from'], $params['to']);
                                $endTime = microtime(true);
                                $duration = round(($endTime - $startTime) * 1000, 2);
                                error_log("[DEBUG] API Proxy - getRoute - Método 'api' completado en {$duration}ms");
                                break;

                            case 'curl':
                                // Método 2: Usar curl directamente
                                $startTime = microtime(true);
                                $url = $api->getApiUrl() . "/reports/route?deviceId={$params['deviceId']}&from={$params['from']}&to={$params['to']}";
                                $ch = curl_init($url);
                                curl_setopt_array($ch, [
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_HEADER => false,
                                    CURLOPT_HTTPHEADER => ['Accept: application/json'],
                                    CURLOPT_COOKIE => 'JSESSIONID=' . $api->getSessionCookie(),
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

                                error_log("[DEBUG] API Proxy - getRoute - Método 'curl' completado en {$duration}ms: HTTP {$httpCode}, Content-Type: {$contentType}");

                                if ($curlResult !== false) {
                                    $jsonResult = json_decode($curlResult, true);
                                    if ($jsonResult !== null || json_last_error() === JSON_ERROR_NONE) {
                                        $result = $jsonResult;
                                        error_log("[DEBUG] API Proxy - getRoute - Método 'curl' obtuvo JSON válido");
                                    } else {
                                        error_log("[DEBUG] API Proxy - getRoute - Método 'curl' no obtuvo JSON válido: " . json_last_error_msg());
                                        error_log("[DEBUG] API Proxy - getRoute - Primeros 500 bytes: " . substr($curlResult, 0, 500));
                                    }
                                }
                                break;

                            case 'direct':
                                // Método 3: Intentar acceder directamente a la API
                                $startTime = microtime(true);
                                $url = $api->getApiUrl() . "/reports/route?deviceId={$params['deviceId']}&from={$params['from']}&to={$params['to']}";

                                // Crear un contexto con las cabeceras necesarias
                                $options = [
                                    'http' => [
                                        'method' => 'GET',
                                        'header' => [
                                            'Accept: application/json',
                                            'Cookie: JSESSIONID=' . $api->getSessionCookie()
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

                                error_log("[DEBUG] API Proxy - getRoute - Método 'direct' completado en {$duration}ms");

                                if ($directResult !== false) {
                                    $jsonResult = json_decode($directResult, true);
                                    if ($jsonResult !== null || json_last_error() === JSON_ERROR_NONE) {
                                        $result = $jsonResult;
                                        error_log("[DEBUG] API Proxy - getRoute - Método 'direct' obtuvo JSON válido");
                                    } else {
                                        error_log("[DEBUG] API Proxy - getRoute - Método 'direct' no obtuvo JSON válido: " . json_last_error_msg());
                                        error_log("[DEBUG] API Proxy - getRoute - Primeros 500 bytes: " . substr($directResult, 0, 500));
                                    }
                                }
                                break;
                        }

                        // Si obtuvimos resultados, salir del bucle
                        if (is_array($result) && count($result) > 0) {
                            error_log("[DEBUG] API Proxy - getRoute - Método '{$method}' obtuvo " . count($result) . " puntos. Usando estos resultados.");
                            break;
                        }
                    } catch (Exception $e) {
                        error_log("[DEBUG] API Proxy - getRoute - Error con método '{$method}': " . $e->getMessage());
                    }
                }

                // Registrar el resultado final
                if (is_array($result)) {
                    error_log("[DEBUG] API Proxy - getRoute - Resultado final: " . count($result) . " puntos");

                    if (count($result) > 0) {
                        // Verificar si los puntos pertenecen al dispositivo solicitado
                        $deviceIds = array_unique(array_column($result, 'deviceId'));
                        $deviceIdsStr = implode(', ', $deviceIds);
                        error_log("[DEBUG] API Proxy - getRoute - IDs de dispositivos en el resultado: {$deviceIdsStr}");

                        // Contar puntos por dispositivo
                        $countByDevice = [];
                        foreach ($result as $point) {
                            $deviceId = $point['deviceId'];
                            if (!isset($countByDevice[$deviceId])) {
                                $countByDevice[$deviceId] = 0;
                            }
                            $countByDevice[$deviceId]++;
                        }
                        error_log("[DEBUG] API Proxy - getRoute - Puntos por dispositivo: " . json_encode($countByDevice));

                        // Mostrar información sobre el primer y último punto
                        $firstPoint = $result[0];
                        $lastPoint = $result[count($result) - 1];
                        error_log("[DEBUG] API Proxy - getRoute - Primer punto: " . json_encode($firstPoint));
                        error_log("[DEBUG] API Proxy - getRoute - Último punto: " . json_encode($lastPoint));

                        // Verificar rango de tiempo de los puntos
                        $firstTime = isset($firstPoint['deviceTime']) ? $firstPoint['deviceTime'] : (isset($firstPoint['fixTime']) ? $firstPoint['fixTime'] : null);
                        $lastTime = isset($lastPoint['deviceTime']) ? $lastPoint['deviceTime'] : (isset($lastPoint['fixTime']) ? $lastPoint['fixTime'] : null);

                        if ($firstTime && $lastTime) {
                            error_log("[DEBUG] API Proxy - getRoute - Rango de tiempo de los puntos: {$firstTime} a {$lastTime}");
                        }
                    } else {
                        error_log("[DEBUG] API Proxy - getRoute - ADVERTENCIA: No se encontraron puntos para el dispositivo {$params['deviceId']}");

                        // Probar con un dispositivo conocido que tiene datos (solo para depuración)
                        $testDeviceId = 257; // Dispositivo que sabemos que tiene datos
                        if ($params['deviceId'] != $testDeviceId) {
                            error_log("[DEBUG] API Proxy - getRoute - Probando con dispositivo de prueba ID: {$testDeviceId}");

                            try {
                                $testResult = $api->getRoute($testDeviceId, $params['from'], $params['to']);
                                if (is_array($testResult) && count($testResult) > 0) {
                                    error_log("[DEBUG] API Proxy - getRoute - ÉXITO: El dispositivo de prueba tiene " . count($testResult) . " puntos");
                                } else {
                                    error_log("[DEBUG] API Proxy - getRoute - El dispositivo de prueba tampoco tiene datos");
                                }
                            } catch (Exception $e) {
                                error_log("[DEBUG] API Proxy - getRoute - Error al probar con dispositivo de prueba: " . $e->getMessage());
                            }
                        }
                    }
                } else {
                    error_log("[DEBUG] API Proxy - getRoute - Resultado final no es un array: " . gettype($result));
                    $result = [];
                }
            } else {
                error_log("[DEBUG] API Proxy - getRoute - Parámetros faltantes");
                if (!isset($params['deviceId'])) error_log("[DEBUG] API Proxy - getRoute - Falta deviceId");
                if (!isset($params['from'])) error_log("[DEBUG] API Proxy - getRoute - Falta from");
                if (!isset($params['to'])) error_log("[DEBUG] API Proxy - getRoute - Falta to");
                $result = [];
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida']);
            exit;
    }

    // Devolver resultado
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la solicitud']);
    } else {
        echo json_encode(['success' => true, 'data' => $result]);
    }

    exit;
}
