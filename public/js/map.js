// Inicializar el mapa
function initMap() {
    try {
        /* console.log eliminado */('Inicializando mapa...');

        // Crear mapa
        window.map = L.map('map', {
            layers: [],
            minZoom: 2,
            maxZoom: 19
        }).setView(config.defaultPosition, config.defaultZoom);

        // Definir capas base
        const locationIQStreets = L.tileLayer('https://{s}-tiles.locationiq.com/v3/streets/r/{z}/{x}/{y}.png?key=pk.e63c15fb2d66e143a9ffe1a1e9596fb5', {
            attribution: '&copy; <a href="https://locationiq.com">LocationIQ</a> | &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            subdomains: ['a', 'b', 'c'],
            maxZoom: 19
        });

        const locationIQDark = L.tileLayer('https://{s}-tiles.locationiq.com/v3/dark/r/{z}/{x}/{y}.png?key=pk.e63c15fb2d66e143a9ffe1a1e9596fb5', {
            attribution: '&copy; <a href="https://locationiq.com">LocationIQ</a> | &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            subdomains: ['a', 'b', 'c'],
            maxZoom: 19
        });

        const openStreetMap = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        });

        // Definir capas base para el control de capas
        const baseLayers = {
            "LocationIQ Streets": locationIQStreets,
            "LocationIQ Dark": locationIQDark,
            "OpenStreetMap": openStreetMap
        };

        // Añadir capa base por defecto
        locationIQStreets.addTo(map);

        // Añadir control de zoom
        L.control.zoom({
            position: 'topright'
        }).addTo(map);

        // Crear grupo de marcadores para clustering
        window.markerCluster = L.markerClusterGroup({
            disableClusteringAtZoom: 16,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true,
            maxClusterRadius: config.markerClusterThreshold
        });

        // Añadir grupo de marcadores al mapa
        map.addLayer(markerCluster);

        // Inicializar capa de ruta
        window.routeLayer = null;

        // Inicializar ID del dispositivo seleccionado
        window.selectedDeviceId = null;

        // Variables para el modo de visualización exclusiva
        window.isRouteMode = false;
        window.currentRouteDeviceId = null;
        window.routeBackButton = null;

        // Variables para las rutas
        window.rutaIdaPolyline = null;
        window.rutaVueltaPolyline = null;
        window.rutasLegend = null;

        // Inicializar capa de geocercas
        window.geofencesLayer = L.layerGroup().addTo(map);

        // Añadir capa de geocercas al control de capas
        const overlays = {
            "Geocercas": geofencesLayer
        };

        // Actualizar control de capas para incluir las capas superpuestas
        L.control.layers(baseLayers, overlays, {
            position: 'topright',
            collapsed: true
        }).addTo(map);

        // Sincronizar tema del mapa con el tema de la aplicación
        syncMapTheme();

        // Cargar y mostrar las rutas
        loadRoutes();

        // Cargar y mostrar las geocercas
        loadGeofences();

        // TEST: Obtener y mostrar datos externos de vehículos en consola usando el script de prueba
        fetch('test_api_external.php')
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    console.log('[DEBUG] Datos de vehículos externos obtenidos correctamente:', result.data);
                    // Procesar y mostrar datos si se necesita
                    processExternalVehicleData(result.data);
                } else {
                    console.error('[DEBUG] Error al obtener datos externos:', result);
                }
            })
            .catch(error => {
                console.error('[DEBUG] Error en la solicitud de datos externos:', error);
            });

        /* console.log eliminado */('Mapa inicializado correctamente');

        // Iniciar WebSocket para actualizaciones en tiempo real
        if (typeof window.initWebSocket === 'function') {
            window.initWebSocket();
        } else {
            console.error('La función initWebSocket no está disponible');
        }
    } catch (error) {
        console.error('Error al inicializar el mapa:', error);
    }
}

// Cargar dispositivos desde la API
function loadDevices() {
    /* console.log eliminado */('Cargando dispositivos...');

    // Devolver una promesa para poder encadenar acciones
    return new Promise((resolve, reject) => {
        apiRequest('getDevices')
            .then(response => {
                if (response.success) {
                    console.log('[DEBUG] Dispositivos cargados:', response.data.length);

                    // Mostrar información detallada de dispositivos con sus atributos de terminal y último despacho
                    console.log('[INFO] Datos combinados de dispositivos y API externa:', response.data);

                    // Verificar si hay dispositivos con datos externos
                    let devicesWithExternalData = response.data.filter(device =>
                        device.padron !== null ||
                        device.terminal !== null ||
                        device.ultimo_despacho !== null
                    );

                    console.log('[INFO] Dispositivos con datos externos:', devicesWithExternalData.length);
                    if (devicesWithExternalData.length > 0) {
                        console.log('[INFO] Muestra de dispositivos con datos externos:', devicesWithExternalData.slice(0, 3));
                    }

                    updateDevices(response.data);

                    // Cargar posiciones después de obtener dispositivos
                    loadPositions();

                    resolve(response.data);
                } else {
                    console.error('Error al cargar dispositivos:', response.message);
                    showToast('Error al cargar dispositivos: ' + response.message, 'error');
                    reject(new Error(response.message || 'Error al cargar dispositivos'));
                }
            })
            .catch(error => {
                console.error('Error al cargar dispositivos:', error);
                showToast('Error al cargar dispositivos: ' + error.message, 'error');
                reject(error);
            });
    });
}

// Cargar posiciones desde la API
window.loadPositions = function() {
    // Si estamos en modo de ruta, no cargar todas las posiciones
    if (window.isRouteMode && window.currentRouteDeviceId) {
        return;
    }

    apiRequest('getPositions', {})
        .then(response => {
            if (response.success && response.data) {
                // Actualizar marcadores con nuevas posiciones
                updateMarkers(response.data);
            }
        })
        .catch(error => {
            console.error('Error al cargar posiciones:', error);
            showToast('Error al cargar posiciones: ' + error.message, 'error');
        });
}

// Actualizar dispositivos en el mapa
function updateDevices(devices) {
    /* console.log eliminado */('Actualizando dispositivos:', devices.length);

    // Actualizar datos globales
    window.deviceData = {};
    devices.forEach(device => {
        deviceData[device.id] = device;
    });

    // Actualizar UI
    if (typeof window.updateVehiclesList === 'function') {
        window.updateVehiclesList();
    }
    if (typeof window.updateVehicleCount === 'function') {
        window.updateVehicleCount(devices.length);
    }

    // Actualizar marcadores en el mapa
    devices.forEach(device => {
        if (device.lastPosition) {
            // Determinar si está en línea (menos de 5 minutos desde la última actualización)
            const isOnline = new Date() - new Date(device.lastPosition.deviceTime) < 5 * 60 * 1000;
            device.status = isOnline ? 'online' : 'offline';

            // Actualizar marcador
            updateDeviceMarker(device, device.lastPosition);
        }
    });

    // Eliminar marcadores de dispositivos que ya no existen
    Object.keys(markers).forEach(id => {
        if (!deviceData[id]) {
            markerCluster.removeLayer(markers[id]);
            delete markers[id];
        }
    });
}

// Actualizar o crear marcador para un dispositivo
function updateDeviceMarker(device, position) {
    // Si ya existe un marcador para este dispositivo, actualizarlo
    if (markers[device.id]) {
        const marker = markers[device.id];
        // Actualizar posición
        marker.setLatLng([position.latitude, position.longitude]);
        // Actualizar icono según estado
        updateMarkerIcon(marker, device, position);
        // Actualizar popup
        updateMarkerPopup(marker, device, position);
        // Si este es el dispositivo seleccionado, actualizar detalles
        if (selectedDeviceId === device.id) {
            showVehicleDetails(device, position);
        }
    } else {
        // Si no existe, crear uno nuevo
        createMarker(device, position);
    }
}



// Crear un nuevo marcador
function createMarker(device, position) {
    // Calcular datos para el icono
    const isOnline = new Date() - new Date(position.deviceTime) < 5 * 60 * 1000;
    const isMoving = position.speed > 0.54; // 0.54 nudos ≈ 1 km/h
    const iconClass = isMoving ? 'moving' : 'stopped';
    let statusClass = isOnline ? '' : 'offline';

    // Determinar clase adicional según terminal y último despacho
    const terminalClass = getTerminalClass(device);
    const despachoClass = getDespachoClass(device);

    // Priorizar clases: primero despacho, luego terminal, luego estado online/offline
    let finalClass = statusClass;
    if (terminalClass) {
        finalClass = terminalClass;
    }
    if (despachoClass) {
        finalClass = despachoClass;
    }

    // Determinar la rotación según el terminal
    let rotation = position.course || 0;
    if (device.terminal) {
        if (device.terminal.toUpperCase() === 'A') {
            rotation = 0; // Apunta hacia arriba para terminal A (ida)
        } else if (device.terminal.toUpperCase() === 'B') {
            rotation = 180; // Apunta hacia abajo para terminal B (vuelta)
        }
    }

    // Crear HTML para el marcador con etiqueta de nombre
    const iconHtml = `
        <div class="marker-container">
            <div class="vehicle-marker-icon ${finalClass} ${iconClass}" style="transform: rotate(${rotation}deg);"></div>
            <div class="vehicle-name-label">${device.name}</div>
        </div>
    `;

    // Crear marcador con icono personalizado
    const marker = L.marker([position.latitude, position.longitude], {
        icon: L.divIcon({
            html: iconHtml,
            className: 'vehicle-marker',
            iconSize: [80, 70],  // Ancho y alto suficientes para el icono y la etiqueta
            iconAnchor: [40, 35], // El ancla en el centro del contenedor
            popupAnchor: [0, -35]  // Popup encima del icono
        })
    });

    // Añadir popup
    updateMarkerPopup(marker, device, position);

    // Añadir evento de clic
    marker.on('click', () => {
        selectedDeviceId = device.id;
        showVehicleDetails(device, position);
    });

    // Guardar marcador y añadir al cluster
    markers[device.id] = marker;
    markerCluster.addLayer(marker);

    return marker;
}

// Función para determinar la clase según el terminal
function getTerminalClass(device) {
    if (!device.terminal) return null;

    // Si el terminal es A, usar clase terminal-a
    if (device.terminal.toUpperCase() === 'A') {
        return 'terminal-a';
    }
    // Si el terminal es B, usar clase terminal-b
    else if (device.terminal.toUpperCase() === 'B') {
        return 'terminal-b';
    }

    return null;
}

// Función para determinar la clase según la fecha de último despacho
function getDespachoClass(device) {
    if (!device.ultimo_despacho) return null;

    // Obtener la fecha actual y la fecha del último despacho
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Establecer a las 00:00:00 para comparar solo la fecha

    const despachoDate = new Date(device.ultimo_despacho);
    despachoDate.setHours(0, 0, 0, 0);

    // Si la fecha de último despacho no es hoy, usar clase no-despacho
    if (despachoDate.getTime() < today.getTime()) {
        return 'no-despacho';
    }

    return null;
}

// Cargar geocercas desde la API
function loadGeofences() {
    console.log('Iniciando carga de geocercas...');

    // Mostrar mensaje al usuario
    showToast('Cargando geocercas...', 'info');

    // Usar fetch directamente para depurar la respuesta completa
    const formData = new FormData();
    formData.append('csrf_token', config.csrfToken);
    formData.append('action', 'getGeofences');
    formData.append('params', JSON.stringify({ all: true }));

    fetch(config.apiUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => {
        // Primero verificar si la respuesta es OK
        if (!response.ok) {
            console.error('Error de red al cargar geocercas:', response.status, response.statusText);
            throw new Error('Error de red: ' + response.status);
        }

        // Intentar parsear como JSON
        return response.json();
    })
    .then(data => {
        if (data && data.success && data.data) {
            console.log('Geocercas cargadas correctamente:', data.data.length);
            displayGeofences(data.data);
            showToast('Se han cargado ' + data.data.length + ' geocercas', 'success');
        } else {
            console.error('Error en la respuesta de geocercas:', data);
            showToast('Error al cargar geocercas: ' + (data.message || 'Formato de respuesta incorrecto'), 'error');
        }
    })
    .catch(error => {
        console.error('Error al procesar geocercas:', error);
        showToast('Error al cargar geocercas: ' + error.message, 'error');
    });
}

// Mostrar geocercas en el mapa
function displayGeofences(geofences) {
    // Limpiar capa de geocercas
    geofencesLayer.clearLayers();

    // Verificar si hay geocercas
    if (!geofences || !Array.isArray(geofences) || geofences.length === 0) {
        console.log('No hay geocercas para mostrar');
        showToast('No hay geocercas disponibles', 'warning');
        return;
    }

    console.log('Procesando ' + geofences.length + ' geocercas');

    // Contador de geocercas procesadas correctamente
    let processedCount = 0;

    // Colores más visibles para las geocercas (rojo, verde, morado)
    const geofenceColors = [
        { stroke: '#FF5252', fill: '#FF5252' }, // Rojo
        { stroke: '#4CAF50', fill: '#4CAF50' }, // Verde
        { stroke: '#9C27B0', fill: '#9C27B0' }  // Morado
    ];

    // Procesar cada geocerca
    geofences.forEach((geofence, index) => {
        try {

            // Verificar si la geocerca tiene un área definida
            if (!geofence.area) {
                console.warn(`Geocerca ${index + 1} no tiene área definida`);
                return;
            }

            // Parsear el área de la geocerca
            let area;

            // Verificar si el área está en formato WKT (Well-Known Text) o JSON
            if (typeof geofence.area === 'string') {
                const wktString = geofence.area.trim();

                // Detectar el tipo de geometría WKT
                if (wktString.startsWith('POLYGON')) {
                    // Formato WKT POLYGON detectado
                    console.log(`🌍 GEOCERCAS: Detectado formato WKT POLYGON para geocerca ${index + 1}`);

                    try {
                        // Extraer las coordenadas del polígono WKT
                        const coordsMatch = wktString.match(/POLYGON\s*\(\((.*)\)\)/);

                        if (!coordsMatch || !coordsMatch[1]) {
                            console.error(`Formato WKT inválido para geocerca ${index + 1}`);
                            return;
                        }

                        // Parsear las coordenadas
                        const coordPairs = coordsMatch[1].split(',').map(pair => {
                            // En WKT, el formato es "lat lon" (primero latitud, luego longitud)
                            const parts = pair.trim().split(' ');
                            if (parts.length >= 2) {
                                const lat = parseFloat(parts[0]);
                                const lon = parseFloat(parts[1]);
                                if (!isNaN(lat) && !isNaN(lon)) {
                                    return { latitude: lat, longitude: lon };
                                }
                            }
                            console.warn(`Par de coordenadas WKT inválido`);
                            return null;
                        }).filter(coord => coord !== null);

                        // Crear un objeto de área compatible
                        area = {
                            type: 'POLYGON',
                            coordinates: coordPairs
                        };

                        console.log(`🌍 GEOCERCAS: Convertido WKT POLYGON a formato interno con ${coordPairs.length} puntos`);
                    } catch (wktError) {
                        console.error(`Error al procesar WKT POLYGON para geocerca ${index + 1}:`, wktError);
                        return;
                    }
                } else if (wktString.startsWith('CIRCLE')) {
                    // Formato WKT CIRCLE detectado
                    console.log(`🌍 GEOCERCAS: Detectado formato WKT CIRCLE para geocerca ${index + 1}`);

                    try {
                        // Extraer el centro y el radio del círculo WKT
                        // Formato esperado: CIRCLE (lon lat, radius)
                        const circleMatch = wktString.match(/CIRCLE\s*\(\s*([-\d.]+)\s+([-\d.]+)\s*,\s*([\d.]+)\s*\)/);

                        if (!circleMatch || circleMatch.length < 4) {
                            console.error(`Formato WKT CIRCLE inválido para geocerca ${index + 1}`);
                            return;
                        }

                        // En WKT, el formato es "lat lon" (primero latitud, luego longitud)
                        const lat = parseFloat(circleMatch[1]);
                        const lon = parseFloat(circleMatch[2]);
                        const radius = parseFloat(circleMatch[3]);

                        // Crear un objeto de área compatible
                        area = {
                            type: 'CIRCLE',
                            center: { latitude: lat, longitude: lon },
                            radius: radius
                        };

                        console.log(`🌍 GEOCERCAS: Convertido WKT CIRCLE a formato interno: centro [${lat}, ${lon}], radio ${radius}m`);
                    } catch (wktError) {
                        console.error(`Error al procesar WKT CIRCLE para geocerca ${index + 1}:`, wktError);
                        return;
                    }
                } else if (wktString.startsWith('LINESTRING')) {
                    // Formato WKT LINESTRING detectado
                    console.log(`🌍 GEOCERCAS: Detectado formato WKT LINESTRING para geocerca ${index + 1}`);

                    try {
                        // Extraer las coordenadas de la línea WKT
                        const coordsMatch = wktString.match(/LINESTRING\s*\((.*)\)/);

                        if (!coordsMatch || !coordsMatch[1]) {
                            console.error(`Formato WKT LINESTRING inválido para geocerca ${index + 1}`);
                            return;
                        }

                        // Parsear las coordenadas
                        const coordPairs = coordsMatch[1].split(',').map(pair => {
                            // En WKT, el formato es "lat lon" (primero latitud, luego longitud)
                            const parts = pair.trim().split(' ');
                            if (parts.length >= 2) {
                                const lat = parseFloat(parts[0]);
                                const lon = parseFloat(parts[1]);
                                if (!isNaN(lat) && !isNaN(lon)) {
                                    return { latitude: lat, longitude: lon };
                                }
                            }
                            console.warn(`Par de coordenadas WKT inválido para LINESTRING`);
                            return null;
                        }).filter(coord => coord !== null);

                        // Crear un objeto de área compatible (trataremos la línea como un polígono)
                        area = {
                            type: 'POLYLINE',
                            coordinates: coordPairs
                        };

                        console.log(`🌍 GEOCERCAS: Convertido WKT LINESTRING a formato interno con ${coordPairs.length} puntos`);
                    } catch (wktError) {
                        console.error(`Error al procesar WKT LINESTRING para geocerca ${index + 1}:`, wktError);
                        return;
                    }
                } else {
                    // Intentar parsear como JSON
                    try {
                        area = JSON.parse(geofence.area);
                    } catch (parseError) {
                        console.error(`Formato no reconocido para geocerca ${index + 1}`);
                        return;
                    }
                }
            } else if (geofence.area && typeof geofence.area === 'object') {
                // Si ya es un objeto, usarlo directamente
                area = geofence.area;
            } else {
                // Intentar parsear como JSON
                try {
                    area = JSON.parse(geofence.area);
                } catch (parseError) {
                    console.error(`Error al parsear el área de la geocerca ${index + 1}:`, parseError);
                    return;
                }
            }

            // Verificar si el área tiene un tipo válido
            if (!area.type) {
                console.warn(`Geocerca ${index + 1} no tiene tipo de área definido`);
                return;
            }

            // Seleccionar color basado en el índice (rotación de colores)
            const colorIndex = index % geofenceColors.length;
            const color = geofenceColors[colorIndex];

            // Crear capa según el tipo de geocerca
            let layer;

            switch (area.type) {
                case 'CIRCLE':
                    // Verificar si el círculo tiene centro y radio
                    if (!area.center || !area.radius) {
                        console.warn(`Círculo ${index + 1} no tiene centro o radio definido`);
                        return;
                    }

                    // Crear círculo con el centro y radio especificados

                    // Crear círculo
                    // Leaflet usa [lat, lng] y nuestras coordenadas ya están en ese formato
                    layer = L.circle([area.center.latitude, area.center.longitude], {
                        radius: area.radius,
                        color: color.stroke,
                        fillColor: color.fill,
                        fillOpacity: 0.4,
                        weight: 3,
                        className: 'geofence-circle'
                    });
                    break;

                case 'POLYGON':
                    // Verificar si el polígono tiene coordenadas
                    if (!area.coordinates || !Array.isArray(area.coordinates) || area.coordinates.length < 3) {
                        console.warn(`Polígono ${index + 1} no tiene suficientes coordenadas`);
                        return;
                    }

                    // Crear polígono
                    const coordinates = area.coordinates.map(coord => {
                        // Verificar si las coordenadas tienen el formato correcto
                        if (typeof coord.latitude === 'number' && typeof coord.longitude === 'number') {
                            // Leaflet usa [lat, lng] y nuestras coordenadas ya están en ese formato
                            return [coord.latitude, coord.longitude];
                        } else if (Array.isArray(coord) && coord.length >= 2) {
                            // Si es un array, asumir que es [lat, lng]
                            return [coord[0], coord[1]];
                        } else {
                            console.warn(`Formato de coordenada inválido`);
                            // Devolver una coordenada por defecto para evitar errores
                            return [0, 0];
                        }
                    });

                    // Crear polígono con las coordenadas

                    // Verificar si las coordenadas son válidas (no son todas 0,0)
                    const hasValidCoords = coordinates.some(coord =>
                        coord[0] !== 0 || coord[1] !== 0
                    );

                    if (!hasValidCoords) {
                        console.warn(`🌍 GEOCERCAS: Polígono ${index + 1} no tiene coordenadas válidas`);
                        return;
                    }

                    layer = L.polygon(coordinates, {
                        color: color.stroke,
                        fillColor: color.fill,
                        fillOpacity: 0.4,
                        weight: 3,
                        className: 'geofence-polygon'
                    });
                    break;

                case 'RECTANGLE':
                    // Verificar si el rectángulo tiene coordenadas
                    if (!area.coordinates || !Array.isArray(area.coordinates) || area.coordinates.length < 2) {
                        console.warn(`Rectángulo ${index + 1} no tiene suficientes coordenadas`);
                        return;
                    }

                    // Crear rectángulo con las coordenadas especificadas

                    // Crear rectángulo
                    // Leaflet usa [lat, lng] y nuestras coordenadas ya están en ese formato
                    const bounds = L.latLngBounds(
                        [area.coordinates[0].latitude, area.coordinates[0].longitude],
                        [area.coordinates[1].latitude, area.coordinates[1].longitude]
                    );
                    layer = L.rectangle(bounds, {
                        color: color.stroke,
                        fillColor: color.fill,
                        fillOpacity: 0.4,
                        weight: 3,
                        className: 'geofence-rectangle'
                    });
                    break;

                case 'POLYLINE':
                    // Verificar si la polilínea tiene coordenadas
                    if (!area.coordinates || !Array.isArray(area.coordinates) || area.coordinates.length < 2) {
                        console.warn(`Polilínea ${index + 1} no tiene suficientes coordenadas`);
                        return;
                    }

                    // Crear polilínea
                    const polylineCoords = area.coordinates.map(coord => {
                        // Verificar si las coordenadas tienen el formato correcto
                        if (typeof coord.latitude === 'number' && typeof coord.longitude === 'number') {
                            // Leaflet usa [lat, lng] y nuestras coordenadas ya están en ese formato
                            return [coord.latitude, coord.longitude];
                        } else if (Array.isArray(coord) && coord.length >= 2) {
                            // Si es un array, asumir que es [lat, lng]
                            return [coord[0], coord[1]];
                        } else {
                            console.warn(`Formato de coordenada inválido para polilínea`);
                            // Devolver una coordenada por defecto para evitar errores
                            return [0, 0];
                        }
                    });

                    // Crear polilínea con las coordenadas

                    layer = L.polyline(polylineCoords, {
                        color: color.stroke,
                        weight: 4,
                        opacity: 0.8,
                        lineJoin: 'round',
                        lineCap: 'round',
                        className: 'geofence-polyline'
                    });
                    break;

                default:
                    console.warn(`Tipo de geocerca no soportado (${index + 1}): ${area.type}`);
                    return;
            }

            // Añadir popup con información
            layer.bindPopup(`
                <div class="p-3 bg-white rounded-lg shadow-md">
                    <h3 class="font-bold text-lg text-gray-800 mb-1">${geofence.name || 'Sin nombre'}</h3>
                    <p class="text-sm text-gray-600">${geofence.description || 'Sin descripción'}</p>
                    <div class="text-xs text-gray-500 mt-2">ID: ${geofence.id}</div>
                </div>
            `);

            // Añadir tooltip para mostrar el nombre al pasar el mouse
            layer.bindTooltip(geofence.name || 'Geocerca', {
                permanent: false,
                direction: 'top',
                className: 'geofence-tooltip'
            });

            // Añadir capa a la capa de geocercas
            geofencesLayer.addLayer(layer);
            processedCount++;

        } catch (error) {
            console.error(`Error al procesar geocerca ${index + 1}:`, error);
        }
    });

    console.log(`${processedCount} de ${geofences.length} geocercas mostradas correctamente`);

    // Si no se procesó ninguna geocerca, mostrar un mensaje
    if (processedCount === 0) {
        showToast('No se pudieron mostrar las geocercas', 'warning');
    } else {
        // Hacer zoom a las geocercas si hay alguna
        try {
            // Crear un grupo de límites para todas las capas
            const bounds = L.latLngBounds([]);

            // Iterar sobre todas las capas en geofencesLayer
            geofencesLayer.eachLayer(layer => {
                // Verificar si la capa tiene un método getBounds
                if (typeof layer.getBounds === 'function') {
                    bounds.extend(layer.getBounds());
                }
                // Para círculos que no tienen getBounds pero tienen getLatLng y getRadius
                else if (typeof layer.getLatLng === 'function' && typeof layer.getRadius === 'function') {
                    const center = layer.getLatLng();
                    const radius = layer.getRadius();
                    bounds.extend([center.lat + 0.01, center.lng + 0.01]);
                    bounds.extend([center.lat - 0.01, center.lng - 0.01]);
                }
            });

            if (bounds.isValid()) {
                console.log('Ajustando vista a las geocercas');
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        } catch (error) {
            console.warn('No se pudo ajustar la vista a las geocercas:', error);
        }
    }
}

// Actualizar el icono del marcador
function updateMarkerIcon(marker, device, position) {
    // Calcular datos para el icono
    const isOnline = new Date() - new Date(position.deviceTime) < 5 * 60 * 1000;
    const isMoving = position.speed > 0.54; // 0.54 nudos ≈ 1 km/h
    const iconClass = isMoving ? 'moving' : 'stopped';
    let statusClass = isOnline ? '' : 'offline';

    // Determinar clase adicional según terminal y último despacho
    const terminalClass = getTerminalClass(device);
    const despachoClass = getDespachoClass(device);

    // Priorizar clases: primero despacho, luego terminal, luego estado online/offline
    let finalClass = statusClass;
    if (terminalClass) {
        finalClass = terminalClass;
    }
    if (despachoClass) {
        finalClass = despachoClass;
    }

    // Determinar la rotación según el terminal
    let rotation = position.course || 0;
    if (device.terminal) {
        if (device.terminal.toUpperCase() === 'A') {
            rotation = 0; // Apunta hacia arriba para terminal A (ida)
        } else if (device.terminal.toUpperCase() === 'B') {
            rotation = 180; // Apunta hacia abajo para terminal B (vuelta)
        }
    }

    // Crear HTML para el marcador con etiqueta de nombre
    const iconHtml = `
        <div class="marker-container">
            <div class="vehicle-marker-icon ${finalClass} ${iconClass}" style="transform: rotate(${rotation}deg);"></div>
            <div class="vehicle-name-label">${device.name}</div>
        </div>
    `;

    // Actualizar icono
    marker.setIcon(L.divIcon({
        html: iconHtml,
        className: 'vehicle-marker',
        iconSize: [80, 70],
        iconAnchor: [40, 35],
        popupAnchor: [0, -35]
    }));
}

// Actualizar el popup del marcador
function updateMarkerPopup(marker, device, position) {
    // Calcular datos para el popup
    const isOnline = new Date() - new Date(position.deviceTime) < 5 * 60 * 1000;
    const statusClass = isOnline ? 'badge-success' : 'badge-error';
    const statusText = isOnline ? 'En línea' : 'Desconectado';

    const speed = (position.speed * 1.852).toFixed(1); // Convertir de nudos a km/h
    const isMoving = position.speed > 0.54; // 0.54 nudos ≈ 1 km/h
    const movementClass = isMoving ? 'badge-info' : 'badge-warning';
    const movementText = isMoving ? 'En movimiento' : 'Detenido';

    const lastUpdate = new Date(position.deviceTime).toLocaleString();
    const course = position.course ? position.course.toFixed(1) + '°' : 'N/A';

    // Crear contenido del popup más compacto y sin botón de detalles
    const popupContent = `
        <div class="vehicle-popup p-2">
            <div class="flex justify-between items-center mb-1">
                <h3 class="text-lg font-bold">${device.name}</h3>
                <button class="btn btn-xs btn-circle btn-ghost close-popup">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <div class="flex flex-wrap gap-1 mb-2">
                <div class="badge ${statusClass} badge-sm">${statusText}</div>
                <div class="badge ${movementClass} badge-sm">${movementText}</div>
            </div>
            <div class="grid grid-cols-2 gap-x-2 gap-y-1 text-xs">
                <div class="flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                    <span>${speed} km/h</span>
                </div>
                <div class="flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v9a1 1 0 11-2 0V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    <span>${course}</span>
                </div>
                <div class="flex items-center gap-1 col-span-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                    <span>${lastUpdate}</span>
                </div>
                <!-- Nuevos campos -->
                <div class="flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L10 4.414l6.293 6.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    <span>Padrón: ${device.padron || 'N/A'}</span>
                </div>
                <div class="flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L10 4.414l6.293 6.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    <span>Terminal: ${device.terminal || 'N/A'}</span>
                </div>
                <div class="flex items-center gap-1 col-span-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                    </svg>
                    <span>Últ. Despacho: ${device.ultimo_despacho ? new Date(device.ultimo_despacho).toLocaleString() : 'N/A'}</span>
                </div>
            </div>
        </div>
    `;

    // Configurar opciones del popup para hacerlo más compacto
    const popupOptions = {
        className: 'custom-popup',
        closeButton: false,
        maxWidth: 250,
        minWidth: 200,
        autoPan: true
    };

    marker.bindPopup(popupContent, popupOptions);

    // Si el popup está abierto, actualizarlo
    if (marker.isPopupOpen()) {
        marker.getPopup().setContent(popupContent);
    }

    // Añadir eventos después de abrir el popup
    marker.on('popupopen', () => {
        // Botón de cerrar
        const closeButton = document.querySelector('.close-popup');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                marker.closePopup();
            });
        }

        // Al hacer clic en el popup, mostrar detalles del vehículo
        const popup = document.querySelector('.vehicle-popup');
        if (popup) {
            popup.addEventListener('click', (e) => {
                // Solo si no se hizo clic en el botón de cerrar
                if (!e.target.closest('.close-popup')) {
                    selectedDeviceId = device.id;
                    showVehicleDetails(device, position);
                }
            });
        }
    });
}

// Mostrar detalles del vehículo en el panel inferior
// Función para cargar y mostrar las rutas desde el archivo rutas.json
function loadRoutes() {
    // KISS: Solo cargar desde la ubicación fija '/public/rutas.json'
    clearRoutes();
    fetch('rutas.json')
        .then(response => {
            if (!response.ok) {
                throw new Error('No se pudo cargar el archivo de rutas desde /public/rutas.json');
            }
            return response.json();
        })
        .then(data => {
            if (data.ruta_ida) {
                displayRoute(data.ruta_ida, 'ida');
            }
            if (data.ruta_vuelta) {
                displayRoute(data.ruta_vuelta, 'vuelta');
            }
            addRoutesLegend();
        })
        .catch(error => {
            console.error('Error al cargar rutas:', error);
            addRoutesLegend();
        });
}

// Función para mostrar una ruta en el mapa
function displayRoute(routeData, routeType) {
    if (!routeData || routeData.length === 0) {
        console.warn(`No hay datos para mostrar ruta de ${routeType}`);
        return;
    }

    /* console.log eliminado */(`Mostrando ruta de ${routeType} con ${routeData.length} puntos`);

    // Validación y conversión de puntos
    let validPoints = 0;
    const routePoints = [];

    for (let i = 0; i < routeData.length; i++) {
        const point = routeData[i];

        // Verificar que el punto tenga coordenadas válidas
        if (point && typeof point.latitud === 'number' && !isNaN(point.latitud) &&
            typeof point.longitud === 'number' && !isNaN(point.longitud)) {

            // Verificar rango de coordenadas válido
            if (point.latitud >= -90 && point.latitud <= 90 &&
                point.longitud >= -180 && point.longitud <= 180) {

                routePoints.push(L.latLng(point.latitud, point.longitud));
                validPoints++;

                // Mostrar algunos puntos de muestra en la consola
                if (i < 3 || i >= routeData.length - 3 || i % Math.floor(routeData.length / 5) === 0) {
                    /* console.log eliminado */(`Punto ${i}: [${point.latitud}, ${point.longitud}]`);
                }
            } else {
                console.error(`Punto ${i} fuera de rango: [${point.latitud}, ${point.longitud}]`);
            }
        } else {
            console.error(`Punto ${i} inválido:`, point);
        }
    }

    /* console.log eliminado */(`Puntos válidos para ${routeType}: ${validPoints} de ${routeData.length}`);

    if (routePoints.length < 2) {
        console.error(`No hay suficientes puntos válidos para crear la ruta de ${routeType}`);
        return;
    }

    // Definir opciones de estilo para la ruta
    let routeOptions = {
        weight: 5,
        opacity: 0.7,
        smoothFactor: 1
    };

    // Asignar colores diferentes según el tipo de ruta
    if (routeType === 'ida') {
        routeOptions.color = '#3388ff'; // Azul para la ruta de ida

        // Crear la polilínea para la ruta de ida
        window.rutaIdaPolyline = L.polyline(routePoints, routeOptions);
        window.rutaIdaPolyline.addTo(map);

        // Agregar popup con información al hacer clic en la ruta
        window.rutaIdaPolyline.bindPopup('Ruta de Ida');

        // Ajustar la vista del mapa para mostrar la ruta completa
        /* console.log eliminado */('Ajustando vista a ruta de ida');
        try {
            const bounds = window.rutaIdaPolyline.getBounds();
            map.fitBounds(bounds, { padding: [50, 50] });
            /* console.log eliminado */('Vista ajustada a ruta de ida');
        } catch (e) {
            console.error('Error al ajustar vista a la ruta:', e);
            // Intentar ajustar la vista a un punto específico si hay error
            if (routePoints.length > 0) {
                map.setView(routePoints[0], 13);
                /* console.log eliminado */('Vista ajustada al primer punto');
            }
        }
    } else {
        routeOptions.color = '#ff3333'; // Rojo para la ruta de vuelta

        // Crear la polilínea para la ruta de vuelta
        window.rutaVueltaPolyline = L.polyline(routePoints, routeOptions);
        window.rutaVueltaPolyline.addTo(map);

        // Agregar popup con información al hacer clic en la ruta
        window.rutaVueltaPolyline.bindPopup('Ruta de Vuelta');

        /* console.log eliminado */('Ruta de vuelta añadida al mapa');
    }
}

// Función para limpiar las rutas existentes del mapa
function clearRoutes() {
    /* console.log eliminado */('Limpiando rutas existentes...');

    // Eliminar polilínea de la ruta de ida si existe
    if (window.rutaIdaPolyline) {
        window.rutaIdaPolyline.remove();
        window.rutaIdaPolyline = null;
        /* console.log eliminado */('Ruta de ida eliminada');
    }

    // Eliminar polilínea de la ruta de vuelta si existe
    if (window.rutaVueltaPolyline) {
        window.rutaVueltaPolyline.remove();
        window.rutaVueltaPolyline = null;
        /* console.log eliminado */('Ruta de vuelta eliminada');
    }

    // Eliminar leyenda si existe
    if (window.rutasLegend) {
        window.rutasLegend.remove();
        window.rutasLegend = null;
        /* console.log eliminado */('Leyenda eliminada');
    }
}

// Función para añadir leyenda de rutas al mapa
function addRoutesLegend() {
    /* console.log eliminado */('Añadiendo leyenda de rutas completas y variadas...');

    // Si ya existe una leyenda, eliminarla
    if (window.rutasLegend) {
        window.rutasLegend.remove();
    }

    // Crear la leyenda como un control de Leaflet
    window.rutasLegend = L.control({ position: 'bottomleft' });

    window.rutasLegend.onAdd = function(map) {
        const div = L.DomUtil.create('div', 'legend-control');
        div.innerHTML = '<h4 style="margin: 0 0 5px 0; font-weight: bold;">Leyenda de Rutas</h4>';

        // Añadir elemento para la ruta de ida
        div.innerHTML += '<div style="display: flex; align-items: center; margin-bottom: 3px;"><div style="width: 20px; height: 3px; background-color: #3388ff; margin-right: 5px;"></div>Ruta de Ida</div>';

        // Añadir elemento para la ruta de vuelta
        div.innerHTML += '<div style="display: flex; align-items: center;"><div style="width: 20px; height: 3px; background-color: #ff3333; margin-right: 5px;"></div>Ruta de Vuelta</div>';

        // Aplicar estilos CSS para que sea más visible y elegante
        div.style.backgroundColor = 'white';
        div.style.padding = '10px';
        div.style.borderRadius = '8px';
        div.style.boxShadow = '0 0 15px rgba(0, 0, 0, 0.2)';
        div.style.lineHeight = '1.5';
        div.style.fontFamily = 'Arial, sans-serif';
        div.style.fontSize = '12px';
        div.style.color = '#333';
        div.style.minWidth = '150px';
        div.style.maxWidth = '200px';
        div.style.border = '1px solid rgba(0,0,0,0.1)';

        // Hacer que la leyenda sea interactiva (no pasar eventos al mapa)
        L.DomEvent.disableClickPropagation(div);
        L.DomEvent.disableScrollPropagation(div);

        return div;
    };

    window.rutasLegend.addTo(map);
}

// Función para ocultar los detalles del vehículo
function hideVehicleDetails() {
    const panel = document.getElementById('vehicle-details-panel');

    panel.classList.add('hidden');
}

// Función para mostrar detalles del vehículo en el panel inferior
function showVehicleDetails(device, position) {
    const panel = document.getElementById('vehicle-details-panel');
    const nameElement = document.getElementById('vehicle-name-text');
    const detailsElement = document.getElementById('panel-vehicle-details');

    // Establecer nombre del vehículo
    nameElement.textContent = device.name;

    // Calcular datos
    const isOnline = new Date() - new Date(position.deviceTime) < 5 * 60 * 1000;
    const statusClass = isOnline ? 'badge-success' : 'badge-error';
    const statusText = isOnline ? 'En línea' : 'Desconectado';

    const speed = (position.speed * 1.852).toFixed(1); // Convertir de nudos a km/h
    const isMoving = position.speed > 0.54; // 0.54 nudos ≈ 1 km/h
    const movementClass = isMoving ? 'badge-info' : 'badge-warning';
    const movementText = isMoving ? 'En movimiento' : 'Detenido';

    const lastUpdate = new Date(position.deviceTime).toLocaleString();

    // Generar HTML de detalles agrupados por tipo de datos con títulos descriptivos
    detailsElement.innerHTML = `
        <!-- Grupo: Información de Movimiento -->

              <div class="flex flex-col border rounded-lg p-2 bg-base-200 mb-2">
            <div class="text-xs font-semibold mb-1 text-primary flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
                <span>Información Despacho</span>
            </div>
            <div class="text-xs flex items-center gap-1 mb-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L10 4.414l6.293 6.293a1 1 0 001.414-1.414l-7-7z" />
                </svg>
                <span>Padrón:</span>
                <span class="font-medium ml-1">${device.padron || 'N/A'}</span>
            </div>
            <div class="text-xs flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L10 4.414l6.293 6.293a1 1 0 001.414-1.414l-7-7z" />
                </svg>
                <span>Terminal:</span>
                <span class="font-medium ml-1">${device.terminal || 'N/A'}</span>
            </div>
            <div class="text-xs flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                </svg>
                <span>Últ. Despacho:</span>
                <span class="font-medium ml-1">${device.ultimo_despacho ? new Date(device.ultimo_despacho).toLocaleString() : 'N/A'}</span>
            </div>
        </div>


        <!-- Grupo: Información de Tiempo -->
        <div class="flex flex-col border rounded-lg p-2 bg-base-200">
            <div class="text-xs font-semibold mb-1 text-primary flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>Tiempo</span>
            </div>
            <div class="text-xs flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>Última actualización:</span>
                <span class="font-medium">${lastUpdate}</span>
            </div>
        </div>

        <!-- Grupo: Información de Ubicación -->
        <div class="flex flex-col border rounded-lg p-2 bg-base-200">
            <div class="text-xs font-semibold mb-1 text-primary flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                </svg>
                <span>Ubicación</span>
            </div>
            <div class="text-xs flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <span>Coordenadas:</span>
                <span class="font-medium">${position.latitude.toFixed(6)}, ${position.longitude.toFixed(6)}</span>
            </div>
            <div class="text-xs flex items-center gap-1" id="panel-address-${device.id}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                </svg>
                <span>Dirección:</span>
                <button class="btn btn-xs btn-primary btn-outline gap-1 show-address" data-lat="${position.latitude}" data-lon="${position.longitude}" data-device-id="${device.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    Ver dirección
                </button>
            </div>
        </div>


    `;



    // Mostrar paneles
    panel.classList.remove('hidden');

    // Asegurarse de que el panel sea visible
    /* console.log eliminado */('Mostrando panel de detalles para:', device.name);

    // Forzar un reflow para asegurar que la transición funcione
    void panel.offsetWidth;

    // Ajustar el mapa para que no quede oculto por el panel
    setTimeout(() => {
        if (window.map && typeof window.map.invalidateSize === 'function') {
            window.map.invalidateSize();
        }
    }, 300);

    // Remover eventos anteriores para evitar duplicados
    const closeButton = document.getElementById('close-details-panel');
    const routeButton = document.getElementById('btn-show-route-panel');
    const engineStopButton = document.getElementById('btn-engine-stop-panel');

    // Clonar y reemplazar los botones para eliminar todos los event listeners
    if (closeButton) {
        const newCloseButton = closeButton.cloneNode(true);
        closeButton.parentNode.replaceChild(newCloseButton, closeButton);
        newCloseButton.addEventListener('click', () => {
            panel.classList.add('hidden');
        });
    }

    if (routeButton) {
        const newRouteButton = routeButton.cloneNode(true);
        routeButton.parentNode.replaceChild(newRouteButton, routeButton);
        newRouteButton.addEventListener('click', () => {
            loadDeviceRoute(device.id);
        });
    }

    if (engineStopButton) {
        const newEngineStopButton = engineStopButton.cloneNode(true);
        engineStopButton.parentNode.replaceChild(newEngineStopButton, engineStopButton);
        newEngineStopButton.addEventListener('click', (e) => {
            e.preventDefault();
            sendEngineStopCommand(device.id);
        });
    }

    // El evento para mostrar la dirección ahora se maneja globalmente
}

// Cargar ruta del dispositivo
function loadDeviceRoute(deviceId) {
    /* console.log eliminado */(`[DEBUG] loadDeviceRoute - Iniciando carga de ruta para dispositivo ID: ${deviceId}`);

    // Verificar que el dispositivo existe en los datos
    if (!window.deviceData || !window.deviceData[deviceId]) {
        console.error(`[DEBUG] loadDeviceRoute - Error: No se encontró el dispositivo con ID ${deviceId} en deviceData`);
        /* console.log eliminado */('[DEBUG] deviceData disponible:', window.deviceData);
        showToast('Error: No se encontró el dispositivo seleccionado', 'error');
        return;
    }

    // Obtener el nombre del dispositivo
    const deviceName = window.deviceData[deviceId].name;
    /* console.log eliminado */(`[DEBUG] loadDeviceRoute - Dispositivo seleccionado: ${deviceName} (ID: ${deviceId})`);

    // Mostrar el modal de selección de fechas
    const modal = document.getElementById('route-modal');
    const deviceIdInput = document.getElementById('route-device-id');
    const deviceNameElement = document.getElementById('route-device-name');

    /* console.log eliminado */('[DEBUG] loadDeviceRoute - Elementos del DOM:', {
        modal: modal ? 'Encontrado' : 'No encontrado',
        deviceIdInput: deviceIdInput ? 'Encontrado' : 'No encontrado',
        deviceNameElement: deviceNameElement ? 'Encontrado' : 'No encontrado'
    });

    // Establecer el ID del dispositivo en el campo oculto
    deviceIdInput.value = deviceId;
    /* console.log eliminado */(`[DEBUG] loadDeviceRoute - ID del dispositivo establecido en input: ${deviceIdInput.value}`);

    // Mostrar el nombre del dispositivo en el modal
    if (deviceNameElement) {
        deviceNameElement.textContent = deviceName;
        /* console.log eliminado */(`[DEBUG] loadDeviceRoute - Nombre del dispositivo establecido en modal: ${deviceName}`);
    } else {
        console.warn('[DEBUG] loadDeviceRoute - Elemento route-device-name no encontrado');
    }
    /* console.log eliminado */(deviceIdInput)

    // Establecer fechas por defecto (hoy)
    const today = new Date();
    const dateFromInput = document.getElementById('route-date-from');
    const dateToInput = document.getElementById('route-date-to');

    // Formatear fecha para input date (YYYY-MM-DD)
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    // Establecer fecha actual en ambos campos
    dateFromInput.value = formatDate(today);
    dateToInput.value = formatDate(today);

    // Mostrar el modal
    modal.showModal();
}

// Cargar ruta con fechas específicas
function loadRouteWithDates(deviceId, fromDate, toDate) {
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Iniciando carga de ruta para dispositivo ID: ${deviceId}`);
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Rango de fechas: ${fromDate.toLocaleString()} a ${toDate.toLocaleString()}`);

    // Verificar si estamos usando el dispositivo de prueba que sabemos que tiene datos
    const testDeviceId = 257; // Dispositivo que sabemos que tiene datos según las pruebas
    if (deviceId === testDeviceId) {
        /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Usando dispositivo de prueba conocido (ID: ${testDeviceId})`);
    }

    // Mostrar indicador de carga
    showToast('Cargando ruta...', 'info');

    // Verificar si las fechas son válidas
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Verificando fechas:`);
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Fecha actual: ${new Date().toISOString()}`);
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - fromDate objeto: ${fromDate}`);
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - toDate objeto: ${toDate}`);

    // Verificar si las fechas están en el futuro
    const now = new Date();
    let useAlternativeDates = false;

    if (fromDate > now) {
        console.warn(`[DEBUG] loadRouteWithDates - ADVERTENCIA: La fecha 'from' está en el futuro`);
        useAlternativeDates = true;
    }
    if (toDate > now) {
        console.warn(`[DEBUG] loadRouteWithDates - ADVERTENCIA: La fecha 'to' está en el futuro`);
        useAlternativeDates = true;
    }

    // Si las fechas están en el futuro o necesitamos un rango más amplio
    if (useAlternativeDates) {
        /* console.log eliminado */(`[DEBUG] loadRouteWithDates - PRUEBA: Intentando con fechas alternativas en el pasado`);

        // Crear fechas alternativas (7 días atrás hasta hoy)
        const alternativeFromDate = new Date(now);
        alternativeFromDate.setDate(alternativeFromDate.getDate() - 7); // 7 días atrás
        const alternativeToDate = new Date(now); // Hoy

        /* console.log eliminado */(`[DEBUG] loadRouteWithDates - PRUEBA: Fechas alternativas con rango ampliado:`);
        /* console.log eliminado */(`[DEBUG] loadRouteWithDates - PRUEBA: From alternativo: ${alternativeFromDate.toLocaleString()}`);
        /* console.log eliminado */(`[DEBUG] loadRouteWithDates - PRUEBA: To alternativo: ${alternativeToDate.toLocaleString()}`);

        // Actualizar las fechas para la solicitud
        fromDate = alternativeFromDate;
        toDate = alternativeToDate;

        /* console.log eliminado */(`[DEBUG] loadRouteWithDates - PRUEBA: Usando fechas alternativas con rango ampliado (7 días) para la solicitud`);
    }

    // Calcular intervalo de tiempo
    const intervalMs = toDate - fromDate;
    const intervalDays = Math.floor(intervalMs / (1000 * 60 * 60 * 24));
    const intervalHours = Math.floor((intervalMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const intervalMinutes = Math.floor((intervalMs % (1000 * 60 * 60)) / (1000 * 60));
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Intervalo de tiempo: ${intervalDays} días, ${intervalHours} horas, ${intervalMinutes} minutos`);

    // Formatear fechas correctamente (sin milisegundos)
    const fromISO = fromDate.toISOString().split('.')[0] + 'Z';
    const toISO = toDate.toISOString().split('.')[0] + 'Z';
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Fechas formateadas para API:`);
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - From: ${fromISO}`);
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - To: ${toISO}`);

    // Construir URL completa para referencia (no se usa directamente)
    const apiUrlExample = `/api/reports/route?deviceId=${deviceId}&from=${fromISO}&to=${toISO}`;
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - URL de API que se construirá: ${apiUrlExample}`);

    // Verificar si el dispositivo existe en los datos
    if (window.deviceData && window.deviceData[deviceId]) {
        /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Dispositivo encontrado en datos locales: ${window.deviceData[deviceId].name} (ID: ${deviceId})`);
    } else {
        console.warn(`[DEBUG] loadRouteWithDates - ADVERTENCIA: No se encontró el dispositivo ${deviceId} en datos locales`);
    }

    // Limpiar cualquier ruta anterior
    if (routeLayer) {
        /* console.log eliminado */('[DEBUG] loadRouteWithDates - Eliminando capa de ruta anterior');
        map.removeLayer(routeLayer);
        routeLayer = null;
    }

    // Eliminar marcadores de inicio y fin
    let markersRemoved = 0;
    map.eachLayer(layer => {
        if (layer instanceof L.Marker && layer.getElement() && layer.getElement().querySelector('.route-marker')) {
            map.removeLayer(layer);
            markersRemoved++;
        }
    });
    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Marcadores de ruta eliminados: ${markersRemoved}`);

    // Solicitar ruta a la API usando el endpoint de reportes
    /* console.log eliminado */('[DEBUG] loadRouteWithDates - Enviando solicitud a la API con parámetros:', {
        action: 'getRoute',
        deviceId: deviceId,
        from: fromISO,
        to: toISO
    });

    apiRequest('getRoute', {
        deviceId: deviceId,
        from: fromISO,
        to: toISO
    })
        .then(response => {
            /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Respuesta recibida:`, response);

            if (response.success && response.data && response.data.length > 0) {
                /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Puntos recibidos: ${response.data.length}`);
                /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Primer punto:`, response.data[0]);
                /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Último punto:`, response.data[response.data.length - 1]);

                // Filtrar puntos para asegurarnos de que todos pertenecen al dispositivo solicitado
                const filteredData = response.data.filter(pos => pos.deviceId === deviceId);
                /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Puntos filtrados: ${filteredData.length} (de ${response.data.length})`);

                if (filteredData.length === 0) {
                    console.error(`[DEBUG] loadRouteWithDates - Error: No se encontraron puntos para el dispositivo ${deviceId}`);
                    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - IDs de dispositivos en la respuesta:`,
                        [...new Set(response.data.map(pos => pos.deviceId))]);
                    showToast(`No se encontraron puntos para el dispositivo seleccionado`, 'warning');
                    return;
                }

                /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Llamando a showDeviceRoute con ${filteredData.length} puntos`);
                showDeviceRoute(filteredData);
                showToast(`Mostrando ruta con ${filteredData.length} puntos`, 'success');
            } else {
                console.warn(`[DEBUG] loadRouteWithDates - No hay datos disponibles:`, response);
                // Mostrar un mensaje más detallado
                const message = 'No hay datos de ruta disponibles para el período seleccionado. ' +
                    'Se ha probado con un rango ampliado de 7 días sin éxito. ' +
                    'Es posible que este vehículo no tenga datos históricos registrados.';
                showToast(message, 'warning');

                // Probar con un dispositivo que sabemos que tiene datos (para depuración)
                /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Intentando con dispositivo de prueba conocido`);
                const testDeviceId = 257; // Dispositivo que sabemos que tiene datos
                if (deviceId !== testDeviceId) {
                    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Probando con dispositivo ID: ${testDeviceId}`);

                    // Crear fechas de prueba (30 días atrás hasta hoy)
                    const testFromDate = new Date(now);
                    testFromDate.setDate(testFromDate.getDate() - 30);
                    const testToDate = new Date(now);

                    /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Fechas de prueba: ${testFromDate.toLocaleString()} a ${testToDate.toLocaleString()}`);

                    // Formatear fechas para la API
                    const testFromISO = testFromDate.toISOString().split('.')[0] + 'Z';
                    const testToISO = testToDate.toISOString().split('.')[0] + 'Z';

                    // Realizar solicitud de prueba (solo para depuración, no mostrar en el mapa)
                    apiRequest('getRoute', {
                        deviceId: testDeviceId,
                        from: testFromISO,
                        to: testToISO
                    }).then(testResponse => {
                        /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Respuesta de prueba:`, testResponse);
                        if (testResponse.success && Array.isArray(testResponse.data) && testResponse.data.length > 0) {
                            /* console.log eliminado */(`[DEBUG] loadRouteWithDates - ÉXITO: El dispositivo de prueba tiene ${testResponse.data.length} puntos`);
                            /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Primer punto:`, testResponse.data[0]);
                            /* console.log eliminado */(`[DEBUG] loadRouteWithDates - Último punto:`, testResponse.data[testResponse.data.length - 1]);
                        } else {
                            /* console.log eliminado */(`[DEBUG] loadRouteWithDates - El dispositivo de prueba tampoco tiene datos`);
                        }
                    }).catch(error => {
                        console.error(`[DEBUG] loadRouteWithDates - Error en solicitud de prueba:`, error);
                    });
                }
            }
        })
        .catch(error => {
            console.error(`[DEBUG] loadRouteWithDates - Error en la solicitud:`, error);
            console.error(`[DEBUG] loadRouteWithDates - Stack trace:`, error.stack);
            // Mostrar un mensaje más detallado sobre el error
            const errorMessage = 'Error al obtener datos de ruta. ' +
                'Se ha intentado con un rango ampliado de 7 días sin éxito. ' +
                'Por favor, intente con otro vehículo o contacte al administrador.';
            showToast(errorMessage, 'error');
        });
}

// Mostrar ruta en el mapa
function showDeviceRoute(positions) {
    /* console.log eliminado */(`[DEBUG] showDeviceRoute - Iniciando visualización de ruta con ${positions ? positions.length : 0} puntos`);

    // Limpiar ruta anterior si existe
    if (routeLayer) {
        /* console.log eliminado */('[DEBUG] showDeviceRoute - Eliminando capa de ruta anterior');
        map.removeLayer(routeLayer);
        routeLayer = null;
    }

    // Eliminar marcadores de inicio y fin
    let markersRemoved = 0;
    map.eachLayer(layer => {
        if (layer instanceof L.Marker && layer.getElement() && layer.getElement().querySelector('.route-marker')) {
            map.removeLayer(layer);
            markersRemoved++;
        }
    });
    /* console.log eliminado */(`[DEBUG] showDeviceRoute - Marcadores de ruta eliminados: ${markersRemoved}`);

    // Verificar si hay suficientes puntos
    if (!positions) {
        console.error('[DEBUG] showDeviceRoute - Error: positions es null o undefined');
        showToast('No hay datos de ruta disponibles', 'warning');
        return;
    }

    /* console.log eliminado */(`[DEBUG] showDeviceRoute - Procesando ${positions.length} puntos`);

    // Obtener el ID del dispositivo del primer punto
    const firstDeviceId = positions[0].deviceId;
    /* console.log eliminado */(`[DEBUG] showDeviceRoute - ID del dispositivo del primer punto: ${firstDeviceId}`);

    // Filtrar puntos para asegurarnos de que todos pertenecen al mismo dispositivo
    const filteredPositions = positions.filter(pos => pos.deviceId === firstDeviceId);

    if (filteredPositions.length !== positions.length) {
        console.warn(`[DEBUG] showDeviceRoute - Se filtraron ${positions.length - filteredPositions.length} puntos de otros dispositivos`);
        /* console.log eliminado */(`[DEBUG] showDeviceRoute - IDs de dispositivos en los puntos originales:`,
            [...new Set(positions.map(pos => pos.deviceId))]);
        positions = filteredPositions;
        /* console.log eliminado */(`[DEBUG] showDeviceRoute - Usando ${positions.length} puntos del dispositivo ${firstDeviceId}`);
    }

    if (positions.length < 2) {
        console.error('[DEBUG] showDeviceRoute - Error: menos de 2 puntos, no se puede dibujar una ruta');
        showToast('No hay suficientes puntos para mostrar una ruta', 'warning');
        return;
    }

    // Mostrar los primeros puntos para depuración
    /* console.log eliminado */('[DEBUG] showDeviceRoute - Primer punto:', positions[0]);
    /* console.log eliminado */('[DEBUG] showDeviceRoute - Último punto:', positions[positions.length - 1]);
    /* console.log eliminado */('[DEBUG] showDeviceRoute - Rango de tiempo:', {
        inicio: new Date(positions[0].deviceTime).toLocaleString(),
        fin: new Date(positions[positions.length - 1].deviceTime).toLocaleString(),
        duracion: Math.round((new Date(positions[positions.length - 1].deviceTime) - new Date(positions[0].deviceTime)) / 60000) + ' minutos'
    });

    // Verificar si hay puntos con coordenadas inválidas
    const invalidPoints = positions.filter(point =>
        !point.latitude || !point.longitude ||
        isNaN(point.latitude) || isNaN(point.longitude));

    if (invalidPoints.length > 0) {
        console.warn(`[DEBUG] showDeviceRoute - ADVERTENCIA: Hay ${invalidPoints.length} puntos con coordenadas inválidas`);
        /* console.log eliminado */(`[DEBUG] showDeviceRoute - Primer punto inválido:`, invalidPoints[0]);
    }

    // Verificar si hay puntos duplicados
    const uniqueCoords = new Set();
    const duplicatePoints = [];

    positions.forEach(point => {
        const coordKey = `${point.latitude},${point.longitude}`;
        if (uniqueCoords.has(coordKey)) {
            duplicatePoints.push(point);
        } else {
            uniqueCoords.add(coordKey);
        }
    });

    if (duplicatePoints.length > 0) {
        /* console.log eliminado */(`[DEBUG] showDeviceRoute - Hay ${duplicatePoints.length} puntos duplicados de ${positions.length} totales`);
    }

    // Verificar la distancia entre puntos consecutivos
    let maxDistance = 0;
    let maxDistanceIndex = -1;

    for (let i = 1; i < positions.length; i++) {
        const p1 = L.latLng(positions[i-1].latitude, positions[i-1].longitude);
        const p2 = L.latLng(positions[i].latitude, positions[i].longitude);
        const distance = p1.distanceTo(p2);

        if (distance > maxDistance) {
            maxDistance = distance;
            maxDistanceIndex = i;
        }
    }

    if (maxDistanceIndex > 0) {
        /* console.log eliminado */(`[DEBUG] showDeviceRoute - Distancia máxima entre puntos: ${(maxDistance/1000).toFixed(2)} km entre los puntos ${maxDistanceIndex-1} y ${maxDistanceIndex}`);
    }

    // Activar modo de visualización exclusiva
    enterRouteMode(positions[0].deviceId);

    // Crear array de puntos para la polilínea
    const points = positions.map(position => [position.latitude, position.longitude]);

    // Crear polilínea con estilo mejorado
    routeLayer = L.polyline(points, {
        color: '#3b82f6', // Azul más moderno
        weight: 4,
        opacity: 0.8,
        lineJoin: 'round',
        lineCap: 'round',
        dashArray: null
    }).addTo(map);

    // Añadir marcadores para el inicio y fin de la ruta
    const startPosition = positions[0];
    const endPosition = positions[positions.length - 1];

    // Marcador de inicio (verde)
    L.marker([startPosition.latitude, startPosition.longitude], {
        icon: L.divIcon({
            html: `<div class="route-marker start-marker"></div>`,
            className: 'custom-div-icon',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        })
    }).addTo(map).bindTooltip('Inicio: ' + new Date(startPosition.deviceTime).toLocaleString(), {
        permanent: false,
        direction: 'top'
    });

    // Marcador de fin (rojo)
    L.marker([endPosition.latitude, endPosition.longitude], {
        icon: L.divIcon({
            html: `<div class="route-marker end-marker"></div>`,
            className: 'custom-div-icon',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        })
    }).addTo(map).bindTooltip('Fin: ' + new Date(endPosition.deviceTime).toLocaleString(), {
        permanent: false,
        direction: 'top'
    });

    // Ajustar vista del mapa a la ruta
    map.fitBounds(routeLayer.getBounds(), {
        padding: [50, 50]
    });

    // Ocultar panel de detalles
    document.getElementById('vehicle-details-panel').classList.add('hidden');

    // Calcular distancia total (en km)
    let totalDistance = 0;
    for (let i = 1; i < positions.length; i++) {
        const p1 = L.latLng(positions[i-1].latitude, positions[i-1].longitude);
        const p2 = L.latLng(positions[i].latitude, positions[i].longitude);
        totalDistance += p1.distanceTo(p2);
    }

    // Calcular duración
    const startTime = new Date(startPosition.deviceTime);
    const endTime = new Date(endPosition.deviceTime);
    const durationMs = endTime - startTime;
    const hours = Math.floor(durationMs / (1000 * 60 * 60));
    const minutes = Math.floor((durationMs % (1000 * 60 * 60)) / (1000 * 60));

    // Mostrar información de la ruta
    showToast(`Ruta: ${(totalDistance / 1000).toFixed(2)} km - Duración: ${hours}h ${minutes}m`, 'info');
}

// Enviar comando para detener el motor
function sendEngineStopCommand(deviceId) {
    // Confirmar acción
    if (!confirm('¿Estás seguro de que deseas detener el motor de este vehículo?')) {
        return;
    }

    // Enviar comando
    apiRequest('sendCommand', {
        deviceId: deviceId,
        type: 'engineStop',
        attributes: {}
    })
        .then(response => {
            if (response.success) {
                showToast('Comando enviado correctamente', 'success');
            } else {
                showToast('Error al enviar el comando', 'error');
            }
        })
        .catch(error => {
            showToast('Error al enviar el comando: ' + error.message, 'error');
        });
}

// Función para realizar solicitudes a la API
function apiRequest(action, params) {
    const formData = new FormData();
    formData.append('csrf_token', config.csrfToken);
    formData.append('action', action);
    formData.append('params', JSON.stringify(params));


    const startTime = Date.now();

    // Agregar cabeceras especiales para asegurar que se reciba JSON
    const fetchOptions = {
        method: 'POST',
        body: formData,
        headers: {}
    };

    // Si es una solicitud de ruta, asegurarse de que se solicite JSON
    if (action === 'getRoute') {
        /* console.log eliminado */(`[DEBUG] apiRequest - Agregando cabecera Accept: application/json para solicitud de ruta`);
        fetchOptions.headers['Accept'] = 'application/json';
    }

    return fetch(config.apiUrl, fetchOptions)
        .then(response => {
            const endTime = Date.now();
            const duration = endTime - startTime;

            /* console.log eliminado */(`[DEBUG] apiRequest - Respuesta recibida en ${duration}ms`);
            /* console.log eliminado */(`[DEBUG] apiRequest - Status: ${response.status} ${response.statusText}`);

            if (!response.ok) {
                console.error(`[DEBUG] apiRequest - Error de red: ${response.status} ${response.statusText}`);
                throw new Error('Error de red: ' + response.status);
            }

            // Clonar la respuesta para poder leerla como texto para depuración
            const clonedResponse = response.clone();

            // Intentar leer el texto de la respuesta para depuración
            clonedResponse.text().then(text => {
                try {
                    if (text.length > 1000) {
                        /* console.log eliminado */(`[DEBUG] apiRequest - Respuesta (truncada): ${text.substring(0, 1000)}...`);
                    } else {
                        /* console.log eliminado */(`[DEBUG] apiRequest - Respuesta completa: ${text}`);
                    }
                } catch (e) {
                    /* console.log eliminado */(`[DEBUG] apiRequest - Error al mostrar texto de respuesta:`, e);
                }
            });

            return response.json();
        })
        .then(data => {
            /* console.log eliminado */(`[DEBUG] apiRequest - Datos JSON parseados:`, data);

            // Verificar si es una solicitud de ruta
            if (action === 'getRoute') {
                /* console.log eliminado */(`[DEBUG] apiRequest - Analizando respuesta de ruta:`);

                if (data.success && Array.isArray(data.data)) {
                    /* console.log eliminado */(`[DEBUG] apiRequest - Respuesta de ruta contiene ${data.data.length} puntos`);

                    if (data.data.length === 0) {
                        console.warn(`[DEBUG] apiRequest - ADVERTENCIA: La respuesta de ruta no contiene puntos`);
                        /* console.log eliminado */(`[DEBUG] apiRequest - Verificar parámetros: deviceId=${params.deviceId}, from=${params.from}, to=${params.to}`);

                        // Verificar si las fechas están en el futuro
                        const now = new Date();
                        const fromDate = new Date(params.from);
                        const toDate = new Date(params.to);

                        if (fromDate > now || toDate > now) {
                            console.warn(`[DEBUG] apiRequest - ADVERTENCIA: Las fechas están en el futuro, lo que podría explicar la falta de datos`);
                        }

                        // Verificar si el dispositivo existe
                        if (window.deviceData && window.deviceData[params.deviceId]) {
                            /* console.log eliminado */(`[DEBUG] apiRequest - Dispositivo ${params.deviceId} existe en datos locales: ${window.deviceData[params.deviceId].name}`);
                        } else {
                            console.warn(`[DEBUG] apiRequest - ADVERTENCIA: Dispositivo ${params.deviceId} no encontrado en datos locales`);
                        }
                    } else {
                        // Mostrar información sobre los puntos recibidos
                        const firstPoint = data.data[0];
                        const lastPoint = data.data[data.data.length - 1];

                        /* console.log eliminado */(`[DEBUG] apiRequest - Primer punto: deviceId=${firstPoint.deviceId}, tiempo=${new Date(firstPoint.deviceTime).toLocaleString()}`);
                        /* console.log eliminado */(`[DEBUG] apiRequest - Último punto: deviceId=${lastPoint.deviceId}, tiempo=${new Date(lastPoint.deviceTime).toLocaleString()}`);

                        // Verificar si todos los puntos pertenecen al mismo dispositivo
                        const deviceIds = [...new Set(data.data.map(point => point.deviceId))];
                        /* console.log eliminado */(`[DEBUG] apiRequest - Dispositivos en los puntos: ${deviceIds.join(', ')}`);

                        if (deviceIds.length > 1) {
                            console.warn(`[DEBUG] apiRequest - ADVERTENCIA: Los puntos pertenecen a múltiples dispositivos`);
                        }

                        if (!deviceIds.includes(parseInt(params.deviceId))) {
                            console.warn(`[DEBUG] apiRequest - ADVERTENCIA: Ninguno de los puntos pertenece al dispositivo solicitado ${params.deviceId}`);
                        }
                    }
                } else {
                    console.warn(`[DEBUG] apiRequest - ADVERTENCIA: Respuesta de ruta no tiene el formato esperado`);
                }
            }

            return data;
        })
        .catch(error => {
            console.error(`[DEBUG] apiRequest - Error en la solicitud:`, error);
            console.error(`[DEBUG] apiRequest - Stack trace:`, error.stack);
            throw error;
        });
}

// Cache para almacenar direcciones ya consultadas
const addressCache = {};

// Variable para controlar el tiempo entre solicitudes

// Obtener dirección usando la API de geocodificación inversa de LocationIQ
async function getAddress(lat, lon) {
    try {
        // Redondear coordenadas para mejorar el cache (6 decimales es suficiente precisión)
        const roundedLat = parseFloat(lat).toFixed(6);
        const roundedLon = parseFloat(lon).toFixed(6);
        const cacheKey = `${roundedLat},${roundedLon}`;

        // Verificar si ya tenemos esta dirección en cache
        if (addressCache[cacheKey]) {
            return addressCache[cacheKey];
        }

        // Implementar limitación de velocidad (rate limiting)
        const now = Date.now();
        const timeElapsed = now - lastAddressRequest;

        if (timeElapsed < MIN_REQUEST_INTERVAL) {
            // Esperar el tiempo necesario antes de hacer la siguiente solicitud
            await new Promise(resolve => setTimeout(resolve, MIN_REQUEST_INTERVAL - timeElapsed));
        }

        // Actualizar el tiempo de la última solicitud
        lastAddressRequest = Date.now();

        const response = await fetch(`https://us1.locationiq.com/v1/reverse?key=pk.e63c15fb2d66e143a9ffe1a1e9596fb5&lat=${roundedLat}&lon=${roundedLon}&format=json`);

        if (!response.ok) {
            if (response.status === 429) {
                throw new Error('Límite de solicitudes excedido. Por favor, inténtalo de nuevo más tarde.');
            }
            throw new Error('Error al obtener la dirección');
        }

        const data = await response.json();
        const address = data.display_name || 'Dirección no disponible';

        // Guardar en cache
        addressCache[cacheKey] = address;

        return address;
    } catch (error) {
        console.error('Error al obtener la dirección:', error);
        return 'Error al obtener la dirección: ' + error.message;
    }
}

// Actualizar marcadores con nuevas posiciones
window.updateMarkers = function(positions) {
    // Verificar si hay posiciones
    if (!positions || !positions.length) {
        return;
    }

    // Si estamos en modo de ruta, solo actualizar el dispositivo seleccionado
    if (window.isRouteMode && window.currentRouteDeviceId) {
        positions = positions.filter(position => position.deviceId === parseInt(window.currentRouteDeviceId));

        if (positions.length === 0) {
            return;
        }
    }

    // Actualizar cada posición
    positions.forEach(position => {
        const deviceId = position.deviceId;
        const device = deviceData[deviceId];

        if (!device) return;

        // Actualizar la última posición en los datos del dispositivo
        device.lastPosition = position;

        // Determinar si está en línea (menos de 5 minutos desde la última actualización)
        const isOnline = new Date() - new Date(position.deviceTime) < 5 * 60 * 1000;

        // Actualizar estado en datos del dispositivo
        device.status = isOnline ? 'online' : 'offline';

        // Actualizar marcador
        updateDeviceMarker(device, position);
    });

    // Actualizar lista de vehículos con nuevos estados
    if (typeof window.updateVehiclesList === 'function') {
        window.updateVehiclesList();
    }
}

// Sincronizar el tema del mapa con el tema de la aplicación
function syncMapTheme() {
    // Obtener el tema actual
    const isDarkTheme = document.documentElement.getAttribute('data-theme') === 'dark';

    // Obtener todas las capas base
    const layers = Object.values(map._layers);

    // Buscar las capas de LocationIQ
    const locationIQStreets = layers.find(layer =>
        layer._url && layer._url.includes('locationiq.com/v3/streets'));

    const locationIQDark = layers.find(layer =>
        layer._url && layer._url.includes('locationiq.com/v3/dark'));

    // Cambiar la capa según el tema
    if (isDarkTheme && locationIQDark && !map.hasLayer(locationIQDark)) {
        // Si es tema oscuro y no está activa la capa oscura
        if (locationIQStreets && map.hasLayer(locationIQStreets)) {
            map.removeLayer(locationIQStreets);
        }
        map.addLayer(locationIQDark);
    } else if (!isDarkTheme && locationIQStreets && !map.hasLayer(locationIQStreets)) {
        // Si es tema claro y no está activa la capa clara
        if (locationIQDark && map.hasLayer(locationIQDark)) {
            map.removeLayer(locationIQDark);
        }
        map.addLayer(locationIQStreets);
    }
}

// Inicializar mapa cuando se cargue la página
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar objeto para almacenar datos de dispositivos
    window.deviceData = {};

    // Inicializar objeto para almacenar marcadores
    window.markers = {};

    // Inicializar mapa
    initMap();

    // Cargar dispositivos iniciales
    loadDevices().then(() => {
        // Verificar si hay parámetros de ruta en la URL
        if (typeof routeParams !== 'undefined' && routeParams.deviceId && routeParams.from && routeParams.to) {
            /* console.log eliminado */(`[DEBUG] Cargando ruta desde parámetros de URL:`, routeParams);

            // Convertir deviceId a número
            const deviceId = parseInt(routeParams.deviceId);

            // Convertir fechas ISO a objetos Date
            const fromDate = new Date(routeParams.from);
            const toDate = new Date(routeParams.to);

            // Verificar si las fechas son válidas
            if (!isNaN(fromDate.getTime()) && !isNaN(toDate.getTime())) {
                /* console.log eliminado */(`[DEBUG] Cargando ruta para dispositivo ${deviceId} desde ${fromDate.toLocaleString()} hasta ${toDate.toLocaleString()}`);

                // Esperar un momento para asegurarse de que los dispositivos estén cargados
                setTimeout(() => {
                    loadRouteWithDates(deviceId, fromDate, toDate);
                }, 1000);
            } else {
                console.error(`[DEBUG] Fechas inválidas en los parámetros de URL:`, routeParams);
                showToast('Fechas inválidas en los parámetros de URL', 'error');
            }
        }
    });
});

// Escuchar cambios en el tema
document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('change', () => {
            // Esperar a que el tema se actualice
            setTimeout(syncMapTheme, 100);
        });
    }
});

// Entrar en modo de visualización exclusiva (solo mostrar el vehículo seleccionado)
function enterRouteMode(deviceId) {

    // Guardar el ID del dispositivo actual
    window.currentRouteDeviceId = deviceId;

    // Activar el modo de ruta
    window.isRouteMode = true;

    // Convertir deviceId a entero para comparaciones
    deviceId = parseInt(deviceId);

    // Limpiar todos los marcadores del cluster
    markerCluster.clearLayers();

    // Volver a añadir solo el marcador seleccionado
    if (markers[deviceId]) {
        markerCluster.addLayer(markers[deviceId]);

        // Resaltar el marcador seleccionado
        if (markers[deviceId]._icon) {
            markers[deviceId]._icon.classList.add('selected-vehicle');
        }
    } else {
        console.warn(`[enterRouteMode] No se encontró el marcador para el dispositivo ${deviceId}`);
        // Intentar buscar el dispositivo en los datos
        if (deviceData[deviceId] && deviceData[deviceId].lastPosition) {
            /* console.log eliminado */(`[enterRouteMode] Creando marcador para el dispositivo ${deviceId}`);
            createMarker(deviceData[deviceId], deviceData[deviceId].lastPosition);
        } else {
            console.error(`[enterRouteMode] No se encontraron datos para el dispositivo ${deviceId}`);
        }
    }

    // Eliminar el botón anterior si existe
    if (window.routeBackButton) {
        map.removeControl(window.routeBackButton);
        window.routeBackButton = null;
    }

    // Crear botón para volver a la vista normal
    /* console.log eliminado */('[enterRouteMode] Creando botón para volver a la vista normal');

    // Crear botón personalizado
    const RouteBackControl = L.Control.extend({
        options: {
            position: 'bottomright'
        },

        onAdd: function() {
            const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
            const button = L.DomUtil.create('a', 'route-back-button', container);
            button.href = '#';
            button.title = 'Volver a vista normal';
            button.innerHTML = `
                <div class="flex items-center justify-center w-full h-full bg-base-100 text-base-content p-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                    </svg>
                </div>
            `;

            L.DomEvent.on(button, 'click', function(e) {
                L.DomEvent.preventDefault(e);
                exitRouteMode();
            });

            return container;
        }
    });

    // Añadir el control al mapa
    window.routeBackButton = new RouteBackControl();
    map.addControl(window.routeBackButton);



    // Mostrar mensaje
    showToast('Mostrando solo la ruta del vehículo seleccionado', 'info');
}

// Salir del modo de visualización exclusiva (mostrar todos los vehículos)
function exitRouteMode() {

    // Desactivar el modo de ruta
    window.isRouteMode = false;
    window.currentRouteDeviceId = null;

    // Eliminar la ruta
    if (routeLayer) {
        map.removeLayer(routeLayer);
        routeLayer = null;
    }

    // Eliminar marcadores de inicio y fin
    map.eachLayer(layer => {
        if (layer instanceof L.Marker && layer.getElement() && layer.getElement().querySelector('.route-marker')) {
            map.removeLayer(layer);
        }
    });

    // Eliminar el botón de volver
    if (window.routeBackButton) {
        map.removeControl(window.routeBackButton);
        window.routeBackButton = null;
    }

    // Limpiar todos los marcadores del cluster
    markerCluster.clearLayers();

    // Volver a añadir todos los marcadores
    Object.values(markers).forEach(marker => {
        markerCluster.addLayer(marker);
    });

    // Recargar las posiciones actuales
    loadPositions();

    // Mostrar mensaje
    showToast('Volviendo a la vista normal', 'info');
}

// Inicializar eventos para el botón de geocercas
document.addEventListener('DOMContentLoaded', function() {
    // Botón para cargar geocercas
    const loadGeofencesBtn = document.getElementById('load-geofences-btn');
    if (loadGeofencesBtn) {
        loadGeofencesBtn.addEventListener('click', function() {
            console.log('%c🌍 GEOCERCAS: Botón de carga de geocercas pulsado', 'background: #4CAF50; color: white; padding: 2px 5px; border-radius: 3px;');
            showToast('Cargando geocercas...', 'info');
            loadGeofences();
        });
    }

    // Botón para cargar ruta con fechas personalizadas
    document.getElementById('btn-load-route').addEventListener('click', function() {
        const deviceId = parseInt(document.getElementById('route-device-id').value);
        const dateFrom = document.getElementById('route-date-from').value;
        const dateTo = document.getElementById('route-date-to').value;
        const timeFrom = document.getElementById('route-time-from').value;
        const timeTo = document.getElementById('route-time-to').value;

        // Validar fechas
        if (!dateFrom || !dateTo) {
            showToast('Por favor, seleccione fechas válidas', 'error');
            return;
        }

        // Crear objetos de fecha
        const fromParts = timeFrom.split(':');
        const toParts = timeTo.split(':');

        const fromDate = new Date(dateFrom);
        fromDate.setHours(parseInt(fromParts[0]), parseInt(fromParts[1]), 0, 0);

        const toDate = new Date(dateTo);
        toDate.setHours(parseInt(toParts[0]), parseInt(toParts[1]), 59, 999);

        // Validar rango de fechas
        if (fromDate > toDate) {
            showToast('La fecha de inicio debe ser anterior a la fecha de fin', 'error');
            return;
        }

        // Cerrar modal
        document.getElementById('route-modal').close();

        // Cargar ruta
        loadRouteWithDates(deviceId, fromDate, toDate);
    });

    // Botón para mostrar ruta de hoy
    document.getElementById('btn-show-today-route').addEventListener('click', function() {
        const deviceId = parseInt(document.getElementById('route-device-id').value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);

        // Cerrar modal
        document.getElementById('route-modal').close();

        // Cargar ruta
        loadRouteWithDates(deviceId, today, tomorrow);
    });

    // Botón para mostrar ruta de ayer
    document.getElementById('btn-show-yesterday-route').addEventListener('click', function() {
        const deviceId = parseInt(document.getElementById('route-device-id').value);
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        yesterday.setHours(0, 0, 0, 0);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Cerrar modal
        document.getElementById('route-modal').close();

        // Cargar ruta
        loadRouteWithDates(deviceId, yesterday, today);
    });

    // Botón para mostrar ruta de la última semana
    document.getElementById('btn-show-week-route').addEventListener('click', function() {
        const deviceId = parseInt(document.getElementById('route-device-id').value);
        const weekAgo = new Date();
        weekAgo.setDate(weekAgo.getDate() - 7);
        weekAgo.setHours(0, 0, 0, 0);
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(0, 0, 0, 0);

        // Cerrar modal
        document.getElementById('route-modal').close();

        // Cargar ruta
        loadRouteWithDates(deviceId, weekAgo, tomorrow);
    });

    // Botón para mostrar ruta de los últimos 30 días
    document.getElementById('btn-show-month-route').addEventListener('click', function() {
        const deviceId = parseInt(document.getElementById('route-device-id').value);
        const monthAgo = new Date();
        monthAgo.setDate(monthAgo.getDate() - 30);
        monthAgo.setHours(0, 0, 0, 0);
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(0, 0, 0, 0);

        // Cerrar modal
        document.getElementById('route-modal').close();

        // Cargar ruta
        loadRouteWithDates(deviceId, monthAgo, tomorrow);
    });
});

// Evento para mostrar la dirección en el modal (versión simplificada)
document.addEventListener('click', function(e) {
    // Verificar si el clic fue en un botón de mostrar dirección
    if (e.target.closest('.show-address')) {
        e.preventDefault();

        // Obtener el botón y sus datos
        const button = e.target.closest('.show-address');
        const lat = button.getAttribute('data-lat');
        const lon = button.getAttribute('data-lon');

        // Obtener el modal y su contenido
        const modal = document.getElementById('address-modal');
        const modalContent = document.getElementById('address-modal-content');

        // Mostrar indicador de carga
        modalContent.innerHTML = '<div class="flex justify-center py-4"><span class="loading loading-spinner loading-md"></span></div>';

        // Mostrar el modal
        modal.showModal();

        // Obtener la dirección
        fetch(`https://us1.locationiq.com/v1/reverse.php?key=pk.e63c15fb2d66e143a9ffe1a1e9596fb5&lat=${lat}&lon=${lon}&format=json`)
            .then(response => {
                if (!response.ok) throw new Error('Error al obtener la dirección');
                return response.json();
            })
            .then(data => {
                // Mostrar la dirección en el modal (versión simplificada)
                modalContent.innerHTML = `
                    <p class="text-lg font-medium mb-3">${data.display_name}</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        ${data.address ? `
                            ${data.address.road ? `<p><span class="font-medium">Calle:</span> ${data.address.road}</p>` : ''}
                            ${data.address.house_number ? `<p><span class="font-medium">Número:</span> ${data.address.house_number}</p>` : ''}
                            ${data.address.suburb ? `<p><span class="font-medium">Barrio:</span> ${data.address.suburb}</p>` : ''}
                            ${data.address.city || data.address.town ? `<p><span class="font-medium">Ciudad:</span> ${data.address.city || data.address.town}</p>` : ''}
                            ${data.address.state ? `<p><span class="font-medium">Estado:</span> ${data.address.state}</p>` : ''}
                            ${data.address.postcode ? `<p><span class="font-medium">C.P.:</span> ${data.address.postcode}</p>` : ''}
                            ${data.address.country ? `<p><span class="font-medium">País:</span> ${data.address.country}</p>` : ''}
                        ` : '<p>No hay detalles disponibles</p>'}
                    </div>
                    <div class="mt-4">
                        <a href="https://www.google.com/maps/dir/?api=1&destination=${lat},${lon}" target="_blank" class="btn btn-sm btn-primary">
                            Obtener dirección en Google Maps
                        </a>
                    </div>
                `;
            })
            .catch(error => {
                // Mostrar error
                modalContent.innerHTML = `
                    <div class="alert alert-error">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>Error al obtener la dirección. Por favor, intente nuevamente.</span>
                    </div>
                `;
                console.error('Error al obtener la dirección:', error);
            });
    }
});
