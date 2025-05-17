/**
 * Funcionalidad del mapa con Leaflet
 */

// Variables globales
let map;
let markers = {};
let deviceData = {};
let windowSelectedDeviceId = null;
let routeLayer = null;

// Inicializar el mapa
function initMap() {
    // Crear mapa
    map = L.map('map').setView(config.defaultPosition, config.defaultZoom);
    
    // Añadir capa de mosaicos (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Cargar dispositivos iniciales
    loadDevices();
}

// Cargar dispositivos desde la API
function loadDevices() {
    apiRequest('getDevices', {})
        .then(response => {
            if (response.success && response.data) {
                // Guardar datos de dispositivos
                deviceData = {};
                response.data.forEach(device => {
                    deviceData[device.id] = device;
                });
                
                // Actualizar contador
                updateVehicleCount(response.data.length);
                
                // Cargar posiciones iniciales
                loadPositions();
                
                // Actualizar lista de vehículos
                updateVehiclesList();
            }
        })
        .catch(error => {
            showToast('Error al cargar dispositivos: ' + error.message, 'error');
        });
}

// Cargar posiciones desde la API
function loadPositions() {
    apiRequest('getPositions', {})
        .then(response => {
            if (response.success && response.data) {
                // Actualizar marcadores
                updateMarkers(response.data);
            }
        })
        .catch(error => {
            showToast('Error al cargar posiciones: ' + error.message, 'error');
        });
}

// Actualizar marcadores en el mapa
function updateMarkers(positions) {
    // Crear o actualizar marcadores
    positions.forEach(position => {
        const deviceId = position.deviceId;
        const device = deviceData[deviceId];
        
        if (!device) return;
        
        // Determinar si está en línea (menos de 5 minutos desde la última actualización)
        const isOnline = new Date() - new Date(position.deviceTime) < 5 * 60 * 1000;
        
        // Actualizar estado en datos del dispositivo
        device.status = isOnline ? 'online' : 'offline';
        device.lastPosition = position;
        
        // Crear o actualizar marcador
        if (markers[deviceId]) {
            // Actualizar posición con animación
            const marker = markers[deviceId];
            marker.setLatLng([position.latitude, position.longitude]);
            
            // Actualizar clase según estado
            const icon = marker.getElement();
            if (icon) {
                icon.classList.toggle('offline', !isOnline);
            }
            
            // Actualizar popup
            updateMarkerPopup(marker, device, position);
        } else {
            // Crear nuevo marcador
            createMarker(device, position);
        }
    });
    
    // Actualizar lista de vehículos con nuevos estados
    updateVehiclesList();
}

// Crear un nuevo marcador
function createMarker(device, position) {
    // Crear elemento HTML personalizado para el marcador
    const isOnline = new Date() - new Date(position.deviceTime) < 5 * 60 * 1000;
    const iconHtml = `<div class="vehicle-marker-icon ${isOnline ? '' : 'offline'}">${device.name.charAt(0)}</div>`;
    
    // Crear marcador con icono personalizado
    const marker = L.marker([position.latitude, position.longitude], {
        icon: L.divIcon({
            html: iconHtml,
            className: 'vehicle-marker',
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        })
    });
    
    // Añadir popup
    updateMarkerPopup(marker, device, position);
    
    // Añadir evento de clic
    marker.on('click', () => {
        windowSelectedDeviceId = device.id;
        showVehicleDetails(device, position);
    });
    
    // Guardar marcador
    markers[device.id] = marker;
    marker.addTo(map);
    
    return marker;
}

// Actualizar el popup de un marcador
function updateMarkerPopup(marker, device, position) {
    const isOnline = new Date() - new Date(position.deviceTime) < 5 * 60 * 1000;
    const statusClass = isOnline ? 'badge-success' : 'badge-error';
    const statusText = isOnline ? 'En línea' : 'Desconectado';
    
    const speed = (position.speed * 1.852).toFixed(1); // Convertir de nudos a km/h
    const lastUpdate = new Date(position.deviceTime).toLocaleString();
    
    const popupContent = `
        <div class="vehicle-popup">
            <h3 class="text-lg font-bold">${device.name}</h3>
            <div class="badge ${statusClass} my-1">${statusText}</div>
            <div class="mt-2">
                <p><strong>Velocidad:</strong> ${speed} km/h</p>
                <p><strong>Última actualización:</strong> ${lastUpdate}</p>
                <p><strong>Dirección:</strong> ${position.address || 'No disponible'}</p>
            </div>
            <button class="btn btn-sm btn-primary mt-2 show-details" data-device-id="${device.id}">Ver detalles</button>
        </div>
    `;
    
    marker.bindPopup(popupContent);
    
    // Si el popup está abierto, actualizarlo
    if (marker.isPopupOpen()) {
        marker.getPopup().setContent(popupContent);
    }
    
    // Añadir evento al botón después de abrir el popup
    marker.on('popupopen', () => {
        document.querySelector(`.show-details[data-device-id="${device.id}"]`).addEventListener('click', () => {
            windowSelectedDeviceId = device.id;
            showVehicleDetails(device, position);
        });
    });
}

// Mostrar detalles del vehículo en el modal
function showVehicleDetails(device, position) {
    const modal = document.getElementById('vehicle-details-modal');
    const nameElement = document.getElementById('modal-vehicle-name');
    const detailsElement = document.getElementById('modal-vehicle-details');
    
    // Establecer nombre del vehículo
    nameElement.textContent = device.name;
    
    // Calcular datos
    const isOnline = new Date() - new Date(position.deviceTime) < 5 * 60 * 1000;
    const statusClass = isOnline ? 'badge-success' : 'badge-error';
    const statusText = isOnline ? 'En línea' : 'Desconectado';
    const speed = (position.speed * 1.852).toFixed(1); // Convertir de nudos a km/h
    const lastUpdate = new Date(position.deviceTime).toLocaleString();
    
    // Generar HTML de detalles
    detailsElement.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <div class="badge ${statusClass} my-1">${statusText}</div>
                <p class="mt-2"><strong>ID:</strong> ${device.id}</p>
                <p><strong>Identificador único:</strong> ${device.uniqueId}</p>
                <p><strong>Velocidad actual:</strong> ${speed} km/h</p>
                <p><strong>Última actualización:</strong> ${lastUpdate}</p>
                <p><strong>Dirección:</strong> ${position.address || 'No disponible'}</p>
            </div>
            <div>
                <p><strong>Latitud:</strong> ${position.latitude.toFixed(6)}</p>
                <p><strong>Longitud:</strong> ${position.longitude.toFixed(6)}</p>
                <p><strong>Altitud:</strong> ${position.altitude ? position.altitude.toFixed(1) + ' m' : 'No disponible'}</p>
                <p><strong>Curso:</strong> ${position.course ? position.course.toFixed(1) + '°' : 'No disponible'}</p>
                <button class="btn btn-sm btn-info mt-4" id="btn-show-route">Ver ruta del día</button>
            </div>
        </div>
    `;
    
    // Mostrar modal
    modal.showModal();
    
    // Añadir evento al botón de ruta
    document.getElementById('btn-show-route').addEventListener('click', () => {
        loadDeviceRoute(device.id);
    });
    
    // Añadir evento al botón de detener motor
    document.getElementById('btn-engine-stop').addEventListener('click', (e) => {
        e.preventDefault();
        sendEngineStopCommand(device.id);
    });
}

// Cargar ruta del dispositivo para el día actual
function loadDeviceRoute(deviceId) {
    // Obtener fechas de inicio y fin del día actual
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    const from = today.toISOString();
    const to = tomorrow.toISOString();
    
    // Solicitar ruta a la API
    apiRequest('getRoute', {
        deviceId: deviceId,
        from: from,
        to: to
    })
        .then(response => {
            if (response.success && response.data) {
                showDeviceRoute(response.data);
            } else {
                showToast('No hay datos de ruta disponibles para hoy', 'warning');
            }
        })
        .catch(error => {
            showToast('Error al cargar la ruta: ' + error.message, 'error');
        });
}

// Mostrar ruta en el mapa
function showDeviceRoute(positions) {
    // Limpiar ruta anterior si existe
    if (routeLayer) {
        map.removeLayer(routeLayer);
    }
    
    // Verificar si hay suficientes puntos
    if (positions.length < 2) {
        showToast('No hay suficientes puntos para mostrar una ruta', 'warning');
        return;
    }
    
    // Crear array de puntos para la polilínea
    const points = positions.map(position => [position.latitude, position.longitude]);
    
    // Crear polilínea
    routeLayer = L.polyline(points, {
        color: 'blue',
        weight: 4,
        opacity: 0.7,
        lineJoin: 'round'
    }).addTo(map);
    
    // Ajustar vista del mapa a la ruta
    map.fitBounds(routeLayer.getBounds(), {
        padding: [50, 50]
    });
    
    // Cerrar modal
    document.getElementById('vehicle-details-modal').close();
    
    showToast('Mostrando ruta del día', 'info');
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
    
    return fetch(config.apiUrl, {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error de red: ' + response.status);
            }
            return response.json();
        });
}

// Inicializar mapa cuando se cargue la página
document.addEventListener('DOMContentLoaded', initMap);
