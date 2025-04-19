<?php
/**
 * Script de prueba para depurar problemas con la obtención de rutas
 */

// Incluir archivos necesarios
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/api_proxy.php';

// Configurar reporte de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión si no está activa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['JSESSIONID'])) {
    die("Error: No hay sesión activa. Por favor, inicie sesión primero.");
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

// Obtener parámetros
$deviceId = isset($_GET['deviceId']) ? intval($_GET['deviceId']) : 257; // Dispositivo por defecto
$days = isset($_GET['days']) ? intval($_GET['days']) : 30; // Días atrás por defecto
$method = isset($_GET['method']) ? $_GET['method'] : 'api'; // Método por defecto

// Calcular fechas
$to = new DateTime();
$from = clone $to;
$from->modify("-{$days} days");

// Formatear fechas para la API
$fromStr = $from->format('Y-m-d\TH:i:s\Z');
$toStr = $to->format('Y-m-d\TH:i:s\Z');

// Obtener datos del dispositivo
$devices = $api->getDevices();
$deviceName = "Desconocido";
$deviceFound = false;

if (is_array($devices)) {
    foreach ($devices as $device) {
        if ($device['id'] == $deviceId) {
            $deviceName = $device['name'];
            $deviceFound = true;
            break;
        }
    }
}

// Obtener ruta usando diferentes métodos
$results = [];

// Método 1: API directa
if ($method == 'api' || $method == 'all') {
    $startTime = microtime(true);
    try {
        $route = $api->getRoute($deviceId, $fromStr, $toStr);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // en milisegundos
        
        $results['api'] = [
            'success' => is_array($route),
            'pointCount' => is_array($route) ? count($route) : 0,
            'duration' => $duration,
            'error' => null
        ];
        
        if (is_array($route) && count($route) > 0) {
            $results['api']['firstPoint'] = $route[0];
            $results['api']['lastPoint'] = $route[count($route) - 1];
        }
    } catch (Exception $e) {
        $results['api'] = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Método 2: CURL
if ($method == 'curl' || $method == 'all') {
    $startTime = microtime(true);
    try {
        // Construir URL
        $url = TRACCAR_API_URL . "/reports/route?deviceId={$deviceId}&from={$fromStr}&to={$toStr}";
        
        // Inicializar CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Cookie: JSESSIONID=' . $sessionCookie
        ]);
        
        // Ejecutar CURL
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // en milisegundos
        
        if ($httpCode == 200 && !$error) {
            $route = json_decode($response, true);
            $results['curl'] = [
                'success' => is_array($route),
                'pointCount' => is_array($route) ? count($route) : 0,
                'duration' => $duration,
                'error' => null
            ];
            
            if (is_array($route) && count($route) > 0) {
                $results['curl']['firstPoint'] = $route[0];
                $results['curl']['lastPoint'] = $route[count($route) - 1];
            }
        } else {
            $results['curl'] = [
                'success' => false,
                'httpCode' => $httpCode,
                'error' => $error ?: "HTTP Error: {$httpCode}"
            ];
        }
    } catch (Exception $e) {
        $results['curl'] = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Método 3: file_get_contents
if ($method == 'file' || $method == 'all') {
    $startTime = microtime(true);
    try {
        // Construir URL
        $url = TRACCAR_API_URL . "/reports/route?deviceId={$deviceId}&from={$fromStr}&to={$toStr}";
        
        // Configurar contexto
        $context = stream_context_create([
            'http' => [
                'header' => "Accept: application/json\r\nCookie: JSESSIONID={$sessionCookie}\r\n"
            ]
        ]);
        
        // Obtener datos
        $response = @file_get_contents($url, false, $context);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // en milisegundos
        
        if ($response !== false) {
            $route = json_decode($response, true);
            $results['file'] = [
                'success' => is_array($route),
                'pointCount' => is_array($route) ? count($route) : 0,
                'duration' => $duration,
                'error' => null
            ];
            
            if (is_array($route) && count($route) > 0) {
                $results['file']['firstPoint'] = $route[0];
                $results['file']['lastPoint'] = $route[count($route) - 1];
            }
        } else {
            $results['file'] = [
                'success' => false,
                'error' => "Error al obtener datos con file_get_contents"
            ];
        }
    } catch (Exception $e) {
        $results['file'] = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Método 4: API Proxy
if ($method == 'proxy' || $method == 'all') {
    $startTime = microtime(true);
    try {
        // Usar el proxy API
        $proxyApi = new ApiProxy($sessionCookie);
        $route = $proxyApi->getRoute($deviceId, $fromStr, $toStr);
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // en milisegundos
        
        $results['proxy'] = [
            'success' => isset($route['success']) && $route['success'] && isset($route['data']),
            'pointCount' => isset($route['data']) && is_array($route['data']) ? count($route['data']) : 0,
            'duration' => $duration,
            'error' => null
        ];
        
        if (isset($route['data']) && is_array($route['data']) && count($route['data']) > 0) {
            $results['proxy']['firstPoint'] = $route['data'][0];
            $results['proxy']['lastPoint'] = $route['data'][count($route['data']) - 1];
        }
    } catch (Exception $e) {
        $results['proxy'] = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Probar con diferentes dispositivos
$testDevices = [];
if (isset($_GET['test_all']) && $_GET['test_all'] == 1) {
    // Probar con todos los dispositivos
    foreach ($devices as $device) {
        $testDevices[] = [
            'id' => $device['id'],
            'name' => $device['name']
        ];
    }
} else {
    // Probar solo con algunos dispositivos específicos
    $testIds = [257, 283, 251, 252, 253, 254, 255, 256];
    foreach ($devices as $device) {
        if (in_array($device['id'], $testIds)) {
            $testDevices[] = [
                'id' => $device['id'],
                'name' => $device['name']
            ];
        }
    }
}

// Resultados de pruebas con diferentes dispositivos
$deviceTests = [];
if (isset($_GET['test_devices']) && $_GET['test_devices'] == 1) {
    foreach ($testDevices as $testDevice) {
        $testId = $testDevice['id'];
        $testName = $testDevice['name'];
        
        try {
            $testRoute = $api->getRoute($testId, $fromStr, $toStr);
            $deviceTests[$testId] = [
                'name' => $testName,
                'success' => is_array($testRoute),
                'pointCount' => is_array($testRoute) ? count($testRoute) : 0
            ];
            
            if (is_array($testRoute) && count($testRoute) > 0) {
                $deviceTests[$testId]['firstPoint'] = $testRoute[0];
                $deviceTests[$testId]['lastPoint'] = $testRoute[count($testRoute) - 1];
            }
        } catch (Exception $e) {
            $deviceTests[$testId] = [
                'name' => $testName,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Depuración de Rutas - Dispositivo <?php echo htmlspecialchars($deviceId); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            margin-bottom: 20px;
            font-size: 12px;
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
    <div class="container mx-auto bg-white p-6 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-6">Depuración de Rutas - Dispositivo <?php echo htmlspecialchars($deviceId); ?> (<?php echo htmlspecialchars($deviceName); ?>)</h1>
        
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
                        <span class="label-text">Días atrás</span>
                    </label>
                    <input type="number" name="days" value="<?php echo htmlspecialchars($days); ?>" class="input input-bordered" required>
                </div>
                
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Método</span>
                    </label>
                    <select name="method" class="select select-bordered">
                        <option value="all" <?php echo $method == 'all' ? 'selected' : ''; ?>>Todos los métodos</option>
                        <option value="api" <?php echo $method == 'api' ? 'selected' : ''; ?>>API Directa</option>
                        <option value="curl" <?php echo $method == 'curl' ? 'selected' : ''; ?>>CURL</option>
                        <option value="file" <?php echo $method == 'file' ? 'selected' : ''; ?>>file_get_contents</option>
                        <option value="proxy" <?php echo $method == 'proxy' ? 'selected' : ''; ?>>API Proxy</option>
                    </select>
                </div>
            </div>
            
            <div class="flex flex-wrap gap-2 mt-4">
                <div class="form-control">
                    <label class="label cursor-pointer">
                        <input type="checkbox" name="test_devices" value="1" class="checkbox checkbox-primary" <?php echo isset($_GET['test_devices']) && $_GET['test_devices'] == 1 ? 'checked' : ''; ?>>
                        <span class="label-text ml-2">Probar con dispositivos específicos</span>
                    </label>
                </div>
                
                <div class="form-control">
                    <label class="label cursor-pointer">
                        <input type="checkbox" name="test_all" value="1" class="checkbox checkbox-primary" <?php echo isset($_GET['test_all']) && $_GET['test_all'] == 1 ? 'checked' : ''; ?>>
                        <span class="label-text ml-2">Probar con todos los dispositivos</span>
                    </label>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Probar</button>
            </div>
        </form>
        
        <div class="divider">Información de la Solicitud</div>
        
        <div class="overflow-x-auto mb-8">
            <table class="table table-zebra">
                <tbody>
                    <tr>
                        <td class="font-medium">Dispositivo ID</td>
                        <td><?php echo htmlspecialchars($deviceId); ?></td>
                    </tr>
                    <tr>
                        <td class="font-medium">Nombre del Dispositivo</td>
                        <td><?php echo htmlspecialchars($deviceName); ?></td>
                    </tr>
                    <tr>
                        <td class="font-medium">Dispositivo Encontrado</td>
                        <td>
                            <?php if ($deviceFound): ?>
                                <span class="badge badge-success">Sí</span>
                            <?php else: ?>
                                <span class="badge badge-error">No</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="font-medium">Fecha Desde</td>
                        <td><?php echo htmlspecialchars($fromStr); ?></td>
                    </tr>
                    <tr>
                        <td class="font-medium">Fecha Hasta</td>
                        <td><?php echo htmlspecialchars($toStr); ?></td>
                    </tr>
                    <tr>
                        <td class="font-medium">Días</td>
                        <td><?php echo htmlspecialchars($days); ?></td>
                    </tr>
                    <tr>
                        <td class="font-medium">URL de API</td>
                        <td><code><?php echo htmlspecialchars(TRACCAR_API_URL . "/reports/route?deviceId={$deviceId}&from={$fromStr}&to={$toStr}"); ?></code></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <?php if (!empty($results)): ?>
        <div class="divider">Resultados por Método</div>
        
        <div class="overflow-x-auto mb-8">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>Método</th>
                        <th>Éxito</th>
                        <th>Puntos</th>
                        <th>Tiempo (ms)</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $methodName => $result): ?>
                    <tr>
                        <td class="font-medium"><?php echo htmlspecialchars(ucfirst($methodName)); ?></td>
                        <td>
                            <?php if ($result['success']): ?>
                                <span class="badge badge-success">Sí</span>
                            <?php else: ?>
                                <span class="badge badge-error">No</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo isset($result['pointCount']) ? htmlspecialchars($result['pointCount']) : 'N/A'; ?></td>
                        <td><?php echo isset($result['duration']) ? htmlspecialchars($result['duration']) : 'N/A'; ?></td>
                        <td><?php echo isset($result['error']) && $result['error'] ? htmlspecialchars($result['error']) : 'Ninguno'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php foreach ($results as $methodName => $result): ?>
            <?php if ($result['success'] && isset($result['pointCount']) && $result['pointCount'] > 0): ?>
            <div class="mb-8">
                <h2 class="text-xl font-bold mb-4">Detalles del Método: <?php echo htmlspecialchars(ucfirst($methodName)); ?></h2>
                
                <div class="tabs tabs-boxed mb-4">
                    <a class="tab tab-active" onclick="showTab('<?php echo $methodName; ?>-first')">Primer Punto</a>
                    <a class="tab" onclick="showTab('<?php echo $methodName; ?>-last')">Último Punto</a>
                </div>
                
                <div id="<?php echo $methodName; ?>-first" class="tab-content">
                    <h3 class="text-lg font-bold mb-2">Primer Punto</h3>
                    <?php prettyPrint($result['firstPoint']); ?>
                </div>
                
                <div id="<?php echo $methodName; ?>-last" class="tab-content hidden">
                    <h3 class="text-lg font-bold mb-2">Último Punto</h3>
                    <?php prettyPrint($result['lastPoint']); ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($deviceTests)): ?>
        <div class="divider">Pruebas con Diferentes Dispositivos</div>
        
        <div class="overflow-x-auto mb-8">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Éxito</th>
                        <th>Puntos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deviceTests as $id => $test): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($id); ?></td>
                        <td><?php echo htmlspecialchars($test['name']); ?></td>
                        <td>
                            <?php if ($test['success']): ?>
                                <span class="badge badge-success">Sí</span>
                            <?php else: ?>
                                <span class="badge badge-error">No</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo isset($test['pointCount']) ? htmlspecialchars($test['pointCount']) : 'N/A'; ?></td>
                        <td>
                            <a href="?deviceId=<?php echo htmlspecialchars($id); ?>&days=<?php echo htmlspecialchars($days); ?>&method=<?php echo htmlspecialchars($method); ?>" class="btn btn-xs btn-primary">Probar</a>
                            <?php if ($test['success'] && isset($test['pointCount']) && $test['pointCount'] > 0): ?>
                            <a href="map.php?route=<?php echo htmlspecialchars($id); ?>&from=<?php echo htmlspecialchars($fromStr); ?>&to=<?php echo htmlspecialchars($toStr); ?>" class="btn btn-xs btn-secondary">Ver en Mapa</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="divider">Recomendaciones</div>
        
        <div class="alert alert-info mb-8">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <div>
                <h3 class="font-bold">Consejos para solucionar problemas</h3>
                <ul class="list-disc pl-5 mt-2">
                    <li>Asegúrese de que el dispositivo tenga datos históricos registrados</li>
                    <li>Pruebe con un rango de fechas más amplio (aumente el número de días)</li>
                    <li>Verifique que la cabecera 'Accept: application/json' esté presente en todas las solicitudes</li>
                    <li>Pruebe con diferentes dispositivos para identificar si el problema es específico de un dispositivo</li>
                    <li>Verifique los logs del servidor para obtener más información sobre posibles errores</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabId) {
            // Ocultar todos los contenidos de pestañas
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Mostrar el contenido de la pestaña seleccionada
            document.getElementById(tabId).classList.remove('hidden');
            
            // Actualizar clases de las pestañas
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('tab-active');
            });
            
            // Activar la pestaña seleccionada
            event.currentTarget.classList.add('tab-active');
        }
    </script>
</body>
</html>
