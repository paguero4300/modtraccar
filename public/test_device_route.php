<?php
/**
 * Script de prueba para la funcionalidad de obtención de rutas para un dispositivo específico
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
$deviceId = isset($_GET['deviceId']) ? intval($_GET['deviceId']) : 257; // Dispositivo por defecto (el que sabemos que tiene datos)
$days = isset($_GET['days']) ? intval($_GET['days']) : 30; // Días atrás por defecto

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

// Obtener ruta
$startTime = microtime(true);
$route = $api->getRoute($deviceId, $fromStr, $toStr);
$endTime = microtime(true);
$duration = round(($endTime - $startTime) * 1000, 2); // en milisegundos

// Preparar resultados
$results = [
    'deviceId' => $deviceId,
    'deviceName' => $deviceName,
    'deviceFound' => $deviceFound,
    'from' => $fromStr,
    'to' => $toStr,
    'days' => $days,
    'duration' => $duration,
    'pointCount' => is_array($route) ? count($route) : 0,
    'success' => is_array($route) && count($route) > 0,
    'firstPoint' => is_array($route) && count($route) > 0 ? $route[0] : null,
    'lastPoint' => is_array($route) && count($route) > 0 ? $route[count($route) - 1] : null
];

// Analizar dispositivos en los puntos
if (is_array($route) && count($route) > 0) {
    $deviceIds = array_unique(array_column($route, 'deviceId'));
    $results['deviceIds'] = $deviceIds;

    // Contar puntos por dispositivo
    $countByDevice = [];
    foreach ($route as $point) {
        $pointDeviceId = $point['deviceId'];
        if (!isset($countByDevice[$pointDeviceId])) {
            $countByDevice[$pointDeviceId] = 0;
        }
        $countByDevice[$pointDeviceId]++;
    }
    $results['countByDevice'] = $countByDevice;

    // Verificar rango de tiempo
    $firstTime = isset($route[0]['deviceTime']) ? $route[0]['deviceTime'] : (isset($route[0]['fixTime']) ? $route[0]['fixTime'] : null);
    $lastTime = isset($route[count($route) - 1]['deviceTime']) ? $route[count($route) - 1]['deviceTime'] : (isset($route[count($route) - 1]['fixTime']) ? $route[count($route) - 1]['fixTime'] : null);

    if ($firstTime && $lastTime) {
        $firstDateTime = new DateTime($firstTime);
        $lastDateTime = new DateTime($lastTime);
        $interval = $firstDateTime->diff($lastDateTime);

        $results['timeRange'] = [
            'first' => $firstTime,
            'last' => $lastTime,
            'interval' => $interval->format('%d días, %h horas, %i minutos')
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Ruta para Dispositivo <?php echo htmlspecialchars($deviceId); ?></title>
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
        <h1 class="text-2xl font-bold mb-6">Prueba de Ruta para Dispositivo <?php echo htmlspecialchars($deviceId); ?> (<?php echo htmlspecialchars($deviceName); ?>)</h1>

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

                <div class="form-control flex items-end">
                    <button type="submit" class="btn btn-primary">Probar</button>
                </div>
            </div>
        </form>

        <div class="divider">Resultados</div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h2 class="text-xl font-bold mb-4">Información de la Solicitud</h2>
                <div class="overflow-x-auto">
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
                                <td class="font-medium">Tiempo de Ejecución</td>
                                <td><?php echo htmlspecialchars($duration); ?> ms</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h2 class="text-xl font-bold mb-4">Resultados de la Ruta</h2>
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <tbody>
                            <tr>
                                <td class="font-medium">Éxito</td>
                                <td>
                                    <?php if ($results['success']): ?>
                                        <span class="badge badge-success">Sí</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">No</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium">Cantidad de Puntos</td>
                                <td><?php echo htmlspecialchars($results['pointCount']); ?></td>
                            </tr>
                            <?php if (isset($results['deviceIds'])): ?>
                            <tr>
                                <td class="font-medium">IDs de Dispositivos</td>
                                <td><?php echo htmlspecialchars(implode(', ', $results['deviceIds'])); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (isset($results['timeRange'])): ?>
                            <tr>
                                <td class="font-medium">Rango de Tiempo</td>
                                <td><?php echo htmlspecialchars($results['timeRange']['interval']); ?></td>
                            </tr>
                            <tr>
                                <td class="font-medium">Primer Punto</td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($results['timeRange']['first']))); ?></td>
                            </tr>
                            <tr>
                                <td class="font-medium">Último Punto</td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($results['timeRange']['last']))); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($results['success']): ?>
        <div class="mt-8">
            <h2 class="text-xl font-bold mb-4">Detalles de los Puntos</h2>

            <div class="tabs tabs-boxed mb-4">
                <a class="tab tab-active" onclick="showTab('first-point')">Primer Punto</a>
                <a class="tab" onclick="showTab('last-point')">Último Punto</a>
                <?php if (isset($results['countByDevice'])): ?>
                <a class="tab" onclick="showTab('count-by-device')">Puntos por Dispositivo</a>
                <?php endif; ?>
            </div>

            <div id="first-point" class="tab-content">
                <h3 class="text-lg font-bold mb-2">Primer Punto</h3>
                <?php prettyPrint($results['firstPoint']); ?>
            </div>

            <div id="last-point" class="tab-content hidden">
                <h3 class="text-lg font-bold mb-2">Último Punto</h3>
                <?php prettyPrint($results['lastPoint']); ?>
            </div>

            <?php if (isset($results['countByDevice'])): ?>
            <div id="count-by-device" class="tab-content hidden">
                <h3 class="text-lg font-bold mb-2">Puntos por Dispositivo</h3>
                <?php prettyPrint($results['countByDevice']); ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-8">
            <h2 class="text-xl font-bold mb-4">Visualizar en el Mapa</h2>
            <p class="mb-4">Para visualizar esta ruta en el mapa, haga clic en el siguiente botón:</p>

            <a href="map.php?route=<?php echo htmlspecialchars($deviceId); ?>&from=<?php echo htmlspecialchars($fromStr); ?>&to=<?php echo htmlspecialchars($toStr); ?>" class="btn btn-primary">
                Ver en el Mapa
            </a>
        </div>
        <?php else: ?>
        <div class="alert alert-warning mt-8">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <div>
                <h3 class="font-bold">No se encontraron puntos</h3>
                <div class="text-sm">No se encontraron puntos de ruta para el dispositivo <?php echo htmlspecialchars($deviceId); ?> en el rango de fechas especificado.</div>
            </div>
        </div>

        <div class="mt-8">
            <h2 class="text-xl font-bold mb-4">Recomendaciones</h2>
            <ul class="list-disc pl-5">
                <li>Pruebe con un rango de fechas más amplio (aumente el número de días)</li>
                <li>Pruebe con otro dispositivo (por ejemplo, el dispositivo 257 que sabemos que tiene datos)</li>
                <li>Verifique que el dispositivo tenga datos históricos registrados</li>
            </ul>
        </div>
        <?php endif; ?>
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
