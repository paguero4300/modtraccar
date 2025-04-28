<?php
/**
 * Vista lineal para mostrar la posición relativa de los vehículos en la ruta
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

// Obtener posiciones actuales
$positions = $api->getPositions();

// Crear un mapa de posiciones por ID de dispositivo
$positionMap = [];
foreach ($positions as $position) {
    $positionMap[$position['deviceId']] = $position;
}

// Filtrar dispositivos con terminal A o B y que tengan posición
$terminalADevices = [];
$terminalBDevices = [];

foreach ($devices as $device) {
    // Verificar si el dispositivo tiene terminal y posición
    if (isset($device['terminal']) && !empty($device['terminal']) && isset($positionMap[$device['id']])) {
        $terminal = strtoupper(trim($device['terminal']));

        // Verificar si tiene despacho hoy
        $hasDispatchToday = false;
        if (isset($device['ultimo_despacho']) && !empty($device['ultimo_despacho'])) {
            $despachoDate = new DateTime($device['ultimo_despacho']);
            $despachoDate->setTime(0, 0, 0);

            $today = new DateTime();
            $today->setTime(0, 0, 0);

            $hasDispatchToday = $despachoDate->format('Y-m-d') === $today->format('Y-m-d');
        }

        // Solo incluir dispositivos con despacho hoy
        if ($hasDispatchToday) {
            $device['position'] = $positionMap[$device['id']];

            if ($terminal === 'A') {
                $terminalADevices[] = $device;
            } elseif ($terminal === 'B') {
                $terminalBDevices[] = $device;
            }
        }
    }
}

// Obtener datos del usuario
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; connect-src 'self' https://localhost https://monitoreo.transporteurbanogps.click wss://monitoreo.transporteurbanogps.click https://*.locationiq.com wss://rastreo.transporteurbanogps.click; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://unpkg.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://cdn.tailwindcss.com; img-src 'self' data: https://*.locationiq.com https://*.tile.openstreetmap.org;">
    <title>Vista Lineal - <?php echo APP_NAME; ?></title>

    <!-- Estilos -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@2.51.5/dist/full.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" rel="stylesheet">

    <style>
        /* Variables para proporción áurea (1:1.618) */
        :root {
            --golden-ratio: 1.618;
            --base-unit: 8px;
            --golden-sm: calc(var(--base-unit) * 1);
            --golden-md: calc(var(--base-unit) * var(--golden-ratio));
            --golden-lg: calc(var(--golden-md) * var(--golden-ratio));
            --golden-xl: calc(var(--golden-lg) * var(--golden-ratio));
            --golden-xxl: calc(var(--golden-xl) * var(--golden-ratio));

            /* Colores armónicos basados en proporción áurea en el círculo cromático */
            --color-primary: #3b82f6;
            --color-secondary: #8b5cf6;
            --color-accent-1: #10b981;
            --color-accent-2: #ef4444;
            --color-terminal-a: #36D399;
            --color-terminal-b: #FF7AC6;

            /* Espaciado basado en proporción áurea */
            --spacing-xs: calc(var(--base-unit) * 0.618);
            --spacing-sm: var(--base-unit);
            --spacing-md: calc(var(--base-unit) * 1.618);
            --spacing-lg: calc(var(--base-unit) * 2.618);
            --spacing-xl: calc(var(--base-unit) * 4.236);

            /* Radios de borde basados en proporción áurea */
            --radius-sm: 4px;
            --radius-md: 6px;
            --radius-lg: 10px;
            --radius-xl: 16px;
        }

        /* Estilos para la vista lineal - simplificados para mejor alineación */
        .linear-container {
            position: relative;
            width: 100%;
            height: 80px; /* Altura fija para mejor alineación */
            background-color: #f8fafc;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: visible; /* Permitir que los elementos sobresalgan */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] .linear-container {
            background-color: #1e293b;
            border-color: rgba(255, 255, 255, 0.1);
        }

        /* Principio de continuidad de Gestalt - la línea guía el ojo a través de la ruta */
        .linear-track {
            position: absolute;
            top: 50%;
            /* Espaciado basado en proporción áurea - ajustado para alinearse con los vehículos */
            left: 0;
            right: 0;
            height: var(--spacing-sm);
            /* Color sólido para mejor visibilidad */
            background-color: #000;
            transform: translateY(-50%);
            border-radius: 0;
            /* Eliminar sombra y marcas para un diseño más limpio */
        }

        /* Puntos de inicio y fin simplificados */
        .linear-start {
            display: none; /* Ocultar para simplificar el diseño */
        }

        .linear-end {
            display: none; /* Ocultar para simplificar el diseño */
        }

        /* Estilos simplificados para los marcadores de vehículos */
        .vehicle-marker {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 60px;
            margin-left: -20px;
            z-index: 20;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .vehicle-marker:hover {
            transform: translateY(-50%) scale(1.1);
            z-index: 30;
        }

        .vehicle-icon {
            width: 30px;
            height: 30px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
        }

        .vehicle-marker.terminal-a .vehicle-icon {
            background-color: #36D399;
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            border: 1px solid rgba(0, 0, 0, 0.2);
        }

        .vehicle-marker.terminal-b .vehicle-icon {
            background-color: #FF7AC6;
            clip-path: polygon(50% 100%, 0% 0%, 100% 0%);
            border: 1px solid rgba(0, 0, 0, 0.2);
        }

        .vehicle-padron {
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 3px;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .vehicle-marker.terminal-a .vehicle-padron {
            background-color: rgba(54, 211, 153, 0.9);
        }

        .vehicle-marker.terminal-b .vehicle-padron {
            background-color: rgba(255, 122, 198, 0.9);
        }

        /* Principio de cierre de Gestalt - información completa en tooltips */
        .vehicle-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.9);
            color: white;
            /* Espaciado basado en proporción áurea */
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--radius-md);
            font-size: calc(var(--spacing-md) * 0.9);
            /* Permitir múltiples líneas */
            white-space: normal;
            width: max-content;
            max-width: 250px;
            min-width: 200px;
            opacity: 0;
            /* Transición suave con curva de aceleración natural */
            transition: all 0.3s cubic-bezier(0.25, 0.1, 0.25, 1);
            pointer-events: none;
            z-index: 30;
            box-shadow: 0 var(--spacing-xs) var(--spacing-md) rgba(0, 0, 0, 0.2);
            transform: translateX(-50%) translateY(var(--spacing-md));
            border: 1px solid rgba(255, 255, 255, 0.1);
            /* Añadir desenfoque para efecto de profundidad */
            backdrop-filter: blur(4px);
            /* Mejorar espaciado interno */
            line-height: 1.5;
        }

        /* Estilos para los elementos dentro del tooltip */
        .vehicle-tooltip > div {
            margin-bottom: var(--spacing-xs);
        }

        .vehicle-tooltip > div:last-child {
            margin-bottom: 0;
        }

        .vehicle-tooltip .font-bold {
            font-weight: bold;
            font-size: calc(var(--spacing-md) * 1.1);
            margin-bottom: var(--spacing-sm);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding-bottom: var(--spacing-xs);
        }

        /* Estilos para los indicadores de tiempo */
        .vehicle-tooltip .text-success {
            color: #36D399;
            font-weight: bold;
        }

        .vehicle-tooltip .text-warning {
            color: #FBBD23;
            font-weight: bold;
        }

        .vehicle-tooltip .text-error {
            color: #F87272;
            font-weight: bold;
        }

        .vehicle-tooltip .text-info {
            color: #3ABFF8;
            font-weight: bold;
        }

        /* Estilo para el contador de vehículos */
        .device-counter {
            position: absolute;
            top: -25px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            z-index: 10;
        }

        .frequency-info {
            position: absolute;
            top: -25px;
            left: 10px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            z-index: 10;
        }

        [data-theme="dark"] .device-counter,
        [data-theme="dark"] .frequency-info {
            background-color: rgba(255, 255, 255, 0.2);
        }

        /* Principio de continuidad de Gestalt - flecha que conecta tooltip con vehículo */
        .vehicle-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: calc(var(--spacing-sm) * -0.75);
            border-width: calc(var(--spacing-sm) * 0.75);
            border-style: solid;
            border-color: rgba(0, 0, 0, 0.85) transparent transparent transparent;
            /* Transición para movimiento coordinado */
            transition: all 0.3s ease;
        }

        /* Efecto de aparición suave */
        .vehicle-marker:hover .vehicle-tooltip {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        /* Principio de destino común de Gestalt - etiquetas que pertenecen al contenedor */
        .distance-label {
            position: absolute;
            top: var(--spacing-md);
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(255, 255, 255, 0.9);
            /* Espaciado basado en proporción áurea */
            padding: var(--spacing-xs) var(--spacing-md);
            border-radius: var(--spacing-xl);
            font-size: var(--spacing-md);
            font-weight: bold;
            box-shadow: 0 var(--spacing-xs) var(--spacing-sm) rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            color: #4b5563;
            /* Añadir transición para interactividad */
            transition: all 0.3s ease;
            /* Añadir icono de distancia */
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }

        .distance-label::before {
            content: '';
            width: var(--spacing-md);
            height: var(--spacing-md);
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="%234b5563" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>');
            background-size: contain;
            background-repeat: no-repeat;
        }

        .distance-label:hover {
            transform: translateX(-50%) scale(1.05);
            box-shadow: 0 var(--spacing-sm) var(--spacing-md) rgba(0, 0, 0, 0.15);
        }

        [data-theme="dark"] .distance-label {
            background-color: rgba(30, 41, 59, 0.9);
            color: #e5e7eb;
            border-color: rgba(255, 255, 255, 0.1);
        }

        [data-theme="dark"] .distance-label::before {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="%23e5e7eb" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>');
        }

        /* Principio de simetría de Gestalt - etiquetas de terminal equilibradas */
        .terminal-label {
            position: absolute;
            bottom: var(--spacing-md);
            font-size: var(--spacing-md);
            font-weight: bold;
            color: #4b5563;
            /* Añadir fondo para mejor legibilidad */
            background-color: rgba(255, 255, 255, 0.7);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-sm);
            /* Transición suave */
            transition: all 0.3s ease;
        }

        .terminal-label:hover {
            transform: scale(1.1);
            background-color: rgba(255, 255, 255, 0.9);
        }

        [data-theme="dark"] .terminal-label {
            color: #e5e7eb;
            background-color: rgba(30, 41, 59, 0.7);
        }

        [data-theme="dark"] .terminal-label:hover {
            background-color: rgba(30, 41, 59, 0.9);
        }

        .terminal-a-label {
            left: var(--spacing-lg);
        }

        .terminal-b-label {
            right: var(--spacing-lg);
        }

        .legend-container {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        [data-theme="dark"] .legend-container {
            background-color: rgba(30, 41, 59, 0.5);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background-color: white;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        [data-theme="dark"] .legend-item {
            background-color: #1e293b;
        }

        .legend-icon-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .legend-icon {
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        .legend-padron {
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 1px 3px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            margin-top: 2px;
        }

        .legend-terminal-a .legend-padron {
            background-color: rgba(54, 211, 153, 0.9);
        }

        .legend-terminal-b .legend-padron {
            background-color: rgba(255, 122, 198, 0.9);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }

        [data-theme="dark"] .section-title {
            border-bottom-color: #374151;
        }

        .section-title .badge {
            font-size: 14px;
        }

        .section-title .badge-success {
            background-color: #10b981;
        }

        .section-title .badge-secondary {
            background-color: #ff7ac6;
        }

        .table-container {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .table-header {
            position: sticky;
            top: 0;
            z-index: 10;
        }
    </style>
</head>
<body class="min-h-screen bg-base-200">
    <!-- Navbar -->
    <div class="navbar bg-base-100 shadow-md">
        <div class="navbar-start">
            <a href="map.php" class="btn btn-ghost normal-case text-xl"><?php echo APP_NAME; ?></a>
            <a href="map.php" class="btn btn-sm btn-primary ml-2 flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                </svg>
                Volver al Mapa
            </a>
        </div>
        <div class="navbar-center hidden lg:flex items-center gap-3">
            <div class="avatar placeholder">
                <div class="bg-neutral text-neutral-content rounded-full w-8">
                    <span class="text-sm"><?php echo substr(htmlspecialchars($user['name']), 0, 1); ?></span>
                </div>
            </div>
            <span class="text-sm font-medium"><?php echo htmlspecialchars($user['name']); ?></span>
        </div>
        <div class="navbar-end">
            <label class="swap swap-rotate mr-2">
                <input type="checkbox" id="theme-toggle" />
                <svg class="swap-on w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z" />
                </svg>
                <svg class="swap-off w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd" />
                </svg>
            </label>
            <a href="logout.php" class="btn btn-sm btn-error gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                    <path fill-rule="evenodd" d="M7.5 3.75A1.5 1.5 0 006 5.25v13.5a1.5 1.5 0 001.5 1.5h6a1.5 1.5 0 001.5-1.5V15a.75.75 0 011.5 0v3.75a3 3 0 01-3 3h-6a3 3 0 01-3-3V5.25a3 3 0 013-3h6a3 3 0 013 3V9A.75.75 0 0115 9V5.25a1.5 1.5 0 00-1.5-1.5h-6zm10.72 4.72a.75.75 0 011.06 0l3 3a.75.75 0 010 1.06l-3 3a.75.75 0 11-1.06-1.06l1.72-1.72H9a.75.75 0 010-1.5h10.94l-1.72-1.72a.75.75 0 010-1.06z" clip-rule="evenodd" />
                </svg>
                Cerrar sesión
            </a>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="container mx-auto p-4">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-2xl mb-4">Vista Lineal de Vehículos</h2>

                <div class="mb-8">
                    <div class="alert alert-info shadow-lg mb-6">
                        <div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <div>
                                <h3 class="font-bold">Vista Lineal de Vehículos</h3>
                                <div class="text-sm">Esta vista muestra la posición relativa de los vehículos en la ruta. Los vehículos se ordenan según su progreso en la ruta.</div>
                            </div>
                        </div>
                    </div>

                    <div class="legend-container">
                        <div class="legend-item">
                            <div class="w-6 h-6 bg-success rounded-full flex items-center justify-center text-white font-bold text-xs">A</div>
                            <span>Terminal A (Inicio)</span>
                        </div>
                        <div class="legend-item">
                            <div class="w-6 h-6 bg-error rounded-full flex items-center justify-center text-white font-bold text-xs">B</div>
                            <span>Terminal B (Fin)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon-container legend-terminal-a">
                                <div class="legend-icon" style="background-color: #36D399; clip-path: polygon(50% 0%, 0% 100%, 100% 100%);"></div>
                                <div class="legend-padron">123</div>
                            </div>
                            <span>Vehículo Terminal A (Ida)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon-container legend-terminal-b">
                                <div class="legend-icon" style="background-color: #FF7AC6; clip-path: polygon(50% 100%, 0% 0%, 100% 0%);"></div>
                                <div class="legend-padron">456</div>
                            </div>
                            <span>Vehículo Terminal B (Vuelta)</span>
                        </div>
                    </div>
                </div>

                <!-- Vista lineal para Terminal A (Ida) -->
                <div class="mb-8">
                    <div class="section-title">
                        <h3 class="text-xl font-bold">Terminal A (Ida)</h3>
                        <div class="badge badge-success"><?php echo count($terminalADevices); ?> vehículos</div>
                    </div>

                    <?php if (count($terminalADevices) > 0): ?>
                        <div class="linear-container">
                            <div class="linear-track"></div>
                            <div class="linear-start"></div>
                            <div class="linear-end"></div>
                            <div class="distance-label">Distancia total aproximada: 15 km</div>
                            <div class="terminal-label terminal-a-label">Terminal A</div>
                            <div class="terminal-label terminal-b-label">Terminal B</div>

                            <?php
                            // Filtrar vehículos para mostrar solo aquellos con datos GPS recientes (menos de 30 minutos)
                            $currentTerminalADevices = array_filter($terminalADevices, function($device) {
                                if (!isset($device['position']['deviceTime'])) {
                                    return false; // No tiene datos de GPS
                                }

                                $gpsTime = strtotime($device['position']['deviceTime']);
                                $timeDiff = time() - $gpsTime;

                                // Solo incluir vehículos con datos de menos de 30 minutos
                                return $timeDiff < 1800; // 30 minutos en segundos
                            });

                            // Ordenar vehículos de Terminal A por su último despacho (más reciente primero)
                            usort($currentTerminalADevices, function($a, $b) {
                                // Ordenar por tiempo de último despacho (más reciente primero)
                                $timeA = isset($a['ultimo_despacho']) ? strtotime($a['ultimo_despacho']) : 0;
                                $timeB = isset($b['ultimo_despacho']) ? strtotime($b['ultimo_despacho']) : 0;

                                // Si los tiempos son iguales, usar el padrón como desempate
                                if ($timeA == $timeB) {
                                    $padronA = isset($a['padron']) ? intval($a['padron']) : 0;
                                    $padronB = isset($b['padron']) ? intval($b['padron']) : 0;
                                    return $padronA <=> $padronB;
                                }

                                return $timeB <=> $timeA; // Más reciente primero
                            });

                            // Colocar vehículos en la vista lineal con distribución uniforme
                            $totalDevices = count($currentTerminalADevices);

                            // Calcular el espacio disponible (dejando margen en los extremos)
                            $startMargin = 5; // 5% desde el inicio
                            $endMargin = 5;   // 5% desde el final
                            $availableSpace = 100 - $startMargin - $endMargin;

                            // Mostrar contador de vehículos con datos recientes
                            echo '<div class="device-counter">Vehículos en tiempo real: ' . $totalDevices . '</div>';

                            // Calcular frecuencia promedio (si hay más de 1 vehículo)
                            if ($totalDevices > 1) {
                                $frecuencia = 0;
                                $ultimosDespachos = [];

                                // Recopilar tiempos de despacho
                                foreach ($currentTerminalADevices as $device) {
                                    if (isset($device['ultimo_despacho'])) {
                                        $ultimosDespachos[] = strtotime($device['ultimo_despacho']);
                                    }
                                }

                                // Ordenar tiempos
                                sort($ultimosDespachos);

                                // Calcular diferencias
                                $diferencias = [];
                                for ($i = 1; $i < count($ultimosDespachos); $i++) {
                                    $diferencias[] = $ultimosDespachos[$i] - $ultimosDespachos[$i-1];
                                }

                                // Calcular promedio en minutos
                                if (count($diferencias) > 0) {
                                    $frecuencia = round(array_sum($diferencias) / count($diferencias) / 60);
                                    echo '<div class="frequency-info">Frecuencia promedio: ' . $frecuencia . ' min</div>';
                                }
                            }

                            foreach ($currentTerminalADevices as $index => $device) {
                                // Calcular posición relativa (0-100%) con distribución uniforme
                                if ($totalDevices > 1) {
                                    $position = $startMargin + ($index / ($totalDevices - 1)) * $availableSpace;
                                } else {
                                    $position = 50; // Si solo hay un vehículo, colocarlo en el centro
                                }

                                // Formatear velocidad
                                $speed = isset($device['position']['speed']) ? round($device['position']['speed'] * 1.852, 1) : 0;

                                $padron = htmlspecialchars($device['padron'] ?? 'N/A');
                                echo '<div class="vehicle-marker terminal-a" style="left: ' . $position . '%;">';
                                echo '<div class="vehicle-tooltip">';
                                echo '<div class="font-bold">' . htmlspecialchars($device['name']) . '</div>';
                                echo '<div>Padrón: ' . $padron . '</div>';

                                // Determinar dirección y estado del servicio
                                $direccion = isset($device['attributes']['direccion']) ? $device['attributes']['direccion'] : '';

                                // Determinar si está en servicio basado en el último despacho
                                $enServicio = false;
                                $tiempoMaximoServicio = 24 * 3600; // 24 horas en segundos

                                if (isset($device['ultimo_despacho'])) {
                                    $tiempoDespacho = strtotime($device['ultimo_despacho']);
                                    $tiempoActual = time();
                                    $diferenciaTiempo = $tiempoActual - $tiempoDespacho;

                                    // Si el despacho fue en las últimas 24 horas, consideramos que está en servicio
                                    if ($diferenciaTiempo < $tiempoMaximoServicio) {
                                        $enServicio = true;
                                    }
                                }

                                // Verificar también si está en movimiento (velocidad > 0)
                                $enMovimiento = isset($device['position']['speed']) && $device['position']['speed'] > 0;

                                // Determinar estado final del servicio
                                $estadoServicio = 'Desconocido';
                                $servicioClass = 'text-warning';

                                if ($enServicio) {
                                    if ($enMovimiento) {
                                        $estadoServicio = 'En servicio (en movimiento)';
                                        $servicioClass = 'text-success';
                                    } else {
                                        $estadoServicio = 'En servicio (detenido)';
                                        $servicioClass = 'text-info';
                                    }
                                } else {
                                    $estadoServicio = 'Fuera de servicio';
                                    $servicioClass = 'text-error';
                                }

                                $servicioText = $estadoServicio;
                                echo '<div>Estado: <span class="' . $servicioClass . '">' . $servicioText . '</span></div>';

                                // Mostrar dirección (ida/vuelta)
                                if ($direccion) {
                                    $direccionText = $direccion == 'A' ? 'Ida (A)' : 'Vuelta (B)';
                                    echo '<div>Dirección: ' . $direccionText . '</div>';
                                }

                                echo '<div>Velocidad: ' . $speed . ' km/h</div>';

                                // Añadir fecha y hora del GPS
                                $gpsTime = isset($device['position']['deviceTime']) ? date('d/m/Y H:i:s', strtotime($device['position']['deviceTime'])) : 'N/A';
                                // Calcular cuánto tiempo ha pasado desde la última actualización GPS
                                $gpsTimeClass = 'text-success';
                                $gpsTimeText = $gpsTime;

                                if (isset($device['position']['deviceTime'])) {
                                    $timeDiff = time() - strtotime($device['position']['deviceTime']);
                                    if ($timeDiff > 3600) { // Más de 1 hora
                                        $gpsTimeClass = 'text-error';
                                        $hoursAgo = floor($timeDiff / 3600);
                                        $gpsTimeText .= ' (' . $hoursAgo . 'h atrás)';
                                    } elseif ($timeDiff > 300) { // Más de 5 minutos
                                        $gpsTimeClass = 'text-warning';
                                        $minutesAgo = floor($timeDiff / 60);
                                        $gpsTimeText .= ' (' . $minutesAgo . 'm atrás)';
                                    }
                                }

                                echo '<div>Hora GPS: <span class="' . $gpsTimeClass . '">' . $gpsTimeText . '</span></div>';

                                echo '<div>Último despacho: ' . (isset($device['ultimo_despacho']) ? date('d/m/Y H:i', strtotime($device['ultimo_despacho'])) : 'N/A') . '</div>';

                                // Verificar si está atrasado o adelantado respecto al horario
                                if (isset($device['attributes']['diferencia_horario'])) {
                                    $diferencia = $device['attributes']['diferencia_horario'];
                                    $horarioClass = 'text-warning';
                                    $horarioText = 'A tiempo';

                                    if ($diferencia > 5) {
                                        $horarioClass = 'text-error';
                                        $horarioText = 'Atrasado ' . $diferencia . ' min';
                                    } elseif ($diferencia < -5) {
                                        $horarioClass = 'text-info';
                                        $horarioText = 'Adelantado ' . abs($diferencia) . ' min';
                                    }

                                    echo '<div>Horario: <span class="' . $horarioClass . '">' . $horarioText . '</span></div>';
                                }

                                echo '<div>Conexión: ' . (isset($device['status']) && $device['status'] == 'online' ? '<span class="text-success">En línea</span>' : '<span class="text-error">Desconectado</span>') . '</div>';
                                echo '</div>';
                                echo '<div class="vehicle-icon"></div>';
                                echo '<div class="vehicle-padron">' . $padron . '</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>

                        <div class="overflow-x-auto table-container">
                            <table class="table table-zebra w-full">
                                <thead class="table-header">
                                    <tr>
                                        <th class="bg-success bg-opacity-10">#</th>
                                        <th class="bg-success bg-opacity-10">Vehículo</th>
                                        <th class="bg-success bg-opacity-10">Padrón</th>
                                        <th class="bg-success bg-opacity-10">Velocidad</th>
                                        <th class="bg-success bg-opacity-10">Último Despacho</th>
                                        <th class="bg-success bg-opacity-10 text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($terminalADevices as $index => $device): ?>
                                        <?php
                                        $speed = isset($device['position']['speed']) ? round($device['position']['speed'] * 1.852, 1) : 0;
                                        $despacho = isset($device['ultimo_despacho']) ? date('d/m/Y H:i', strtotime($device['ultimo_despacho'])) : 'N/A';
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($device['name']); ?></td>
                                            <td><?php echo htmlspecialchars($device['padron'] ?? 'N/A'); ?></td>
                                            <td><?php echo $speed; ?> km/h</td>
                                            <td><?php echo $despacho; ?></td>
                                            <td class="text-center">
                                                <a href="map.php?device=<?php echo $device['id']; ?>" class="btn btn-xs btn-primary">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                                    </svg>
                                                    Ver en Mapa
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">No hay vehículos de Terminal A con despacho hoy.</div>
                    <?php endif; ?>
                </div>

                <!-- Vista lineal para Terminal B (Vuelta) -->
                <div>
                    <div class="section-title">
                        <h3 class="text-xl font-bold">Terminal B (Vuelta)</h3>
                        <div class="badge badge-secondary"><?php echo count($terminalBDevices); ?> vehículos</div>
                    </div>

                    <?php if (count($terminalBDevices) > 0): ?>
                        <div class="linear-container">
                            <div class="linear-track"></div>
                            <div class="linear-start"></div>
                            <div class="linear-end"></div>
                            <div class="distance-label">Distancia total aproximada: 15 km</div>
                            <div class="terminal-label terminal-a-label">Terminal A</div>
                            <div class="terminal-label terminal-b-label">Terminal B</div>

                            <?php
                            // Filtrar vehículos para mostrar solo aquellos con datos GPS recientes (menos de 30 minutos)
                            $currentTerminalBDevices = array_filter($terminalBDevices, function($device) {
                                if (!isset($device['position']['deviceTime'])) {
                                    return false; // No tiene datos de GPS
                                }

                                $gpsTime = strtotime($device['position']['deviceTime']);
                                $timeDiff = time() - $gpsTime;

                                // Solo incluir vehículos con datos de menos de 30 minutos
                                return $timeDiff < 1800; // 30 minutos en segundos
                            });

                            // Ordenar vehículos de Terminal B por su último despacho (más reciente primero)
                            usort($currentTerminalBDevices, function($a, $b) {
                                // Ordenar por tiempo de último despacho (más reciente primero)
                                $timeA = isset($a['ultimo_despacho']) ? strtotime($a['ultimo_despacho']) : 0;
                                $timeB = isset($b['ultimo_despacho']) ? strtotime($b['ultimo_despacho']) : 0;

                                // Si los tiempos son iguales, usar el padrón como desempate
                                if ($timeA == $timeB) {
                                    $padronA = isset($a['padron']) ? intval($a['padron']) : 0;
                                    $padronB = isset($b['padron']) ? intval($b['padron']) : 0;
                                    return $padronA <=> $padronB;
                                }

                                return $timeB <=> $timeA; // Más reciente primero
                            });

                            // Colocar vehículos en la vista lineal con distribución uniforme
                            $totalDevices = count($currentTerminalBDevices);

                            // Calcular el espacio disponible (dejando margen en los extremos)
                            $startMargin = 5; // 5% desde el inicio
                            $endMargin = 5;   // 5% desde el final
                            $availableSpace = 100 - $startMargin - $endMargin;

                            // Mostrar contador de vehículos con datos recientes
                            echo '<div class="device-counter">Vehículos en tiempo real: ' . $totalDevices . '</div>';

                            // Calcular frecuencia promedio (si hay más de 1 vehículo)
                            if ($totalDevices > 1) {
                                $frecuencia = 0;
                                $ultimosDespachos = [];

                                // Recopilar tiempos de despacho
                                foreach ($currentTerminalBDevices as $device) {
                                    if (isset($device['ultimo_despacho'])) {
                                        $ultimosDespachos[] = strtotime($device['ultimo_despacho']);
                                    }
                                }

                                // Ordenar tiempos
                                sort($ultimosDespachos);

                                // Calcular diferencias
                                $diferencias = [];
                                for ($i = 1; $i < count($ultimosDespachos); $i++) {
                                    $diferencias[] = $ultimosDespachos[$i] - $ultimosDespachos[$i-1];
                                }

                                // Calcular promedio en minutos
                                if (count($diferencias) > 0) {
                                    $frecuencia = round(array_sum($diferencias) / count($diferencias) / 60);
                                    echo '<div class="frequency-info">Frecuencia promedio: ' . $frecuencia . ' min</div>';
                                }
                            }

                            foreach ($currentTerminalBDevices as $index => $device) {
                                // Calcular posición relativa (0-100%) con distribución uniforme
                                if ($totalDevices > 1) {
                                    $position = $startMargin + ($index / ($totalDevices - 1)) * $availableSpace;
                                } else {
                                    $position = 50; // Si solo hay un vehículo, colocarlo en el centro
                                }

                                // Formatear velocidad
                                $speed = isset($device['position']['speed']) ? round($device['position']['speed'] * 1.852, 1) : 0;

                                $padron = htmlspecialchars($device['padron'] ?? 'N/A');
                                echo '<div class="vehicle-marker terminal-b" style="left: ' . $position . '%;">';
                                echo '<div class="vehicle-tooltip">';
                                echo '<div class="font-bold">' . htmlspecialchars($device['name']) . '</div>';
                                echo '<div>Padrón: ' . $padron . '</div>';

                                // Determinar dirección y estado del servicio
                                $direccion = isset($device['attributes']['direccion']) ? $device['attributes']['direccion'] : '';

                                // Determinar si está en servicio basado en el último despacho
                                $enServicio = false;
                                $tiempoMaximoServicio = 24 * 3600; // 24 horas en segundos

                                if (isset($device['ultimo_despacho'])) {
                                    $tiempoDespacho = strtotime($device['ultimo_despacho']);
                                    $tiempoActual = time();
                                    $diferenciaTiempo = $tiempoActual - $tiempoDespacho;

                                    // Si el despacho fue en las últimas 24 horas, consideramos que está en servicio
                                    if ($diferenciaTiempo < $tiempoMaximoServicio) {
                                        $enServicio = true;
                                    }
                                }

                                // Verificar también si está en movimiento (velocidad > 0)
                                $enMovimiento = isset($device['position']['speed']) && $device['position']['speed'] > 0;

                                // Determinar estado final del servicio
                                $estadoServicio = 'Desconocido';
                                $servicioClass = 'text-warning';

                                if ($enServicio) {
                                    if ($enMovimiento) {
                                        $estadoServicio = 'En servicio (en movimiento)';
                                        $servicioClass = 'text-success';
                                    } else {
                                        $estadoServicio = 'En servicio (detenido)';
                                        $servicioClass = 'text-info';
                                    }
                                } else {
                                    $estadoServicio = 'Fuera de servicio';
                                    $servicioClass = 'text-error';
                                }

                                $servicioText = $estadoServicio;
                                echo '<div>Estado: <span class="' . $servicioClass . '">' . $servicioText . '</span></div>';

                                // Mostrar dirección (ida/vuelta)
                                if ($direccion) {
                                    $direccionText = $direccion == 'A' ? 'Ida (A)' : 'Vuelta (B)';
                                    echo '<div>Dirección: ' . $direccionText . '</div>';
                                }

                                echo '<div>Velocidad: ' . $speed . ' km/h</div>';

                                // Añadir fecha y hora del GPS
                                $gpsTime = isset($device['position']['deviceTime']) ? date('d/m/Y H:i:s', strtotime($device['position']['deviceTime'])) : 'N/A';
                                // Calcular cuánto tiempo ha pasado desde la última actualización GPS
                                $gpsTimeClass = 'text-success';
                                $gpsTimeText = $gpsTime;

                                if (isset($device['position']['deviceTime'])) {
                                    $timeDiff = time() - strtotime($device['position']['deviceTime']);
                                    if ($timeDiff > 3600) { // Más de 1 hora
                                        $gpsTimeClass = 'text-error';
                                        $hoursAgo = floor($timeDiff / 3600);
                                        $gpsTimeText .= ' (' . $hoursAgo . 'h atrás)';
                                    } elseif ($timeDiff > 300) { // Más de 5 minutos
                                        $gpsTimeClass = 'text-warning';
                                        $minutesAgo = floor($timeDiff / 60);
                                        $gpsTimeText .= ' (' . $minutesAgo . 'm atrás)';
                                    }
                                }

                                echo '<div>Hora GPS: <span class="' . $gpsTimeClass . '">' . $gpsTimeText . '</span></div>';

                                echo '<div>Último despacho: ' . (isset($device['ultimo_despacho']) ? date('d/m/Y H:i', strtotime($device['ultimo_despacho'])) : 'N/A') . '</div>';

                                // Verificar si está atrasado o adelantado respecto al horario
                                if (isset($device['attributes']['diferencia_horario'])) {
                                    $diferencia = $device['attributes']['diferencia_horario'];
                                    $horarioClass = 'text-warning';
                                    $horarioText = 'A tiempo';

                                    if ($diferencia > 5) {
                                        $horarioClass = 'text-error';
                                        $horarioText = 'Atrasado ' . $diferencia . ' min';
                                    } elseif ($diferencia < -5) {
                                        $horarioClass = 'text-info';
                                        $horarioText = 'Adelantado ' . abs($diferencia) . ' min';
                                    }

                                    echo '<div>Horario: <span class="' . $horarioClass . '">' . $horarioText . '</span></div>';
                                }

                                echo '<div>Conexión: ' . (isset($device['status']) && $device['status'] == 'online' ? '<span class="text-success">En línea</span>' : '<span class="text-error">Desconectado</span>') . '</div>';
                                echo '</div>';
                                echo '<div class="vehicle-icon"></div>';
                                echo '<div class="vehicle-padron">' . $padron . '</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>

                        <div class="overflow-x-auto table-container">
                            <table class="table table-zebra w-full">
                                <thead class="table-header">
                                    <tr>
                                        <th class="bg-secondary bg-opacity-10">#</th>
                                        <th class="bg-secondary bg-opacity-10">Vehículo</th>
                                        <th class="bg-secondary bg-opacity-10">Padrón</th>
                                        <th class="bg-secondary bg-opacity-10">Velocidad</th>
                                        <th class="bg-secondary bg-opacity-10">Último Despacho</th>
                                        <th class="bg-secondary bg-opacity-10 text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($terminalBDevices as $index => $device): ?>
                                        <?php
                                        $speed = isset($device['position']['speed']) ? round($device['position']['speed'] * 1.852, 1) : 0;
                                        $despacho = isset($device['ultimo_despacho']) ? date('d/m/Y H:i', strtotime($device['ultimo_despacho'])) : 'N/A';
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($device['name']); ?></td>
                                            <td><?php echo htmlspecialchars($device['padron'] ?? 'N/A'); ?></td>
                                            <td><?php echo $speed; ?> km/h</td>
                                            <td><?php echo $despacho; ?></td>
                                            <td class="text-center">
                                                <a href="map.php?device=<?php echo $device['id']; ?>" class="btn btn-xs btn-primary">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                                    </svg>
                                                    Ver en Mapa
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">No hay vehículos de Terminal B con despacho hoy.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Configurar cambio de tema claro/oscuro
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('theme-toggle');

            // Verificar preferencia guardada
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
                themeToggle.checked = true;
            }

            // Manejar cambio de tema
            themeToggle.addEventListener('change', function() {
                if (this.checked) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                    localStorage.setItem('theme', 'light');
                }
            });

            // Actualizar automáticamente cada 30 segundos
            setInterval(function() {
                location.reload();
            }, 30000);
        });
    </script>
</body>
</html>
