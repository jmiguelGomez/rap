<?php
// Clase de conexión a BD para la intranet.
// Adaptada de /var/www/isorga-net/app/clases/Conectar.php — mismo API
// (varias / una / cuantos / ejecutar / ultimoId / totalFilas) para mantener
// patrones familiares con ISORGA. Las credenciales se leen de app/config.php.

class Conectar
{
    private static $pdo;
    private static $ultimoError = '';

    public static function ultimoError()
    {
        return self::$ultimoError;
    }

    private static function obtenerConexion()
    {
        if (!self::$pdo) {
            $config = require __DIR__ . '/config.php';
            $db = $config['db'];

            try {
                self::$pdo = new PDO(
                    "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}",
                    $db['user'],
                    $db['pass'],
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                error_log('[CHECK] Conexión BD falló: ' . $e->getMessage());
                die('Error al conectar con la base de datos.');
            }
        }
        return self::$pdo;
    }

    public static function conexion()
    {
        return self::obtenerConexion();
    }

    public static function varias($query, $params = [])
    {
        try {
            $stmt = self::obtenerConexion()->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            self::$ultimoError = $e->getMessage();
            error_log('[CHECK] varias(): ' . $e->getMessage() . ' Query: ' . $query);
            return false;
        }
    }

    public static function una($query, $params = [])
    {
        try {
            $stmt = self::obtenerConexion()->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            self::$ultimoError = $e->getMessage();
            error_log('[CHECK] una(): ' . $e->getMessage() . ' Query: ' . $query);
            return null;
        }
    }

    public static function cuantos($query, $params = [])
    {
        try {
            $stmt = self::obtenerConexion()->prepare($query);
            $stmt->execute($params);

            if (stripos($query, 'SELECT COUNT(') !== false) {
                $result = $stmt->fetch(PDO::FETCH_NUM);
                return (int) $result[0];
            }
            return $stmt->rowCount();
        } catch (PDOException $e) {
            self::$ultimoError = $e->getMessage();
            error_log('[CHECK] cuantos(): ' . $e->getMessage());
            return 0;
        }
    }

    public static function ejecutar($query, $params = [])
    {
        try {
            self::$ultimoError = '';
            $stmt = self::obtenerConexion()->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            self::$ultimoError = $e->getMessage();
            error_log('[CHECK] ejecutar(): ' . $e->getMessage() . ' Query: ' . $query);
            return false;
        }
    }

    public static function ultimoId()
    {
        try {
            return self::obtenerConexion()->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function totalFilas($query, $params = [])
    {
        try {
            $stmt = self::obtenerConexion()->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            self::$ultimoError = $e->getMessage();
            error_log('[CHECK] totalFilas(): ' . $e->getMessage() . ' Query: ' . $query);
            return 0;
        }
    }
}
