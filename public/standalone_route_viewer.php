<?php
/**
 * Visualizador de rutas independiente
 * Este archivo contiene todo el código necesario para visualizar rutas sin depender de archivos externos
 */

// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['JSESSIONID'])) {
    header('Location: login.php');
    exit;
}

// Definir constantes
define('TRACCAR_API_URL', 'http://localhost:8082/api');

// Clase para comunicarse con la API de Traccar
class TraccarAPI {
    private $sessionId;

    public function __construct($sessionId) {
        $this->sessionId = $sessionId;
    }

    public function getDevices() {
        return $this->request('/devices');
    }

    public function getRoute($deviceId, $from, $to) {
        return $this->request("/reports/route?deviceId={$deviceId}&from={$from}&to={$to}");
    }

    private function request($endpoint) {
        $url = TRACCAR_API_URL . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Cookie: JSESSIONID=' . $this->sessionId
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode != 200 || $error) {
            throw new Exception("Error en la solicitud: " . ($error ?: "HTTP Code: {$httpCode}"));
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
        }

        return $data;
    }
}

// Obtener dispositivos
$api = new TraccarAPI($_SESSION['JSESSIONID']);
$devices = [];
$error = null;

try {
    $devices = $api->getDevices();

    // Ordenar dispositivos por nombre
    usort($devices, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Obtener parámetros de la URL
$selectedDeviceId = isset($_GET['deviceId']) ? intval($_GET['deviceId']) : null;

// Por defecto, usar el día actual para ambas fechas
$fromDate = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d');
$toDate = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

// Formatear fechas para la API si se ha seleccionado un dispositivo
$fromDateTime = null;
$toDateTime = null;
$routeData = null;
$routeError = null;

if ($selectedDeviceId) {
    // Convertir fechas a objetos DateTime
    $fromDateTime = new DateTime($fromDate);
    $fromDateTime->setTime(0, 0, 0);
    $toDateTime = new DateTime($toDate);
    $toDateTime->setTime(23, 59, 59);

    // Formatear para la API
    $fromStr = $fromDateTime->format('Y-m-d\TH:i:s\Z');
    $toStr = $toDateTime->format('Y-m-d\TH:i:s\Z');

    // Obtener ruta
    try {
        $routeData = $api->getRoute($selectedDeviceId, $fromStr, $toStr);
    } catch (Exception $e) {
        $routeError = $e->getMessage();
    }
}

// Función para obtener el nombre del dispositivo por ID
function getDeviceName($deviceId, $devices) {
    foreach ($devices as $device) {
        if ($device['id'] == $deviceId) {
            return $device['name'];
        }
    }
    return "Dispositivo #" . $deviceId;
}

// Obtener el nombre del dispositivo seleccionado
$selectedDeviceName = $selectedDeviceId ? getDeviceName($selectedDeviceId, $devices) : '';
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizador de Rutas Independiente</title>

    <!-- Estilos -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        #map {
            height: 600px;
            width: 100%;
            border-radius: 0.5rem;
        }
        .route-marker-start {
            color: green;
            font-size: 24px;
        }
        .route-marker-end {
            color: red;
            font-size: 24px;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto p-4">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Visualizador de Rutas Independiente</h1>
            <a href="map.php" class="btn btn-primary">Volver al Mapa</a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span>Error al cargar dispositivos: <?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Panel de control -->
            <div class="bg-white p-4 rounded-lg shadow-md">
                <h2 class="text-xl font-bold mb-4">Seleccionar Vehículo y Fechas</h2>

                <form action="" method="get" class="space-y-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Vehículo</span>
                        </label>
                        <select name="deviceId" class="select select-bordered w-full" required>
                            <option value="" disabled <?php echo !$selectedDeviceId ? 'selected' : ''; ?>>Seleccione un vehículo</option>
                            <?php foreach ($devices as $device): ?>
                                <option value="<?php echo $device['id']; ?>" <?php echo $selectedDeviceId == $device['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($device['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($selectedDeviceId): ?>
                    <div class="divider">Rangos Predefinidos</div>

                    <div class="grid grid-cols-2 gap-2">
                        <a href="?deviceId=<?php echo $selectedDeviceId; ?>&from=<?php echo date('Y-m-d'); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline">Hoy</a>
                        <a href="?deviceId=<?php echo $selectedDeviceId; ?>&from=<?php echo date('Y-m-d', strtotime('-1 day')); ?>&to=<?php echo date('Y-m-d', strtotime('-1 day')); ?>" class="btn btn-sm btn-outline">Ayer</a>
                        <a href="?deviceId=<?php echo $selectedDeviceId; ?>&from=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline">Última Semana</a>
                        <a href="?deviceId=<?php echo $selectedDeviceId; ?>&from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline">Último Mes</a>
                    </div>

                    <div class="divider">Rango Personalizado</div>
                    <?php endif; ?>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Desde</span>
                        </label>
                        <input type="date" name="from" value="<?php echo $fromDate; ?>" class="input input-bordered" required>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Hasta</span>
                        </label>
                        <input type="date" name="to" value="<?php echo $toDate; ?>" class="input input-bordered" required>
                    </div>

                    <div class="form-control">
                        <button type="submit" class="btn btn-primary w-full">Mostrar Ruta</button>
                    </div>
                </form>

                <?php if ($selectedDeviceId && $routeData && count($routeData) > 0): ?>
                <div class="divider">Información</div>

                <div class="stats stats-vertical shadow w-full">
                    <div class="stat">
                        <div class="stat-title">Vehículo</div>
                        <div class="stat-value text-lg"><?php echo htmlspecialchars($selectedDeviceName); ?></div>
                    </div>

                    <div class="stat">
                        <div class="stat-title">Puntos</div>
                        <div class="stat-value text-lg"><?php echo count($routeData); ?></div>
                    </div>

                    <div class="stat">
                        <div class="stat-title">Período</div>
                        <div class="stat-value text-lg"><?php echo date('d/m/Y', strtotime($fromDate)) . ' - ' . date('d/m/Y', strtotime($toDate)); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Mapa -->
            <div class="md:col-span-2">
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold mb-4">
                        <?php if ($selectedDeviceId): ?>
                            Ruta de <?php echo htmlspecialchars($selectedDeviceName); ?>
                        <?php else: ?>
                            Seleccione un vehículo para ver su ruta
                        <?php endif; ?>
                    </h2>

                    <div id="map"></div>

                    <?php if ($routeError): ?>
                    <div class="alert alert-error mt-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>Error al cargar la ruta: <?php echo htmlspecialchars($routeError); ?></span>
                    </div>
                    <?php elseif ($selectedDeviceId && (!$routeData || count($routeData) == 0)): ?>
                    <div class="alert alert-warning mt-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>No se encontraron datos de ruta para el período seleccionado.</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <div class="alert alert-info">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <h3 class="font-bold">Información</h3>
                    <div class="text-sm">
                        Este visualizador de rutas es independiente y no depende de ningún archivo externo.
                        Utiliza directamente la API de Traccar para obtener los datos.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Inicializar mapa
        const map = L.map('map').setView([-12.046374, -77.042793], 10);

        // Añadir capa base
        L.tileLayer('https://{s}-tiles.locationiq.com/v3/streets/r/{z}/{x}/{y}.png?key=pk.e63c15fb2d66e143a9ffe1a1e9596fb5', {
            attribution: '&copy; <a href="https://locationiq.com">LocationIQ</a> | &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            subdomains: ['a', 'b', 'c'],
            maxZoom: 19
        }).addTo(map);

        <?php if ($selectedDeviceId && $routeData && count($routeData) > 0): ?>
        // Mostrar ruta
        const routePoints = [
            <?php foreach ($routeData as $point): ?>
            [<?php echo $point['latitude']; ?>, <?php echo $point['longitude']; ?>],
            <?php endforeach; ?>
        ];

        // Crear línea de ruta
        const routeLine = L.polyline(routePoints, {
            color: 'blue',
            weight: 3,
            opacity: 0.7
        }).addTo(map);

        // Añadir marcadores de inicio y fin
        const startPoint = routePoints[0];
        const endPoint = routePoints[routePoints.length - 1];

        // Marcador de inicio
        const startIcon = L.divIcon({
            html: '<div class="route-marker-start">●</div>',
            className: '',
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });

        // Marcador de fin
        const endIcon = L.divIcon({
            html: '<div class="route-marker-end">●</div>',
            className: '',
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });

        // Añadir marcadores al mapa
        const startMarker = L.marker(startPoint, { icon: startIcon }).addTo(map);
        const endMarker = L.marker(endPoint, { icon: endIcon }).addTo(map);

        // Añadir popups a los marcadores
        const startTime = new Date('<?php echo $routeData[0]['deviceTime']; ?>').toLocaleString();
        const endTime = new Date('<?php echo $routeData[count($routeData) - 1]['deviceTime']; ?>').toLocaleString();

        startMarker.bindPopup(`<b>Inicio</b><br>Hora: ${startTime}`);
        endMarker.bindPopup(`<b>Fin</b><br>Hora: ${endTime}`);

        // Ajustar vista del mapa para mostrar toda la ruta
        map.fitBounds(routeLine.getBounds(), { padding: [50, 50] });

        // Mostrar información sobre la ruta
        console.log('Ruta cargada con éxito');
        console.log('Puntos:', routePoints.length);
        console.log('Inicio:', startTime);
        console.log('Fin:', endTime);
        <?php endif; ?>
    </script>
</body>
</html>
