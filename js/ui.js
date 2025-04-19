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
    
    // Configurar sidebar en móvil
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

// Configurar sidebar en móvil
function setupSidebar() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('hidden');
    });
    
    // Ocultar sidebar al hacer clic en el mapa en móvil
    document.getElementById('map').addEventListener('click', () => {
        if (window.innerWidth < 768 && !sidebar.classList.contains('hidden')) {
            sidebar.classList.add('hidden');
        }
    });
}

// Configurar filtros de vehículos
function setupVehicleFilters() {
    const filterTabs = document.querySelectorAll('.tabs .tab');
    
    filterTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Actualizar estado activo
            filterTabs.forEach(t => t.classList.remove('tab-active'));
            tab.classList.add('tab-active');
            
            // Actualizar filtro actual
            currentFilter = tab.dataset.filter;
            
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
function updateVehicleCount(count) {
    document.getElementById('vehicles-count').textContent = count;
}

// Actualizar lista de vehículos
function updateVehiclesList() {
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
            
            html += `
                <div class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow cursor-pointer vehicle-item" data-device-id="${device.id}">
                    <div class="card-body p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold">${device.name}</h3>
                                <div class="badge ${statusClass} my-1">${statusText}</div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm">${speed}</p>
                                <p class="text-xs opacity-70">${lastUpdate}</p>
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
