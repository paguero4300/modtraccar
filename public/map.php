<?php
/**
 * Página principal con mapa
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

// Incluir configuración
require_once __DIR__ . '/../config.php';

// Obtener datos del usuario
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; connect-src 'self' https://localhost https://monitoreo.transporteurbanogps.click wss://monitoreo.transporteurbanogps.click https://*.locationiq.com wss://rastreo.transporteurbanogps.click; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://unpkg.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; img-src 'self' data: https://*.locationiq.com https://*.tile.openstreetmap.org;">
    <title><?php echo APP_NAME; ?> - Mapa</title>

    <!-- Tailwind CSS y DaisyUI -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />

    <!-- Hero Icons -->
    <script src="https://unpkg.com/heroicons@2.0.18/dist/heroicons.min.js"></script>

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Toastify para notificaciones -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .navbar {
            height: 64px;
        }

        .flex-1 {
            flex: 1 1 0%;
            min-height: 0;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        #map-container {
            position: relative;
            flex: 1;
            height: 100%;
            width: 100%;
        }

        .vehicle-marker {
            transition: all 0.5s ease;
            background: none !important;
            border: none !important;
        }

        /* Estilos para el contador de vehículos con proporción áurea */
        .counter-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        #vehicles-count {
            /* Aplicando proporción áurea (1:1.618) */
            width: 2.618rem;
            height: 1.618rem;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 0.5rem;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        #vehicles-count.highlight-change {
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(87, 13, 248, 0.5);
        }

        #vehicles-count-label {
            font-size: 0.75rem;
            opacity: 0.7;
            transition: all 0.3s ease;
        }

        .marker-container {
            position: relative;
            width: 80px;
            height: 70px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
        }

        .vehicle-marker-icon {
            display: block;
            width: 40px;
            height: 40px;
            background-color: transparent;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            transform-origin: center;
            filter: drop-shadow(0 2px 5px rgba(0, 0, 0, 0.5));
            text-indent: -9999px; /* Ocultar cualquier texto dentro del icono */
            overflow: hidden;
            position: absolute;
            top: 0;
            left: 50%;
            margin-top: 5px;
        }

        /* Estilos para los iconos de vehículos */
        .vehicle-marker-icon {
            width: 40px;
            height: 40px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        /* Iconos para vehículos en línea */
        .vehicle-marker-icon.moving {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23570df8" stroke="%23000000" stroke-width="0.2"><path d="M12,2L4.5,20.29L5.21,21L12,18L18.79,21L19.5,20.29L12,2Z"/></svg>');
        }

        .vehicle-marker-icon.stopped {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23570df8" stroke="%23000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>');
        }

        /* Iconos para vehículos desconectados */
        .vehicle-marker-icon.offline.moving {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23f87272" stroke="%23000000" stroke-width="0.2"><path d="M12,2L4.5,20.29L5.21,21L12,18L18.79,21L19.5,20.29L12,2Z"/></svg>');
        }

        .vehicle-marker-icon.offline.stopped {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23f87272" stroke="%23000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>');
        }

        /* Iconos para vehículos según terminal */
        .vehicle-marker-icon.terminal-a.moving {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%233b82f6" stroke="%23000000" stroke-width="0.2"><path d="M12,2L4.5,20.29L5.21,21L12,18L18.79,21L19.5,20.29L12,2Z"/></svg>');
        }

        .vehicle-marker-icon.terminal-a.stopped {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%233b82f6" stroke="%23000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>');
        }

        .vehicle-marker-icon.terminal-b.moving {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23ef4444" stroke="%23000000" stroke-width="0.2"><path d="M12,2L4.5,20.29L5.21,21L12,18L18.79,21L19.5,20.29L12,2Z"/></svg>');
        }

        .vehicle-marker-icon.terminal-b.stopped {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23ef4444" stroke="%23000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>');
        }

        /* Iconos para vehículos sin despacho hoy */
        .vehicle-marker-icon.no-despacho.moving {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23FBBD23" stroke="%23000000" stroke-width="0.2"><path d="M12,2L4.5,20.29L5.21,21L12,18L18.79,21L19.5,20.29L12,2Z"/></svg>');
        }

        .vehicle-marker-icon.no-despacho.stopped {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23FBBD23" stroke="%23000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>');
        }

        .vehicle-name-label {
            color: #333;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 4px;
            padding: 2px 5px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            white-space: nowrap;
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: auto;
            min-width: 40px;
        }

        /* Estilos para el popup del vehículo */
        .vehicle-popup {
            min-width: 200px;
            max-width: 220px;
            cursor: pointer;
        }

        /* Estilos para el popup personalizado */
        .custom-popup .leaflet-popup-content-wrapper {
            border-radius: 8px;
            padding: 0;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }

        .custom-popup .leaflet-popup-content {
            margin: 0;
            width: auto !important;
        }

        .custom-popup .leaflet-popup-tip {
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.2);
        }

        /* Estilos para modo oscuro */
        [data-theme="dark"] .custom-popup .leaflet-popup-content-wrapper {
            background-color: #2a2a2a;
            color: #e0e0e0;
        }

        [data-theme="dark"] .custom-popup .leaflet-popup-tip {
            background-color: #2a2a2a;
        }

        /* Estilos para el sidebar colapsable */
        .flex-1 {
            flex: 1 1 0%;
        }

        /* Estilos para el sidebar colapsable */
        #sidebar {
            transition: width 0.3s ease, min-width 0.3s ease, opacity 0.3s ease;
            width: 320px;
            min-width: 320px;
            background-color: var(--b2);
            height: 100%;
            z-index: 40;
            position: relative;
        }

        #sidebar.collapsed {
            width: 0;
            min-width: 0;
            opacity: 0;
            overflow: hidden;
            pointer-events: none;
        }

        /* Estilos para el botón de colapsar */
        #collapse-sidebar {
            transition: all 0.3s ease;
            z-index: 50;
            width: 40px;
            height: 40px;
            min-height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            border: 2px solid white;
            position: absolute;
            right: 10px;
            top: 10px;
        }

        /* Botón flotante para mostrar sidebar */
        #show-sidebar {
            display: none;
            z-index: 1000;
            width: 40px;
            height: 40px;
            min-height: 40px;
            position: fixed;
            left: 10px;
            top: 74px; /* Debajo de la navbar */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }

        /* Estilos para los botones de filtro */
        .filter-btn {
            /* Estilo base para todos los botones de filtro */
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #f0f0f0;
            border: 2px solid transparent;
        }

        .filter-btn:hover {
            transform: scale(1.05);
        }

        /* Estilo para el botón activo - Mejorado con UI/UX */
        .filter-active {
            background-color: #570df8; /* Color primario */
            box-shadow: 0 0 10px rgba(87, 13, 248, 0.4);
            transform: scale(1.05);
        }

        /* Estilos para los iconos */
        .filter-btn svg {
            width: 24px;
            height: 24px;
            stroke: #333;
            transition: all 0.3s ease;
        }

        /* Icono en botón activo */
        .filter-active svg {
            stroke: white;
        }

        /* Contador de vehículos filtrados */
        .filter-btn .filter-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #f87171;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s ease;
        }

        .filter-btn .filter-count.show {
            opacity: 1;
            transform: scale(1);
        }

        /* Estilos para modo oscuro */
        [data-theme="dark"] .filter-btn {
            background-color: #2a2a2a;
            --btn-bg: #2a2a2a;
        }

        [data-theme="dark"] .filter-btn svg {
            stroke: #e0e0e0;
        }

        [data-theme="dark"] .filter-active {
            background-color: #661AE6;
            box-shadow: 0 0 10px rgba(102, 26, 230, 0.4);
        }

        /* Estilos para el control de capas de Leaflet */
        .leaflet-control-layers {
            border-radius: 8px !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2) !important;
        }

        .leaflet-control-layers-toggle {
            width: 36px !important;
            height: 36px !important;
            background-size: 20px 20px !important;
        }

        .leaflet-control-layers-expanded {
            padding: 10px !important;
            background-color: white !important;
            color: #333 !important;
            border: none !important;
            border-radius: 8px !important;
        }

        [data-theme="dark"] .leaflet-control-layers-expanded {
            background-color: #2a2a2a !important;
            color: #e0e0e0 !important;
        }

        /* Estilos para los marcadores de ruta */
        .route-marker {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
        }

        .start-marker {
            background-color: #10b981; /* Verde */
        }

        .end-marker {
            background-color: #ef4444; /* Rojo */
        }

        /* Estilos para el botón de volver a vista normal */
        .route-back-button {
            width: 36px !important;
            height: 36px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background-color: white !important;
            border-radius: 4px !important;
            box-shadow: 0 2px 6px rgba(0,0,0,0.5) !important;
            cursor: pointer !important;
            border: 2px solid #3b82f6 !important;
        }

        /* Estilos para la leyenda de iconos */
        .icon-legend {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background-color: white;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            max-width: 250px;
        }

        [data-theme="dark"] .icon-legend {
            background-color: #2a2a2a;
            color: #e0e0e0;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .legend-icon {
            width: 20px;
            height: 20px;
            margin-right: 8px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        .legend-terminal-a {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%233b82f6" stroke="%23000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>');
        }

        .legend-terminal-b {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23ef4444" stroke="%23000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>');
        }

        .legend-no-despacho {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23FBBD23" stroke="%23000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>');
        }

        .legend-default {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23570df8" stroke="%23000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>');
        }

        .legend-offline {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23f87272" stroke="%23000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>');
        }

        .route-back-button:hover {
            background-color: #f0f9ff !important;
            transform: scale(1.05) !important;
        }

        [data-theme="dark"] .route-back-button {
            background-color: #2a2a2a !important;
            color: #e0e0e0 !important;
            border: 2px solid #60a5fa !important;
        }

        [data-theme="dark"] .route-back-button:hover {
            background-color: #374151 !important;
        }

        /* Estilo para resaltar el vehículo seleccionado */
        .selected-vehicle {
            z-index: 1000 !important;
            filter: drop-shadow(0 0 5px #3b82f6) !important;
            transform: scale(1.2) !important;
        }

        .leaflet-control-layers-selector {
            margin-right: 5px !important;
        }

        .leaflet-control-layers-separator {
            margin: 10px 0 !important;
        }

        /* Estilos para las geocercas */
        .geofence-circle {
            transition: all 0.3s ease;
        }
        
        .geofence-circle:hover {
            fillOpacity: 0.4 !important;
            weight: 4 !important;
        }
        
        .geofence-tooltip {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        /* Estilos para el modo oscuro */
        [data-theme="dark"] .geofence-tooltip {
            background-color: rgba(42, 42, 42, 0.9);
            color: #e0e0e0;
            border-color: #666;
        }
        
        /* Estilos para el listado permanente de geocercas */
        #permanent-geofences-list {
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            z-index: 1000;
            max-width: 280px;
            overflow-y: auto;
        }
        
        /* Estilos para el panel minimizado */
        #permanent-geofences-list.geofences-minimized {
            max-height: 30px;
            overflow: hidden;
        }
        
        #permanent-geofences-list .geofences-content {
            transition: all 0.3s ease;
            opacity: 1;
        }
        
        #permanent-geofences-list.geofences-minimized .geofences-content {
            opacity: 0;
        }
        
        #permanent-geofences-list .permanent-geofence-item {
            transition: all 0.2s ease;
        }
        
        #permanent-geofences-list .permanent-geofence-item:hover {
            background-color: rgba(33, 150, 243, 0.1);
            transform: translateX(3px);
        }
        
        #permanent-geofences-list .permanent-geofence-item.active {
            background-color: rgba(33, 150, 243, 0.2);
            border-left: 3px solid #2196F3;
        }
        
        /* Estilos para los iconos de expandir/colapsar */
        #toggle-geofences-btn {
            transition: all 0.2s ease;
        }
        
        #toggle-geofences-btn:hover {
            background-color: rgba(33, 150, 243, 0.1);
        }
        
        /* Estilos para modo oscuro */
        [data-theme="dark"] #permanent-geofences-list {
            background-color: rgba(42, 42, 42, 0.95);
            border-color: rgba(255, 255, 255, 0.1);
        }
        
        [data-theme="dark"] #permanent-geofences-list .permanent-geofence-item:hover {
            background-color: rgba(33, 150, 243, 0.2);
        }
        
        [data-theme="dark"] #toggle-geofences-btn:hover {
            background-color: rgba(33, 150, 243, 0.2);
        }
    </style>
</head>
<body class="h-screen overflow-hidden">
    <!-- Layout simple con sidebar fijo -->
    <div class="flex flex-col h-full">
        <!-- Navbar -->
        <div class="navbar bg-base-100 shadow-md z-10">
            <div class="navbar-start">
                <a class="btn btn-ghost normal-case text-xl" title="<?php echo APP_NAME; ?>">GPS</a>
                <button id="toggle-sidebar-nav" class="btn btn-sm btn-primary ml-2 flex" title="Mostrar/Ocultar panel lateral">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                    </svg>
                </button>
                <div class="ml-2 flex gap-2">
                    <a href="route_viewer.php" class="btn btn-sm btn-primary flex items-center" title="Ver recorrido">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                        </svg>
                    </a>
                    <a href="linear_view.php" class="btn btn-sm btn-accent flex items-center" title="Vista Lineal">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                        </svg>
                    </a>
                    <button id="btn-show-dispatch-report" class="btn btn-sm btn-secondary flex items-center" title="Reporte de despachos">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" />
                        </svg>
                    </button>
                </div>
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
                <a href="logout.php" class="btn btn-sm btn-error" title="Cerrar sesión">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                        <path fill-rule="evenodd" d="M7.5 3.75A1.5 1.5 0 006 5.25v13.5a1.5 1.5 0 001.5 1.5h6a1.5 1.5 0 001.5-1.5V15a.75.75 0 011.5 0v3.75a3 3 0 01-3 3h-6a3 3 0 01-3-3V5.25a3 3 0 013-3h6a3 3 0 013 3V9A.75.75 0 0115 9V5.25a1.5 1.5 0 00-1.5-1.5h-6zm10.72 4.72a.75.75 0 011.06 0l3 3a.75.75 0 010 1.06l-3 3a.75.75 0 11-1.06-1.06l1.72-1.72H9a.75.75 0 010-1.5h10.94l-1.72-1.72a.75.75 0 010-1.06z" clip-rule="evenodd" />
                    </svg>
                </a>
            </div>
        </div>

        <!-- Contenido principal con sidebar colapsable -->
        <div class="flex flex-1 overflow-hidden relative">
            <!-- Sidebar -->
            <div id="sidebar" class="w-80 bg-base-200 overflow-y-auto shadow-lg transition-all duration-300">
                <div class="p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">Vehículos</h2>
                        <div class="flex items-center gap-1">
                            <div class="counter-container">
                                <div class="badge badge-primary text-lg px-3 py-3 transition-all duration-300" id="vehicles-count">0</div>
                                <span id="vehicles-count-label" class="text-xs text-opacity-70 ml-1 hidden"></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-control mb-4">
                        <div class="input-group">
                            <input type="text" id="search-vehicle" placeholder="Buscar vehículo..." class="input input-bordered w-full" />
                            <button class="btn btn-square">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                                    <path fill-rule="evenodd" d="M10.5 3.75a6.75 6.75 0 100 13.5 6.75 6.75 0 000-13.5zM2.25 10.5a8.25 8.25 0 1114.59 5.28l4.69 4.69a.75.75 0 11-1.06 1.06l-4.69-4.69A8.25 8.25 0 012.25 10.5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-center gap-3 mb-4">
                        <!-- Botones de filtro con Heroicons -->
                        <button class="filter-btn filter-active" data-filter="all" aria-label="Todos los vehículos" data-tooltip="Todos los vehículos">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <span class="filter-count" id="count-all">0</span>
                        </button>
                        <button class="filter-btn" data-filter="online" aria-label="Vehículos en línea" data-tooltip="Vehículos en línea">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="filter-count" id="count-online">0</span>
                        </button>
                        <button class="filter-btn" data-filter="offline" aria-label="Vehículos desconectados" data-tooltip="Vehículos desconectados">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="filter-count" id="count-offline">0</span>
                        </button>
                    </div>

                    <div id="vehicles-list" class="space-y-2">
                        <!-- Lista de vehículos (se llena dinámicamente) -->
                        <div class="skeleton h-12 w-full"></div>

                    </div>
                </div>
            </div>

            <!-- Mapa -->
            <div id="map-container" class="flex-1 relative">
                <div id="map" class="h-full w-full"></div>

                <!-- Botón para cargar geocercas -->
                <button id="load-geofences-btn" class="btn btn-sm btn-primary gap-1" title="Cargar geocercas">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                    </svg>
                    Geocercas
                </button>

                <!-- Botón para recargar geocercas -->
                <button id="reload-geofences-btn" class="btn btn-sm btn-ghost gap-1" title="Recargar geocercas">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </button>

                <!-- Listado permanente de geocercas (minimizable) -->
                <div id="permanent-geofences-list" class="absolute top-12 left-4 bg-base-100 shadow-lg rounded-lg p-1 w-40 max-h-[70vh] geofences-minimized">
                    <div class="flex justify-between items-center mb-1 px-1">
                        <div class="text-xs font-semibold">Paraderos</div>
                        <button id="toggle-geofences-btn" class="btn btn-xs btn-ghost p-0 min-h-0 h-5 w-5" title="Expandir/Minimizar lista">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 expand-icon">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0l-3.75-3.75M17.25 21L21 17.25" />
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 collapse-icon hidden">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-.75L9 17.25m0 0L3 12m6 5.25h5.25M16.5 4.5v12m0 0L21 12m-4.5 4.5h5.25" />
                            </svg>
                        </button>
                    </div>
                    <div class="divider my-0.5"></div>
                    <div class="space-y-0.5 geofences-content">
                        <!-- Ruta AB -->
                        <div class="text-xs font-medium text-primary px-1">AB</div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="01_AB_Proceres" data-lat="-12.151569" data-lon="-76.988855">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>01_AB_Proceres</span>
                            </div>
                        </div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="02_AB_Espinar" data-lat="-12.114344" data-lon="-77.036921">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>02_AB_Espinar</span>
                            </div>
                        </div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="03_AB_EjercitoBrasil" data-lat="-12.097006" data-lon="-77.071756">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>03_AB_EjercitoBrasil</span>
                            </div>
                        </div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="04_AB_Bolognesi" data-lat="-12.060921" data-lon="-77.042050">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>04_AB_Bolognesi</span>
                            </div>
                        </div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="05_AB_OvaloLaPaz" data-lat="-12.040389" data-lon="-76.998019">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>05_AB_OvaloLaPaz</span>
                            </div>
                        </div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="06_AB_Ceres" data-lat="-12.030840" data-lon="-76.929611">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>06_AB_Ceres</span>
                            </div>
                        </div>
                        <!-- Ruta BA -->
                        <div class="text-xs font-medium text-primary px-1 mt-1">BA</div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="01_BA_Chucocaminoreal" data-lat="-12.041180" data-lon="-76.976033">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>01_BA_Chucocaminoreal</span>
                            </div>
                        </div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="02_BA_Estaciongrau" data-lat="-12.054154" data-lon="-77.012864">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>02_BA_Estaciongrau</span>
                            </div>
                        </div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="03_BA_EjercitoBrasil" data-lat="-12.097006" data-lon="-77.071756">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>03_BA_EjercitoBrasil</span>
                            </div>
                        </div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="04_BA_Angamosaviacion" data-lat="-12.111845" data-lon="-77.000777">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>04_BA_Angamosaviacion</span>
                            </div>
                        </div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="05_BA_Jorgechavez" data-lat="-12.142627" data-lon="-76.991186">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>05_BA_Jorgechavez</span>
                            </div>
                        </div>
                        <div class="permanent-geofence-item cursor-pointer hover:bg-base-200 rounded px-1 py-0.5 text-xs" data-name="06_BA_Paradero" data-lat="-12.182013" data-lon="-77.000407">
                            <div class="flex items-center gap-1">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <span>06_BA_Paradero</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Panel original de lista de geocercas (mantener para compatibilidad) -->
                <div id="geofences-list" class="absolute top-12 right-4 bg-base-100 shadow-lg rounded-lg p-2 w-64 max-h-96 overflow-y-auto hidden">
                    <div class="text-sm font-semibold mb-2 px-2">Lista de Geocercas</div>
                    <div class="divider my-1"></div>
                    <div class="space-y-1">
                        <!-- Ruta AB -->
                        <div class="text-xs font-medium text-primary px-2">Ruta AB</div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="01_AB_Proceres" data-lat="-12.151569" data-lon="-76.988855">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>01_AB_Proceres</span>
                            </div>
                        </div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="02_AB_Espinar" data-lat="-12.114344" data-lon="-77.036921">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>02_AB_Espinar</span>
                            </div>
                        </div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="03_AB_EjercitoBrasil" data-lat="-12.097006" data-lon="-77.071756">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>03_AB_EjercitoBrasil</span>
                            </div>
                        </div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="04_AB_Bolognesi" data-lat="-12.060921" data-lon="-77.042050">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>04_AB_Bolognesi</span>
                            </div>
                        </div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="05_AB_OvaloLaPaz" data-lat="-12.040389" data-lon="-76.998019">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>05_AB_OvaloLaPaz</span>
                            </div>
                        </div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="06_AB_Ceres" data-lat="-12.030840" data-lon="-76.929611">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>06_AB_Ceres</span>
                            </div>
                        </div>
                        <!-- Ruta BA -->
                        <div class="text-xs font-medium text-primary px-2 mt-2">Ruta BA</div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="01_BA_Chucocaminoreal" data-lat="-12.041180" data-lon="-76.976033">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>01_BA_Chucocaminoreal</span>
                            </div>
                        </div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="02_BA_Estaciongrau" data-lat="-12.054154" data-lon="-77.012864">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>02_BA_Estaciongrau</span>
                            </div>
                        </div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="03_BA_EjercitoBrasil" data-lat="-12.097006" data-lon="-77.071756">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>03_BA_EjercitoBrasil</span>
                            </div>
                        </div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="04_BA_Angamosaviacion" data-lat="-12.111845" data-lon="-77.000777">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>04_BA_Angamosaviacion</span>
                            </div>
                        </div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="05_BA_Jorgechavez" data-lat="-12.142627" data-lon="-76.991186">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>05_BA_Jorgechavez</span>
                            </div>
                        </div>
                        <div class="geofence-item cursor-pointer hover:bg-base-200 rounded px-2 py-1 text-sm" data-name="06_BA_Paradero" data-lat="-12.182013" data-lon="-77.000407">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span>06_BA_Paradero</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leyenda de iconos -->
                <div class="icon-legend">
                    <h3 class="text-sm font-bold mb-2">Leyenda de iconos</h3>
                    <div class="flex flex-col gap-2">
                        <div class="legend-item">
                            <div class="legend-icon legend-terminal-a"></div>
                            <span class="text-xs ml-2">Terminal A (Ida) - Hacia arriba</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon legend-terminal-b"></div>
                            <span class="text-xs ml-2">Terminal B (Vuelta) - Hacia abajo</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon legend-no-despacho"></div>
                            <span class="text-xs ml-2">Sin despacho hoy</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon legend-default"></div>
                            <span class="text-xs ml-2">En línea</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-icon legend-offline"></div>
                            <span class="text-xs ml-2">Desconectado</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de detalles del vehículo (inicialmente oculto) - Ahora en la parte inferior -->
            <div id="vehicle-details-panel" class="hidden fixed bottom-0 left-1/2 transform -translate-x-1/2 w-[700px] max-w-[95%] bg-base-100 shadow-lg border border-base-300 rounded-t-lg h-auto max-h-[180px] overflow-y-auto z-[1000] transition-all duration-300 ease-in-out">
                <div class="p-3">
                    <div class="flex justify-between items-center mb-2">
                        <h2 class="text-base font-bold flex items-center gap-1" id="panel-vehicle-name">

                            <span id="vehicle-name-text">Detalles del vehículo</span>
                        </h2>

                    </div>
                    <div id="panel-vehicle-details" class="grid grid-cols-3 gap-3 mb-2">
                        <!-- Detalles del vehículo (se llena dinámicamente) -->
                        <div class="skeleton h-16 w-full"></div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Ya no necesitamos el modal de detalles del vehículo -->

    <!-- Modal para mostrar la dirección completa (simplificado) -->
    <dialog id="address-modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-primary">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                </svg>
                Dirección completa
            </h3>
            <div class="py-4" id="address-modal-content">
                <!-- El contenido de la dirección se carga dinámicamente -->
                <div class="flex justify-center">
                    <span class="loading loading-spinner loading-lg"></span>
                </div>
            </div>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn">Cerrar </button>
                </form>
            </div>
        </div>
    </dialog>

    <!-- Modal para el reporte de despachos -->
    <dialog id="dispatch-report-modal" class="modal">
        <div class="modal-box max-w-4xl">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-secondary">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" />
                </svg>
                Reporte de Despachos
            </h3>
            <div class="py-4">
                <div class="stats shadow w-full mb-4">
                    <div class="stat">
                        <div class="stat-figure text-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                            </svg>
                        </div>
                        <div class="stat-title">Total de Vehículos</div>
                        <div class="stat-value" id="total-vehicles">0</div>
                    </div>

                    <div class="stat">
                        <div class="stat-figure text-success">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-success">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <div class="stat-title">Despachados Hoy</div>
                        <div class="stat-value text-success" id="dispatched-today">0</div>
                    </div>

                    <div class="stat">
                        <div class="stat-figure text-warning">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-warning">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <div class="stat-title">Sin Despacho</div>
                        <div class="stat-value text-warning" id="not-dispatched">0</div>
                    </div>
                </div>

                <div class="tabs tabs-boxed mb-4">
                    <a class="tab tab-active" id="tab-all">Todos</a>
                    <a class="tab" id="tab-dispatched">Despachados</a>
                    <a class="tab" id="tab-not-dispatched">Sin Despacho</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Vehículo</th>
                                <th>Padrón</th>
                                <th>Terminal</th>
                                <th>Último Despacho</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="dispatch-report-table">
                            <!-- Se llenará dinámicamente -->
                            <tr>
                                <td colspan="6" class="text-center">
                                    <span class="loading loading-spinner loading-lg"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-action">
                <button class="btn btn-primary" id="btn-export-report">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Exportar CSV
                </button>
                <form method="dialog">
                    <button class="btn">Cerrar</button>
                </form>
            </div>
        </div>
    </dialog>

    <!-- Modal para seleccionar rango de fechas para la ruta -->
    <dialog id="route-modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-primary">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                </svg>
                Historial de ruta
            </h3>
            <div class="text-sm font-medium text-primary mb-2">
                Vehículo: <span id="route-device-name" class="font-bold">Cargando...</span>
            </div>
            <div class="py-4" id="route-modal-content">
                <input type="hidden" id="route-device-id" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="label">
                            <span class="label-text">Fecha de inicio</span>
                        </label>
                        <input type="date" id="route-date-from" class="input input-bordered w-full">
                    </div>
                    <div>
                        <label class="label">
                            <span class="label-text">Fecha de fin</span>
                        </label>
                        <input type="date" id="route-date-to" class="input input-bordered w-full">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">
                            <span class="label-text">Hora de inicio</span>
                        </label>
                        <input type="time" id="route-time-from" class="input input-bordered w-full" value="00:00">
                    </div>
                    <div>
                        <label class="label">
                            <span class="label-text">Hora de fin</span>
                        </label>
                        <input type="time" id="route-time-to" class="input input-bordered w-full" value="23:59">
                    </div>
                </div>
                <div class="flex flex-wrap justify-between gap-2 mt-6">
                    <button class="btn btn-sm btn-primary" id="btn-show-today-route">Ruta de hoy</button>
                    <button class="btn btn-sm btn-primary" id="btn-show-yesterday-route">Ruta de ayer</button>
                    <button class="btn btn-sm btn-primary" id="btn-show-week-route">Última semana</button>
                    <button class="btn btn-sm btn-primary" id="btn-show-month-route">Últimos 30 días</button>
                </div>
            </div>
            <div class="modal-action">
                <button class="btn btn-primary" id="btn-load-route">Cargar ruta</button>
                <form method="dialog">
                    <button class="btn">Cancelar</button>
                </form>
            </div>
        </div>
    </dialog>

    <!-- Configuración y datos para JavaScript -->
    <script>
        // Configuración
        const config = {
            apiUrl: 'api_proxy.php',
            wsUrl: '<?php echo TRACCAR_WS_URL; ?>',
            refreshInterval: <?php echo REFRESH_INTERVAL; ?>,
            markerClusterThreshold: <?php echo MARKER_CLUSTER_THRESHOLD; ?>,
            defaultPosition: [<?php echo MAP_DEFAULT_LAT; ?>, <?php echo MAP_DEFAULT_LON; ?>],
            defaultZoom: <?php echo MAP_DEFAULT_ZOOM; ?>,
            csrfToken: '<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>'
        };

        // Parámetros de ruta desde la URL (si existen)
        const urlParams = new URLSearchParams(window.location.search);
        const routeParams = {
            deviceId: urlParams.get('route'),
            from: urlParams.get('from'),
            to: urlParams.get('to')
        };

        // Si hay parámetros de ruta en la URL, cargarla automáticamente al iniciar
        if (routeParams.deviceId && routeParams.from && routeParams.to) {
            console.log('Parámetros de ruta detectados en la URL:', routeParams);
            // La carga se realizará desde map.js cuando se inicialice el mapa
        }
    </script>

    <!-- Scripts de la aplicación -->
    <script src="js/websocket.js"></script>
    <script src="js/map.js"></script>
    <script src="js/ui.js"></script>
    <script src="js/dispatch-report.js"></script>
</body>
</html>
