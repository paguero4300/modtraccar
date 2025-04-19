<?php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['JSESSIONID'])) {
    header('Location: login.php');
    exit;
}

// Obtener la cookie de sesión para pasarla al JavaScript
$sessionId = $_SESSION['JSESSIONID'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizador de Rutas (JavaScript)</title>

    <!-- Estilos -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        #map {
            height: 500px;
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
<body class="bg-gray-100 min-h-screen p-4">
    <div class="container mx-auto">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold">Visualizador de Rutas (JavaScript)</h1>
                <a href="map.php" class="btn btn-primary">Volver al Mapa</a>
            </div>

            <div class="mb-6">
                <div class="alert alert-info">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Esta versión utiliza JavaScript para comunicarse directamente con la API de Traccar.</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Vehículo</span>
                    </label>
                    <select id="deviceSelect" class="select select-bordered">
                        <option value="" disabled selected>Cargando dispositivos...</option>
                    </select>
                </div>
            </div>

            <div id="rangeControls" class="hidden">
                <div class="divider">Rangos Predefinidos</div>

                <div class="flex flex-wrap gap-2 mb-6">
                    <button id="btnToday" class="btn btn-sm btn-outline">Hoy</button>
                    <button id="btnYesterday" class="btn btn-sm btn-outline">Ayer</button>
                    <button id="btnLastWeek" class="btn btn-sm btn-outline">Última Semana</button>
                    <button id="btnLastMonth" class="btn btn-sm btn-outline">Último Mes</button>
                </div>

                <div class="divider">Rango Personalizado</div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Desde</span>
                        </label>
                        <input type="date" id="fromDate" class="input input-bordered">
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Hasta</span>
                        </label>
                        <input type="date" id="toDate" class="input input-bordered">
                    </div>
                </div>

                <div class="flex justify-end mb-6">
                    <button id="btnGetRoute" class="btn btn-primary">Obtener Ruta</button>
                </div>
            </div>

            <div class="mb-6">
                <div id="map"></div>
            </div>

            <div id="routeInfo" class="alert hidden mb-6"></div>

            <div class="stats shadow mb-6 hidden" id="routeStats">
                <div class="stat">
                    <div class="stat-title">Vehículo</div>
                    <div class="stat-value text-lg" id="statVehicle">-</div>
                </div>

                <div class="stat">
                    <div class="stat-title">Puntos</div>
                    <div class="stat-value text-lg" id="statPoints">-</div>
                </div>

                <div class="stat">
                    <div class="stat-title">Período</div>
                    <div class="stat-value text-lg" id="statPeriod">-</div>
                </div>
            </div>

            <div class="flex justify-end">
                <button id="btnClearRoute" class="btn btn-error">Limpiar Ruta</button>
            </div>
        </div>
    </div>

    <script>
        // Configuración
        const config = {
            apiUrl: 'http://localhost:8082/api',
            sessionId: '<?php echo $sessionId; ?>'
        };

        // Variables globales
        let map;
        let routeLine = null;
        let startMarker = null;
        let endMarker = null;
        let devices = [];

        // Elementos del DOM
        const deviceSelect = document.getElementById('deviceSelect');
        const fromDateInput = document.getElementById('fromDate');
        const toDateInput = document.getElementById('toDate');
        const btnGetRoute = document.getElementById('btnGetRoute');
        const btnClearRoute = document.getElementById('btnClearRoute');
        const routeInfo = document.getElementById('routeInfo');
        const routeStats = document.getElementById('routeStats');
        const statVehicle = document.getElementById('statVehicle');
        const statPoints = document.getElementById('statPoints');
        const statPeriod = document.getElementById('statPeriod');

        // Botones de fechas predefinidas
        const btnToday = document.getElementById('btnToday');
        const btnYesterday = document.getElementById('btnYesterday');
        const btnLastWeek = document.getElementById('btnLastWeek');
        const btnLastMonth = document.getElementById('btnLastMonth');

        // Inicializar mapa
        function initMap() {
            map = L.map('map').setView([-12.046374, -77.042793], 10);

            L.tileLayer('https://{s}-tiles.locationiq.com/v3/streets/r/{z}/{x}/{y}.png?key=pk.e63c15fb2d66e143a9ffe1a1e9596fb5', {
                attribution: '&copy; <a href="https://locationiq.com">LocationIQ</a> | &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                subdomains: ['a', 'b', 'c'],
                maxZoom: 19
            }).addTo(map);
        }

        // Función para realizar solicitudes a la API
        async function apiRequest(endpoint) {
            try {
                const response = await fetch(config.apiUrl + endpoint, {
                    headers: {
                        'Accept': 'application/json',
                        'Cookie': 'JSESSIONID=' + config.sessionId
                    }
                });

                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }

                return await response.json();
            } catch (error) {
                console.error('Error en la solicitud API:', error);
                throw error;
            }
        }

        // Cargar dispositivos
        async function loadDevices() {
            try {
                showInfo('Cargando dispositivos...', 'info');

                devices = await apiRequest('/devices');

                // Ordenar dispositivos por nombre
                devices.sort((a, b) => a.name.localeCompare(b.name));

                // Limpiar select
                deviceSelect.innerHTML = '';

                // Opción por defecto
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.disabled = true;
                defaultOption.selected = true;
                defaultOption.textContent = 'Seleccione un vehículo';
                deviceSelect.appendChild(defaultOption);

                // Añadir dispositivos
                devices.forEach(device => {
                    const option = document.createElement('option');
                    option.value = device.id;
                    option.textContent = device.name;
                    deviceSelect.appendChild(option);
                });

                hideInfo();
            } catch (error) {
                showInfo('Error al cargar dispositivos: ' + error.message, 'error');
            }
        }

        // Función para mostrar información
        function showInfo(message, type = 'info') {
            routeInfo.textContent = message;
            routeInfo.className = `alert alert-${type} mb-6`;
            routeInfo.classList.remove('hidden');
        }

        // Función para ocultar información
        function hideInfo() {
            routeInfo.classList.add('hidden');
        }

        // Función para limpiar la ruta
        function clearRoute() {
            if (routeLine) {
                map.removeLayer(routeLine);
                routeLine = null;
            }

            if (startMarker) {
                map.removeLayer(startMarker);
                startMarker = null;
            }

            if (endMarker) {
                map.removeLayer(endMarker);
                endMarker = null;
            }

            hideInfo();
            routeStats.classList.add('hidden');
        }

        // Función para obtener la ruta
        async function getRoute() {
            // Limpiar ruta anterior
            clearRoute();

            // Obtener valores
            const deviceId = deviceSelect.value;
            const fromDate = fromDateInput.value;
            const toDate = toDateInput.value;

            // Validar valores
            if (!deviceId) {
                showInfo('Por favor, seleccione un vehículo', 'warning');
                return;
            }

            if (!fromDate || !toDate) {
                showInfo('Por favor, seleccione fechas de inicio y fin', 'warning');
                return;
            }

            // Mostrar mensaje de carga
            showInfo('Cargando ruta...', 'info');

            try {
                // Formatear fechas para la API
                const fromDateTime = new Date(fromDate);
                fromDateTime.setHours(0, 0, 0, 0);
                const toDateTime = new Date(toDate);
                toDateTime.setHours(23, 59, 59, 999);

                const fromStr = fromDateTime.toISOString().split('.')[0] + 'Z';
                const toStr = toDateTime.toISOString().split('.')[0] + 'Z';

                // Realizar solicitud
                const routeData = await apiRequest(`/reports/route?deviceId=${deviceId}&from=${fromStr}&to=${toStr}`);

                if (routeData && routeData.length > 0) {
                    // Mostrar ruta
                    showRoute(routeData);

                    // Actualizar estadísticas
                    const deviceName = devices.find(d => d.id == deviceId).name;
                    statVehicle.textContent = deviceName;
                    statPoints.textContent = routeData.length;
                    statPeriod.textContent = `${fromDate} - ${toDate}`;
                    routeStats.classList.remove('hidden');

                    // Mostrar mensaje de éxito
                    showInfo(`Ruta cargada con éxito: ${routeData.length} puntos`, 'success');
                } else {
                    showInfo('No se encontraron datos de ruta para el período seleccionado', 'warning');
                }
            } catch (error) {
                showInfo(`Error al cargar la ruta: ${error.message}`, 'error');
                console.error('Error al cargar ruta:', error);
            }
        }

        // Función para mostrar la ruta en el mapa
        function showRoute(routeData) {
            // Crear array de puntos
            const routePoints = routeData.map(point => [point.latitude, point.longitude]);

            // Crear línea de ruta
            routeLine = L.polyline(routePoints, {
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
            startMarker = L.marker(startPoint, { icon: startIcon }).addTo(map);
            endMarker = L.marker(endPoint, { icon: endIcon }).addTo(map);

            // Añadir popups a los marcadores
            const startTime = new Date(routeData[0].deviceTime).toLocaleString();
            const endTime = new Date(routeData[routeData.length - 1].deviceTime).toLocaleString();

            startMarker.bindPopup(`<b>Inicio</b><br>Hora: ${startTime}`);
            endMarker.bindPopup(`<b>Fin</b><br>Hora: ${endTime}`);

            // Ajustar vista del mapa para mostrar toda la ruta
            map.fitBounds(routeLine.getBounds(), { padding: [50, 50] });

            // Mostrar información en consola
            console.log('Ruta cargada con éxito');
            console.log('Puntos:', routePoints.length);
            console.log('Inicio:', startTime);
            console.log('Fin:', endTime);
        }

        // Establecer fechas por defecto
        function setDefaultDates() {
            const today = new Date();
            const formatDate = (date) => {
                return date.toISOString().split('T')[0];
            };

            // Establecer fecha de hoy
            fromDateInput.value = formatDate(today);
            toDateInput.value = formatDate(today);
        }

        // Mostrar controles de rango cuando se selecciona un vehículo
        function showRangeControls() {
            const rangeControls = document.getElementById('rangeControls');
            rangeControls.classList.remove('hidden');
        }

        // Inicializar
        function init() {
            // Inicializar mapa
            initMap();

            // Establecer fechas por defecto
            setDefaultDates();

            // Cargar dispositivos
            loadDevices();

            // Eventos
            btnGetRoute.addEventListener('click', getRoute);
            btnClearRoute.addEventListener('click', clearRoute);

            // Mostrar controles de rango cuando se selecciona un vehículo
            deviceSelect.addEventListener('change', function() {
                if (this.value) {
                    showRangeControls();
                }
            });

            // Eventos para fechas predefinidas
            btnToday.addEventListener('click', () => {
                const today = new Date();
                fromDateInput.value = toDateInput.value = today.toISOString().split('T')[0];
            });

            btnYesterday.addEventListener('click', () => {
                const yesterday = new Date();
                yesterday.setDate(yesterday.getDate() - 1);
                fromDateInput.value = toDateInput.value = yesterday.toISOString().split('T')[0];
            });

            btnLastWeek.addEventListener('click', () => {
                const today = new Date();
                const lastWeek = new Date();
                lastWeek.setDate(lastWeek.getDate() - 7);
                fromDateInput.value = lastWeek.toISOString().split('T')[0];
                toDateInput.value = today.toISOString().split('T')[0];
            });

            btnLastMonth.addEventListener('click', () => {
                const today = new Date();
                const lastMonth = new Date();
                lastMonth.setDate(lastMonth.getDate() - 30);
                fromDateInput.value = lastMonth.toISOString().split('T')[0];
                toDateInput.value = today.toISOString().split('T')[0];
            });
        }

        // Iniciar cuando el DOM esté cargado
        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
