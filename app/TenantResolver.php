<?php
/**
 * TenantResolver — Resuelve el tenant activo a partir del dominio HTTP.
 *
 * Uso:
 *   $tenant = TenantResolver::resolve();      // array completo de config
 *   $id     = TenantResolver::tenantId();     // int
 *   $lang   = TenantResolver::get('idioma_defecto', 'es');
 *
 * El resolver busca app/tenants/{host}.php.
 * Si no existe, usa app/tenants/_base.php como fallback.
 * Si tampoco existe _base.php, muere con error 404 controlado.
 *
 * El resultado se cachea en memoria para la duración de la request.
 */
class TenantResolver
{
    private static array $config = [];
    private static bool  $resolved = false;

    /**
     * Resuelve y devuelve la config completa del tenant activo.
     */
    public static function resolve(): array
    {
        if (self::$resolved) {
            return self::$config;
        }

        $host = self::normalizeHost($_SERVER['HTTP_HOST'] ?? '');
        $file = __DIR__ . "/tenants/{$host}.php";

        if (file_exists($file)) {
            self::$config = require $file;
        } else {
            // Fallback explícito — nunca silencioso
            $base = __DIR__ . '/tenants/_base.php';
            if (file_exists($base)) {
                self::$config = require $base;
                error_log("[TenantResolver] No tenant config for host '{$host}', using _base fallback.");
            } else {
                self::notFound($host);
            }
        }

        self::$resolved = true;
        return self::$config;
    }

    /**
     * Obtiene un valor de la config del tenant.
     *
     * @param string $key     Clave de configuración
     * @param mixed  $default Valor por defecto si la clave no existe
     */
    public static function get(string $key, $default = null)
    {
        return self::resolve()[$key] ?? $default;
    }

    /**
     * Devuelve el tenant_id (= distribuidor.id) del tenant activo.
     */
    public static function tenantId(): int
    {
        return (int) self::get('tenant_id', 1);
    }

    /**
     * Devuelve el idioma por defecto del tenant.
     */
    public static function idioma(): string
    {
        return self::get('idioma_defecto', 'es');
    }

    /**
     * Normaliza el host HTTP eliminando www. y puerto.
     * Ejemplos:
     *   www.isorga.com:8080 → isorga.com
     *   WWW.ISORGA.FR       → isorga.fr
     *   localhost           → localhost
     */
    private static function normalizeHost(string $host): string
    {
        // Quitar puerto
        $host = preg_replace('/:\d+$/', '', $host);
        // Minúsculas
        $host = strtolower(trim($host));
        // Quitar www.
        $host = preg_replace('/^www\./', '', $host);

        return $host;
    }

    /**
     * Respuesta controlada cuando el dominio no tiene tenant configurado.
     * No expone información interna del sistema.
     */
    private static function notFound(string $host): never
    {
        error_log("[TenantResolver] FATAL: No tenant config for host '{$host}' and no _base.php fallback.");
        http_response_code(404);
        // Página de error mínima, sin información del sistema
        echo '<!DOCTYPE html><html><head><title>404</title></head><body>'
           . '<h1>404 — Página no encontrada</h1>'
           . '<p>Este dominio no está configurado.</p>'
           . '</body></html>';
        exit;
    }

    /**
     * Limpia el estado cacheado. Útil en tests.
     */
    public static function reset(): void
    {
        self::$config   = [];
        self::$resolved = false;
    }
}
