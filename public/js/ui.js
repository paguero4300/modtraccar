/**
 * Funciones de interfaz de usuario
 */

// Variables globales
let currentFilter = 'all';
let searchTerm = '';

// Inicializar UI
function initUI() {
    // Configurar cambio de tema
    setupThemeToggle();

    // Configurar sidebar colapsable
    setupSidebar();

    // Configurar filtros de vehículos
    setupVehicleFilters();

    // Configurar búsqueda de vehículos
    setupVehicleSearch();
}

// Configurar cambio de tema claro/oscuro
function setupThemeToggle() {
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
}

// Configurar sidebar colapsable
function setupSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleNavButton = document.getElementById('toggle-sidebar-nav');

    if (!sidebar || !toggleNavButton) {
        return;
    }

    // Verificar preferencia guardada
    if (localStorage.getItem('sidebar-collapsed') === 'true') {
        sidebar.classList.add('collapsed');

        // Asegurar que el mapa se ajuste correctamente al cargar
        setTimeout(() => {
            if (window.map && typeof window.map.invalidateSize === 'function') {
                window.map.invalidateSize();
            }
        }, 100);
    }

    // Función para colapsar el sidebar
    function collapseSidebar() {
        sidebar.classList.add('collapsed');

        // Guardar preferencia
        localStorage.setItem('sidebar-collapsed', 'true');

        // Actualizar el mapa para que se ajuste al nuevo tamaño
        setTimeout(() => {
            if (window.map && typeof window.map.invalidateSize === 'function') {
                window.map.invalidateSize();
            }
        }, 300); // Esperar a que termine la transición
    }

    // Función para expandir el sidebar
    function expandSidebar() {
        sidebar.classList.remove('collapsed');

        // Guardar preferencia
        localStorage.setItem('sidebar-collapsed', 'false');

        // Actualizar el mapa para que se ajuste al nuevo tamaño
        setTimeout(() => {
            if (window.map && typeof window.map.invalidateSize === 'function') {
                window.map.invalidateSize();
            }
        }, 300); // Esperar a que termine la transición
    }

    // Manejar clic en el botón de la barra de navegación
    toggleNavButton.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        // Alternar entre mostrar y ocultar
        if (sidebar.classList.contains('collapsed')) {
            expandSidebar();
        } else {
            collapseSidebar();
        }
    });

    // Asegurar que el mapa se ajuste correctamente en caso de cambios de tamaño de ventana
    window.addEventListener('resize', function() {
        setTimeout(() => {
            if (window.map && typeof window.map.invalidateSize === 'function') {
                window.map.invalidateSize();
            }
        }, 100);
    });
}

// Configurar filtros de vehículos
function setupVehicleFilters() {
    const filterButtons = document.querySelectorAll('button[data-filter]');

    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Actualizar estado activo
            filterButtons.forEach(b => b.classList.remove('filter-active'));
            button.classList.add('filter-active');

            // Actualizar filtro actual
            currentFilter = button.dataset.filter;

            // Actualizar lista
            updateVehiclesList();
        });
    });
}



// Configurar búsqueda de vehículos
function setupVehicleSearch() {
    const searchInput = document.getElementById('search-vehicle');

    searchInput.addEventListener('input', () => {
        searchTerm = searchInput.value.toLowerCase();
        updateVehiclesList();
    });
}

// Actualizar contador de vehículos
window.updateVehicleCount = function(count) {
    document.getElementById('vehicles-count').textContent = count;
}

// Actualizar lista de vehículos
window.updateVehiclesList = function() {
    const vehiclesList = document.getElementById('vehicles-list');
    const devices = Object.values(deviceData);

    // Filtrar dispositivos
    let filteredDevices = devices;

    // Aplicar filtro de estado
    if (currentFilter !== 'all') {
        filteredDevices = filteredDevices.filter(device => device.status === currentFilter);
    }

    // Aplicar búsqueda
    if (searchTerm) {
        filteredDevices = filteredDevices.filter(device =>
            device.name.toLowerCase().includes(searchTerm) ||
            device.uniqueId.toLowerCase().includes(searchTerm)
        );
    }

    // Ordenar por nombre
    filteredDevices.sort((a, b) => a.name.localeCompare(b.name));

    // Generar HTML
    let html = '';

    if (filteredDevices.length === 0) {
        html = '<div class="alert alert-info">No se encontraron vehículos</div>';
    } else {
        filteredDevices.forEach(device => {
            const position = device.lastPosition;
            const isOnline = device.status === 'online';
            const statusClass = isOnline ? 'badge-success' : 'badge-error';
            const statusText = isOnline ? 'En línea' : 'Desconectado';

            let speed = '0.0 km/h';
            let lastUpdate = 'No disponible';

            if (position) {
                speed = ((position.speed || 0) * 1.852).toFixed(1) + ' km/h';
                lastUpdate = new Date(position.deviceTime).toLocaleString();
            }

            // Determinar si está en movimiento
            const isMoving = position && position.speed > 0.54; // 0.54 nudos ≈ 1 km/h
            const movementClass = isMoving ? 'badge-info' : 'badge-warning';
            const movementText = isMoving ? 'En movimiento' : 'Detenido';

            // Icono según estado
            let iconSvg = '';
            if (isOnline) {
                if (isMoving) {
                    iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" viewBox="0 0 24 24" fill="currentColor" stroke="#000000" stroke-width="0.2"><path d="M12,2L4.5,20.29L5.21,21L12,18L18.79,21L19.5,20.29L12,2Z"/></svg>';
                } else {
                    iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary" viewBox="0 0 24 24" fill="currentColor" stroke="#000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>';
                }
            } else {
                if (isMoving) {
                    iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-error" viewBox="0 0 24 24" fill="currentColor" stroke="#000000" stroke-width="0.2"><path d="M12,2L4.5,20.29L5.21,21L12,18L18.79,21L19.5,20.29L12,2Z"/></svg>';
                } else {
                    iconSvg = '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-error" viewBox="0 0 24 24" fill="currentColor" stroke="#000000" stroke-width="0.2"><path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6"/></svg>';
                }
            }

            // Dirección (si está disponible)
            const address = position && position.address ? position.address : 'Ubicación desconocida';

            html += `
                <div class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow cursor-pointer vehicle-item" data-device-id="${device.id}">
                    <div class="card-body p-3">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                ${iconSvg}
                            </div>
                            <div class="flex-grow">
                                <h3 class="font-bold text-base">${device.name}</h3>
                                <div class="flex flex-wrap gap-1 my-1">
                                    <div class="badge ${statusClass} badge-sm">${statusText}</div>
                                    ${position ? `<div class="badge ${movementClass} badge-sm">${movementText}</div>` : ''}
                                </div>
                                <p class="text-xs truncate opacity-70">${address}</p>
                            </div>
                            <div class="text-right text-xs">
                                <p class="font-semibold">${speed}</p>
                                <p class="opacity-70">${lastUpdate}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }

    vehiclesList.innerHTML = html;

    // Añadir eventos de clic
    document.querySelectorAll('.vehicle-item').forEach(item => {
        item.addEventListener('click', () => {
            const deviceId = parseInt(item.dataset.deviceId);
            const device = deviceData[deviceId];
            const position = device.lastPosition;

            if (device && position) {
                // Centrar mapa en el vehículo
                map.setView([position.latitude, position.longitude], 16);

                // Mostrar popup
                const marker = markers[deviceId];
                if (marker) {
                    marker.openPopup();
                }

                // Mostrar detalles del vehículo
                window.selectedDeviceId = deviceId;
                if (typeof window.showVehicleDetails === 'function') {
                    window.showVehicleDetails(device, position);
                }

                // En móvil, ocultar sidebar
                if (window.innerWidth < 768) {
                    document.getElementById('sidebar').classList.add('hidden');
                }
            }
        });
    });
}

// Mostrar notificación toast
function showToast(message, type = 'info') {
    // Configurar colores según tipo
    let backgroundColor = '#3498db'; // info (azul)

    switch (type) {
        case 'success':
            backgroundColor = '#2ecc71'; // verde
            break;
        case 'warning':
            backgroundColor = '#f39c12'; // naranja
            break;
        case 'error':
            backgroundColor = '#e74c3c'; // rojo
            break;
    }

    // Mostrar toast
    Toastify({
        text: message,
        duration: 3000,
        close: true,
        gravity: 'bottom',
        position: 'right',
        backgroundColor: backgroundColor,
        stopOnFocus: true
    }).showToast();
}

// Inicializar UI cuando se cargue la página
document.addEventListener('DOMContentLoaded', initUI);
