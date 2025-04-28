<?php
/**
 * Vista independiente para visualizar rutas de vehículos
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

// Incluir archivos necesarios
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../php/api_proxy.php';

// Obtener dispositivos
$api = new TraccarAPI($_SESSION['JSESSIONID']);
$devices = $api->getDevices();

// Ordenar dispositivos por nombre
usort($devices, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Obtener parámetros de la URL
$selectedDeviceId = isset($_GET['deviceId']) ? intval($_GET['deviceId']) : null;

// Por defecto, usar el día actual para ambas fechas
$fromDate = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d');
$toDate = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

// Formatear fechas para la API si se ha seleccionado un dispositivo
$fromDateTime = null;
$toDateTime = null;
$routeData = null;

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
        $error = $e->getMessage();
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

// Generar token CSRF
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizador de Rutas</title>

    <!-- Estilos -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        #map {
            height: 100%;
            width: 100%;
            border-radius: 0.5rem;
            min-height: 600px;
        }
        .route-marker-start {
            color: green;
            font-size: 24px;
        }
        .route-marker-end {
            color: red;
            font-size: 24px;
        }
        .vehicle-marker-icon {
            width: 30px;
            height: 30px;
            background-image: url('img/car-arrow.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }
        .vehicle-marker-icon.stopped {
            background-image: url('img/car-stopped.png');
        }
        .vehicle-marker-icon.offline {
            opacity: 0.6;
        }
        .route-point {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #f59e0b; /* Ámbar */
            border: 1px solid white;
            box-shadow: 0 0 2px rgba(0, 0, 0, 0.3);
            transition: all 0.2s ease;
        }
        .route-point:hover {
            transform: scale(1.5);
            background-color: #f97316; /* Naranja */
        }
        .route-point.active {
            width: 12px;
            height: 12px;
            background-color: #ef4444; /* Rojo */
            border: 2px solid white;
            box-shadow: 0 0 4px rgba(0, 0, 0, 0.5);
            z-index: 1000 !important;
        }
        .route-point-popup .leaflet-popup-content-wrapper {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        .route-point-popup .leaflet-popup-content {
            margin: 8px 10px;
            font-size: 12px;
            line-height: 1.4;
        }
        .route-vehicle {
            width: 32px;
            height: 32px;
            background-color: #0ea5e9; /* Azul cielo */
            border-radius: 8px;
            border: 2px solid white;
            box-shadow: 0 0 6px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            transform-origin: center;
            z-index: 1001 !important;
            transition: all 0.3s ease;
        }
        .route-vehicle:hover {
            transform: scale(1.2) !important;
        }
        .vehicle-popup .leaflet-popup-content-wrapper {
            background-color: rgba(15, 23, 42, 0.9); /* Slate 900 con transparencia */
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            padding: 0;
            overflow: hidden;
        }
        .vehicle-popup .leaflet-popup-content {
            margin: 0;
            padding: 0;
        }
        .vehicle-popup .leaflet-popup-tip {
            background-color: rgba(15, 23, 42, 0.9);
        }
        .vehicle-popup-content {
            padding: 8px 12px;
            font-size: 12px;
            line-height: 1.4;
        }
        .vehicle-popup-header {
            background-color: #0ea5e9; /* Azul cielo */
            padding: 4px 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .vehicle-popup-data {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
        }
        /* Estilos para pantalla completa */
        .form-control {
            margin-bottom: 0.5rem;
        }
        .divider {
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .btn-sm {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body class="bg-gray-100 m-0 p-0 overflow-hidden">
    <div class="w-full h-screen flex flex-col p-2">
        <div class="flex justify-between items-center mb-2">
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-primary">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                </svg>
                Visualizador de Rutas
            </h1>
            <a href="map.php" class="btn btn-primary gap-2 shadow-lg hover:shadow-xl transition-all duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                </svg>
                Volver al Mapa
            </a>
        </div>

        <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-4 h-full overflow-hidden">
            <!-- Panel de control -->
            <div class="bg-white p-4 rounded-lg shadow-md h-full overflow-y-auto">
                <h2 class="text-xl font-bold mb-4 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                    </svg>
                    Seleccionar Vehículo y Fechas
                </h2>

                <form action="" method="get" class="space-y-4">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                Vehículo
                            </span>
                        </label>
                        <div class="relative">
                            <select name="deviceId" class="select select-bordered w-full pl-10" required>
                                <option value="" disabled <?php echo !$selectedDeviceId ? 'selected' : ''; ?>>Seleccione un vehículo</option>
                                <?php foreach ($devices as $device): ?>
                                    <option value="<?php echo $device['id']; ?>" <?php echo $selectedDeviceId == $device['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($device['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="divider">Rangos Predefinidos</div>

                    <div class="grid grid-cols-2 gap-2" id="predefined-ranges">
                        <a href="#" onclick="goToDateRange('today'); return false;" class="btn btn-sm btn-outline" id="btn-today">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                            </svg>
                            Hoy
                        </a>
                        <a href="#" onclick="goToDateRange('yesterday'); return false;" class="btn btn-sm btn-outline" id="btn-yesterday">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                            </svg>
                            Ayer
                        </a>
                        <a href="#" onclick="goToDateRange('week'); return false;" class="btn btn-sm btn-outline" id="btn-week">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5m-6-1.5h.008v.008H12v-.008ZM12 15h.008v.008H12V15Z" />
                            </svg>
                            Última Semana
                        </a>
                        <a href="#" onclick="goToDateRange('month'); return false;" class="btn btn-sm btn-outline" id="btn-month">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            Último Mes
                        </a>
                    </div>

                    <div class="divider">Rango Personalizado</div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                                </svg>
                                Desde
                            </span>
                        </label>
                        <div class="relative">
                            <input type="date" name="from" value="<?php echo $fromDate; ?>" class="input input-bordered w-full pl-10" required>
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                                </svg>
                                Hasta
                            </span>
                        </label>
                        <div class="relative">
                            <input type="date" name="to" value="<?php echo $toDate; ?>" class="input input-bordered w-full pl-10" required>
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="form-control">
                        <button type="submit" class="btn btn-primary w-full flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                            </svg>
                            Mostrar Ruta
                        </button>
                    </div>
                </form>

                <?php if ($selectedDeviceId && $routeData): ?>
                <div class="divider">Información</div>

                <div class="stats stats-vertical shadow w-full">
                    <div class="stat">
                        <div class="stat-title flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                            </svg>
                            Vehículo
                        </div>
                        <div class="stat-value text-lg"><?php echo htmlspecialchars($selectedDeviceName); ?></div>
                    </div>

                    <div class="stat">
                        <div class="stat-title flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                            </svg>
                            Puntos
                        </div>
                        <div class="stat-value text-lg"><?php echo count($routeData); ?></div>
                    </div>

                    <div class="stat">
                        <div class="stat-title flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 9v7.5" />
                            </svg>
                            Período
                        </div>
                        <div class="stat-value text-lg"><?php echo date('d/m/Y', strtotime($fromDate)) . ' - ' . date('d/m/Y', strtotime($toDate)); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Mapa -->
            <div class="md:col-span-3 h-full flex flex-col">
                <div class="bg-white p-4 rounded-lg shadow-md flex-1 flex flex-col">
                    <h2 class="text-xl font-bold mb-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                        </svg>
                        <?php if ($selectedDeviceId): ?>
                            Ruta de <?php echo htmlspecialchars($selectedDeviceName); ?>
                        <?php else: ?>
                            Seleccione un vehículo para ver su ruta
                        <?php endif; ?>
                    </h2>

                    <div id="map" class="flex-1"></div>

                    <?php if ($selectedDeviceId && $routeData && count($routeData) > 0): ?>
                    <div class="bg-base-200 p-2 rounded-lg mt-2">
                        <!-- Controles de visualización -->
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <div class="form-control">
                                <label class="label cursor-pointer flex items-center gap-2">
                                    <input type="checkbox" id="show-points" class="checkbox checkbox-sm checkbox-primary">
                                    <span class="label-text flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                        </svg>
                                        Mostrar puntos GPS
                                    </span>
                                </label>
                            </div>

                            <div class="divider divider-horizontal"></div>

                            <!-- Reproductor de recorrido -->
                            <div class="flex-1 flex flex-wrap items-center gap-2">
                                <div class="btn-group">
                                    <button id="btn-prev" class="btn btn-sm btn-outline" data-tooltip-id="tooltip-prev">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 16.811c0 .864-.933 1.406-1.683.977l-7.108-4.061a1.125 1.125 0 0 1 0-1.954l7.108-4.061A1.125 1.125 0 0 1 21 8.689v8.122ZM11.25 16.811c0 .864-.933 1.406-1.683.977l-7.108-4.061a1.125 1.125 0 0 1 0-1.954l7.108-4.061a1.125 1.125 0 0 1 1.683.977v8.122Z" />
                                        </svg>
                                    </button>
                                    <button id="btn-play" class="btn btn-sm btn-primary" data-tooltip-id="tooltip-play">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                        </svg>
                                    </button>
                                    <button id="btn-pause" class="btn btn-sm btn-warning hidden" data-tooltip-id="tooltip-pause">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                                        </svg>
                                    </button>
                                    <button id="btn-next" class="btn btn-sm btn-outline" data-tooltip-id="tooltip-next">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061A1.125 1.125 0 0 1 3 16.811V8.69ZM12.75 8.689c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061a1.125 1.125 0 0 1-1.683-.977V8.69Z" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="tooltip tooltip-bottom" id="tooltip-prev" data-tip="Punto anterior"></div>
                                <div class="tooltip tooltip-bottom" id="tooltip-play" data-tip="Reproducir"></div>
                                <div class="tooltip tooltip-bottom" id="tooltip-pause" data-tip="Pausar"></div>
                                <div class="tooltip tooltip-bottom" id="tooltip-next" data-tip="Punto siguiente"></div>

                                <input type="range" id="route-progress" min="0" max="100" value="0" class="range range-xs range-primary flex-1">
                                <div class="w-full flex justify-between text-xs px-1">
                                    <span id="progress-start">--:--</span>
                                    <span id="progress-current">--:--</span>
                                    <span id="progress-end">--:--</span>
                                </div>

                                <div class="dropdown dropdown-end">
                                    <label tabindex="0" class="btn btn-sm btn-outline">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        Velocidad
                                    </label>
                                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                        <li><a class="speed-option" data-speed="0.5">0.5x (Lento)</a></li>
                                        <li><a class="speed-option active" data-speed="1">1x (Normal)</a></li>
                                        <li><a class="speed-option" data-speed="2">2x (Rápido)</a></li>
                                        <li><a class="speed-option" data-speed="4">4x (Muy rápido)</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Información del punto actual -->
                        <div id="point-info" class="bg-base-100 p-2 rounded-lg text-sm hidden">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    <span class="font-bold">Punto: </span>
                                    <span id="point-index">0</span> / <span id="point-total">0</span>
                                </div>
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span id="point-time">--:--:--</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mt-1">
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6.115 5.19.319 1.913A6 6 0 0 0 8.11 10.36L9.75 12l-.387.775c-.217.433-.132.956.21 1.298l1.348 1.348c.21.21.329.497.329.795v1.089c0 .426.24.815.622 1.006l.153.076c.433.217.956.132 1.298-.21l.723-.723a8.7 8.7 0 0 0 2.288-4.042 1.087 1.087 0 0 0-.358-1.099l-1.33-1.108c-.251-.21-.582-.299-.905-.245l-1.17.195a1.125 1.125 0 0 1-.98-.314l-.295-.295a1.125 1.125 0 0 1 0-1.591l.13-.132a1.125 1.125 0 0 1 1.3-.21l.603.302a.809.809 0 0 0 1.086-1.086L14.25 7.5l1.256-.837a4.5 4.5 0 0 0 1.528-1.732l.146-.292M6.115 5.19A9 9 0 1 0 17.18 4.64M6.115 5.19A8.965 8.965 0 0 0 3.95 8.05m0 0A9 9 0 0 0 3 14.128m1.757-8.545c-.061.08-.122.161-.183.241M3 14.128c.14.36.304.736.492 1.094m2.422-8.716-.15.137a8.965 8.965 0 0 0-1.593 3.164m2.421-8.716 1.573.735.29-.146a8.97 8.97 0 0 0 2.416-.789l.136-.075m-4.415 0a9 9 0 0 0-3.755 8.979" />
                                    </svg>
                                    <span class="font-bold">Lat: </span>
                                    <span id="point-lat">0.000000</span>
                                </div>
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6.115 5.19.319 1.913A6 6 0 0 0 8.11 10.36L9.75 12l-.387.775c-.217.433-.132.956.21 1.298l1.348 1.348c.21.21.329.497.329.795v1.089c0 .426.24.815.622 1.006l.153.076c.433.217.956.132 1.298-.21l.723-.723a8.7 8.7 0 0 0 2.288-4.042 1.087 1.087 0 0 0-.358-1.099l-1.33-1.108c-.251-.21-.582-.299-.905-.245l-1.17.195a1.125 1.125 0 0 1-.98-.314l-.295-.295a1.125 1.125 0 0 1 0-1.591l.13-.132a1.125 1.125 0 0 1 1.3-.21l.603.302a.809.809 0 0 0 1.086-1.086L14.25 7.5l1.256-.837a4.5 4.5 0 0 0 1.528-1.732l.146-.292M6.115 5.19A9 9 0 1 0 17.18 4.64M6.115 5.19A8.965 8.965 0 0 0 3.95 8.05m0 0A9 9 0 0 0 3 14.128m1.757-8.545c-.061.08-.122.161-.183.241M3 14.128c.14.36.304.736.492 1.094m2.422-8.716-.15.137a8.965 8.965 0 0 0-1.593 3.164m2.421-8.716 1.573.735.29-.146a8.97 8.97 0 0 0 2.416-.789l.136-.075m-4.415 0a9 9 0 0 0-3.755 8.979" />
                                    </svg>
                                    <span class="font-bold">Lon: </span>
                                    <span id="point-lon">0.000000</span>
                                </div>
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.25V18a2.25 2.25 0 0 0 2.25 2.25h13.5A2.25 2.25 0 0 0 21 18V8.25m-18 0V6a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 6v2.25m-18 0h18M5.25 6h.008v.008H5.25V6ZM7.5 6h.008v.008H7.5V6Zm2.25 0h.008v.008H9.75V6Z" />
                                    </svg>
                                    <span class="font-bold">Vel: </span>
                                    <span id="point-speed">0.0</span> km/h
                                </div>
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    <span class="font-bold">Alt: </span>
                                    <span id="point-altitude">0.0</span> m
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($selectedDeviceId && (!$routeData || count($routeData) == 0)): ?>
                    <div class="alert alert-warning mt-2 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h3 class="font-bold">No se encontraron datos</h3>
                            <div class="text-sm">No hay datos de ruta disponibles para el vehículo en el período seleccionado. Intente con un rango de fechas diferente.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Inicializar mapa
        const map = L.map('map').setView([<?php echo MAP_DEFAULT_LAT; ?>, <?php echo MAP_DEFAULT_LON; ?>], <?php echo MAP_DEFAULT_ZOOM; ?>);

        // Añadir capa base
        L.tileLayer('https://{s}-tiles.locationiq.com/v3/streets/r/{z}/{x}/{y}.png?key=pk.e63c15fb2d66e143a9ffe1a1e9596fb5', {
            attribution: '&copy; <a href="https://locationiq.com">LocationIQ</a> | &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            subdomains: ['a', 'b', 'c'],
            maxZoom: 19
        }).addTo(map);

        // Función para manejar los botones de rangos predefinidos
        function handlePredefinedRangeButtons() {
            const deviceSelect = document.querySelector('select[name=deviceId]');
            const rangeButtons = document.querySelectorAll('#predefined-ranges a');

            // Verificar si hay un dispositivo seleccionado
            const isDeviceSelected = deviceSelect.value !== '';

            // Habilitar o deshabilitar botones según si hay un dispositivo seleccionado
            rangeButtons.forEach(button => {
                if (isDeviceSelected) {
                    button.classList.remove('btn-disabled');
                    button.removeAttribute('disabled');
                } else {
                    button.classList.add('btn-disabled');
                    button.setAttribute('disabled', 'disabled');
                }
            });
        }

        // Función para ir a un rango de fechas predefinido
        function goToDateRange(range) {
            const deviceId = document.querySelector('select[name=deviceId]').value;

            if (!deviceId) {
                alert('Por favor, seleccione un vehículo primero');
                return;
            }

            let fromDate, toDate;
            const today = new Date();
            const formatDate = date => {
                return date.toISOString().split('T')[0]; // Formato YYYY-MM-DD
            };

            switch (range) {
                case 'today':
                    fromDate = formatDate(today);
                    toDate = formatDate(today);
                    break;

                case 'yesterday':
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    fromDate = formatDate(yesterday);
                    toDate = formatDate(yesterday);
                    break;

                case 'week':
                    const weekAgo = new Date(today);
                    weekAgo.setDate(weekAgo.getDate() - 7);
                    fromDate = formatDate(weekAgo);
                    toDate = formatDate(today);
                    break;

                case 'month':
                    const monthAgo = new Date(today);
                    monthAgo.setDate(monthAgo.getDate() - 30);
                    fromDate = formatDate(monthAgo);
                    toDate = formatDate(today);
                    break;
            }

            // Redirigir a la URL con los parámetros actualizados
            window.location.href = `route_viewer.php?deviceId=${deviceId}&from=${fromDate}&to=${toDate}`;
        }

        // Ejecutar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const deviceSelect = document.querySelector('select[name=deviceId]');

            // Manejar cambios en la selección de dispositivo
            deviceSelect.addEventListener('change', handlePredefinedRangeButtons);

            // Ejecutar al inicio
            handlePredefinedRangeButtons();
        });

        <?php if ($selectedDeviceId && $routeData && count($routeData) > 0): ?>
        // Datos de la ruta
        const routeData = [
            <?php foreach ($routeData as $point): ?>
            {
                latitude: <?php echo $point['latitude']; ?>,
                longitude: <?php echo $point['longitude']; ?>,
                time: "<?php echo isset($point['deviceTime']) ? $point['deviceTime'] : (isset($point['fixTime']) ? $point['fixTime'] : ''); ?>",
                speed: <?php echo isset($point['speed']) ? $point['speed'] : 0; ?>,
                altitude: <?php echo isset($point['altitude']) ? $point['altitude'] : 0; ?>,
                attributes: <?php echo isset($point['attributes']) ? json_encode($point['attributes']) : '{}'; ?>
            },
            <?php endforeach; ?>
        ];

        // Extraer coordenadas para la línea de ruta
        const routePoints = routeData.map(point => [point.latitude, point.longitude]);

        // Variables para el reproductor de ruta
        let routePointMarkers = [];
        let vehicleMarker = null;
        let currentPointIndex = 0;
        let isPlaying = false;
        let playbackSpeed = 1;
        let playbackInterval = null;

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
        const startTime = new Date(routeData[0].time).toLocaleString();
        const endTime = new Date(routeData[routeData.length - 1].time).toLocaleString();

        startMarker.bindPopup(`<b>Inicio</b><br>Hora: ${startTime}`);
        endMarker.bindPopup(`<b>Fin</b><br>Hora: ${endTime}`);

        // Ajustar vista del mapa para mostrar toda la ruta
        map.fitBounds(routeLine.getBounds(), { padding: [50, 50] });

        // Función para crear marcadores de puntos GPS
        function createRoutePointMarkers() {
            // Limpiar marcadores existentes
            routePointMarkers.forEach(marker => map.removeLayer(marker));
            routePointMarkers = [];

            // Crear nuevos marcadores
            routeData.forEach((point, index) => {
                // Determinar el color según la velocidad
                let pointColor = '#10b981'; // Verde para velocidad baja
                if (point.speed * 3.6 > 80) {
                    pointColor = '#ef4444'; // Rojo para velocidad alta
                } else if (point.speed * 3.6 > 40) {
                    pointColor = '#f59e0b'; // Ámbar para velocidad media
                }

                const pointIcon = L.divIcon({
                    html: `<div class="route-point" id="route-point-${index}" style="background-color: ${pointColor}"></div>`,
                    className: '',
                    iconSize: [8, 8],
                    iconAnchor: [4, 4]
                });

                const marker = L.marker([point.latitude, point.longitude], { icon: pointIcon });

                // Crear contenido del popup con DaisyUI
                const time = new Date(point.time).toLocaleString();
                const speed = (point.speed * 3.6).toFixed(1); // Convertir a km/h

                const popupContent = `
                    <div class="card card-compact bg-base-100 shadow-xl" style="width: 220px;">
                        <div class="card-body p-2">
                            <h3 class="card-title text-sm flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                                Punto ${index + 1} de ${routeData.length}
                            </h3>
                            <div class="text-xs space-y-1">
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span><b>Hora:</b> ${time}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6.115 5.19.319 1.913A6 6 0 0 0 8.11 10.36L9.75 12l-.387.775c-.217.433-.132.956.21 1.298l1.348 1.348c.21.21.329.497.329.795v1.089c0 .426.24.815.622 1.006l.153.076c.433.217.956.132 1.298-.21l.723-.723a8.7 8.7 0 0 0 2.288-4.042 1.087 1.087 0 0 0-.358-1.099l-1.33-1.108c-.251-.21-.582-.299-.905-.245l-1.17.195a1.125 1.125 0 0 1-.98-.314l-.295-.295a1.125 1.125 0 0 1 0-1.591l.13-.132a1.125 1.125 0 0 1 1.3-.21l.603.302a.809.809 0 0 0 1.086-1.086L14.25 7.5l1.256-.837a4.5 4.5 0 0 0 1.528-1.732l.146-.292M6.115 5.19A9 9 0 1 0 17.18 4.64M6.115 5.19A8.965 8.965 0 0 0 3.95 8.05m0 0A9 9 0 0 0 3 14.128m1.757-8.545c-.061.08-.122.161-.183.241M3 14.128c.14.36.304.736.492 1.094m2.422-8.716-.15.137a8.965 8.965 0 0 0-1.593 3.164m2.421-8.716 1.573.735.29-.146a8.97 8.97 0 0 0 2.416-.789l.136-.075m-4.415 0a9 9 0 0 0-3.755 8.979" />
                                    </svg>
                                    <span><b>Coordenadas:</b> ${point.latitude.toFixed(6)}, ${point.longitude.toFixed(6)}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3" style="color: ${pointColor}">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.25V18a2.25 2.25 0 0 0 2.25 2.25h13.5A2.25 2.25 0 0 0 21 18V8.25m-18 0V6a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 6v2.25m-18 0h18M5.25 6h.008v.008H5.25V6ZM7.5 6h.008v.008H7.5V6Zm2.25 0h.008v.008H9.75V6Z" />
                                    </svg>
                                    <span style="color: ${pointColor}"><b>Velocidad:</b> ${speed} km/h</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                    </svg>
                                    <span><b>Altitud:</b> ${point.altitude.toFixed(1)} m</span>
                                </div>
                            </div>
                            <div class="card-actions justify-end mt-1">
                                <button class="btn btn-xs btn-primary" onclick="setCurrentPoint(${index}); return false;">Ir a este punto</button>
                            </div>
                        </div>
                    </div>
                `;

                marker.bindPopup(popupContent, {
                    className: 'route-point-popup',
                    offset: [0, -4],
                    maxWidth: 250
                });

                // Evento al hacer clic en el punto
                marker.on('click', function() {
                    setCurrentPoint(index);
                });

                routePointMarkers.push(marker);
            });
        }

        // Función para mostrar/ocultar marcadores de puntos GPS
        function toggleRoutePoints() {
            const showPoints = document.getElementById('show-points').checked;

            if (showPoints) {
                // Si no hay marcadores, crearlos
                if (routePointMarkers.length === 0) {
                    createRoutePointMarkers();
                }

                // Mostrar marcadores
                routePointMarkers.forEach(marker => marker.addTo(map));
            } else {
                // Ocultar marcadores
                routePointMarkers.forEach(marker => map.removeLayer(marker));
            }
        }

        // Función para crear el marcador del vehículo
        function createVehicleMarker() {
            if (vehicleMarker) {
                map.removeLayer(vehicleMarker);
            }

            const point = routeData[currentPointIndex];
            const heading = calculateHeading(currentPointIndex);
            const speed = (point.speed * 3.6).toFixed(1); // Convertir a km/h
            const time = new Date(point.time).toLocaleString();

            // Determinar el color según la velocidad
            let speedColor = '#10b981'; // Verde para velocidad baja
            if (point.speed * 3.6 > 80) {
                speedColor = '#ef4444'; // Rojo para velocidad alta
            } else if (point.speed * 3.6 > 40) {
                speedColor = '#f59e0b'; // Ámbar para velocidad media
            }

            const vehicleIcon = L.divIcon({
                html: `
                    <div class="route-vehicle" style="transform: rotate(${heading}deg)">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                        </svg>
                    </div>
                `,
                className: '',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });

            // Crear el popup con información del vehículo
            const popupContent = `
                <div class="vehicle-popup-content">
                    <div class="vehicle-popup-header">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                        </svg>
                        Punto ${currentPointIndex + 1} de ${routeData.length}
                    </div>
                    <div class="vehicle-popup-data">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        ${time}
                    </div>
                    <div class="vehicle-popup-data">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" style="color: ${speedColor}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.25V18a2.25 2.25 0 0 0 2.25 2.25h13.5A2.25 2.25 0 0 0 21 18V8.25m-18 0V6a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 6v2.25m-18 0h18M5.25 6h.008v.008H5.25V6ZM7.5 6h.008v.008H7.5V6Zm2.25 0h.008v.008H9.75V6Z" />
                        </svg>
                        <span style="color: ${speedColor}">${speed} km/h</span>
                    </div>
                </div>
            `;

            vehicleMarker = L.marker([point.latitude, point.longitude], { icon: vehicleIcon }).addTo(map);
            vehicleMarker.bindPopup(popupContent, {
                className: 'vehicle-popup',
                offset: [0, -16],
                closeButton: false,
                autoClose: false,
                closeOnClick: false
            });

            // Mostrar el popup si la velocidad es alta
            if (point.speed * 3.6 > 60) {
                vehicleMarker.openPopup();
            }
        }

        // Función para calcular el ángulo de dirección
        function calculateHeading(index) {
            if (index === routeData.length - 1 || index === 0) {
                return 0; // Sin dirección para el primer o último punto
            }

            const current = routeData[index];
            const next = routeData[index + 1];

            // Calcular ángulo entre puntos
            const dx = next.longitude - current.longitude;
            const dy = next.latitude - current.latitude;

            let angle = Math.atan2(dy, dx) * 180 / Math.PI;
            angle = (angle + 90) % 360; // Ajustar para que 0 sea norte

            return angle;
        }

        // Función para establecer el punto actual
        function setCurrentPoint(index) {
            // Validar índice
            if (index < 0) index = 0;
            if (index >= routeData.length) index = routeData.length - 1;

            currentPointIndex = index;

            // Actualizar marcadores de puntos
            routePointMarkers.forEach((marker, i) => {
                const pointElement = document.getElementById(`route-point-${i}`);
                if (pointElement) {
                    if (i === currentPointIndex) {
                        pointElement.classList.add('active');
                    } else {
                        pointElement.classList.remove('active');
                    }
                }
            });

            // Actualizar marcador del vehículo
            createVehicleMarker();

            // Actualizar información del punto
            updatePointInfo();

            // Actualizar barra de progreso
            document.getElementById('route-progress').value = (currentPointIndex / (routeData.length - 1)) * 100;

            // Centrar mapa en el punto actual
            const point = routeData[currentPointIndex];
            map.panTo([point.latitude, point.longitude]);
        }

        // Función para actualizar la información del punto
        function updatePointInfo() {
            const point = routeData[currentPointIndex];
            const pointInfo = document.getElementById('point-info');

            // Mostrar panel de información
            pointInfo.classList.remove('hidden');

            // Actualizar datos
            document.getElementById('point-index').textContent = currentPointIndex + 1;
            document.getElementById('point-total').textContent = routeData.length;
            document.getElementById('point-time').textContent = new Date(point.time).toLocaleString();
            document.getElementById('point-lat').textContent = point.latitude.toFixed(6);
            document.getElementById('point-lon').textContent = point.longitude.toFixed(6);
            document.getElementById('point-speed').textContent = (point.speed * 3.6).toFixed(1); // Convertir a km/h
            document.getElementById('point-altitude').textContent = point.altitude.toFixed(1);

            // Actualizar información de progreso
            const formatTimeShort = (date) => {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            };

            // Actualizar tiempos en la barra de progreso
            document.getElementById('progress-start').textContent = formatTimeShort(new Date(routeData[0].time));
            document.getElementById('progress-current').textContent = formatTimeShort(new Date(point.time));
            document.getElementById('progress-end').textContent = formatTimeShort(new Date(routeData[routeData.length - 1].time));

            // Cambiar el color del punto actual en el mapa
            routePointMarkers.forEach((marker, i) => {
                const pointElement = document.getElementById(`route-point-${i}`);
                if (pointElement) {
                    if (i === currentPointIndex) {
                        pointElement.classList.add('active');
                        // Hacer zoom al punto si está fuera de la vista
                        if (!map.getBounds().contains([routeData[i].latitude, routeData[i].longitude])) {
                            map.panTo([routeData[i].latitude, routeData[i].longitude]);
                        }
                    } else {
                        pointElement.classList.remove('active');
                    }
                }
            });
        }

        // Función para iniciar la reproducción
        function startPlayback() {
            if (isPlaying) return;

            isPlaying = true;
            document.getElementById('btn-play').classList.add('hidden');
            document.getElementById('btn-pause').classList.remove('hidden');

            // Iniciar intervalo de reproducción
            playbackInterval = setInterval(() => {
                if (currentPointIndex < routeData.length - 1) {
                    setCurrentPoint(currentPointIndex + 1);
                } else {
                    pausePlayback();
                }
            }, 1000 / playbackSpeed);
        }

        // Función para pausar la reproducción
        function pausePlayback() {
            if (!isPlaying) return;

            isPlaying = false;
            document.getElementById('btn-play').classList.remove('hidden');
            document.getElementById('btn-pause').classList.add('hidden');

            clearInterval(playbackInterval);
        }

        // Función para cambiar la velocidad de reproducción
        function setPlaybackSpeed(speed) {
            playbackSpeed = speed;

            // Actualizar UI
            document.querySelectorAll('.speed-option').forEach(option => {
                if (parseFloat(option.dataset.speed) === speed) {
                    option.classList.add('active');
                } else {
                    option.classList.remove('active');
                }
            });

            // Reiniciar intervalo si está reproduciendo
            if (isPlaying) {
                clearInterval(playbackInterval);
                startPlayback();
            }
        }

        // Función para mostrar notificaciones
        function showToast(message, type = 'info') {
            const bgColors = {
                'success': '#4CAF50',
                'error': '#F44336',
                'warning': '#FF9800',
                'info': '#2196F3'
            };

            Toastify({
                text: message,
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: bgColors[type],
                stopOnFocus: true
            }).showToast();
        }

        // Inicializar reproductor
        document.addEventListener('DOMContentLoaded', function() {
            // Eventos para controles de reproducción
            document.getElementById('btn-play').addEventListener('click', startPlayback);
            document.getElementById('btn-pause').addEventListener('click', pausePlayback);
            document.getElementById('btn-prev').addEventListener('click', () => setCurrentPoint(currentPointIndex - 1));
            document.getElementById('btn-next').addEventListener('click', () => setCurrentPoint(currentPointIndex + 1));

            // Evento para mostrar/ocultar puntos GPS
            const showPointsCheckbox = document.getElementById('show-points');
            showPointsCheckbox.addEventListener('change', toggleRoutePoints);

            // Evento para la barra de progreso
            document.getElementById('route-progress').addEventListener('input', function() {
                const progress = this.value / 100;
                const index = Math.round(progress * (routeData.length - 1));
                setCurrentPoint(index);
            });

            // Eventos para opciones de velocidad
            document.querySelectorAll('.speed-option').forEach(option => {
                option.addEventListener('click', function() {
                    setPlaybackSpeed(parseFloat(this.dataset.speed));
                });
            });

            // Mostrar puntos GPS por defecto
            showPointsCheckbox.checked = true;
            createRoutePointMarkers();
            toggleRoutePoints();

            // Inicializar con el primer punto
            setCurrentPoint(0);

            // Mostrar notificación de éxito
            showToast(`Ruta cargada con ${routePoints.length} puntos`, 'success');
        });

        // Mostrar información sobre la ruta
        console.log('Ruta cargada con éxito');
        console.log('Puntos:', routePoints.length);
        console.log('Inicio:', startTime);
        console.log('Fin:', endTime);
        <?php elseif ($selectedDeviceId): ?>
        // No hay datos de ruta
        console.log('No se encontraron datos de ruta para el período seleccionado');
        <?php endif; ?>
    </script>
</body>
</html>
