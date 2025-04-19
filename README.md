# Sistema de Monitoreo de Flota GPS

Este sistema permite monitorear en tiempo real la ubicación y estado de una flota de vehículos utilizando el servidor Traccar como backend.

## Características

- Login/Logout seguro
- Panel de mapa con ubicación en vivo de todas las unidades
- Conexión WebSocket con fallback a polling cada 15 segundos
- Lista lateral de vehículos con buscador y filtro de estado
- Detalle emergente de vehículo (velocidad, último mensaje, dirección)
- Historial de ruta del día
- Envío de comando "engineStop"
- Diseño responsivo con modo oscuro
- Clusterización de marcadores

## Estructura de carpetas

```
/
├── public/           # Archivos públicos accesibles directamente
├── assets/           # Recursos estáticos (CSS, imágenes)
├── php/              # Archivos PHP de backend
├── js/               # Archivos JavaScript
├── vendor/           # Dependencias externas (si es necesario)
├── config.php        # Configuración y constantes
├── .htaccess         # Configuración de Apache
└── README.md         # Este archivo
```

## Dependencias CDN

- **Tailwind CSS**: Framework CSS utilitario
  - `https://cdn.tailwindcss.com`

- **DaisyUI**: Componentes para Tailwind CSS
  - `https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css`

- **Leaflet**: Biblioteca de mapas interactivos
  - `https://unpkg.com/leaflet@1.9.4/dist/leaflet.css`
  - `https://unpkg.com/leaflet@1.9.4/dist/leaflet.js`

- **Leaflet MarkerCluster**: Plugin para agrupar marcadores
  - `https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css`
  - `https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css`
  - `https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js`

- **Toastify**: Notificaciones toast
  - `https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css`
  - `https://cdn.jsdelivr.net/npm/toastify-js`

## Cómo iniciar

1. **Configurar el archivo config.php**
   - Establecer la URL de la API de Traccar
   - Configurar las coordenadas iniciales del mapa
   - Establecer credenciales por defecto (solo para desarrollo)

2. **Iniciar un servidor PHP local**
   ```
   php -S localhost:8000
   ```

3. **Acceder a la aplicación**
   - Abrir en el navegador: `http://localhost:8000`
   - Iniciar sesión con las credenciales configuradas

4. **Desplegar en servidor de producción**
   - Subir todos los archivos a un servidor con PHP 7.x o superior
   - Asegurarse de que el servidor tenga soporte para WebSockets
   - Configurar correctamente los permisos de archivos

5. **Seguridad en producción**
   - Eliminar credenciales por defecto en config.php
   - Habilitar HTTPS
   - Revisar y ajustar las cabeceras de seguridad

## Checklist de seguridad

- [x] Escapado XSS en todas las salidas HTML
- [x] Protección CSRF en todos los formularios
- [x] Cabeceras de seguridad (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection)
- [x] Política de seguridad de contenido (CSP)
- [x] Validación de entradas de usuario
- [x] Manejo seguro de sesiones

## Futuras mejoras

- Implementación como PWA para uso offline
- Autenticación con tokens JWT
- Tests E2E con Cypress o Playwright
- Dockerización para facilitar el despliegue
- Soporte para múltiples idiomas
- Generación de informes y estadísticas
- Notificaciones push para eventos importantes
