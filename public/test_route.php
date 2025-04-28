<?php
/**
 * Script de prueba para la funcionalidad de obtención de rutas
 *
 * Este archivo permite probar diferentes métodos para obtener datos de ruta
 * desde la API de Traccar, aislando esta funcionalidad del resto del sistema.
 * SOLO PARA ADMINISTRADORES
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

// Verificar si el usuario es administrador
if (!isset($_SESSION['user']['administrator']) || $_SESSION['user']['administrator'] !== true) {
    header('Location: index.php');
    exit;
}

// Incluir archivos necesarios
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/api_proxy.php';

// Configurar reporte de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar si hay una sesión activa
if (!isset($_SESSION['JSESSIONID'])) {
    // Si no hay sesión, mostrar un formulario de login simplificado
    if (isset($_POST['email']) && isset($_POST['password'])) {
        // Intentar iniciar sesión
        $api = new TraccarAPI();
        $loginResult = $api->login($_POST['email'], $_POST['password']);

        if ($loginResult && isset($loginResult['JSESSIONID'])) {
            $_SESSION['JSESSIONID'] = $loginResult['JSESSIONID'];
            // Redirigir para evitar reenvío del formulario
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $loginError = "Error de autenticación. Verifique sus credenciales.";
        }
    }

    // Mostrar formulario de login
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Prueba de Obtención de Rutas</title>
        <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 flex items-center justify-center min-h-screen">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
            <h1 class="text-2xl font-bold mb-6 text-center">Iniciar Sesión</h1>

            <?php if (isset($loginError)): ?>
                <div class="alert alert-error mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span><?php echo htmlspecialchars($loginError); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Email</span>
                    </label>
                    <input type="email" name="email" class="input input-bordered" required>
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Contraseña</span>
                    </label>
                    <input type="password" name="password" class="input input-bordered" required>
                </div>

                <div class="form-control mt-6">
                    <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                </div>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Obtener la cookie de sesión
$sessionCookie = $_SESSION['JSESSIONID'];

// Crear instancia de la API
$api = new TraccarAPI($sessionCookie);

// Función para mostrar resultados en formato legible
function prettyPrint($data) {
    echo "<pre>";
    if (is_array($data) || is_object($data)) {
        echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } else {
        echo htmlspecialchars($data);
    }
    echo "</pre>";
}

// Función para probar la obtención de ruta con diferentes métodos
function testRouteRetrieval($api, $deviceId, $from, $to) {
    // Inicializar array para almacenar resultados de las pruebas
    $testResults = [
        'deviceId' => $deviceId,
        'from' => $from,
        'to' => $to,
        'methods' => []
    ];

    echo "<h3>Probando obtención de ruta para dispositivo ID: $deviceId</h3>";
    echo "<p>Rango de fechas: $from a $to</p>";

    // Método 1: Usando la API normal
    echo "<h4>Método 1: API normal</h4>";
    $methodResult = [
        'name' => 'API normal',
        'success' => false,
        'duration' => 0,
        'points' => 0,
        'error' => null,
        'contentType' => null,
        'httpCode' => null,
        'firstPoint' => null,
        'lastPoint' => null,
        'isExcel' => false,
        'excelPath' => null
    ];

    try {
        $startTime = microtime(true);
        $result = $api->getRoute($deviceId, $from, $to);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        $methodResult['duration'] = $duration;
        echo "<p>Tiempo de ejecución: $duration ms</p>";

        if (is_array($result)) {
            $methodResult['success'] = true;
            $methodResult['points'] = count($result);

            echo "<p>Resultado: " . count($result) . " puntos</p>";
            if (count($result) > 0) {
                $methodResult['firstPoint'] = $result[0];
                $methodResult['lastPoint'] = $result[count($result) - 1];

                echo "<p>Primer punto: </p>";
                prettyPrint($result[0]);
                echo "<p>Último punto: </p>";
                prettyPrint($result[count($result) - 1]);
            } else {
                echo "<p>No se encontraron puntos.</p>";
            }
        } else {
            $methodResult['error'] = 'El resultado no es un array';
            echo "<p>Error: El resultado no es un array.</p>";
        }
    } catch (Exception $e) {
        $methodResult['error'] = $e->getMessage();
        echo "<p>Error: " . $e->getMessage() . "</p>";
    }

    $testResults['methods']['api_normal'] = $methodResult;

    // Método 2: Usando curl directamente
    echo "<h4>Método 2: curl directo</h4>";
    $methodResult = [
        'name' => 'curl directo',
        'success' => false,
        'duration' => 0,
        'points' => 0,
        'error' => null,
        'contentType' => null,
        'httpCode' => null,
        'firstPoint' => null,
        'lastPoint' => null,
        'isExcel' => false,
        'excelPath' => null
    ];

    try {
        $url = $api->getApiUrl() . "/reports/route?deviceId=$deviceId&from=$from&to=$to";
        $methodResult['url'] = $url;
        echo "<p>URL: $url</p>";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_COOKIE => 'JSESSIONID=' . $api->getSessionCookie(),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $methodResult['duration'] = $duration;
        $methodResult['httpCode'] = $httpCode;
        $methodResult['contentType'] = $contentType;
        $methodResult['headerSize'] = $headerSize;
        $methodResult['bodySize'] = strlen($body);

        echo "<p>Tiempo de ejecución: $duration ms</p>";
        echo "<p>Código HTTP: $httpCode</p>";
        echo "<p>Content-Type: $contentType</p>";
        echo "<p>Cabeceras:</p>";
        prettyPrint($header);

        if ($contentType == 'application/json') {
            $result = json_decode($body, true);
            if ($result !== null) {
                $methodResult['success'] = true;
                $methodResult['points'] = count($result);

                echo "<p>Resultado: " . count($result) . " puntos</p>";
                if (count($result) > 0) {
                    $methodResult['firstPoint'] = $result[0];
                    $methodResult['lastPoint'] = $result[count($result) - 1];

                    echo "<p>Primer punto: </p>";
                    prettyPrint($result[0]);
                    echo "<p>Último punto: </p>";
                    prettyPrint($result[count($result) - 1]);
                } else {
                    echo "<p>No se encontraron puntos.</p>";
                }
            } else {
                $methodResult['error'] = 'Error al decodificar JSON: ' . json_last_error_msg();
                echo "<p>Error al decodificar JSON: " . json_last_error_msg() . "</p>";
                echo "<p>Primeros 500 bytes del cuerpo:</p>";
                prettyPrint(substr($body, 0, 500));
            }
        } else if (strpos($contentType, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') !== false) {
            $methodResult['isExcel'] = true;
            echo "<p>La respuesta es un archivo Excel.</p>";
            echo "<p>Longitud del cuerpo: " . strlen($body) . " bytes</p>";

            // Guardar el Excel temporalmente para análisis
            $tempFile = tempnam(sys_get_temp_dir(), 'route_excel_');
            file_put_contents($tempFile, $body);
            echo "<p>Excel guardado temporalmente en: $tempFile</p>";

            // Proporcionar un enlace para descargar el Excel
            $tempFileName = basename($tempFile);
            $downloadPath = "temp/$tempFileName.xlsx";
            $methodResult['excelPath'] = $downloadPath;

            // Asegurarse de que el directorio temp existe
            $tempDir = __DIR__ . '/temp';
            if (!file_exists($tempDir)) {
                try {
                    mkdir($tempDir, 0755, true);
                } catch (Exception $e) {
                    echo "<p>Error al crear directorio temporal: " . $e->getMessage() . "</p>";
                }
            }

            // Verificar si el directorio es escribible
            if (!is_writable($tempDir)) {
                echo "<p>Advertencia: El directorio temporal no es escribible.</p>";
            }

            // Copiar el archivo a un lugar accesible desde la web
            copy($tempFile, __DIR__ . '/' . $downloadPath);

            echo "<p><a href='$downloadPath' download>Descargar archivo Excel</a></p>";
        } else {
            $methodResult['error'] = 'Tipo de contenido no reconocido: ' . $contentType;
            echo "<p>Tipo de contenido no reconocido.</p>";
            echo "<p>Primeros 500 bytes del cuerpo:</p>";
            prettyPrint(substr($body, 0, 500));
        }
    } catch (Exception $e) {
        $methodResult['error'] = $e->getMessage();
        echo "<p>Error: " . $e->getMessage() . "</p>";
    }

    $testResults['methods']['curl_direct'] = $methodResult;

    // Método 3: Usando file_get_contents directamente
    echo "<h4>Método 3: file_get_contents directo</h4>";
    $methodResult = [
        'name' => 'file_get_contents directo',
        'success' => false,
        'duration' => 0,
        'points' => 0,
        'error' => null,
        'contentType' => null,
        'httpCode' => null,
        'firstPoint' => null,
        'lastPoint' => null,
        'isExcel' => false,
        'excelPath' => null
    ];

    try {
        $url = $api->getApiUrl() . "/reports/route?deviceId=$deviceId&from=$from&to=$to";
        $methodResult['url'] = $url;
        echo "<p>URL: $url</p>";

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

        $startTime = microtime(true);
        $result = @file_get_contents($url, false, $context);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        $methodResult['duration'] = $duration;
        echo "<p>Tiempo de ejecución: $duration ms</p>";

        // Obtener código HTTP y tipo de contenido de las cabeceras de respuesta
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d+)\s+(.*)$/', $header, $matches)) {
                    $methodResult['httpCode'] = $matches[1];
                    echo "<p>Código HTTP: {$matches[1]} {$matches[2]}</p>";
                } else if (preg_match('/^Content-Type:\s+(.*)$/i', $header, $matches)) {
                    $methodResult['contentType'] = $matches[1];
                    echo "<p>Content-Type: {$matches[1]}</p>";
                }
            }
        }

        if ($result !== false) {
            $methodResult['bodySize'] = strlen($result);
            echo "<p>Longitud de la respuesta: " . strlen($result) . " bytes</p>";

            // Verificar si es JSON válido
            $jsonResult = json_decode($result, true);
            if ($jsonResult !== null) {
                $methodResult['success'] = true;
                $methodResult['points'] = count($jsonResult);

                echo "<p>Resultado: " . count($jsonResult) . " puntos</p>";
                if (count($jsonResult) > 0) {
                    $methodResult['firstPoint'] = $jsonResult[0];
                    $methodResult['lastPoint'] = $jsonResult[count($jsonResult) - 1];

                    echo "<p>Primer punto: </p>";
                    prettyPrint($jsonResult[0]);
                    echo "<p>Último punto: </p>";
                    prettyPrint($jsonResult[count($jsonResult) - 1]);
                } else {
                    echo "<p>No se encontraron puntos.</p>";
                }
            } else {
                $methodResult['error'] = 'Error al decodificar JSON: ' . json_last_error_msg();
                echo "<p>Error al decodificar JSON: " . json_last_error_msg() . "</p>";
                echo "<p>Primeros 500 bytes de la respuesta:</p>";
                prettyPrint(substr($result, 0, 500));

                // Verificar si es un archivo Excel
                if (substr($result, 0, 4) == "PK\x03\x04") {
                    $methodResult['isExcel'] = true;
                    echo "<p>La respuesta parece ser un archivo Excel (formato ZIP).</p>";

                    // Guardar el Excel temporalmente para análisis
                    $tempFile = tempnam(sys_get_temp_dir(), 'route_excel_');
                    file_put_contents($tempFile, $result);
                    echo "<p>Excel guardado temporalmente en: $tempFile</p>";

                    // Proporcionar un enlace para descargar el Excel
                    $tempFileName = basename($tempFile);
                    $downloadPath = "temp/$tempFileName.xlsx";
                    $methodResult['excelPath'] = $downloadPath;

                    // Asegurarse de que el directorio temp existe
                    $tempDir = __DIR__ . '/temp';
                    if (!file_exists($tempDir)) {
                        try {
                            mkdir($tempDir, 0755, true);
                        } catch (Exception $e) {
                            echo "<p>Error al crear directorio temporal: " . $e->getMessage() . "</p>";
                        }
                    }

                    // Verificar si el directorio es escribible
                    if (!is_writable($tempDir)) {
                        echo "<p>Advertencia: El directorio temporal no es escribible.</p>";
                    }

                    // Copiar el archivo a un lugar accesible desde la web
                    copy($tempFile, __DIR__ . '/' . $downloadPath);

                    echo "<p><a href='$downloadPath' download>Descargar archivo Excel</a></p>";
                }
            }
        } else {
            $methodResult['error'] = 'No se pudo obtener la respuesta';
            echo "<p>Error: No se pudo obtener la respuesta.</p>";
            if (isset($http_response_header)) {
                echo "<p>Cabeceras de respuesta:</p>";
                prettyPrint($http_response_header);
            }
        }
    } catch (Exception $e) {
        $methodResult['error'] = $e->getMessage();
        echo "<p>Error: " . $e->getMessage() . "</p>";
    }

    $testResults['methods']['file_get_contents'] = $methodResult;

    // Método 4: Usando el API proxy directamente
    echo "<h4>Método 4: API proxy</h4>";
    $methodResult = [
        'name' => 'API proxy',
        'success' => false,
        'duration' => 0,
        'points' => 0,
        'error' => null,
        'contentType' => null,
        'httpCode' => null,
        'firstPoint' => null,
        'lastPoint' => null,
        'isExcel' => false,
        'excelPath' => null
    ];

    try {
        $params = [
            'deviceId' => $deviceId,
            'from' => $from,
            'to' => $to
        ];

        $startTime = microtime(true);

        // Crear una instancia del manejador de API
        require_once __DIR__ . '/../php/api_handler.php';
        $handler = new ApiHandler();
        $response = $handler->handleRequest('getRoute', $params);

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        $methodResult['duration'] = $duration;
        echo "<p>Tiempo de ejecución: $duration ms</p>";
        echo "<p>Respuesta:</p>";
        prettyPrint($response);

        if (isset($response['success']) && $response['success'] && isset($response['data'])) {
            $methodResult['success'] = true;
            $methodResult['points'] = count($response['data']);

            echo "<p>Resultado: " . count($response['data']) . " puntos</p>";
            if (count($response['data']) > 0) {
                $methodResult['firstPoint'] = $response['data'][0];
                $methodResult['lastPoint'] = $response['data'][count($response['data']) - 1];

                echo "<p>Primer punto: </p>";
                prettyPrint($response['data'][0]);
                echo "<p>Último punto: </p>";
                prettyPrint($response['data'][count($response['data']) - 1]);
            } else {
                echo "<p>No se encontraron puntos.</p>";
            }
        } else {
            $methodResult['error'] = 'Error en la respuesta del API proxy';
            echo "<p>Error en la respuesta del API proxy.</p>";
        }
    } catch (Exception $e) {
        $methodResult['error'] = $e->getMessage();
        echo "<p>Error: " . $e->getMessage() . "</p>";
    }

    $testResults['methods']['api_proxy'] = $methodResult;

    // Generar resumen de resultados
    echo "<div class='divider'>Resumen de Resultados</div>";

    echo "<div class='overflow-x-auto'>";
    echo "<table class='table table-zebra'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>Método</th>";
    echo "<th>Resultado</th>";
    echo "<th>Tiempo (ms)</th>";
    echo "<th>Puntos</th>";
    echo "<th>Tipo de Contenido</th>";
    echo "<th>Código HTTP</th>";
    echo "<th>Error</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";

    foreach ($testResults['methods'] as $key => $method) {
        // Asegurarse de que todas las claves existan
        $method = array_merge([
            'name' => 'Desconocido',
            'success' => false,
            'duration' => 0,
            'points' => 0,
            'contentType' => null,
            'httpCode' => null,
            'error' => null,
            'isExcel' => false,
            'excelPath' => null
        ], $method);

        echo "<tr>";
        echo "<td>{$method['name']}</td>";
        echo "<td>" . ($method['success'] ? "<span class='badge badge-success'>Éxito</span>" : "<span class='badge badge-error'>Fallido</span>") . "</td>";
        echo "<td>{$method['duration']}</td>";
        echo "<td>{$method['points']}</td>";
        echo "<td>" . ($method['isExcel'] ? "<span class='badge badge-warning'>Excel</span>" : ($method['contentType'] ? $method['contentType'] : 'N/A')) . "</td>";
        echo "<td>" . ($method['httpCode'] ? $method['httpCode'] : 'N/A') . "</td>";
        echo "<td>" . ($method['error'] ? "<span class='text-error'>{$method['error']}</span>" : 'Ninguno') . "</td>";
        echo "</tr>";
    }

    echo "</tbody>";
    echo "</table>";
    echo "</div>";

    // Conclusiones
    echo "<div class='divider'>Conclusiones</div>";

    // Determinar el mejor método
    $bestMethod = null;
    $maxPoints = 0;
    $hasExcel = false;

    foreach ($testResults['methods'] as $key => $method) {
        // Asegurarse de que todas las claves existan
        $method = array_merge([
            'name' => 'Desconocido',
            'success' => false,
            'duration' => 0,
            'points' => 0,
            'contentType' => null,
            'httpCode' => null,
            'error' => null,
            'isExcel' => false,
            'excelPath' => null
        ], $method);

        if ($method['isExcel']) {
            $hasExcel = true;
        }
        if ($method['success'] && $method['points'] > $maxPoints) {
            $maxPoints = $method['points'];
            $bestMethod = $key;
        }
    }

    echo "<div class='alert " . ($maxPoints > 0 ? "alert-success" : "alert-warning") . " shadow-lg'>";
    echo "<div>";
    if ($maxPoints > 0) {
        echo "<svg xmlns='http://www.w3.org/2000/svg' class='stroke-current flex-shrink-0 h-6 w-6' fill='none' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' /></svg>";
        echo "<div>";
        echo "<h3 class='font-bold'>Resultado exitoso</h3>";
        echo "<p>El mejor método fue <strong>{$testResults['methods'][$bestMethod]['name']}</strong> con {$maxPoints} puntos en {$testResults['methods'][$bestMethod]['duration']}ms.</p>";
        echo "</div>";
    } else {
        echo "<svg xmlns='http://www.w3.org/2000/svg' class='stroke-current flex-shrink-0 h-6 w-6' fill='none' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' /></svg>";
        echo "<div>";
        echo "<h3 class='font-bold'>Advertencia</h3>";
        echo "<p>Ninguno de los métodos pudo obtener puntos de ruta.</p>";
        echo "</div>";
    }
    echo "</div>";
    echo "</div>";

    // Recomendaciones
    echo "<div class='mt-4'>";
    echo "<h3 class='text-lg font-bold'>Recomendaciones:</h3>";
    echo "<ul class='list-disc pl-5'>";

    if ($maxPoints > 0) {
        echo "<li>Utilizar el método <strong>{$testResults['methods'][$bestMethod]['name']}</strong> para obtener datos de ruta.</li>";
    } else if ($hasExcel) {
        echo "<li>La API está devolviendo archivos Excel en lugar de JSON. Verificar las cabeceras 'Accept' en las solicitudes.</li>";
        echo "<li>Considerar implementar un parser de Excel para extraer los datos si no es posible obtener JSON.</li>";
    } else {
        echo "<li>Verificar que el dispositivo tenga datos de posición en el rango de fechas especificado.</li>";
        echo "<li>Probar con un rango de fechas más amplio.</li>";
        echo "<li>Verificar las credenciales y permisos de acceso a la API.</li>";
    }

    // Verificar si hay problemas con las fechas
    $now = new DateTime();
    $fromDate = new DateTime($from);
    $toDate = new DateTime($to);

    if ($fromDate > $now || $toDate > $now) {
        echo "<li class='text-warning'>Las fechas especificadas están en el futuro. Esto puede explicar la falta de datos.</li>";
    }

    // Verificar si hay problemas con el formato de las fechas
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $to)) {
        echo "<li class='text-warning'>El formato de las fechas puede no ser el correcto. Asegúrate de usar el formato ISO 8601 (YYYY-MM-DDThh:mm:ssZ).</li>";
    }

    echo "</ul>";
    echo "</div>";

    return $testResults;
}

// Obtener parámetros de la solicitud
$deviceId = isset($_GET['deviceId']) ? intval($_GET['deviceId']) : 257; // Dispositivo por defecto
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d\TH:i:s\Z', strtotime('-1 day'));
$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d\TH:i:s\Z');

// Formulario para cambiar parámetros
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Obtención de Rutas</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
    <div class="container mx-auto bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-6">Prueba de Obtención de Rutas</h1>

        <form method="get" class="mb-8 p-4 bg-gray-50 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">ID del Dispositivo</span>
                    </label>
                    <input type="number" name="deviceId" value="<?php echo htmlspecialchars($deviceId); ?>" class="input input-bordered" required>
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Fecha de Inicio (ISO 8601)</span>
                    </label>
                    <input type="text" name="from" value="<?php echo htmlspecialchars($from); ?>" class="input input-bordered" required>
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Fecha de Fin (ISO 8601)</span>
                    </label>
                    <input type="text" name="to" value="<?php echo htmlspecialchars($to); ?>" class="input input-bordered" required>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Probar</button>
            </div>
        </form>

        <div class="divider">Resultados</div>

        <?php
        // Ejecutar la prueba
        testRouteRetrieval($api, $deviceId, $from, $to);
        ?>
    </div>
</body>
</html>
