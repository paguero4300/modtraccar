<?php
/**
 * Configuración del Sistema de Monitoreo de Flota GPS
 */

// Incluir archivo de seguridad centralizado
require_once __DIR__ . '/php/security.php';

// Configuración de la API de Traccar
define('TRACCAR_API_URL', 'https://rastreo.transporteurbanogps.click/api');
define('TRACCAR_WS_URL', 'wss://rastreo.transporteurbanogps.click/api/socket');

// Configuración del mapa
define('MAP_DEFAULT_LAT', '19.4326'); // Ciudad de México
define('MAP_DEFAULT_LON', '-99.1332'); // Ciudad de México
define('MAP_DEFAULT_ZOOM', '12'); // Nivel de zoom

// Configuración de la aplicación
define('APP_NAME', 'Monitoreo de Flota GPS');
define('APP_VERSION', '1.0.0');

// Configuración de seguridad
define('SESSION_TIMEOUT', 3600); // 1 hora en segundos

// Configuración de la interfaz
define('REFRESH_INTERVAL', 15000); // 15 segundos para el polling fallback
define('MARKER_CLUSTER_THRESHOLD', 100); // Umbral para la clusterización de marcadores

// Credenciales por defecto (solo para desarrollo)
define('DEFAULT_EMAIL', 'demo@traccar.org');
define('DEFAULT_PASSWORD', 'demo');


