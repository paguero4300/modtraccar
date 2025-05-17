/**
 * Conexión WebSocket para actualizaciones en tiempo real
 */

// Variables globales
let socket;
let reconnectInterval;
let isConnected = false;

// Inicializar conexión WebSocket
window.initWebSocket = function() {
    // Verificar soporte de WebSocket
    if (!window.WebSocket) {
        console.warn('WebSocket no soportado. Usando polling como fallback.');
        initPolling();
        return;
    }

    try {
        // Verificar si la URL es válida
        if (!config.wsUrl || config.wsUrl === 'wss://monitoreo.transporteurbanogps.click/api/socket') {
            // URL no válida o no configurada, usar polling
            console.warn('URL de WebSocket no válida o no configurada. Usando polling como fallback.');
            initPolling();
            return;
        }

        // Crear conexión WebSocket
        socket = new WebSocket(config.wsUrl);

        // Configurar eventos
        socket.onopen = handleSocketOpen;
        socket.onmessage = handleSocketMessage;
        socket.onclose = handleSocketClose;
        socket.onerror = handleSocketError;
    } catch (error) {
        console.error('Error al inicializar WebSocket:', error);
        initPolling();
    }
}

// Manejar apertura de conexión
function handleSocketOpen() {
    console.log('Conexión WebSocket establecida');
    isConnected = true;

    // Limpiar intervalo de reconexión si existe
    if (reconnectInterval) {
        clearInterval(reconnectInterval);
        reconnectInterval = null;
    }

    showToast('Conexión en tiempo real establecida', 'success');
}

// Manejar mensajes recibidos
function handleSocketMessage(event) {
    try {
        const data = JSON.parse(event.data);

        // Procesar diferentes tipos de mensajes
        if (data.positions && data.positions.length > 0) {
            // Actualizar marcadores con nuevas posiciones
            updateMarkers(data.positions);
        } else if (data.devices && data.devices.length > 0) {
            // Actualizar datos de dispositivos
            data.devices.forEach(device => {
                deviceData[device.id] = device;
            });
            updateVehiclesList();
        } else if (data.events && data.events.length > 0) {
            // Procesar eventos
            handleEvents(data.events);
        }
    } catch (error) {
        console.error('Error al procesar mensaje WebSocket:', error);
    }
}

// Manejar cierre de conexión
function handleSocketClose() {
    console.log('Conexión WebSocket cerrada');
    isConnected = false;

    // Intentar reconectar después de un tiempo
    if (!reconnectInterval) {
        reconnectInterval = setTimeout(() => {
            console.log('Intentando reconectar WebSocket...');
            initWebSocket();
        }, 5000);
    }

    // Iniciar polling como fallback
    initPolling();
}

// Manejar errores de conexión
function handleSocketError(error) {
    console.error('Error en la conexión WebSocket:', error);

    // Si aún no se ha establecido la conexión, usar polling
    if (!isConnected) {
        initPolling();
    }
}

// Inicializar polling como fallback
function initPolling() {
    // Verificar si ya existe un intervalo de polling
    if (window.pollingInterval) {
        return;
    }
    
    console.log('Iniciando polling como fallback');
    
    // Configurar intervalo de polling
    window.pollingInterval = setInterval(() => {
        // Solo hacer polling si WebSocket no está conectado
        if (!isConnected) {
            loadPositions();
        }
    }, config.refreshInterval);
}

// Cargar dispositivos mediante API
function loadDevices() {
    apiRequest('getDevices', {})
        .then(response => {
            if (response.success && response.data) {
                // Actualizar datos de dispositivos
                response.data.forEach(device => {
                    deviceData[device.id] = device;
                });

                // Actualizar lista de vehículos
                updateVehiclesList();

                // Cargar posiciones después de obtener dispositivos
                loadPositions();
            }
        })
        .catch(error => {
            console.error('Error al cargar dispositivos:', error);
        });
}

// Cargar posiciones mediante API
function loadPositions() {
    apiRequest('getPositions', {})
        .then(response => {
            if (response.success && response.data) {
                // Actualizar marcadores con nuevas posiciones
                updateMarkers(response.data);
            }
        })
        .catch(error => {
            console.error('Error al cargar posiciones:', error);
        });
}

// Manejar eventos recibidos
function handleEvents(events) {
    events.forEach(event => {
        // Mostrar notificación para eventos importantes
        switch (event.type) {
            case 'deviceOnline':
                showToast(`${deviceData[event.deviceId]?.name || 'Vehículo'} está en línea`, 'success');
                break;

            case 'deviceOffline':
                showToast(`${deviceData[event.deviceId]?.name || 'Vehículo'} está desconectado`, 'warning');
                break;

            case 'deviceOverspeed':
                showToast(`¡Exceso de velocidad! ${deviceData[event.deviceId]?.name || 'Vehículo'}`, 'error');
                break;

            case 'commandResult':
                if (event.attributes && event.attributes.success) {
                    showToast('Comando ejecutado con éxito', 'success');
                } else {
                    showToast('Error al ejecutar comando', 'error');
                }
                break;
        }
    });
}

// Inicializar WebSocket cuando se cargue la página
document.addEventListener('DOMContentLoaded', initWebSocket);
