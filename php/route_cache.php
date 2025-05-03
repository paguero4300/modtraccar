<?php
/**
 * Clase para manejar el caché de rutas
 * 
 * Esta clase permite almacenar y recuperar rutas del caché para mejorar
 * el rendimiento de las consultas a reports/route.
 */
class RouteCache {
    private $cacheDir;
    private $cacheTTL; // Tiempo de vida del caché en segundos

    /**
     * Constructor
     * 
     * @param string|null $cacheDir Directorio para almacenar el caché (opcional)
     * @param int $cacheTTL Tiempo de vida del caché en segundos (por defecto 1 hora)
     */
    public function __construct($cacheDir = null, $cacheTTL = 3600) {
        // Directorio por defecto para el caché
        $this->cacheDir = $cacheDir ?: sys_get_temp_dir() . '/route_cache';
        $this->cacheTTL = $cacheTTL;

        // Crear directorio si no existe
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Generar clave única para el caché
     * 
     * @param int $deviceId ID del dispositivo
     * @param string $from Fecha de inicio
     * @param string $to Fecha de fin
     * @return string Clave única para el caché
     */
    private function getCacheKey($deviceId, $from, $to) {
        return md5("route_{$deviceId}_{$from}_{$to}");
    }

    /**
     * Obtener ruta del archivo de caché
     * 
     * @param string $key Clave del caché
     * @return string Ruta completa al archivo de caché
     */
    private function getCachePath($key) {
        return $this->cacheDir . '/' . $key . '.json';
    }

    /**
     * Verificar si existe caché válido
     * 
     * @param int $deviceId ID del dispositivo
     * @param string $from Fecha de inicio
     * @param string $to Fecha de fin
     * @return bool True si existe caché válido, false en caso contrario
     */
    public function hasValidCache($deviceId, $from, $to) {
        $key = $this->getCacheKey($deviceId, $from, $to);
        $cachePath = $this->getCachePath($key);

        if (!file_exists($cachePath)) {
            return false;
        }

        // Verificar si el caché ha expirado
        $fileTime = filemtime($cachePath);
        return (time() - $fileTime) < $this->cacheTTL;
    }

    /**
     * Guardar datos en caché
     * 
     * @param int $deviceId ID del dispositivo
     * @param string $from Fecha de inicio
     * @param string $to Fecha de fin
     * @param array $data Datos a guardar
     * @return bool True si se guardó correctamente, false en caso contrario
     */
    public function saveToCache($deviceId, $from, $to, $data) {
        $key = $this->getCacheKey($deviceId, $from, $to);
        $cachePath = $this->getCachePath($key);
        
        return file_put_contents($cachePath, json_encode($data)) !== false;
    }

    /**
     * Obtener datos del caché
     * 
     * @param int $deviceId ID del dispositivo
     * @param string $from Fecha de inicio
     * @param string $to Fecha de fin
     * @return array|null Datos del caché o null si no existe o ha expirado
     */
    public function getFromCache($deviceId, $from, $to) {
        $key = $this->getCacheKey($deviceId, $from, $to);
        $cachePath = $this->getCachePath($key);
        
        if (!$this->hasValidCache($deviceId, $from, $to)) {
            return null;
        }
        
        $data = file_get_contents($cachePath);
        return json_decode($data, true);
    }

    /**
     * Limpiar caché antiguo
     * 
     * @return int Número de archivos eliminados
     */
    public function cleanOldCache() {
        $files = glob($this->cacheDir . '/*.json');
        $now = time();
        $count = 0;
        
        foreach ($files as $file) {
            if ($now - filemtime($file) > $this->cacheTTL) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
}
