/**
 * Funciones para el reporte de despachos
 */

// Variables globales
let allVehicles = [];
let currentReportFilter = 'all';

// Inicializar el reporte de despachos
function initDispatchReport() {
    // Configurar botón para mostrar el reporte
    const showReportBtn = document.getElementById('btn-show-dispatch-report');
    if (showReportBtn) {
        showReportBtn.addEventListener('click', showDispatchReport);
    }

    // Configurar tabs de filtrado
    document.getElementById('tab-all').addEventListener('click', () => filterReportTable('all'));
    document.getElementById('tab-dispatched').addEventListener('click', () => filterReportTable('dispatched'));
    document.getElementById('tab-not-dispatched').addEventListener('click', () => filterReportTable('not-dispatched'));

    // Configurar botón de exportar
    document.getElementById('btn-export-report').addEventListener('click', exportReportToCSV);
}

// Mostrar el reporte de despachos
function showDispatchReport() {
    const modal = document.getElementById('dispatch-report-modal');
    modal.showModal();
    
    // Generar el reporte con los datos actuales
    generateDispatchReport();
}

// Generar el reporte de despachos
function generateDispatchReport() {
    // Obtener todos los vehículos
    allVehicles = Object.values(window.deviceData || {});
    
    // Calcular estadísticas
    const totalVehicles = allVehicles.length;
    
    // Verificar si la fecha de último despacho es hoy
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const dispatchedToday = allVehicles.filter(device => {
        if (!device.ultimo_despacho) return false;
        const despachoDate = new Date(device.ultimo_despacho);
        despachoDate.setHours(0, 0, 0, 0);
        return despachoDate.getTime() === today.getTime();
    }).length;
    
    const notDispatched = totalVehicles - dispatchedToday;
    
    // Actualizar estadísticas en el UI
    document.getElementById('total-vehicles').textContent = totalVehicles;
    document.getElementById('dispatched-today').textContent = dispatchedToday;
    document.getElementById('not-dispatched').textContent = notDispatched;
    
    // Generar tabla
    updateReportTable();
}

// Actualizar la tabla del reporte según el filtro actual
function updateReportTable() {
    const tableBody = document.getElementById('dispatch-report-table');
    
    // Filtrar vehículos según el filtro actual
    let filteredVehicles = [...allVehicles];
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (currentReportFilter === 'dispatched') {
        filteredVehicles = allVehicles.filter(device => {
            if (!device.ultimo_despacho) return false;
            const despachoDate = new Date(device.ultimo_despacho);
            despachoDate.setHours(0, 0, 0, 0);
            return despachoDate.getTime() === today.getTime();
        });
    } else if (currentReportFilter === 'not-dispatched') {
        filteredVehicles = allVehicles.filter(device => {
            if (!device.ultimo_despacho) return true;
            const despachoDate = new Date(device.ultimo_despacho);
            despachoDate.setHours(0, 0, 0, 0);
            return despachoDate.getTime() !== today.getTime();
        });
    }
    
    // Ordenar por nombre
    filteredVehicles.sort((a, b) => a.name.localeCompare(b.name));
    
    // Generar HTML de la tabla
    let html = '';
    
    if (filteredVehicles.length === 0) {
        html = `<tr><td colspan="6" class="text-center">No hay vehículos que coincidan con el filtro</td></tr>`;
    } else {
        filteredVehicles.forEach(device => {
            const isOnline = device.status === 'online';
            const statusClass = isOnline ? 'badge-success' : 'badge-error';
            const statusText = isOnline ? 'En línea' : 'Desconectado';
            
            // Determinar si está despachado hoy
            let dispatchStatus = 'Sin despacho';
            let dispatchClass = 'badge-warning';
            
            if (device.ultimo_despacho) {
                const despachoDate = new Date(device.ultimo_despacho);
                despachoDate.setHours(0, 0, 0, 0);
                
                if (despachoDate.getTime() === today.getTime()) {
                    dispatchStatus = 'Despachado hoy';
                    dispatchClass = 'badge-success';
                } else {
                    const diffDays = Math.floor((today - despachoDate) / (1000 * 60 * 60 * 24));
                    dispatchStatus = `Último despacho hace ${diffDays} día${diffDays !== 1 ? 's' : ''}`;
                }
            }
            
            // Formatear fecha de último despacho
            const formattedDespacho = device.ultimo_despacho 
                ? new Date(device.ultimo_despacho).toLocaleString() 
                : 'N/A';
            
            html += `
                <tr>
                    <td>${device.name}</td>
                    <td>${device.padron || 'N/A'}</td>
                    <td>${device.terminal || 'N/A'}</td>
                    <td>${formattedDespacho}</td>
                    <td>
                        <div class="flex flex-col gap-1">
                            <span class="badge ${statusClass} badge-sm">${statusText}</span>
                            <span class="badge ${dispatchClass} badge-sm">${dispatchStatus}</span>
                        </div>
                    </td>
                    <td>
                        <div class="flex gap-1">
                            <button class="btn btn-xs btn-primary locate-vehicle" data-device-id="${device.id}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                            </button>
                            <button class="btn btn-xs btn-secondary show-route" data-device-id="${device.id}">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
    }
    
    tableBody.innerHTML = html;
    
    // Añadir eventos a los botones
    document.querySelectorAll('.locate-vehicle').forEach(button => {
        button.addEventListener('click', (e) => {
            const deviceId = parseInt(e.currentTarget.dataset.deviceId);
            locateVehicle(deviceId);
        });
    });
    
    document.querySelectorAll('.show-route').forEach(button => {
        button.addEventListener('click', (e) => {
            const deviceId = parseInt(e.currentTarget.dataset.deviceId);
            showVehicleRoute(deviceId);
        });
    });
}

// Filtrar la tabla del reporte
function filterReportTable(filter) {
    // Actualizar estado activo de las pestañas
    document.querySelectorAll('.tabs .tab').forEach(tab => {
        tab.classList.remove('tab-active');
    });
    document.getElementById(`tab-${filter}`).classList.add('tab-active');
    
    // Actualizar filtro actual
    currentReportFilter = filter;
    
    // Actualizar tabla
    updateReportTable();
}

// Localizar un vehículo en el mapa
function locateVehicle(deviceId) {
    const device = window.deviceData[deviceId];
    const position = device.lastPosition;
    
    if (device && position) {
        // Cerrar el modal
        document.getElementById('dispatch-report-modal').close();
        
        // Centrar mapa en el vehículo
        window.map.setView([position.latitude, position.longitude], 16);
        
        // Mostrar popup
        const marker = window.markers[deviceId];
        if (marker) {
            marker.openPopup();
        }
        
        // Mostrar detalles del vehículo
        window.selectedDeviceId = deviceId;
        if (typeof window.showVehicleDetails === 'function') {
            window.showVehicleDetails(device, position);
        }
    }
}

// Mostrar la ruta de un vehículo
function showVehicleRoute(deviceId) {
    // Cerrar el modal actual
    document.getElementById('dispatch-report-modal').close();
    
    // Configurar el modal de ruta
    document.getElementById('route-device-id').value = deviceId;
    
    // Obtener nombre del dispositivo
    const deviceName = window.deviceData[deviceId].name;
    document.getElementById('route-device-name').textContent = deviceName;
    
    // Configurar fechas para hoy
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    
    document.getElementById('route-date-from').value = todayStr;
    document.getElementById('route-date-to').value = todayStr;
    
    // Mostrar el modal de ruta
    document.getElementById('route-modal').showModal();
}

// Exportar reporte a CSV
function exportReportToCSV() {
    // Filtrar vehículos según el filtro actual
    let filteredVehicles = [...allVehicles];
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (currentReportFilter === 'dispatched') {
        filteredVehicles = allVehicles.filter(device => {
            if (!device.ultimo_despacho) return false;
            const despachoDate = new Date(device.ultimo_despacho);
            despachoDate.setHours(0, 0, 0, 0);
            return despachoDate.getTime() === today.getTime();
        });
    } else if (currentReportFilter === 'not-dispatched') {
        filteredVehicles = allVehicles.filter(device => {
            if (!device.ultimo_despacho) return true;
            const despachoDate = new Date(device.ultimo_despacho);
            despachoDate.setHours(0, 0, 0, 0);
            return despachoDate.getTime() !== today.getTime();
        });
    }
    
    // Ordenar por nombre
    filteredVehicles.sort((a, b) => a.name.localeCompare(b.name));
    
    // Crear contenido CSV
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Encabezados
    csvContent += "Vehículo,Padrón,Terminal,Último Despacho,Estado,Estado de Despacho\n";
    
    // Datos
    filteredVehicles.forEach(device => {
        const isOnline = device.status === 'online';
        const statusText = isOnline ? 'En línea' : 'Desconectado';
        
        // Determinar si está despachado hoy
        let dispatchStatus = 'Sin despacho';
        
        if (device.ultimo_despacho) {
            const despachoDate = new Date(device.ultimo_despacho);
            despachoDate.setHours(0, 0, 0, 0);
            
            if (despachoDate.getTime() === today.getTime()) {
                dispatchStatus = 'Despachado hoy';
            } else {
                const diffDays = Math.floor((today - despachoDate) / (1000 * 60 * 60 * 24));
                dispatchStatus = `Último despacho hace ${diffDays} día${diffDays !== 1 ? 's' : ''}`;
            }
        }
        
        // Formatear fecha de último despacho
        const formattedDespacho = device.ultimo_despacho 
            ? new Date(device.ultimo_despacho).toLocaleString() 
            : 'N/A';
        
        // Escapar comas en los campos
        const escapeCsv = (str) => {
            if (typeof str !== 'string') return str;
            if (str.includes(',') || str.includes('"') || str.includes('\n')) {
                return `"${str.replace(/"/g, '""')}"`;
            }
            return str;
        };
        
        csvContent += [
            escapeCsv(device.name),
            escapeCsv(device.padron || 'N/A'),
            escapeCsv(device.terminal || 'N/A'),
            escapeCsv(formattedDespacho),
            escapeCsv(statusText),
            escapeCsv(dispatchStatus)
        ].join(',') + '\n';
    });
    
    // Crear enlace de descarga
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `reporte_despachos_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    
    // Descargar archivo
    link.click();
    
    // Limpiar
    document.body.removeChild(link);
}

// Inicializar cuando se cargue la página
document.addEventListener('DOMContentLoaded', initDispatchReport);
